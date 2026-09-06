#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob globstar

files=(web/modules/custom/**/*.php web/themes/custom/**/*.php)
if ((${#files[@]} == 0)); then
  printf '%s\n' 'PHPStan: no project-owned PHP files yet; check skipped.'
  exit 0
fi

exec vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
