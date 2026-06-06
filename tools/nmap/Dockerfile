FROM alpine:latest
RUN apk add --no-cache nmap
HEALTHCHECK CMD nmap --version || exit 1
CMD ["nmap", "--help"]
