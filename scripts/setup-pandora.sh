#!/usr/bin/env bash
#
# setup-pandora.sh <enabled true|false> [proxy]
#
# Starts the Pandora profile alongside the main app if enabled.
# Pandora services are defined in docker-compose.yml with profiles: [pandora].
# --------------------------------------------------------------------

set -euo pipefail

PANDORA_ENABLED="${1:-false}"
PROXY="${2:-${PROXY:-}}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$PANDORA_ENABLED" != "true" ]]; then
  echo "→ Pandora disabled – skipping."
  exit 0
fi

if [[ -n "$PROXY" ]]; then
  export HTTP_PROXY="$PROXY"
  export HTTPS_PROXY="$PROXY"
  export http_proxy="$PROXY"
  export https_proxy="$PROXY"
fi

cd "$PROJECT_ROOT"

if command -v docker-compose >/dev/null 2>&1; then
  COMPOSE='docker-compose'
else
  COMPOSE='docker compose'
fi

echo "→ Pulling images & starting Pandora stack…"
COMPOSE_PROFILES=pandora $COMPOSE up -d --pull always
