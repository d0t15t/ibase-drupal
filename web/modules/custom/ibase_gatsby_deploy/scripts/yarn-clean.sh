#!/usr/bin/env bash

# default values

NODE_PATH=${NODE_PATH:-''}
YARN_PATH=${YARN_PATH:-''}

# set path for node and yarn

PATH="${PATH}:${NODE_PATH}:${YARN_PATH}"

cd "$(dirname "$0")/../../frontend/usu-com" || return

yarn clean
