#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# YiiMP 2 — start background queue workers
#
# Replaces the legacy blocks.sh / loop2.sh / main.sh trio.
# The Yii2 queue system handles all job scheduling internally; no separate
# loops are needed.  This script:
#   1. Seeds the queue (pushes any missing recurring jobs).
#   2. Starts WORKERS parallel queue/listen processes.
#
# Usage:
#   ./start.sh          — start with the default worker count (2)
#   ./start.sh 4        — start 4 parallel workers
#   WORKERS=4 ./start.sh
#
# Stop:  Ctrl-C  (all workers receive SIGTERM and finish their current job)
# ─────────────────────────────────────────────────────────────────────────────

WORKERS=${1:-${WORKERS:-2}}

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP="${PHP_CLI:-php}"
YII="${DIR}/yii"

cd "${DIR}"

echo "$(date '+%Y-%m-%d %H:%M:%S')  YiiMP2 starting in ${DIR}"
echo "$(date '+%Y-%m-%d %H:%M:%S')  Workers: ${WORKERS}"

# ── Seed the queue ────────────────────────────────────────────────────────────
echo "$(date '+%Y-%m-%d %H:%M:%S')  Seeding queue..."
"${PHP}" "${YII}" queue/seed
if [ $? -ne 0 ]; then
    echo "ERROR: queue/seed failed — check that the queue table exists."
    echo "       Run: php yii migrate --migrationPath=@yii/queue/db/migrations"
    exit 1
fi

# ── Start workers ─────────────────────────────────────────────────────────────
PIDS=()

cleanup() {
    echo ""
    echo "$(date '+%Y-%m-%d %H:%M:%S')  Stopping workers..."
    for pid in "${PIDS[@]}"; do
        kill "${pid}" 2>/dev/null
    done
    wait
    echo "$(date '+%Y-%m-%d %H:%M:%S')  All workers stopped."
    exit 0
}
trap cleanup SIGINT SIGTERM

for i in $(seq 1 "${WORKERS}"); do
    echo "$(date '+%Y-%m-%d %H:%M:%S')  Starting worker ${i}..."
    "${PHP}" "${YII}" queue/listen --verbose=0 &
    PIDS+=($!)
done

echo "$(date '+%Y-%m-%d %H:%M:%S')  ${WORKERS} worker(s) running. Press Ctrl-C to stop."

# Wait for all workers; restart any that exit unexpectedly
while true; do
    for i in "${!PIDS[@]}"; do
        pid="${PIDS[$i]}"
        if ! kill -0 "${pid}" 2>/dev/null; then
            echo "$(date '+%Y-%m-%d %H:%M:%S')  Worker ${pid} exited — restarting..."
            "${PHP}" "${YII}" queue/listen --verbose=0 &
            PIDS[$i]=$!
        fi
    done
    sleep 5
done
