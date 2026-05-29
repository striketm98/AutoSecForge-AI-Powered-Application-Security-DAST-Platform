### ============================================================
### AutoSecForge – tools/pentest-python/Dockerfile
### SCA-001 FIX: Upgrade setuptools to >=78.1.1
### SCA-002 FIX: Upgrade pip to >=26.1
### ============================================================

FROM python:3.13-slim

WORKDIR /app

# SCA-001 FIX: Upgrade pip and setuptools BEFORE installing any
# application dependencies, so the vulnerable bundled versions are
# overwritten immediately.
RUN pip install --upgrade --no-cache-dir "pip>=26.1" "setuptools>=78.1.1"

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 5000
CMD ["python", "app.py"]
