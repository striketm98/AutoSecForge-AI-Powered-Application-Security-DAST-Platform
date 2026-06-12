#!/bin/bash
cd /opt/autosecforge
# Example: mark queued jobs as running, then completed (real integration would call APIs)
docker compose exec -T db mysql -uroot -proot123 security_dashboard -e "UPDATE scan_jobs SET status='running' WHERE status='queued' AND created_at < NOW() - INTERVAL 1 MINUTE; UPDATE scan_jobs SET status='completed' WHERE status='running' AND created_at < NOW() - INTERVAL 2 MINUTE;"
