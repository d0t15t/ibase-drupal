#!/bin/bash

# Set the path to the Drush executable
DRUSH_PATH=/var/www/ibase/vendor/bin/drush

# Check if the specified Drush path exists
if [ ! -x "$DRUSH_PATH" ]; then
  echo "Drush is not found at the specified path: $DRUSH_PATH. Please check the path and try again."
  exit 1
fi

# Create a directory to store the SQL dumps
DUMP_DIR=/var/www/ibase/private/backups
mkdir -p "$DUMP_DIR"

# Loop through each multisite under ./web/sites
for SITE_PATH in ./web/sites/*; do
  if [ -d "$SITE_PATH" ]; then
    # Extract the site name from the path
    SITE_NAME=$(basename "$SITE_PATH")

    # Run 'drush cr' for the current multisite
    $DRUSH_PATH --root=./web --uri=$SITE_NAME cr

    # Run 'drush sql-dump' and save to a file with the current date
    DUMP_FILE="$DUMP_DIR/$SITE_NAME--$(date +'%Y-%m-%d').sql.gz"
    $DRUSH_PATH --root=./web --uri=$SITE_NAME sql-dump --result-file=$DUMP_FILE --gzip

    echo "Site $SITE_NAME: Cache cleared and SQL dump created at $DUMP_FILE"
  fi
done

