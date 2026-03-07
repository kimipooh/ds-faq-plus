<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/*
Plugin Name: WP DS FAQ Plus
Plugin URI: http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_en.html
Description: WP DS FAQ Plus is the expand of WP DS FAQ  plugin. The plugin bases on WP DS FAQ 1.3.3. This plugin includes the fixed some issues (Quotation and Security, such as SQL Injection and CSRF. ) , Japanese translation, improvement of interface, and SSL Admin setting.
Version: 2.0.0
Author: Kimiya Kitani
Author URI: https://profiles.wordpress.org/kimipooh/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-ds-faq-plus
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WP_DSFAQ_PLUS_BOOTSTRAP_FILE' ) ) {
    define( 'WP_DSFAQ_PLUS_BOOTSTRAP_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'wp-ds-faq-plus.php';
