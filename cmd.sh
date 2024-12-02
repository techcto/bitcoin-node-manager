#!/bin/bash

export $(egrep -v '^#' .env | xargs)
args=("$@")

export TAG_RELEASE=$(date +"%y.%m%d.%S")
export SOLODEV_RELEASE=$TAG_RELEASE
export AWS_PROFILE=develop
DATE=$(date +%d%H%M)

bundle(){
    docker-compose -f docker-compose.bundle.yml up --build
    aws s3 cp dist/node-manager.zip s3://litecoin-pro/node-manager.zip --acl public-read
}

$*