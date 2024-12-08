#!/bin/sh
set -eo pipefail

echo "Booting up Node"

cd "$APP_DIR"

export DOCKER_BRIDGE_IP=$(/sbin/ip route|awk '/default/ { print $3 }')

# Skip entrypoint for following commands
case "$1" in
   sh|php|composer) exec "$@" && exit 0;;
esac

if [ ! -z "$SKIP_ENTRYPOINT" ]; then
    exec "$@" && exit 0
fi

case "$APP_ENV" in
   prod|dev|test) ;;
   *) >&2 echo env "APP_ENV" must be in \"prod, dev, test\" && exit 1;;
esac

COMMAND="$@"

if [ "$APP_ENV" == "dev" ]; then
    XDEBUG=${XDEBUG:=true}

elif [ "$APP_ENV" == "test" ]; then
    if [ -f "$APP_DIR/.env.dist" ]; then
        loadEnvFile "$APP_DIR/.env.dist"
    fi
fi

COMMAND=${COMMAND:="php-fpm -F"}

echo "Enabling extensions"

enableExt() {
    extension=$1
    docker-php-ext-enable ${extension}

    if [ "$APP_DEBUG" == 1 ]; then
        echo -e " > $extension enabled"
    fi
}

if [ ! -z "$COMPOSER_EXEC" ]; then
    ${COMPOSER_EXEC}
fi

rm -rf $APP_DIR/var/cache/*


CONFIG="/var/www/node.env"
if [ ! -f $CONFIG ] ; then
    echo "Adding env variables"
    echo "RPC_USER=${RPC_USER}" > $CONFIG
    echo "RPC_PASSWORD=${RPC_PASSWORD}" >> $CONFIG
    echo "RPC_PORT=${RPC_PORT}" >> $CONFIG
    echo "RPC_IP=${RPC_IP}" >> $CONFIG
    echo "CHAIN_SYMBOL=${CHAIN_SYMBOL}" >> $CONFIG
fi

${COMMAND}