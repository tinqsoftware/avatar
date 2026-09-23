FROM vllm/vllm-openai:latest

WORKDIR /app
COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt
COPY app.py start.sh /app/

EXPOSE 8790
# The upstream vLLM image defines `vllm` as its ENTRYPOINT. Clear it so our
# shell launcher starts both vLLM and the authenticated router API.
ENTRYPOINT []
CMD ["sh", "/app/start.sh"]
