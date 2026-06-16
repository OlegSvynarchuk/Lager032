<?php
/**
 * Lager032 theme bootstrap.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAGER032_VERSION' ) ) {
	define( 'LAGER032_VERSION', '0.1.0' );
}

require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/inc/enqueue.php';
require_once get_template_directory() . '/inc/contact.php';
require_once get_template_directory() . '/inc/search.php';
