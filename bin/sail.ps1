$esc = [char]27
$BOLD = "$esc[1m"
$YELLOW = "$esc[33m"
$GREEN = "$esc[32m"
$NC = "$esc[0m"

# Function that prints the available commands...
function Show-Help {
    Write-Host "Laravel Sail"
    Write-Host
    Write-Host "${YELLOW}Usage:${NC}"
    Write-Host "  sail COMMAND [options] [arguments]"
    Write-Host
    Write-Host "Unknown commands are passed to the docker-compose binary."
    Write-Host
    Write-Host "${YELLOW}docker-compose Commands:${NC}"
    Write-Host "  ${GREEN}sail up${NC}        Start the application."
    Write-Host "  ${GREEN}sail up -d${NC}     Start the application in the background"
    Write-Host "  ${GREEN}sail stop${NC}      Stop the application."
    Write-Host "  ${GREEN}sail restart${NC}   Restart the application."
    Write-Host "  ${GREEN}sail ps${NC}        Display the status of all containers"
    Write-Host
    Write-Host "${YELLOW}Artisan Commands:${NC}"
    Write-Host "  ${GREEN}sail artisan ...${NC}          Run an Artisan command"
    Write-Host "  ${GREEN}sail artisan queue:work${NC}"
    Write-Host
    Write-Host "${YELLOW}PHP Commands:${NC}"
    Write-Host "  ${GREEN}sail php ...${NC}   Run a PHP command"
    Write-Host "  ${GREEN}sail php -v${NC}"
    Write-Host
    Write-Host "${YELLOW}Composer Commands:${NC}"
    Write-Host "  ${GREEN}sail composer ...${NC}                       Run a Composer command"
    Write-Host "  ${GREEN}sail composer require laravel/sanctum${NC}"
    Write-Host
    Write-Host "${YELLOW}Node Commands:${NC}"
    Write-Host "  ${GREEN}sail node ...${NC}         Run a Node command"
    Write-Host "  ${GREEN}sail node --version${NC}"
    Write-Host
    Write-Host "${YELLOW}NPM Commands:${NC}"
    Write-Host "  ${GREEN}sail npm ...${NC}        Run a NPM command"
    Write-Host "  ${GREEN}sail npx${NC}            Run a NPX command"
    Write-Host "  ${GREEN}sail npm run prod${NC}"
    Write-Host
    Write-Host "${YELLOW}PNPM Commands:${NC}"
    Write-Host "  ${GREEN}sail pnpm ...${NC}        Run a PNPM command"
    Write-Host "  ${GREEN}sail pnpx${NC}            Run a PNPX command"
    Write-Host "  ${GREEN}sail pnpm run prod${NC}"
    Write-Host
    Write-Host "${YELLOW}Yarn Commands:${NC}"
    Write-Host "  ${GREEN}sail yarn ...${NC}        Run a Yarn command"
    Write-Host "  ${GREEN}sail yarn run prod${NC}"
    Write-Host
    Write-Host "${YELLOW}Bun Commands:${NC}"
    Write-Host "  ${GREEN}sail bun ...${NC}        Run a Bun command"
    Write-Host "  ${GREEN}sail bunx${NC}           Run a BunX command"
    Write-Host "  ${GREEN}sail bun run prod${NC}"
    Write-Host
    Write-Host "${YELLOW}Database Commands:${NC}"
    Write-Host "  ${GREEN}sail mysql${NC}     Start a MySQL CLI session within the 'mysql' container"
    Write-Host "  ${GREEN}sail mariadb${NC}   Start a MariaDB CLI session within the 'mariadb' container"
    Write-Host "  ${GREEN}sail psql${NC}      Start a PostgreSQL CLI session within the 'pgsql' container"
    Write-Host "  ${GREEN}sail redis${NC}     Start a Redis CLI session within the 'redis' container"
    Write-Host
    Write-Host "${YELLOW}Debugging:${NC}"
    Write-Host "  ${GREEN}sail debug ...${NC}          Run an Artisan command in debug mode"
    Write-Host "  ${GREEN}sail debug queue:work${NC}"
    Write-Host
    Write-Host "${YELLOW}Running Tests:${NC}"
    Write-Host "  ${GREEN}sail test${NC}          Run the PHPUnit tests via the Artisan test command"
    Write-Host "  ${GREEN}sail phpunit ...${NC}   Run PHPUnit"
    Write-Host "  ${GREEN}sail pest ...${NC}      Run Pest"
    Write-Host "  ${GREEN}sail pint ...${NC}      Run Pint"
    Write-Host "  ${GREEN}sail dusk${NC}          Run the Dusk tests (Requires the laravel/dusk package)"
    Write-Host "  ${GREEN}sail dusk:fails${NC}    Re-run previously failed Dusk tests (Requires the laravel/dusk package)"
    Write-Host
    Write-Host "${YELLOW}Container CLI:${NC}"
    Write-Host "  ${GREEN}sail shell${NC}        Start a shell session within the application container"
    Write-Host "  ${GREEN}sail bash${NC}         Alias for 'sail shell'"
    Write-Host "  ${GREEN}sail root-shell${NC}   Start a root shell session within the application container"
    Write-Host "  ${GREEN}sail root-bash${NC}    Alias for 'sail root-shell'"
    Write-Host "  ${GREEN}sail tinker${NC}       Start a new Laravel Tinker session"
    Write-Host
    Write-Host "${YELLOW}Sharing:${NC}"
    Write-Host "  ${GREEN}sail share${NC}   Share the application publicly via a temporary URL"
    Write-Host "  ${GREEN}sail open${NC}    Open the site in your browser"
    Write-Host
    Write-Host "${YELLOW}Binaries:${NC}"
    Write-Host "  ${GREEN}sail bin ...${NC}   Run Composer binary scripts from the vendor/bin directory"
    Write-Host
    Write-Host "${YELLOW}Customization:${NC}"
    Write-Host "  ${GREEN}sail artisan sail:publish${NC}   Publish the Sail configuration files"
    Write-Host "  ${GREEN}sail build --no-cache${NC}       Rebuild all of the Sail containers"

    exit 1
}

# Proxy the "help" command...
if ($args.Length -gt 0) {
    if ($args[0] -in "help", "-h", "-help", "--help") {
        Show-Help
    }
} else {
    Show-Help
}

function Read-DotEnv {
    param(
        [String]$file
    )

    Get-Content $file | ForEach-Object {
        $name, $value = $_.split('=')
        if ([String]::IsNullOrWhiteSpace($name) -or $name.Contains('#')) {
            return
        }
        Set-Content env:\$name $value
    }
}

# Source the ".env" file so Laravel's environment variables are available...
if (-not [String]::IsNullOrEmpty($env:APP_ENV) -and (Test-Path ".\.env.$env:APP_ENV")) {
    Read-DotEnv ".\.env.$env:APP_ENV"
} elseif (Test-Path ".\.env") {
    Read-DotEnv ".\.env"
}

# Define environment variables...
$env:APP_PORT = if ($env:APP_PORT) { $env:APP_PORT } else { 80 }
$env:APP_SERVICE = if ($env:APP_SERVICE) { $env:APP_SERVICE } else { "laravel.test" }
$env:DB_PORT = if ($env:DB_PORT) { $env:DB_PORT } else { 3306 }
$env:WWWUSER = if ($env:WWWUSER) { $env:WWWUSER } else { [System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value }
$env:WWWGROUP = if ($env:WWWGROUP) { $env:WWWGROUP } else { [System.Security.Principal.WindowsIdentity]::GetCurrent().Groups | Select-Object -First 1 }

$env:SAIL_FILES = if ($env:SAIL_FILES) { $env:SAIL_FILES } else { "" }
$env:SAIL_SHARE_DASHBOARD = if ($env:SAIL_SHARE_DASHBOARD) { $env:SAIL_SHARE_DASHBOARD } else { 4040 }
$env:SAIL_SHARE_SERVER_HOST = if ($env:SAIL_SHARE_SERVER_HOST) { $env:SAIL_SHARE_SERVER_HOST } else { "laravel-sail.site" }
$env:SAIL_SHARE_SERVER_PORT = if ($env:SAIL_SHARE_SERVER_PORT) { $env:SAIL_SHARE_SERVER_PORT } else { 8080 }
$env:SAIL_SHARE_SUBDOMAIN = if ($env:SAIL_SHARE_SUBDOMAIN) { $env:SAIL_SHARE_SUBDOMAIN } else { "" }
$env:SAIL_SHARE_DOMAIN = if ($env:SAIL_SHARE_DOMAIN) { $env:SAIL_SHARE_DOMAIN } else { "$env:SAIL_SHARE_SERVER_HOST" }
$env:SAIL_SHARE_SERVER = if ($env:SAIL_SHARE_SERVER) { $env:SAIL_SHARE_SERVER } else { "" }

$env:PWD = (Get-Location).Path

# Function that outputs Sail is not running...
function Show-SailNotRunning {
    Write-Host "${BOLD}Sail is not running.${NC}"
    Write-Host
    Write-Host "${BOLD}You may Sail using the following commands:${NC} '.\vendor\bin\sail.ps1 up' or '.\vendor\bin\sail.ps1 up -d'"

    exit 1
}

# Define Docker Compose command prefix...
try {
    docker compose > $null 2>&1
    $DOCKER_COMPOSE = "docker compose"
} catch {
    $DOCKER_COMPOSE = "docker-compose"
}

if (-not [String]::IsNullOrEmpty($env:SAIL_FILES)) {
    # Convert SAIL_FILES to an array...
    $SAIL_FILES = $env:SAIL_FILES -split ":"

    foreach ($FILE in $SAIL_FILES) {
        if (Test-Path $FILE) {
            $DOCKER_COMPOSE += "-f", $FILE
        } else {
            Write-Host "${BOLD}Unable to find Docker Compose file: '$FILE'${NC}"

            exit 1
        }
    }
}

$EXEC = "yes"

if ([String]::IsNullOrEmpty($env:SAIL_SKIP_CHECKS)) {
    # Ensure that Docker is running...
    try {
        $output = docker info 2>&1
        if ($output -match "error during connect") {
            Write-Host "${BOLD}Docker is not running.${NC}"

            exit 1
        }
    } catch {
        Write-Host "${BOLD}Docker is not running.${NC}"

        exit 1
    }

    # Determine if Sail is currently up...
    if ($DOCKER_COMPOSE_PS -match 'Exit|exited') {
        Write-Host "${BOLD}Shutting down old Sail processes...${NC}"

        Invoke-Expression "$DOCKER_COMPOSE down *> `$null"

        $EXEC = "no"
    } elseif ([String]::IsNullOrEmpty((Invoke-Expression "$DOCKER_COMPOSE ps -q" 2>&1))) {
        $EXEC = "no"
    }
}

$PASS_ARGS = @()

# Proxy PHP commands to the "php" binary on the application container...
if ($args[0] -eq "php") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy vendor binary commands on the application container...
elseif ($args[0] -eq "bin") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS = "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "./vendor/bin/"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy docker-compose commands to the docker-compose binary on the application container...
elseif ($args[0] -eq "docker-compose") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, $DOCKER_COMPOSE
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy Composer commands to the "composer" binary on the application container...
elseif ($args[0] -eq "composer") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "composer"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy Artisan commands to the "artisan" binary on the application container...
elseif ($args[0] -in "artisan", "art", "a") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "debug" command to the "php artisan" binary on the application container...
elseif ($args[0] -eq "debug") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail", "-e", "XDEBUG_SESSION=1"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "test" command to the "php artisan test" Artisan command...
elseif ($args[0] -eq "test") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan", "test"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "phpunit" command to "php vendor/bin/phpunit"...
elseif ($args[0] -eq "phpunit") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "vendor/bin/phpunit"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "pest" command to "php vendor/bin/pest"...
elseif ($args[0] -eq "pest") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "vendor/bin/pest"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "pint" command to "php vendor/bin/pint"...
elseif ($args[0] -eq "pint") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "vendor/bin/pint"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "dusk" command to the "php artisan dusk" Artisan command...
elseif ($args[0] -eq "dusk") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += "-e", "APP_URL=http://$env:APP_SERVICE"
        $PASS_ARGS += "-e", "DUSK_DRIVER_URL=http://selenium:4444/wd/hub"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan", "dusk"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy the "dusk:fails" command to the "php artisan dusk:fails" Artisan command...
elseif ($args[0] -eq "dusk:fails") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += "-e", "APP_URL=http://$env:APP_SERVICE"
        $PASS_ARGS += "-e", "DUSK_DRIVER_URL=http://selenium:4444/wd/hub"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan", "dusk:fails"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Initiate a Laravel Tinker session within the application container...
elseif ($args[0] -eq "tinker") {
    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "php", "artisan", "tinker"
    } else {
        Show-SailNotRunning
    }
}

# Proxy Node commands to the "node" binary on the application container...
elseif ($args[0] -eq "node") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "node"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy NPM commands to the "npm" binary on the application container...
elseif ($args[0] -eq "npm") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "npm"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy NPX commands to the "npx" binary on the application container...
elseif ($args[0] -eq "npx") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "npx"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy PNPM commands to the "pnpm" binary on the application container...
elseif ($args[0] -eq "pnpm") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "pnpm"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy PNPX commands to the "pnpx" binary on the application container...
elseif ($args[0] -eq "pnpx") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "pnpx"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy Yarn commands to the "yarn" binary on the application container...
elseif ($args[0] -eq "yarn") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "yarn"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy Bun commands to the "bun" binary on the application container...
elseif ($args[0] -eq "bun") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "bun"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Proxy BunX commands to the "bunx" binary on the application container...
elseif ($args[0] -eq "bunx") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "bunx"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Initiate a MySQL CLI terminal session within the "mysql" container...
elseif ($args[0] -eq "mysql") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec"
        $PASS_ARGS += "mysql", "bash", "-c"
        $PASS_ARGS += \"MYSQL_PWD=$env:MYSQL_PASSWORD" "mysql" "-u" $env:MYSQL_USER $env:MYSQL_DATABASE
    } else {
        Show-SailNotRunning
    }
}

# Initiate a MySQL CLI terminal session within the "mariadb" container...
elseif ($args[0] -eq "mariadb") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec"
        $PASS_ARGS += "mariadb", "bash", "-c"
        $PASS_ARGS += \"MYSQL_PWD=$env:MARIADB_PASSWORD" "mysql" "-u" $env:MARIADB_USER $env:MARIADB_DATABASE
    } else {
        Show-SailNotRunning
    }
}

# Initiate a PostgreSQL CLI terminal session within the "pgsql" container...
elseif ($args[0] -eq "psql") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec"
        $PASS_ARGS += "pgsql", "bash", "-c"
        $PASS_ARGS += \"PGPASSWORD=$env:PGPASSWORD" "psql" "-U" $env:POSTGRES_USER $env:POSTGRES_DB
    } else {
        Show-SailNotRunning
    }
}

# Initiate a Bash shell within the application container...
elseif ($args[0] -in "shell", "bash") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "sail"
        $PASS_ARGS += $env:APP_SERVICE, "bash"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Initiate a root user Bash shell within the application container...
elseif ($args[0] -in "root-shell", "root-bash") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec", "-u", "root"
        $PASS_ARGS += $env:APP_SERVICE, "bash"
        $PASS_ARGS += @($args)
    } else {
        Show-SailNotRunning
    }
}

# Initiate a Redis CLI terminal session within the "redis" container...
elseif ($args[0] -eq "redis") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        $PASS_ARGS += "exec"
        $PASS_ARGS += "redis", "redis-cli"
    } else {
        Show-SailNotRunning
    }
}

# Share the site...
elseif ($args[0] -eq "share") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        docker run --init --rm -p "$env:SAIL_SHARE_DASHBOARD" `
            -t beyondcodegmbh/expose-server:latest share http://host.docker.internal:"$env:APP_PORT" `
            --server-host="$env:SAIL_SHARE_SERVER_HOST" `
            --server-port="$env:SAIL_SHARE_SERVER_PORT" `
            --auth="$env:SAIL_SHARE_TOKEN" `
            --server="$env:SAIL_SHARE_SERVER" `
            --subdomain="$env:SAIL_SHARE_SUBDOMAIN" `
            --domain="$env:SAIL_SHARE_DOMAIN" `
            $args

        exit 0
    } else {
        Show-SailNotRunning
    }
}

# Open the site...
elseif ($args[0] -eq "open") {
    $args = $args[1..$args.Length]

    if ($EXEC -eq "yes") {
        Start-Process "http://$env:SAIL_SHARE_DOMAIN"

        exit 0
    } else {
        Show-SailNotRunning
    }
}

# Pass unkown commands to the "docker-compose" binary...
else {
    $PASS_ARGS += @($args)
}

# Run Docker Compose with the defined arguments...
Invoke-Expression "$DOCKER_COMPOSE $PASS_ARGS"
