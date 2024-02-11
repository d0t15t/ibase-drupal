#!/bin/bash

# Server credentials
SERVER_USERNAME="ja"
SERVER_IP="192.248.182.126"
SERVER_USER="www-data"

# Drupal site details
DRUPAL_ROOT="/var/www/ibase/web"
DRUPAL_PRIVATE_FOLDER="/var/www/ibase/private/the/backup_migrate"

DRUSH="/var/www/ibase/vendor/bin/drush"

# Local Drupal details
LOCAL_DRUPAL_ROOT="/Users/loom23/Sites/ibase/web"

# Check if MULTISITE_NAME is provided as an argument
if [ $# -eq 0 ]; then
    echo "Error: MULTISITE_NAME is missing. Please provide the multisite name as an argument when calling the script."
    exit 1
fi

MULTISITE_NAME="$1"

# Generate backup filename with multisite name and current date and time
BACKUP_FILENAME="${MULTISITE_NAME}_$(date +"%Y%m%d_%H%M%S").sql.gz"

# Log into the server and clear Drupal cache
ssh-agent bash -c "ssh-add && ssh $SERVER_USERNAME@$SERVER_IP 'cd $DRUPAL_ROOT && $DRUSH cr'"

# Export the database (gzipped) to the private folder
ssh-agent bash -c "ssh-add && ssh $SERVER_USERNAME@$SERVER_IP 'cd $DRUPAL_ROOT && $DRUSH sql:dump --gzip --result-file=$DRUPAL_PRIVATE_FOLDER/$BACKUP_FILENAME'"

# Download the exported database file
scp $SERVER_USERNAME@$SERVER_IP:$DRUPAL_PRIVATE_FOLDER/$BACKUP_FILENAME .

# Import the downloaded database into the local Drupal instance
gunzip -c "$BACKUP_FILENAME" | drush sql-cli --root="$LOCAL_DRUPAL_ROOT"

# Clean up temporary files
rm "$BACKUP_FILENAME"
