FROM python:3.12-slim
WORKDIR /app
COPY oasm_api.py .
RUN pip install flask
EXPOSE 6200
CMD ["python", "oasm_api.py"]
