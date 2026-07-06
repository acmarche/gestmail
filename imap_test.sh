#!/usr/bin/env bash
  set -euo pipefail

  HOST="localhost"
  PORT="143"           # 143 = STARTTLS/plain, 993 = implicit TLS
  USER="jf.test"       # use the full mail address if login is user@domain
  read -rsp "Password for ${USER}: " PASS; echo

  echo "== Testing IMAP login for ${USER} against ${HOST}:${PORT} =="

  if [ "$PORT" = "993" ]; then
    # Implicit TLS (imaps)
    curl -v --url "imaps://${HOST}:${PORT}/INBOX" \
         --user "${USER}:${PASS}" \
         --insecure -X 'STATUS INBOX (MESSAGES)'
  else
    # Port 143 (upgrades to STARTTLS if the server offers it)
    curl -v --url "imap://${HOST}:${PORT}/INBOX" \
         --user "${USER}:${PASS}" \
         --ssl --insecure -X 'STATUS INBOX (MESSAGES)'
  fi
