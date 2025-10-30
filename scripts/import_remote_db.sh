#!/usr/bin/env bash
#
# Usage:
#   ./import_remote_db.sh [args...]
#
# Behaviour:
#   1) Runs ./get_remote_db.sh (forwarding any args) to download the remote structure dump.
#   2) After a successful download, runs ./export_local_db.sh (forwarding any args) to create a local backup.
#   3) Imports the downloaded dump into the local site using Drush.
#
# Notes:
#   - This script forwards all arguments to both get_remote_db.sh and export_local_db.sh.
#   - To ensure the import targets the correct site, supply -l <site-alias> / --uri <site-alias>.
#   - If the downloaded dump is gzipped (*.gz) it will be decompressed on the fly.
#

set -euo pipefail

# Save original args so we can forward them verbatim
ORIG_ARGS=("$@")

GET_REMOTE="./scripts/get_remote_db.sh"
EXPORT_LOCAL="./scripts/export_local_db.sh"
DRUSH_BIN="${DRUSH_BIN:-vendor/bin/drush}"

# Minimal parse to extract site alias and any explicit drush options after `--`
SITE_ALIAS="default"
DRUSH_OPTS=()

# Walk args to find -l/--uri and `--` forwarded drush options
i=0
while [ $i -lt ${#ORIG_ARGS[@]} ]; do
  arg="${ORIG_ARGS[$i]}"
  case "$arg" in
    -l|--uri)
      if [ $((i+1)) -ge ${#ORIG_ARGS[@]} ]; then
        echo "Missing value for $arg" >&2
        exit 2
      fi
      SITE_ALIAS="${ORIG_ARGS[$((i+1))]}"
      DRUSH_OPTS+=("-l" "$SITE_ALIAS")
      i=$((i+2))
      ;;
    --)
      # everything after -- is passed as drush options
      j=$((i+1))
      while [ $j -lt ${#ORIG_ARGS[@]} ]; do
        DRUSH_OPTS+=("${ORIG_ARGS[$j]}")
        j=$((j+1))
      done
      break
      ;;
    *)
      i=$((i+1))
      ;;
  esac
done

# Ensure helper scripts exist
if ! [ -f "$EXPORT_LOCAL" ]; then
  echo "Error: $EXPORT_LOCAL not found in $(pwd)." >&2
  exit 1
fi
if ! [ -f "$GET_REMOTE" ]; then
  echo "Error: $GET_REMOTE not found in $(pwd)." >&2
  exit 1
fi

# Make sure both scripts are executable (best-effort)
if ! [ -x "$EXPORT_LOCAL" ]; then
  chmod +x "$EXPORT_LOCAL" 2>/dev/null || true
fi
if ! [ -x "$GET_REMOTE" ]; then
  chmod +x "$GET_REMOTE" 2>/dev/null || true
fi

# Ensure drush binary exists or fall back to PATH
if ! command -v "$DRUSH_BIN" >/dev/null 2>&1; then
  if command -v drush >/dev/null 2>&1; then
    DRUSH_BIN="drush"
  else
    echo "Warning: Drush not found at ${DRUSH_BIN} nor in PATH. Import will likely fail." >&2
  fi
fi

echo "1) Downloading remote dump (using ${GET_REMOTE})..."
GET_OUTPUT="$("${GET_REMOTE}" "${ORIG_ARGS[@]}" 2>&1)" || { echo "get_remote_db failed:"; echo "$GET_OUTPUT"; exit 1; }
# show output for transparency
printf '%s\n' "$GET_OUTPUT"

# Try to extract the local downloaded path from get_remote output.
# get_remote.sh prints a line like: "✅ Download complete: ./dumps/<file>"
DOWNLOAD_PATH=""
DOWNLOAD_PATH=$(printf '%s\n' "$GET_OUTPUT" | awk -F': ' '/Download complete/{print $NF}' | tail -n1 | xargs || true)

# fallback: last non-empty line (and if it contains a colon, take the part after the colon)
if [ -z "$DOWNLOAD_PATH" ]; then
  DOWNLOAD_PATH=$(printf '%s\n' "$GET_OUTPUT" | awk 'NF{line=$0}END{print line}' | xargs || true)
  if printf '%s\n' "$DOWNLOAD_PATH" | grep -q ':'; then
    DOWNLOAD_PATH=$(printf '%s\n' "$DOWNLOAD_PATH" | awk -F': ' '{print $NF}' | xargs || true)
  fi
fi

if [ -z "$DOWNLOAD_PATH" ]; then
  echo "Could not determine local download path from get_remote_db output. Aborting." >&2
  exit 1
fi

if ! [ -f "$DOWNLOAD_PATH" ]; then
  echo "Downloaded file not found at: $DOWNLOAD_PATH" >&2
  exit 1
fi

echo "Download succeeded: $DOWNLOAD_PATH"

echo
echo "2) Creating a local backup now that the remote dump was downloaded (using ${EXPORT_LOCAL})..."
if ! "${EXPORT_LOCAL}" "${ORIG_ARGS[@]}"; then
  echo "Local backup failed. Aborting import." >&2
  exit 1
fi

echo
echo "3) Importing downloaded dump into local DB (site alias: ${SITE_ALIAS})"
echo "   File: $DOWNLOAD_PATH"
if [[ "$DOWNLOAD_PATH" == *.gz ]]; then
  echo "   (decompressing on the fly)"
  gzip -dc "$DOWNLOAD_PATH" | "$DRUSH_BIN" sql-cli "${DRUSH_OPTS[@]}"
else
  cat "$DOWNLOAD_PATH" | "$DRUSH_BIN" sql-cli "${DRUSH_OPTS[@]}"
fi

echo "✅ Import finished successfully."
