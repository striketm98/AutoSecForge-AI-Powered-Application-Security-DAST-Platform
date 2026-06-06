FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends nmap \
    && pip install flask --no-cache-dir \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY app.py /app.py
RUN addgroup --system nmap && adduser --system --ingroup nmap nmapuser
USER nmapuser
EXPOSE 9101
CMD ["python", "/app.py"]
