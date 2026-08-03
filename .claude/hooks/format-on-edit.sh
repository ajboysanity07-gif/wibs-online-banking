#!/usr/bin/env bash
# PostToolUse hook (Edit|Write): format the just-touched file with the project's own formatters.
set -u
input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"

[ -z "$file" ] && exit 0
[ -f "$file" ] || exit 0

case "$file" in
  *.php)
    vendor/bin/pint --dirty >/dev/null 2>&1
    ;;
  *.ts|*.tsx|*.js|*.jsx)
    npx --no-install prettier --write "$file" >/dev/null 2>&1
    npx --no-install eslint --fix "$file" >/dev/null 2>&1
    ;;
esac

exit 0
