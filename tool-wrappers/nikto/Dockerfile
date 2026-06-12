FROM alpine:3.19
# Web server scanner used by the MCP router via
# `docker exec autosecforge-nikto nikto -h <url> ...`.
# nikto is not packaged in Alpine; install from the official repo (Perl app).
RUN apk add --no-cache perl perl-net-ssleay git \
    && git clone --depth 1 https://github.com/sullo/nikto /opt/nikto \
    && apk del git \
    && printf '#!/bin/sh\nexec perl /opt/nikto/program/nikto.pl "$@"\n' > /usr/local/bin/nikto \
    && chmod +x /usr/local/bin/nikto
# Keep the container alive so the orchestrator can exec into it.
CMD ["sleep", "infinity"]
