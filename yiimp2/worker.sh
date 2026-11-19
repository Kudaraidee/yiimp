#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# YiiMP 2 — single queue worker
#
# Run one queue/listen process with automatic restart on crash.
# Useful when you want separate terminal windows per worker, or when
# invoking from a custom process manager that doesn't handle restarts.
#
# Usage:
#   ./worker.sh          — run one worker, restart on exit
#   ./worker.sh once     — run queue/run (drain current jobs and exit)
# ─────────────────────────────────────────────────────────────────────────────

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP="${PHP_CLI:-php}"
YII="${DIR}/yii"
MODE="${1:-listen}"

cd "${DIR}"

echo "$(date '+%Y-%m-%d %H:%M:%S')  YiiMP2 worker starting in ${DIR}"

if [ "${MODE}" = "once" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S')  Running queue/run (drain and exit)..."
    exec "${PHP}" "${YII}" queue/run
fi

# Persistent worker with restart on crash
while true; do
    echo "$(date '+%Y-%m-%d %H:%M:%S')  queue/listen starting..."
    "${PHP}" "${YII}" queue/listen --verbose=0
    EXIT=$?
    if [ ${EXIT} -eq 0 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S')  Worker exited cleanly."
        break
    fi
    echo "$(date '+%Y-%m-%d %H:%M:%S')  Worker exited with code ${EXIT} — restarting in 5s..."
    sleep 5
done
