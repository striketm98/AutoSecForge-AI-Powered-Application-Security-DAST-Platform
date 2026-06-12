FROM python:3.12-slim
WORKDIR /app
COPY openai_free_agents_api.py .
RUN pip install flask openai
EXPOSE 6400
CMD ["python", "openai_free_agents_api.py"]
