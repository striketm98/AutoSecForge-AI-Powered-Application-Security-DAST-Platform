FROM sqlmap/sqlmap:latest
# The official image already has sqlmap installed and on PATH.
# The MCP router orchestrates scans via `docker exec autosecforge-sqlmap sqlmap -u ...`,
# so we keep the container alive rather than running the API daemon.
EXPOSE 6000
CMD ["sleep", "infinity"]
