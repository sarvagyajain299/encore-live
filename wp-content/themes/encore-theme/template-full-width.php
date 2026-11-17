<?php
/**
 * Template Name: Full width (Elementor)
 * Template Post Type: page, post
 *
 * No container, no title: Elementor controls the whole content area,
 * while the theme still provides header, footer and global styles.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	the_content();
endwhile;

get_footer();
