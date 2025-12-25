#!/bin/bash
# Yiimp2 Dedicated System - Blocks Backend
# Requirements: 14.2, 14.4, 14.5, 17.13
# This script runs as a foreground process for Docker Compose

PHP_CLI='php -d max_execution_time=60'

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd ${DIR}

date
echo "Yiimp2 Backend Blocks started in ${DIR}"
echo "Database host: ${DB_HOST:-yiimp2-db}"
echo "Database name: ${DB_NAME:-yiimp2}"

# Run as foreground process (no exec bash at end for Docker)
while true; do
        ${PHP_CLI} runconsole-yiimp2.php cronjob/runBlocks
        sleep 20
done
