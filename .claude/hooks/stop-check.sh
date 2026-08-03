#!/usr/bin/env bash
# Stop hook: surface formatting/type errors before finishing a turn. Informational only (never blocks).
set -u
issues=""

if ! vendor/bin/pint --test >/tmp/claude-stop-pint.txt 2>&1; then
  issues="${issues}Pint formatting issues (see vendor/bin/pint --test). "
fi

if ! npx --no-install tsc --noEmit >/tmp/claude-stop-tsc.txt 2>&1; then
  issues="${issues}TypeScript errors (see npx tsc --noEmit). "
fi

if [ -n "$issues" ]; then
  jq -n --arg msg "$issues" '{"systemMessage": $msg}'
fi

exit 0
