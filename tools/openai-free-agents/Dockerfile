FROM python:3.13-slim

WORKDIR /app

RUN pip install --no-cache-dir flask

COPY . /app

EXPOSE 6400

CMD ["python", "-m", "openai_free_agents_api"]
