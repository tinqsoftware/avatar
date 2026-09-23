import asyncio
import json
import os
import secrets
import time
from typing import Any

import httpx
from fastapi import FastAPI, Header, HTTPException, Response, status
from pydantic import BaseModel, Field

MAX_ACTIVE = int(os.getenv("ROUTER_MAX_ACTIVE", "20"))
MAX_QUEUE = int(os.getenv("ROUTER_MAX_QUEUE", "80"))
RESULT_TTL_SECONDS = int(os.getenv("ROUTER_RESULT_TTL_SECONDS", "90"))
ROUTER_TOKEN = os.environ["ROUTER_TOKEN"]
VLLM_URL = os.getenv("VLLM_URL", "http://127.0.0.1:8000/v1/chat/completions")
MODEL = os.getenv("ROUTER_MODEL", "Qwen/Qwen3-4B")
VLLM_MODELS_URL = os.getenv("VLLM_MODELS_URL", "http://127.0.0.1:8000/v1/models")

app = FastAPI(docs_url=None, redoc_url=None)
queue: asyncio.Queue[str] = asyncio.Queue(MAX_QUEUE)
tickets: dict[str, dict[str, Any]] = {}


class Topic(BaseModel):
    id: str = Field(pattern=r"^[a-z0-9-]{2,80}$")
    title: str = Field(max_length=150)
    keywords: list[str] = Field(min_length=1, max_length=25)


class RouteRequest(BaseModel):
    avatar: str = Field(pattern=r"^[a-z0-9-]{2,80}$")
    transcript: str = Field(min_length=1, max_length=750)
    state: dict[str, Any] = Field(default_factory=dict)
    topics: list[Topic] = Field(min_length=1, max_length=100)


def require_token(router_token: str | None) -> None:
    if not router_token or not secrets.compare_digest(router_token, ROUTER_TOKEN):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED)


def prompt(payload: RouteRequest) -> str:
    candidates = [{"id": topic.id, "title": topic.title, "keywords": topic.keywords} for topic in payload.topics]
    return """Eres un enrutador, no un redactor. Elige solamente un tema permitido para una conversación con respuestas pregrabadas. No inventes datos ni devuelvas explicaciones. Si el usuario pide continuar, usa action continue. Si ningún tema encaja, usa action fallback y topic_id vacío. Responde JSON con topic_id, stage (summary/detail/next), action (select/continue/fallback) y confidence entre 0 y 1.\n\n""" + json.dumps({"transcript": payload.transcript, "state": payload.state, "topics": candidates}, ensure_ascii=False)


async def worker() -> None:
    async with httpx.AsyncClient(timeout=20) as client:
        while True:
            ticket = await queue.get()
            item = tickets.get(ticket)
            if not item:
                queue.task_done()
                continue

            item["status"] = "processing"
            payload: RouteRequest = item.pop("payload")
            try:
                response = await client.post(VLLM_URL, json={
                    "model": MODEL,
                    "messages": [{"role": "system", "content": "Return only JSON."}, {"role": "user", "content": prompt(payload)}],
                    "temperature": 0,
                    "max_tokens": 90,
                    "response_format": {"type": "json_object"},
                })
                response.raise_for_status()
                raw = response.json()["choices"][0]["message"]["content"]
                selected = json.loads(raw)
                allowed = {topic.id for topic in payload.topics}
                if selected.get("action") == "fallback":
                    item["result"] = {"status": "fallback"}
                elif selected.get("topic_id") in allowed and selected.get("stage") in {"summary", "detail", "next"}:
                    item["result"] = {"status": "ready", "topic_id": selected["topic_id"], "stage": selected["stage"], "action": selected.get("action", "select")}
                else:
                    item["result"] = {"status": "fallback"}
            except (httpx.HTTPError, KeyError, TypeError, ValueError, json.JSONDecodeError):
                item["result"] = {"status": "fallback"}
            finally:
                item["status"] = "ready"
                item["expires_at"] = time.monotonic() + RESULT_TTL_SECONDS
                queue.task_done()


async def model_is_ready() -> bool:
    """Only accept traffic once vLLM exposes the configured model."""
    try:
        async with httpx.AsyncClient(timeout=3) as client:
            response = await client.get(VLLM_MODELS_URL)
            response.raise_for_status()
            models = response.json().get("data", [])
            return any(item.get("id") == MODEL for item in models if isinstance(item, dict))
    except (httpx.HTTPError, TypeError, ValueError):
        return False


@app.on_event("startup")
async def start_workers() -> None:
    for _ in range(MAX_ACTIVE):
        asyncio.create_task(worker())


@app.get("/health")
async def health(response: Response) -> dict[str, Any]:
    ready = await model_is_ready()
    if not ready:
        response.status_code = status.HTTP_503_SERVICE_UNAVAILABLE

    return {
        "status": "ok" if ready else "loading",
        "models_loaded": ready,
        "max_active": MAX_ACTIVE,
        "max_queue": MAX_QUEUE,
    }


@app.post("/v1/route", status_code=status.HTTP_202_ACCEPTED)
async def route(payload: RouteRequest, x_router_token: str | None = Header(default=None)) -> dict[str, str]:
    require_token(x_router_token)
    if not await model_is_ready():
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail="Router is still loading")
    if queue.full():
        raise HTTPException(status_code=status.HTTP_429_TOO_MANY_REQUESTS, detail="Router queue is full")
    ticket = secrets.token_urlsafe(24)
    tickets[ticket] = {"status": "queued", "payload": payload}
    await queue.put(ticket)
    return {"ticket": ticket, "status": "queued"}


@app.get("/v1/route/{ticket}")
async def result(ticket: str, response: Response, x_router_token: str | None = Header(default=None)) -> dict[str, Any]:
    require_token(x_router_token)
    item = tickets.get(ticket)
    if not item:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND)
    if item["status"] != "ready":
        response.status_code = status.HTTP_202_ACCEPTED
        return {"status": "queued"}
    if item["expires_at"] < time.monotonic():
        tickets.pop(ticket, None)
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND)
    result = item["result"]
    tickets.pop(ticket, None)
    return result
