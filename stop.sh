#!/usr/bin/env bash
# AutoSecForge Pro — Stop all services
echo "Stopping AutoSecForge Pro services..."
for f in /tmp/asf_pid_*; do
    [ -f "$f" ] && kill "$(cat $f)" 2>/dev/null && rm "$f"
done
for port in 8080 6100 6200 6300 6400; do
    fuser -k ${port}/tcp 2>/dev/null || true
done
echo "All services stopped."
