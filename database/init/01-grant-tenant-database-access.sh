#!/bin/sh

set -eu

# The application creates tenant databases dynamically, so its configured
# MySQL user needs privileges beyond the single central database created by
# the official MySQL image.
escaped_user=$(printf '%s' "$MYSQL_USER" | sed "s/'/''/g")

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot <<SQL
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER,
    CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER
    ON *.* TO '${escaped_user}'@'%';
FLUSH PRIVILEGES;
SQL
