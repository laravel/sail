#!/usr/bin/env bash

UNAMEOUT="$(uname -s)"

# Verify operating system is supported...
case "${UNAMEOUT}" in
    Linux*)             MACHINE=linux;;
    Darwin*)            MACHINE=mac;;
    *)                  MACHINE="UNKNOWN"
esac

if [ "$MACHINE" == "UNKNOWN" ]; then
    echo "Unsupported operating system [$(uname -s)]. Laravel Sail supports MacOS, Linux, and Windows (WSL2)."
fi

# Define environment variables...
export APP_PORT=${APP_PORT:-80}
export APP_SERVICE=${APP_SERVICE:-"laravel.test"}
export MYSQL_PORT=${MYSQL_PORT:-3306}
export WWWUSER=${WWWUSER:-$UID}
export WWWGROUP=${WWWGROUP:-$(id -g)}

if [ "$MACHINE" == "linux" ]; then
    SEDCMD="sed -i"
elif [ "$MACHINE" == "mac" ]; then
    SEDCMD="sed -i .bak"
fi

# Ensure that Docker is running...
docker info > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "Docker is not running."

    exit 1
fi

# Determine if Sail is currently up...
PSRESULT="$(docker-compose ps -q)"

if [ ! -z "$PSRESULT" ]; then
    EXEC="yes"
else
    EXEC="no"
fi

if [ $# -gt 0 ]; then
    # Source the ".env" file so Laravel's environment variables are available...
    if [ -f ./.env ]; then
        source ./.env
    fi

    # Proxy PHP commands to the "php" binary on the application container...
    if [ "$1" == "php" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                php "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy Composer commands to the "composer" binary on the application container...
    elif [ "$1" == "composer" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                composer "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy Artisan commands to the "artisan" binary on the application container...
    elif [ "$1" == "artisan" ] || [ "$1" == "art" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                php artisan "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy the "test" command to the "php artisan test" Artisan command...
    elif [ "$1" == "test" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                php artisan test "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy the "dusk" command to the "php artisan dusk" Artisan command...
    elif [ "$1" == "dusk" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                -e "DUSK_DRIVER_URL=http://selenium:4444/wd/hub" \
                $APP_SERVICE \
                php artisan dusk "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Initiate a Laravel Tinker session within the application container...
    elif [ "$1" == "tinker" ] ; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                php artisan tinker
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy Node commands to the "node" binary on the application container...
    elif [ "$1" == "node" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                node "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Proxy NPM commands to the "npm" binary on the application container...
    elif [ "$1" == "npm" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                npm "$@"
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Initiate a MySQL CLI terminal session within the "mysql" container...
    elif [ "$1" == "mysql" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                mysql \
                bash -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root $MYSQL_DATABASE'
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Initiate a Bash shell within the application container...
    elif [ "$1" == "shell" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                $APP_SERVICE \
                bash
        else
            echo "Sail is not running."
            echo ""
            echo "Start Sail using: './sail up' or './sail up -d'"

            exit 1
        fi

    # Pass unknown commands to the "docker-compose" binary...
    else
        docker-compose "$@"
    fi
else
    docker-compose ps
fi
