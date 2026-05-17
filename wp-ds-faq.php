<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/*
Plugin Name: DS FAQ Plus
Plugin URI: http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_en.html
Description: DS FAQ Plus is an extended version of WP DS FAQ 1.3.3. It includes fixes for quotation handling and security issues such as SQL Injection and CSRF, Japanese translation improvements, interface enhancements, and SSL admin support.
Version: 2.1.0
Author: Kimiya Kitani
Author URI: https://profiles.wordpress.org/kimipooh/
Text Domain: wp-ds-faq-plus
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WP_DSFAQ_PLUS_BOOTSTRAP_FILE' ) ) {
    define( 'WP_DSFAQ_PLUS_BOOTSTRAP_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'wp-ds-faq-plus.php';
