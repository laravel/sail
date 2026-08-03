#!/usr/bin/env bash

set -euo pipefail

sail_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
probe_file="storage/app/sail-background-command-probe"

test ! -e "$probe_file"

cp "$sail_root/tests/fixtures/scheduled-background-command.php" routes/console.php

printf -v sail_command '%q artisan schedule:run' "$sail_root/bin/sail"

script -qec "$sail_command" /dev/null

for _ in {1..100}; do
    if test -f "$probe_file"; then
        exit 0
    fi

    sleep 0.1
done

echo 'The background scheduled command did not complete.' >&2
exit 1
