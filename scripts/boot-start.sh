#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd -- "$script_dir/.." && pwd)"
log_file=/var/tmp/vpos-docker-autostart.log
lock_file=/var/tmp/vpos-docker-autostart.lock

exec >>"$log_file" 2>&1
exec 9>"$lock_file"
if ! flock -n 9; then
    echo "$(date -Is) Startup is already running."
    exit 0
fi

echo "$(date -Is) Waiting for Docker Desktop."
for _ in {1..120}; do
    if docker info >/dev/null 2>&1; then
        echo "$(date -Is) Docker is ready; starting the project."
        bash "$repo_dir/run.sh"
        echo "$(date -Is) Project startup completed."
        exit 0
    fi
    sleep 5
done

echo "$(date -Is) Docker Desktop did not become ready within 10 minutes." >&2
exit 1
