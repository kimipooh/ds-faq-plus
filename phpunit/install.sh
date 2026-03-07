#!/usr/bin/env bash

set -eux

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="${4-localhost}"
WP_VERSION="${5-latest}"

TMPDIR="${TMPDIR-/tmp}"
WP_TESTS_DIR="${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR-$TMPDIR/wordpress}"

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -fsSL "$1" -o "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -q -O "$2" "$1"
	else
		echo "Error: curl or wget required"
		exit 1
	fi
}

parse_db_host() {
	if [[ "$DB_HOST" == *:* ]]; then
		DB_HOSTNAME="${DB_HOST%%:*}"
		DB_PORT="${DB_HOST##*:}"
	else
		DB_HOSTNAME="$DB_HOST"
		DB_PORT="3306"
	fi
}

install_wordpress() {
	if [ -d "$WP_CORE_DIR/wp-includes" ]; then
		echo "WordPress already installed at $WP_CORE_DIR"
		return
	fi

	rm -rf "$TMPDIR/wordpress" "$TMPDIR/wordpress.tar.gz"

	echo "Downloading WordPress core..."
	download "https://wordpress.org/${WP_VERSION}.tar.gz" "$TMPDIR/wordpress.tar.gz"
	tar -xzf "$TMPDIR/wordpress.tar.gz" -C "$TMPDIR"

	test -d "$WP_CORE_DIR/wp-includes"
}

install_test_suite() {
	if [ -f "$WP_TESTS_DIR/includes/functions.php" ]; then
		echo "WordPress test library already installed at $WP_TESTS_DIR"
		return
	fi

	rm -rf "$WP_TESTS_DIR"
	mkdir -p "$WP_TESTS_DIR"

	if ! command -v svn >/dev/null 2>&1; then
		echo "Error: svn command is required to fetch the WordPress test library."
		exit 1
	fi

	echo "Exporting WordPress test library..."
	svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes "$WP_TESTS_DIR/includes"
	svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data "$WP_TESTS_DIR/data"
}

install_db() {
	parse_db_host

	echo "Creating test database if needed..."
	mysqladmin create "$DB_NAME" \
		--host="$DB_HOSTNAME" \
		--port="$DB_PORT" \
		--user="$DB_USER" \
		--password="$DB_PASS" \
		|| true
}

create_wp_tests_config() {
	cat > "$WP_TESTS_DIR/wp-tests-config.php" <<EOF
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

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '$WP_CORE_DIR/' );
}
EOF
}

install_wordpress
install_test_suite
install_db
create_wp_tests_config

echo "WordPress test environment installed."