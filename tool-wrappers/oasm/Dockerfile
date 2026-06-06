FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends nmap dnsutils \
    && pip install flask --no-cache-dir \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY oasm_api.py /oasm_api.py
EXPOSE 6200
CMD ["python", "/oasm_api.py"]
