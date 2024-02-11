#!/bin/bash

# Set the path to the Drush executable
DRUSH_PATH=/var/www/ibase/vendor/bin/drush

# Check if the specified Drush path exists
if [ ! -x "$DRUSH_PATH" ]; then
  echo "Drush is not found at the specified path: $DRUSH_PATH. Please check the path and try again."
  exit 1
fi

# Check if at least one argument is provided
if [ "$#" -lt 1 ]; then
  echo "Usage: $0 [drush flags and arguments]"
  exit 1
fi

# Loop through each multisite under ./web/sites
for SITE_PATH in ./web/sites/*; do
  if [ -d "$SITE_PATH" ]; then
    # Extract the site name from the path
    SITE_NAME=$(basename "$SITE_PATH")
    echo "!!! Site: $SITE_NAME\n\n"

    # Run the Drush command for the current multisite
    $DRUSH_PATH --root=./web --uri=$SITE_NAME "$@"
  fi
done

