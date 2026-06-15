FROM python:3.12-alpine
WORKDIR /app
RUN pip install --no-cache-dir flask requests dnspython
COPY oasm_api.py .
EXPOSE 6200
CMD ["python", "oasm_api.py"]
