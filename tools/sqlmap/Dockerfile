FROM python:3.12-slim
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*
RUN git clone --depth 1 https://github.com/sqlmapproject/sqlmap.git /opt/sqlmap
WORKDIR /opt/sqlmap
HEALTHCHECK --interval=30s --timeout=3s CMD python sqlmap.py --version || exit 1
EXPOSE 6000
CMD ["python", "sqlmap.py", "--batch", "--api", "--server", "--host=0.0.0.0", "--port=6000"]
