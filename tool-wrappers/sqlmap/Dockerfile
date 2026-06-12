FROM sqlmap/sqlmap:latest
# The official image already has sqlmap installed.
# We just need to run the API server.
EXPOSE 6000
CMD ["sqlmapapi", "-s", "-H", "0.0.0.0", "-p", "6000"]
