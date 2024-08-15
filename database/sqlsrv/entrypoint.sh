#!/usr/bin/env bash

# If there is a command, execute it.
if [ "$#" -ne 0 ]; then
    exec "$@"
fi

SQLCMD="/opt/mssql-tools/bin/sqlcmd"

if [ -f /opt/mssql-tools18/bin/sqlcmd ]; then
    SQLCMD="/opt/mssql-tools18/bin/sqlcmd"
fi

echo "$(date '+%Y-%m-%d %H:%M:%S.%2N') sail        Starting Microsoft SQL Server for init scripts."

# Start SQL Server
/opt/mssql/bin/sqlservr -mSQLCMD -f -c -x &

# Function to check if SQL Server is ready
sql_server_is_ready() {
    $SQLCMD -No -U sa -P "${MSSQL_SA_PASSWORD}" -Q "SELECT 1" &> /dev/null
    return $?
}

# Wait for SQL Server to start
sleep 3

# Wait until SQL Server is ready
until sql_server_is_ready; do
    echo "$(date '+%Y-%m-%d %H:%M:%S.%2N') sail        Waiting for SQL Server to be available..."
    sleep 1
done

SQL_INIT=$(cat <<-EOSQL
    IF NOT EXISTS(SELECT * FROM sys.databases WHERE name = "$MSSQL_DB_NAME") BEGIN
        CREATE DATABASE $MSSQL_DB_NAME;
    END
    GO

    IF NOT EXISTS (SELECT name FROM master.sys.server_principals WHERE name = "$MSSQL_USER") BEGIN
        CREATE LOGIN $MSSQL_USER WITH
            PASSWORD = "$MSSQL_PASSWORD",
            DEFAULT_DATABASE = $MSSQL_DB_NAME,
            CHECK_EXPIRATION = OFF,
            CHECK_POLICY = OFF;
    END
    GO

    IF NOT EXISTS(SELECT * FROM sys.database_principals WHERE name = "$MSSQL_USER") BEGIN
        CREATE USER $MSSQL_USER FOR LOGIN $MSSQL_USER;
        ALTER ROLE db_owner ADD MEMBER $MSSQL_USER;
    END
    GO
EOSQL
)

echo "$(date '+%Y-%m-%d %H:%M:%S.%2N') sail        Initializing ${MSSQL_DB_NAME} database for ${MSSQL_USER}."

## Set the engine default user and database name
$SQLCMD -No -U sa -No -P "${MSSQL_SA_PASSWORD}" -Q "${SQL_INIT}"

SQL_INIT_TESTING=$(cat <<-EOSQL
    IF NOT EXISTS(SELECT * FROM sys.databases WHERE name = "testing") BEGIN
        CREATE DATABASE testing;
    END
    GO

    IF NOT EXISTS(SELECT * FROM sys.database_principals WHERE name = "testing") BEGIN
        CREATE USER testing FOR LOGIN testing WITH
          PASSWORD = "",
          DEFAULT_DATABASE = "testing",
          CHECK_EXPIRATION = OFF,
          CHECK_POLICY = OFF;
        ALTER ROLE db_datareader ADD MEMBER testing;
        ALTER ROLE db_datawriter ADD MEMBER testing;
        ALTER ROLE db_ddladmin ADD MEMBER testing;
    END
    GO
EOSQL
)

echo "$(date '+%Y-%m-%d %H:%M:%S.%2N') sail        Initializing testing database."

## Create the testing database if it doesn't exists
$SQLCMD -No -U sa -P "${MSSQL_SA_PASSWORD}" -Q "${SQL_INIT_TESTING}"

echo "$(date '+%Y-%m-%d %H:%M:%S.%2N') sail        Restarting Microsoft SQL Server after init scripts."
echo ""
echo "---"
echo ""

# Kill the server
pkill sqlservr

# Start it again.
exec /opt/mssql/bin/sqlservr
