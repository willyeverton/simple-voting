#!/usr/bin/env bash
set -euo pipefail

composer validate --strict
composer audit --no-interaction
bash scripts/phpcs.sh
bash scripts/phpstan.sh
bash scripts/phpunit.sh
