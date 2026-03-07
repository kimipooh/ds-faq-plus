#!/usr/bin/env bash

set -eux

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

TMPDIR=${TMPDIR-/tmp}
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	if command -v curl > /dev/null 2>&1; then
		curl -s "$1" > "$2"
	elif command -v wget > /dev/null 2>&1; then
		wget -q -O "$2" "$1"
	else
		echo "Error: curl or wget required"
		exit 1
	fi
}

if [ ! -d "$WP_CORE_DIR" ]; then
	mkdir -p "$WP_CORE_DIR"
	echo "Downloading WordPress..."
	download https://wordpress.org/${WP_VERSION}.tar.gz "$TMPDIR/wordpress.tar.gz"
	tar -xzf "$TMPDIR/wordpress.tar.gz" -C "$TMPDIR"
	mv "$TMPDIR/wordpress/"* "$WP_CORE_DIR"
fi

if [ ! -d "$WP_TESTS_DIR" ]; then
	mkdir -p "$WP_TESTS_DIR"
	echo "Downloading WordPress test library..."
	download https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/functions.php "$WP_TESTS_DIR/functions.php"
	download https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/bootstrap.php "$WP_TESTS_DIR/bootstrap.php"
fi

echo "Setting up database..."

mysqladmin create "$DB_NAME" \
	--host="$DB_HOST" \
	--user="$DB_USER" \
	--password="$DB_PASS" \
	|| true

cat <<EOF > "$WP_TESTS_DIR/wp-tests-config.php"
<?php
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', '$DB_HOST' );

define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
EOF

echo "WordPress test environment installed."
