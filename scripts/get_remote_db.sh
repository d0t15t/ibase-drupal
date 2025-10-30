#!/usr/bin/env bash
#
# Usage:
#   ./get_remote_bu.sh [args...]
#
# This script calls ./export_remote_db.sh (forwarding any args)
# to create a DB structure dump on the remote host, then downloads
# the resulting dump file to ./downloads/.
#
# Example:
#   ./get_remote_bu.sh -l my-site --extra=foo
#

set -euo pipefail

REMOTE_HOST="ja@192.248.182.126"
EXPORT_SCRIPT="./scripts/export_remote_db.sh"
DOWNLOAD_DIR="./dumps"

if ! [ -f "$EXPORT_SCRIPT" ]; then
  echo "Error: $EXPORT_SCRIPT not found in $(pwd)."
  exit 1
fi

# Make export script executable if needed
if ! [ -x "$EXPORT_SCRIPT" ]; then
  chmod +x "$EXPORT_SCRIPT" || true
fi

echo "Running export script to create remote dump (forwarding arguments)..."
# Capture stdout/stderr so we can parse the remote path printed by the export script.
# If the export script fails, print its output and exit with its status.
OUTPUT=$("$EXPORT_SCRIPT" "$@" 2>&1) || { echo "export script failed:"; echo "$OUTPUT"; exit 1; }

# Show the export script output for transparency
echo "$OUTPUT"

# The export script prints the remote path on the last non-empty line.
# Extract the last non-empty line and trim whitespace.
REMOTE_PATH=$(printf '%s\n' "$OUTPUT" | awk 'NF{line=$0}END{print line}' | xargs || true)

if [ -z "$REMOTE_PATH" ]; then
  echo "Could not determine remote dump path from export script output."
  exit 1
fi

echo "Detected remote dump path: $REMOTE_PATH"

# Verify the file exists on the remote host
if ! ssh "$REMOTE_HOST" test -f "$REMOTE_PATH"; then
  echo "Remote file does not exist: $REMOTE_PATH"
  exit 1
fi

# Ensure local download directory exists
mkdir -p "$DOWNLOAD_DIR"

BASENAME=$(basename "$REMOTE_PATH")
LOCAL_PATH="$DOWNLOAD_DIR/$BASENAME"

echo "Downloading $REMOTE_HOST:$REMOTE_PATH -> $LOCAL_PATH"

# Use scp to copy the file. Note: if your remote path contains unusual characters
# (spaces/newlines), you may need to adjust quoting or use an alternate method.
scp "$REMOTE_HOST":"$REMOTE_PATH" "$LOCAL_PATH"

echo "✅ Download complete: $LOCAL_PATH"
