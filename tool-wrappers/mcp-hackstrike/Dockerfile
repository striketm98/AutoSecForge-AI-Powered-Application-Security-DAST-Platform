FROM python:3.12-alpine
WORKDIR /app
RUN pip install --no-cache-dir flask
COPY mcp_hackstrike_api.py .
EXPOSE 6300
CMD ["python", "mcp_hackstrike_api.py"]
