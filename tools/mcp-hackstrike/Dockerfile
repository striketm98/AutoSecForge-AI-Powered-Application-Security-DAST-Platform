FROM python:3.12-slim
WORKDIR /app
COPY mcp_hackstrike_api.py .
RUN pip install flask
EXPOSE 6300
CMD ["python", "mcp_hackstrike_api.py"]
