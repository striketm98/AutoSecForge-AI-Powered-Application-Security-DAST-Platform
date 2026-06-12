FROM python:3.12-alpine
# SQL injection scanner used by the MCP router via
# `docker exec autosecforge-sqlmap sqlmap -u ...`.
# sqlmap is not packaged in Alpine; install the official PyPI release,
# which puts a `sqlmap` console script on PATH.
RUN pip install --no-cache-dir sqlmap
# Keep the container alive so the orchestrator can exec into it.
CMD ["sleep", "infinity"]
