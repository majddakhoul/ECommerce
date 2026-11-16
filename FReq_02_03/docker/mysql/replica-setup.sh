#!/bin/sh
set -e

until mysqladmin ping -h db -uroot -proot --silent; do sleep 2; done
until mysqladmin ping -h db-replica -uroot -proot --silent; do sleep 2; done

mysql -h db-replica -uroot -proot <<SQL
STOP REPLICA;
RESET REPLICA ALL;
CHANGE REPLICATION SOURCE TO
  SOURCE_HOST='db',
  SOURCE_PORT=3306,
  SOURCE_USER='repl',
  SOURCE_PASSWORD='replpass',
  SOURCE_AUTO_POSITION=1,
  GET_SOURCE_PUBLIC_KEY=1;
START REPLICA;
SQL

echo "replication configured"
