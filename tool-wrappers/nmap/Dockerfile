FROM alpine:3.19
# Network scanner used by the MCP router via `docker exec autosecforge-nmap nmap ...`
RUN apk add --no-cache nmap nmap-scripts
# Keep the container alive so the orchestrator can exec into it.
CMD ["sleep", "infinity"]
