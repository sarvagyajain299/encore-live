<?php
/**
 * Yoast SEO configuration as code. Run by install.sh:
 *   wp eval-file /scripts/seo-config.php
 *
 * Clicking these settings in wp-admin can't be reviewed or reproduced; a
 * script can. Documented in docs/SEO.md.
 *
 * @package EncoreLive
 */

defined( 'ABSPATH' ) || exit;

$encore_titles = get_option( 'wpseo_titles', array() );
$encore_titles = is_array( $encore_titles ) ? $encore_titles : array();

update_option(
	'wpseo_titles',
	array_merge(
		$encore_titles,
		array(
			// Organisation for the Organization node our Event's organizer points to.
			'company_or_person'              => 'company',
			'company_name'                   => 'Encore Live',
			'website_name'                   => 'Encore Live',

			// Title and description templates per content type.
			'title-encore_show'              => '%%title%% Tickets %%sep%% %%sitename%%',
			'metadesc-encore_show'           => '%%excerpt%%',
			'title-encore_artist'            => '%%title%%: Tour Dates & Tickets %%sep%% %%sitename%%',
			'metadesc-encore_artist'         => '%%excerpt%%',
			'title-encore_venue'             => '%%title%%: What’s On %%sep%% %%sitename%%',
			'metadesc-encore_venue'          => 'Upcoming stand-up comedy and live music at %%title%%. See dates and buy tickets on %%sitename%%.',
			'title-ptarchive-encore_show'    => 'Comedy & Live Music in Dubai, Abu Dhabi and Sharjah %%sep%% %%sitename%%',
			'metadesc-ptarchive-encore_show' => 'Every upcoming Encore Live show: stand-up comedy and live music across the UAE. Filter by city, genre and month.',
			'title-ptarchive-encore_artist'  => 'Comedians & Bands %%sep%% %%sitename%%',
			'metadesc-ptarchive-encore_artist' => 'Meet the comedians and musicians performing with Encore Live, and find their next show.',
			'title-tax-encore_city'          => 'Shows in %%term_title%% %%sep%% %%sitename%%',
			'title-tax-encore_genre'         => '%%term_title%% Shows %%sep%% %%sitename%%',

			// Ticket products are hidden from the catalogue: the show page is the canonical page.
			'noindex-product'                => true,
			'display-metabox-pt-product'     => false,

			// Breadcrumbs (also adds BreadcrumbList to the schema graph).
			'breadcrumbs-enable'             => true,
			'breadcrumbs-home'               => 'Home',
		)
	)
);

echo "Yoast SEO configured.\n";
