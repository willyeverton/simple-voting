#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
bash scripts/quality.sh

if [[ -z "${SIMPLETEST_DB:-}" || -z "${SIMPLETEST_BASE_URL:-}" ]]; then
  printf '%s\n' 'Kernel/Functional tests require SIMPLETEST_DB and SIMPLETEST_BASE_URL.' >&2
  printf '%s\n' 'Set them in the appserver environment before running this script.' >&2
  exit 1
fi

vendor/bin/phpunit --configuration=phpunit.drupal.xml.dist
