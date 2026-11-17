<?php
/**
 * Invented demo content.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seed;

defined( 'ABSPATH' ) || exit;

/**
 * All artists, venues and shows are fictional. Any resemblance to real
 * performers or venues is accidental.
 */
final class DemoData {

	/**
	 * Artists keyed by slug.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function artists(): array {
		return array(
			'layla-haddad'           => array(
				'name'  => 'Layla Haddad',
				'genre' => 'comedy',
				'bio'   => 'Observational stand-up about Dubai traffic, family WhatsApp groups and the politics of brunch.',
			),
			'omar-farouk'            => array(
				'name'  => 'Omar "The Uncle" Farouk',
				'genre' => 'comedy',
				'bio'   => 'Unsolicited advice, delivered at volume. The uncle every family group chat deserves.',
			),
			'priya-raman'            => array(
				'name'  => 'Priya Raman',
				'genre' => 'comedy',
				'bio'   => 'Former management consultant who now turns corporate jargon into punchlines.',
			),
			'marco-bellini'          => array(
				'name'  => 'Marco Bellini',
				'genre' => 'comedy',
				'bio'   => 'An Italian chef turned comic on surviving his first summer in the Gulf.',
			),
			'desert-echo-collective' => array(
				'name'  => 'Desert Echo Collective',
				'genre' => 'live-music',
				'bio'   => 'A six-piece blending oud, hand percussion and analogue synths.',
			),
			'nova-saleh'             => array(
				'name'  => 'Nova Saleh',
				'genre' => 'live-music',
				'bio'   => 'Electro-pop singer-songwriter with bilingual hooks and big choruses.',
			),
			'creek-street-trio'      => array(
				'name'  => 'The Creek Street Trio',
				'genre' => 'live-music',
				'bio'   => 'Late-night jazz standards and originals from piano, double bass and drums.',
			),
			'saffron-skies'          => array(
				'name'  => 'Saffron Skies',
				'genre' => 'live-music',
				'bio'   => 'Indie rock four-piece known for loud guitars and quiet encores.',
			),
		);
	}

	/**
	 * Venues keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function venues(): array {
		return array(
			'lantern-room'        => array(
				'name'     => 'The Lantern Room',
				'city'     => 'dubai',
				'address'  => 'Warehouse 14, Al Quoz Industrial Area 1, Dubai',
				'lat'      => 25.137200,
				'lng'      => 55.229500,
				'capacity' => 250,
			),
			'marina-echo-hall'    => array(
				'name'     => 'Marina Echo Hall',
				'city'     => 'dubai',
				'address'  => 'Marina Walk, Dubai Marina, Dubai',
				'lat'      => 25.077900,
				'lng'      => 55.139000,
				'capacity' => 1200,
			),
			'corniche-arts-stage' => array(
				'name'     => 'Corniche Arts Stage',
				'city'     => 'abu-dhabi',
				'address'  => 'Corniche Road West, Abu Dhabi',
				'lat'      => 24.467200,
				'lng'      => 54.322500,
				'capacity' => 800,
			),
			'heritage-courtyard'  => array(
				'name'     => 'Heritage Courtyard Theatre',
				'city'     => 'sharjah',
				'address'  => 'Heritage Area, Al Mareija, Sharjah',
				'lat'      => 25.359000,
				'lng'      => 55.388000,
				'capacity' => 400,
			),
		);
	}

	/**
	 * Shows. "days" is relative to today; negative = in the past.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function shows(): array {
		return array(
			array(
				'title'   => 'Comedy Night: Expats Anonymous',
				'artists' => array( 'marco-bellini', 'priya-raman', 'layla-haddad' ),
				'venue'   => 'lantern-room',
				'genre'   => 'comedy',
				'days'    => 5,
				'age'     => 18,
			),
			array(
				'title'   => 'Omar Farouk: Uncle Knows Best',
				'artists' => array( 'omar-farouk' ),
				'venue'   => 'heritage-courtyard',
				'genre'   => 'comedy',
				'days'    => 9,
				'age'     => 16,
				'state'   => 'sold_out',
			),
			array(
				'title'   => 'Layla Haddad: Brunch Is a Personality',
				'artists' => array( 'layla-haddad' ),
				'venue'   => 'lantern-room',
				'genre'   => 'comedy',
				'days'    => 12,
				'age'     => 18,
			),
			array(
				'title'   => 'The Creek Street Trio: Jazz After Dark',
				'artists' => array( 'creek-street-trio' ),
				'venue'   => 'lantern-room',
				'genre'   => 'live-music',
				'days'    => 16,
				'hour'    => 21,
			),
			array(
				'title'   => 'Desert Echo Collective Live',
				'artists' => array( 'desert-echo-collective' ),
				'venue'   => 'marina-echo-hall',
				'genre'   => 'live-music',
				'days'    => 20,
			),
			array(
				'title'   => 'Priya Raman: Overqualified',
				'artists' => array( 'priya-raman' ),
				'venue'   => 'corniche-arts-stage',
				'genre'   => 'comedy',
				'days'    => 27,
				'age'     => 16,
				'state'   => 'cancelled',
			),
			array(
				'title'   => 'Nova Saleh: Neon Mirage Tour',
				'artists' => array( 'nova-saleh' ),
				'venue'   => 'corniche-arts-stage',
				'genre'   => 'live-music',
				'days'    => 35,
			),
			array(
				'title'   => 'Saffron Skies with Nova Saleh',
				'artists' => array( 'saffron-skies', 'nova-saleh' ),
				'venue'   => 'marina-echo-hall',
				'genre'   => 'live-music',
				'days'    => 48,
			),
			array(
				'title'   => 'Marco Bellini: Mamma Mia, Dubai',
				'artists' => array( 'marco-bellini' ),
				'venue'   => 'heritage-courtyard',
				'genre'   => 'comedy',
				'days'    => 62,
				'age'     => 16,
			),
			array(
				'title'   => 'Saffron Skies: Monsoon Sessions',
				'artists' => array( 'saffron-skies' ),
				'venue'   => 'marina-echo-hall',
				'genre'   => 'live-music',
				'days'    => -21,
			),
			array(
				'title'   => 'Layla Haddad: Work in Progress',
				'artists' => array( 'layla-haddad' ),
				'venue'   => 'lantern-room',
				'genre'   => 'comedy',
				'days'    => -40,
				'age'     => 18,
			),
			array(
				'title'   => 'Desert Echo Collective: Oud and Synths',
				'artists' => array( 'desert-echo-collective' ),
				'venue'   => 'corniche-arts-stage',
				'genre'   => 'live-music',
				'days'    => -75,
			),
		);
	}

	/**
	 * Tier prices (AED) by genre.
	 *
	 * @return array<string, array<string, float>>
	 */
	public static function prices(): array {
		return array(
			'comedy'     => array(
				'General' => 95.0,
				'Premium' => 175.0,
				'VIP'     => 295.0,
			),
			'live-music' => array(
				'General' => 150.0,
				'Premium' => 250.0,
				'VIP'     => 450.0,
			),
		);
	}

	/**
	 * Share of venue capacity per tier.
	 *
	 * @return array<string, float>
	 */
	public static function tier_split(): array {
		return array(
			'General' => 0.6,
			'Premium' => 0.3,
			'VIP'     => 0.1,
		);
	}
}
