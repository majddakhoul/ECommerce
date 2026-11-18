#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-app}"

wait_for_topology() {
  attempt=0
  until php artisan messaging:topology; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
      echo "RabbitMQ topology could not be declared after 30 attempts" >&2
      exit 1
    fi
    sleep 2
  done
}

case "$ROLE" in
  app)
    php artisan config:clear
    php artisan migrate --force
    php artisan db:seed --force
    php artisan storage:link 2>/dev/null || true
    wait_for_topology
    exec php artisan serve --host=0.0.0.0 --port=8000
    ;;
  worker)
    exec php artisan queue:work rabbitmq --queue="${QUEUE_NAMES:-emails,broadcasts}" --tries=3 --sleep=1 --max-time=3600
    ;;
  reverb)
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
    ;;
  consumer)
    exec php artisan inventory:consume
    ;;
  *)
    echo "Unknown CONTAINER_ROLE: $ROLE" >&2
    exit 1
    ;;
esac
