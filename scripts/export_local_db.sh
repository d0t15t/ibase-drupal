#!/usr/bin/env bash
#
# Usage: ./backup_local.sh [-l site-alias] [-d project-dir] [-k keep] [--dry-run] [-- other-drush-options]
# Example: ./backup_local.sh -l default -k 20
#
# Description:
#   - Runs a local Drush sql-dump (structure-only) for the given site alias.
#   - Stores dumps under <project-dir>/dumps/<site-alias>/
#   - Keeps only the most recent N backups per site-alias (default: 20).
#   - Includes locking to prevent concurrent runs and optional dry-run mode.
#

set -euo pipefail

# === DEFAULTS ===
PROJECT_DIR="${PROJECT_DIR:-$(pwd)}"
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
  -l, --uri <site-alias>   Site alias (used in filename and dump directory)
  -d, --dir <project-dir>  Local project directory (default: current dir)
  -k, --keep <n>           Number of backups to keep (default: ${KEEP})
      --dry-run            Show what would be deleted without deleting
  -h, --help               Show this help and exit
EOF
}

# === PARSE OPTIONS ===
while [[ $# -gt 0 ]]; do
  case "$1" in
    -l|--uri)
      SITE_ALIAS="$2"
      EXTRA_DRUSH_OPTS+=("-l" "$2")
      shift 2
      ;;
    -d|--dir)
      PROJECT_DIR="$2"
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
      echo "Unknown option: $1" >&2
      usage
      exit 2
      ;;
  esac
done

# === BUILD PATHS ===
DATE_STR=$(date +'%Y-%m-%d_%H-%M-%S')
DUMP_DIR="${PROJECT_DIR}/dumps/${SITE_ALIAS}"
DUMP_FILENAME="${SITE_ALIAS}--${DATE_STR}.sql"
DUMP_PATH="${DUMP_DIR}/${DUMP_FILENAME}"

mkdir -p "${DUMP_DIR}"

LOCK_DIR="${PROJECT_DIR}/.backup_lock_${SITE_ALIAS}"
if mkdir "${LOCK_DIR}" 2>/dev/null; then
  echo "Acquired local lock ${LOCK_DIR}"
  trap 'rm -rf "${LOCK_DIR}"; exit 1' INT TERM EXIT
else
  echo "Another backup/export process is running for ${SITE_ALIAS}. Exiting." >&2
  exit 1
fi

# === RUN DRUSH DUMP ===
DRUSH_CMD=(
  "$DRUSH_BIN"
  sql-dump
  "--structure-tables-list=${STRUCTURE_TABLES}"
  "--result-file=${DUMP_PATH}"
  "--gzip"
)

if (( ${#EXTRA_DRUSH_OPTS[@]} > 0 )); then
  DRUSH_CMD+=("${EXTRA_DRUSH_OPTS[@]}")
fi

echo "Running local Drush command in ${PROJECT_DIR}:"
printf '  %q ' "${DRUSH_CMD[@]}"
echo

(cd "${PROJECT_DIR}" && "${DRUSH_CMD[@]}")

FULL_GZ_PATH="${DUMP_PATH}.gz"
if [[ -f "${FULL_GZ_PATH}" ]]; then
  echo "✅ Database structure dump complete: ${FULL_GZ_PATH}"
else
  echo "⚠️  Warning: expected dump at ${FULL_GZ_PATH} not found." >&2
fi

# === PRUNE OLD BACKUPS ===
echo "Pruning old backups in ${DUMP_DIR} (keeping ${KEEP})..."
cd "${DUMP_DIR}"

shopt -s nullglob
mapfile -t sorted < <(ls -1t -- "${SITE_ALIAS}--"*.sql.gz 2>/dev/null || true)
total=${#sorted[@]}

if (( total <= KEEP )); then
  echo "No files to prune (have ${total}, keep ${KEEP})."
else
  echo "Found ${total} backups. Will remove $(( total - KEEP )) oldest file(s)."
  for ((i=KEEP; i<total; i++)); do
    echo "  -> ${sorted[i]}"
  done
  if (( DRY_RUN == 0 )); then
    for ((i=KEEP; i<total; i++)); do
      rm -f -- "${sorted[i]}" || echo "Failed to delete: ${sorted[i]}" >&2
    done
    echo "Pruning complete."
  else
    echo "(dry-run) No files were deleted."
  fi
fi

rm -rf "${LOCK_DIR}"
trap - INT TERM EXIT

echo "✅ Local backup finished successfully."
echo "Path: ${FULL_GZ_PATH}"
