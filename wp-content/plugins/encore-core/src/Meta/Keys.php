<?php
/**
 * Meta key names.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Every meta key in one place, so a key is never typed twice.
 *
 * The leading underscore makes a key "protected": WordPress hides it from the
 * generic Custom Fields box, so the only way to edit it is our meta box (or
 * the REST API, guarded by auth_callback).
 */
final class Keys {

	// Show.
	public const SHOW_START   = '_encore_start';
	public const SHOW_END     = '_encore_end';
	public const SHOW_DOORS   = '_encore_doors';
	public const SHOW_VENUE   = '_encore_venue_id';
	public const SHOW_ARTISTS = '_encore_artist_ids';
	public const SHOW_PRODUCT = '_encore_product_id';
	public const SHOW_AGE     = '_encore_age_restriction';
	public const SHOW_STATUS  = '_encore_status';

	// Artist.
	public const ARTIST_BIO    = '_encore_bio';
	public const ARTIST_SOCIAL = '_encore_social';

	// Venue.
	public const VENUE_ADDRESS  = '_encore_address';
	public const VENUE_LAT      = '_encore_lat';
	public const VENUE_LNG      = '_encore_lng';
	public const VENUE_CAPACITY = '_encore_capacity';
	public const VENUE_MAP_URL  = '_encore_map_url';

	// WooCommerce product -> show back-reference.
	public const PRODUCT_SHOW = '_encore_show_id';

	// Marks demo content created by `wp encore seed`, so `reset` deletes only that.
	public const SEEDED = '_encore_seeded';
}
