#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob globstar

files=(web/modules/custom/**/*.{php,module,install,inc,theme,profile,engine} web/themes/custom/**/*.{php,module,install,inc,theme,profile,engine})
if ((${#files[@]} == 0)); then
  printf '%s\n' 'PHPCBF: no project-owned Drupal files yet; check skipped.'
  exit 0
fi

exec vendor/bin/phpcbf --standard=phpcs.xml.dist "${files[@]}"
