#!/usr/bin/env bash

# Colors
blue="\033[1;36m"
green="\033[1;32m"
red="\033[1;31m"
yellow="\033[1;33;40m"
resetColor="\033[0m"

# Shortcut to run Sail
ROOT_PATH="$(cd $(dirname $0)/../../../../ && pwd)"
SAIL_PATH="${ROOT_PATH}/vendor/bin/sail"
if [ ! -f "${SAIL_PATH}" ]; then
    echo -e "${red}Sail not found! This test is designed to run under a functioning Laravel application.${resetColor}"
    exit 1
fi
sail() {
    {
        set +x
    } 2>/dev/null
    "${SAIL_PATH}" "$@"
}

# Counters
SUCCESS=0
FAILURE=0

# Starts sail, this is needed to ensure sail is running and the test validation below will get the error message.
echo -e "${blue}Starting sail...${resetColor}"
echo "Under CI environments it is common to have a non TTY console."
echo "In order to allow Sail to run under those environments, a few tweaks are necessary."
sail up -d || exit 1

test() {
    echo -e "${blue}Testing: ${resetColor}${yellow}TTY issue at sail $@${resetColor}"
    sail "$@" > output 2>&1 &

    wait

    if [ "$(cat output)" == "the input device is not a TTY" ]; then
        ((FAILURE=FAILURE+1))
        echo -e "${red}Failed!${resetColor}"
    else
        ((SUCCESS=SUCCESS+1))
        echo -e "${green}Passed!${resetColor}"
    fi

    # Run `cat output` before this point to debug the test cases.
    rm -f output
}

echo -e "${blue}Starting tests...${resetColor}"

test artisan --version
test bin phpunit --version
test composer --version
test node --version
test npm --version
test npx --version
test php --version
test share --version # This test is expected to pass as the command already ignores TTY.
test shell -c whoami
test root-shell -c whoami
test yarn --version

sail down && {
    ((SUCCESS=SUCCESS+1))
    echo -e "${green}Sail stopped.${resetColor}"
} || {
    ((FAILURE=FAILURE+1))
    echo -e "${red}Failed to stop Sail.${resetColor}"
}

COMPLETE_MSG="${blue}Tests completed!${resetColor} ${green}${SUCCESS} success"
if [ "${SUCCESS}" != "1" ]; then
    COMPLETE_MSG="${COMPLETE_MSG}es"
fi
COMPLETE_MSG="${COMPLETE_MSG}${resetColor}, ${red}${FAILURE} failure"
if [ "${FAILURE}" != "1" ]; then
    COMPLETE_MSG="${COMPLETE_MSG}s"
fi
COMPLETE_MSG="${COMPLETE_MSG}${resetColor}"

echo -e "${COMPLETE_MSG}"

# Add error code to the script.
[ "${FAILURE}" == "0" ]
