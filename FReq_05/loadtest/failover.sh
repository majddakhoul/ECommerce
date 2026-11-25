#!/bin/sh
set -eu

STOP_AFTER="${STOP_AFTER:-60}"
START_AFTER="${START_AFTER:-100}"
INSTANCE="${INSTANCE:-app2}"
WORKLOAD="${WORKLOAD:-read}"
TOTAL="${TOTAL:-500}"
SECONDS_TOTAL="${SECONDS_TOTAL:-120}"

cd "$(dirname "$0")/.."
mkdir -p loadtest/results

docker compose --profile tools run --rm \
  -e WORKLOAD="$WORKLOAD" \
  -e TOTAL="$TOTAL" \
  -e SECONDS="$SECONDS_TOTAL" \
  -e LABEL="failover-$WORKLOAD" \
  -e FAIL_RATE=0.05 \
  k6 run /scripts/scale.js &
K6_PID=$!

sleep "$STOP_AFTER"
echo "Stopping $INSTANCE"
docker compose stop "$INSTANCE"

sleep $((START_AFTER - STOP_AFTER))
echo "Starting $INSTANCE"
docker compose start "$INSTANCE"

wait "$K6_PID"
