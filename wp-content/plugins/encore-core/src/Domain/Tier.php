<?php
/**
 * Ticket tiers.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The value of the WooCommerce "Tier" attribute on each ticket variation.
 */
enum Tier: string {

	case General = 'General';
	case Premium = 'Premium';
	case Vip     = 'VIP';

	/**
	 * WooCommerce attribute name shared by every ticket product.
	 */
	public const ATTRIBUTE = 'Tier';

	/**
	 * All tier values in display order.
	 *
	 * @return string[]
	 */
	public static function values(): array {
		return array_map( static fn( self $tier ): string => $tier->value, self::cases() );
	}
}
