<?php
/**
 * Show status values.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A backed enum: the database stores the string value, PHP code uses the type.
 * A typo like 'soldout' becomes impossible in code, and ShowStatus::tryFrom()
 * rejects bad input from forms or the database.
 */
enum ShowStatus: string {

	case OnSale    = 'on_sale';
	case SoldOut   = 'sold_out';
	case Cancelled = 'cancelled';
	case Archived  = 'archived';

	/**
	 * Human-readable, translatable label.
	 */
	public function label(): string {
		// PHPCompatibility does not yet know $this is valid inside enum methods.
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
			self::OnSale    => __( 'On sale', 'encore-core' ),
			self::SoldOut   => __( 'Sold out', 'encore-core' ),
			self::Cancelled => __( 'Cancelled', 'encore-core' ),
			self::Archived  => __( 'Archived', 'encore-core' ),
		};
	}

	/**
	 * All stored values, e.g. for a REST schema enum.
	 *
	 * @return string[]
	 */
	public static function values(): array {
		return array_map( static fn( self $status ): string => $status->value, self::cases() );
	}
}
