#!/usr/bin/env bash
#
# setup-pandora.sh <enabled true|false> [proxy]
#
# Starts the Pandora stack alongside the main app if enabled.
# Uses Docker Compose profiles — Pandora services have `profiles: [pandora]`.
# --------------------------------------------------------------------

set -euo pipefail

PANDORA_ENABLED="${1:-false}"
PROXY="${2:-${PROXY:-}}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$PANDORA_ENABLED" != "true" ]]; then
  echo "→ Pandora disabled – skipping."
  exit 0
fi

echo "→ Ensuring Docker network…"
docker network inspect app_network >/dev/null 2>&1 \
  || docker network create app_network

if [[ -n "$PROXY" ]]; then
  export HTTP_PROXY="$PROXY"
  export HTTPS_PROXY="$PROXY"
  export http_proxy="$PROXY"
  export https_proxy="$PROXY"
fi

cd "$PROJECT_ROOT"

export COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml:$PROJECT_ROOT/docker/pandora/pandora.yml"

if command -v docker-compose >/dev/null 2>&1; then
  COMPOSE='docker-compose'
else
  COMPOSE='docker compose'
fi

echo "→ Pulling images & starting Pandora stack…"
$COMPOSE --profile pandora up -d --pull always
