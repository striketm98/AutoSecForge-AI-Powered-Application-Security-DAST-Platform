FROM alpine:3.19
# Web server scanner used by the MCP router via `docker exec autosecforge-nikto nikto ...`
RUN apk add --no-cache nikto
# Keep the container alive so the orchestrator can exec into it.
CMD ["sleep", "infinity"]
