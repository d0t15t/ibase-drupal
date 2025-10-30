#!/usr/bin/env bash
#
# Usage: ./export_remote_db.sh [-l site-alias] [-H host] [-d remote-project-dir] [-k keep] [--dry-run] [-- other-drush-options]
# Example: ./export_remote_db.sh -l my-site -H ja@192.248.182.126 -k 30 --extra=foo
#
# Description:
#   - Runs a remote Drush sql-dump (structure-only) on the given remote host/project dir.
#   - Stores dumps under <remote-project-dir>/dumps/<site-alias>/
#   - Keeps only the most recent N backups per site-alias (default: 20).
#   - Includes locking to prevent concurrent runs and useful logging output.
#

set -euo pipefail

# === DEFAULTS / CONFIGURABLE VARIABLES ===
REMOTE_HOST="${REMOTE_HOST:-ja@192.248.182.126}"
REMOTE_PROJECT_DIR="${REMOTE_PROJECT_DIR:-/var/www/ibase}"
STRUCTURE_TABLES="${STRUCTURE_TABLES:-cache_bootstrap,cache_config,cache_container,cache_data,cache_default,cache_discovery,cache_discovery_migration,cache_entity,cache_jsonapi_normalizations,cache_menu,sessions,watchdog}"
SITE_ALIAS="default"
KEEP=20
DRUSH_BIN="vendor/bin/drush"
DRY_RUN=0

EXTRA_DRUSH_OPTS=()

usage() {
  cat <<EOF
Usage: $0 [options] [-- drush-options]
Options:
  -l, --uri <site-alias>       Site alias (also used in filename and dump directory)
  -H, --host <ssh-host>        Remote SSH host (default: ${REMOTE_HOST})
  -d, --dir <remote-dir>       Remote project directory (default: ${REMOTE_PROJECT_DIR})
  -k, --keep <n>               Number of backups to keep per site-alias (default: ${KEEP})
      --dry-run                Don't delete any files; show what would be deleted.
  -h, --help                   Show this help and exit

Any unknown options starting with -- are forwarded to drush.
Example:
  $0 -l mysite -H ja@192.248.182.126 -- --extra=foo
EOF
}

# === PARSE COMMAND-LINE OPTIONS ===
while [[ $# -gt 0 ]]; do
  case "$1" in
    -l|--uri)
      SITE_ALIAS="$2"
      EXTRA_DRUSH_OPTS+=("-l" "$2")
      shift 2
      ;;
    -H|--host)
      REMOTE_HOST="$2"
      shift 2
      ;;
    -d|--dir)
      REMOTE_PROJECT_DIR="$2"
      shift 2
      ;;
    -k|--keep)
      KEEP="$2"
      if ! [[ "$KEEP" =~ ^[0-9]+$ ]]; then
        echo "Invalid value for --keep: $KEEP" >&2
        exit 2
      fi
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    --)
      shift
      while [[ $# -gt 0 ]]; do
        EXTRA_DRUSH_OPTS+=("$1")
        shift
      done
      ;;
    --*)
      EXTRA_DRUSH_OPTS+=("$1")
      shift
      ;;
    *)
      echo "Unknown option or stray argument: $1" >&2
      usage
      exit 2
      ;;
  esac
done

# === BUILD FILE PATHS ===
DATE_STR=$(date +'%Y-%m-%d_%H-%M-%S')
DUMP_DIR="dumps/${SITE_ALIAS}"
DUMP_FILENAME="${SITE_ALIAS}--${DATE_STR}.sql"
DUMP_PATH="${DUMP_DIR}/${DUMP_FILENAME}"

# === BUILD DRUSH COMMAND ===
DRUSH_CMD=(
  "$DRUSH_BIN"
  sql-dump
  "--structure-tables-list=${STRUCTURE_TABLES}"
  "--result-file=${REMOTE_PROJECT_DIR}/${DUMP_PATH}"
  "--gzip"
)

if (( ${#EXTRA_DRUSH_OPTS[@]} > 0 )); then
  DRUSH_CMD+=("${EXTRA_DRUSH_OPTS[@]}")
fi

# Build safe, escaped command string
DRUSH_CMD_STR=$(printf ' %q' "${DRUSH_CMD[@]}")
DRUSH_CMD_STR=${DRUSH_CMD_STR:1}

echo "Running remote Drush command on host '${REMOTE_HOST}'..."
echo "  ${DRUSH_CMD_STR}"
if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "  (dry-run: will not delete old backups)"
fi

# === RUN REMOTE COMMAND ===
ssh "${REMOTE_HOST}" bash -se <<EOF
set -euo pipefail

REMOTE_PROJECT_DIR=$(printf %q "${REMOTE_PROJECT_DIR}")
DUMP_DIR=$(printf %q "${DUMP_DIR}")
SITE_ALIAS=$(printf %q "${SITE_ALIAS}")
DUMP_FILENAME=$(printf %q "${DUMP_FILENAME}")
DUMP_PATH=$(printf %q "${DUMP_PATH}")
DRUSH_CMD_STR=$(printf %q "${DRUSH_CMD_STR}")
KEEP=${KEEP}
DRY_RUN=${DRY_RUN}
DRUSH_BIN=$(printf %q "${DRUSH_BIN}")

cd "${REMOTE_PROJECT_DIR}"
mkdir -p "\${DUMP_DIR}"

LOCK_DIR="\${REMOTE_PROJECT_DIR}/.export_lock_\${SITE_ALIAS}"
if mkdir "\${LOCK_DIR}" 2>/dev/null; then
  echo "Acquired remote lock \${LOCK_DIR}"
  trap 'rm -rf "\${LOCK_DIR}"; exit 1' INT TERM EXIT
else
  echo "Another backup/export process is running for \${SITE_ALIAS}. Exiting." >&2
  exit 1
fi

if ! command -v "\${DRUSH_BIN}" >/dev/null 2>&1 && [ ! -x "\${DRUSH_BIN}" ]; then
  echo "Warning: drush binary '\${DRUSH_BIN}' not found or not executable. Trying 'drush' in PATH..."
fi

echo "Changing to project dir and running dump..."
cd "${REMOTE_PROJECT_DIR}"
pwd

eval "\${DRUSH_CMD_STR}"

FULL_GZ_PATH="\${DUMP_PATH}.gz"
if [ -f "\${FULL_GZ_PATH}" ]; then
  echo "✅ Database structure dump complete: ${REMOTE_PROJECT_DIR}/\${FULL_GZ_PATH}"
else
  echo "Warning: expected dump at ${REMOTE_PROJECT_DIR}/\${FULL_GZ_PATH} not found." >&2
fi

echo "Pruning old backups in ${REMOTE_PROJECT_DIR}/\${DUMP_DIR} (keeping ${KEEP})..."
cd "\${DUMP_DIR}"

shopt -s nullglob
mapfile -t sorted < <(ls -1t -- "\${SITE_ALIAS}--"*.sql.gz 2>/dev/null || true)

total=\${#sorted[@]}
if (( total <= KEEP )); then
  echo "No files to prune (have \${total}, keep \${KEEP})."
else
  echo "Found \${total} backup(s). Will remove \$(expr \${total} - \${KEEP}) oldest file(s)."
  for ((i=KEEP; i<total; i++)); do
    echo "  -> ${REMOTE_PROJECT_DIR}/\${DUMP_DIR}/\${sorted[i]}"
  done

  if (( DRY_RUN == 0 )); then
    for ((i=KEEP; i<total; i++)); do
      rm -f -- "\${sorted[i]}" || echo "Failed to delete: \${sorted[i]}" >&2
    done
    echo "Pruning complete."
  else
    echo "(dry-run) No files were deleted."
  fi
fi

rm -rf "\${LOCK_DIR}"
trap - INT TERM EXIT
EOF

echo "Path to backup:"
echo "${REMOTE_PROJECT_DIR}/${DUMP_PATH}.gz"
