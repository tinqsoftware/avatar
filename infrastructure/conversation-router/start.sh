#!/bin/sh
set -eu

vllm serve "${ROUTER_MODEL:-Qwen/Qwen3-4B}" \
  --host 127.0.0.1 \
  --port 8000 \
  --generation-config vllm \
  --max-model-len "${ROUTER_MAX_MODEL_LEN:-4096}" &
vllm_pid=$!

uvicorn app:app --host 0.0.0.0 --port 8790 --no-access-log &
api_pid=$!

cleanup() {
  kill "$vllm_pid" "$api_pid" 2>/dev/null || true
  wait "$vllm_pid" 2>/dev/null || true
  wait "$api_pid" 2>/dev/null || true
}

trap cleanup EXIT
trap 'exit 143' INT TERM

while kill -0 "$vllm_pid" 2>/dev/null && kill -0 "$api_pid" 2>/dev/null; do
  sleep 1
done

exit 1
