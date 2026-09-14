#!/usr/bin/env bash
# Source from a script running in the repository root. Do not source environment files as shell code.
if [[ -z ${APP_IMAGE:-} && -f .release-image ]]; then export APP_IMAGE=$(cat .release-image); fi
COMPOSE=(docker compose -f "${COMPOSE_FILE:-docker-compose.prod.yml}" --env-file "${COMPOSE_ENV_FILE:-docker-compose.env}")
