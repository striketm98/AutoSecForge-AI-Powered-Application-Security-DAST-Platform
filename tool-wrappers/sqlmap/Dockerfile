FROM alpine:3.19
# SQL injection scanner used by the MCP router via
# `docker exec autosecforge-sqlmap sqlmap -u ...`.
# (There is no official sqlmap/sqlmap image on Docker Hub, so install from apk.)
RUN apk add --no-cache sqlmap
# Keep the container alive so the orchestrator can exec into it.
CMD ["sleep", "infinity"]
