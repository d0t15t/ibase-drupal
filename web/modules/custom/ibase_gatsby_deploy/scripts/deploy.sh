#!/usr/bin/env bash

# default values

NODE_PATH=${NODE_PATH:-''}
YARN_PATH=${YARN_PATH:-''}
FRONTEND_PATH=${FRONTEND_PATH:-''}

# set path for node and yarn

PATH="${PATH}:${NODE_PATH}:${YARN_PATH}"

# print info

cd "$FRONTEND_PATH" || return
#cd "$(dirname "$0")/../../frontend/usu-com" || return
echo "Date: $(date '+%d.%m.%Y %H:%M:%S')"
echo "Path: $PWD"
echo "Node executable: $(which node)"
echo "Node version: $(node -v)"
echo "Yarn executable: $(which yarn)"
echo "Yarn version: $(yarn -v)"
echo

# build

node deploy.js

if [ $? = 0 ]
then
  # deploy.js is already running.
  exit 1
else
  echo "Deployment succeeded"

  # set permissions
  if [ -z ${SET_USER+x} ]; then
    ``
    # $SET_USER is not set
  else
    # $SET_USER is set
    if [ -z ${SET_GROUP+x} ]; then
      # $SET_GROUP is not set
      USER_AND_GROUP="$SET_USER"
    else
      # $SET_GROUP is set
      USER_AND_GROUP="$SET_USER:$SET_GROUP"
    fi

    echo ""
    echo "Trying to set directory permission"

    sudo chown -R $USER_AND_GROUP .cache
    sudo chown -R $USER_AND_GROUP public
    sudo chown -R $USER_AND_GROUP public_dist
  fi
fi


