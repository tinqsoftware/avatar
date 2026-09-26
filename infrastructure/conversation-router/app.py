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
    description: str = Field(max_length=500)
    examples: list[str] = Field(min_length=1, max_length=12)
    keywords: list[str] = Field(min_length=1, max_length=25)


class SocialIntent(BaseModel):
    id: str = Field(pattern=r"^[a-z0-9-]{2,80}$")
    description: str = Field(max_length=500)
    examples: list[str] = Field(min_length=1, max_length=12)
    keywords: list[str] = Field(min_length=1, max_length=25)


class RouteRequest(BaseModel):
    avatar: str = Field(pattern=r"^[a-z0-9-]{2,80}$")
    transcript: str = Field(min_length=1, max_length=750)
    state: dict[str, Any] = Field(default_factory=dict)
    topics: list[Topic] = Field(min_length=1, max_length=100)
    social: list[SocialIntent] = Field(default_factory=list, max_length=30)


def require_token(router_token: str | None) -> None:
    if not router_token or not secrets.compare_digest(router_token, ROUTER_TOKEN):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED)


def prompt(payload: RouteRequest) -> str:
    candidates = [{"id": topic.id, "title": topic.title, "description": topic.description, "examples": topic.examples, "keywords": topic.keywords} for topic in payload.topics]
    social = [{"id": intent.id, "description": intent.description, "examples": intent.examples, "keywords": intent.keywords} for intent in payload.social]
    return """Eres un enrutador, no un redactor. Interpreta la intención usando solamente los temas e intenciones sociales permitidos. No inventes datos, texto, explicaciones ni nuevas categorías. Puedes elegir una intención social y entre uno y tres temas, en el orden mencionado. Una intención social se representa como {\"kind\":\"social\",\"intent\":\"...\"}. Un tema se representa como {\"kind\":\"topic\",\"topic_id\":\"...\",\"stage\":\"summary|detail|next\",\"action\":\"select|continue|rephrase\"}. Si pide continuar sin nombrar tema, usa solamente el último tema del estado con action continue. Para explicar de otra forma el último tema, usa action rephrase. Si ningún elemento encaja, responde {\"items\": []}. Responde solamente JSON.\n\n""" + json.dumps({"transcript": payload.transcript, "state": payload.state, "topics": candidates, "social": social}, ensure_ascii=False)


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
                    "max_tokens": 220,
                    "response_format": {"type": "json_object"},
                })
                response.raise_for_status()
                raw = response.json()["choices"][0]["message"]["content"]
                selected = json.loads(raw)
                allowed_topics = {topic.id for topic in payload.topics}
                allowed_social = {intent.id for intent in payload.social}
                items = selected.get("items")
                if not isinstance(items, list) or not items or len(items) > 4:
                    item["result"] = {"status": "fallback"}
                else:
                    normalized_items: list[dict[str, str]] = []
                    topic_ids: set[str] = set()
                    social_count = 0
                    for selected_item in items:
                        if not isinstance(selected_item, dict):
                            normalized_items = []
                            break
                        if selected_item.get("kind") == "social":
                            intent = selected_item.get("intent")
                            if intent not in allowed_social or social_count >= 1:
                                normalized_items = []
                                break
                            normalized_items.append({"kind": "social", "intent": intent})
                            social_count += 1
                            continue

                        topic_id = selected_item.get("topic_id")
                        stage = selected_item.get("stage")
                        action = selected_item.get("action", "select")
                        if selected_item.get("kind", "topic") != "topic" or topic_id not in allowed_topics or topic_id in topic_ids or stage not in {"summary", "detail", "next"} or action not in {"select", "continue", "rephrase"}:
                            normalized_items = []
                            break
                        normalized_items.append({"kind": "topic", "topic_id": topic_id, "stage": stage, "action": action})
                        topic_ids.add(topic_id)

                    item["result"] = {"status": "ready", "items": normalized_items} if normalized_items else {"status": "fallback"}
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


async def expire_results() -> None:
    """Discard completed selections even when a browser abandons its ticket."""
    while True:
        now = time.monotonic()
        for ticket, item in list(tickets.items()):
            if item.get("status") == "ready" and item.get("expires_at", now + 1) < now:
                tickets.pop(ticket, None)
        await asyncio.sleep(15)


@app.on_event("startup")
async def start_workers() -> None:
    for _ in range(MAX_ACTIVE):
        asyncio.create_task(worker())
    asyncio.create_task(expire_results())


@app.get("/live")
async def live() -> dict[str, str]:
    """Report that the router process is running, independently of model loading."""
    return {"status": "ok"}


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
