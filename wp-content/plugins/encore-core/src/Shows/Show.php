<?php
/**
 * Read model for one show.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use DateTimeImmutable;
use EncoreCore\Domain\ShowStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a template needs about a show, gathered once.
 *
 * Templates read these properties instead of calling get_post_meta() with
 * key names. If storage changes later (say, a custom table), only
 * ShowRepository changes, and every template keeps working.
 */
final class Show {

	/**
	 * Build the read model.
	 *
	 * @param int                                               $id         Post ID.
	 * @param string                                            $title      Title.
	 * @param string                                            $url        Permalink.
	 * @param DateTimeImmutable|null                            $start      Start time (site timezone).
	 * @param DateTimeImmutable|null                            $end        End time.
	 * @param string                                            $doors      Doors time 'H:i' or ''.
	 * @param array<string, mixed>|null                         $venue      id, title, url, address, city, lat, lng.
	 * @param array<int, array{id:int,title:string,url:string}> $artists Artists, headliner first.
	 * @param ShowStatus                                        $status     Status.
	 * @param int                                               $product_id Ticket product ID (0 = none).
	 * @param int                                               $age        Age restriction (0 = all ages).
	 * @param float|null                                        $min_price  Cheapest tier price.
	 * @param float|null                                        $max_price  Most expensive tier price.
	 * @param string[]                                          $genres     Genre names.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $url,
		public readonly ?DateTimeImmutable $start,
		public readonly ?DateTimeImmutable $end,
		public readonly string $doors,
		public readonly ?array $venue,
		public readonly array $artists,
		public readonly ShowStatus $status,
		public readonly int $product_id,
		public readonly int $age,
		public readonly ?float $min_price,
		public readonly ?float $max_price,
		public readonly array $genres
	) {}

	/**
	 * Can fans buy tickets right now?
	 */
	public function is_on_sale(): bool {
		return ShowStatus::OnSale === $this->status && $this->product_id > 0;
	}

	/**
	 * The first-listed artist.
	 *
	 * @return array{id:int,title:string,url:string}|null
	 */
	public function headliner(): ?array {
		return $this->artists[0] ?? null;
	}
}
