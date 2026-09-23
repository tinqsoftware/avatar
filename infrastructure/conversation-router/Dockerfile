FROM vllm/vllm-openai:latest

WORKDIR /app
COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt
COPY app.py start.sh /app/

EXPOSE 8790
CMD ["sh", "/app/start.sh"]
