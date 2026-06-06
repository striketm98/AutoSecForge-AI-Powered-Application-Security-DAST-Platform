FROM python:3.12-alpine
WORKDIR /app
RUN pip install --no-cache-dir flask
COPY openai_free_agents_api.py .
EXPOSE 6400
CMD ["python", "openai_free_agents_api.py"]
