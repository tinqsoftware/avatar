#!/bin/sh
set -eu

vllm serve "${ROUTER_MODEL:-Qwen/Qwen3-4B}" \
  --host 127.0.0.1 \
  --port 8000 \
  --generation-config vllm \
  --disable-log-requests \
  --max-model-len "${ROUTER_MAX_MODEL_LEN:-4096}" &

exec uvicorn app:app --host 0.0.0.0 --port 8790 --no-access-log
