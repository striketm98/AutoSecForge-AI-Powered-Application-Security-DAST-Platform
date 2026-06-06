FROM alpine:latest
RUN apk add --no-cache nikto perl
HEALTHCHECK CMD nikto -Version || exit 1
EXPOSE 80
CMD ["nikto", "-Listen", "0.0.0.0:80", "-nossl"]
