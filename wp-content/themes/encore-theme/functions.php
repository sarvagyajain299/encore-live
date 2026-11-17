<?php
/**
 * Encore Theme bootstrap.
 *
 * Deliberately small: each concern lives in its own file under inc/.
 * Business rules (what a show is, which shows to list) live in the
 * encore-core plugin. The theme only decides how things look.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'ENCORE_THEME_VERSION', wp_get_theme()->get( 'Version' ) );

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/az-menu.php';
require_once __DIR__ . '/inc/elementor.php';
