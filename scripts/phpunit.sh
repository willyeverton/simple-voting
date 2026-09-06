#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob globstar

tests=(web/modules/custom/**/*Test.php web/themes/custom/**/*Test.php)
if ((${#tests[@]} == 0)); then
  printf '%s\n' 'PHPUnit: no project-owned tests yet; check skipped.'
  exit 0
fi

exec vendor/bin/phpunit --configuration=phpunit.xml.dist
