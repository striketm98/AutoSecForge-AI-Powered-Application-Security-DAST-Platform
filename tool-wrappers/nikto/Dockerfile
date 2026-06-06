FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends perl git \
    && git clone --depth 1 https://github.com/sullo/nikto.git /opt/nikto \
    && ln -s /opt/nikto/program/nikto.pl /usr/local/bin/nikto \
    && pip install flask --no-cache-dir \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY app.py /app.py
EXPOSE 9102
CMD ["python", "/app.py"]
