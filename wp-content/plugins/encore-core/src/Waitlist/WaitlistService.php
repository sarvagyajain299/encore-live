<?php
/**
 * Fan-facing waitlist operations: join, confirm, unsubscribe, claim.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Domain\Tier;
use EncoreCore\Tickets\TicketLookup;
use EncoreCore\Waitlist\Emails\EmailRegistry;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Each public method returns a result code; the REST controller and the
 * no-JS handler turn codes into HTTP statuses and messages.
 */
final class WaitlistService {

	/**
	 * Create with collaborators.
	 *
	 * @param WaitlistRepository $repo    Data.
	 * @param Links              $links   Links and tokens.
	 * @param TicketLookup       $tickets Product lookup.
	 */
	public function __construct(
		private readonly WaitlistRepository $repo,
		private readonly Links $links,
		private readonly TicketLookup $tickets = new TicketLookup()
	) {}

	/**
	 * Join (or re-join) a waitlist.
	 *
	 * @param JoinRequest $request Validated input.
	 * @return array{code: string, position?: int|null}
	 *   code: created | resent | already_waiting | already_converted | tier_available | invalid_tier | error
	 */
	public function join( JoinRequest $request ): array {
		$variation = wc_get_product( $request->variation_id );
		if ( ! $variation instanceof WC_Product_Variation || $this->tickets->show_id_for_product( $variation->get_id() ) !== $request->show_id ) {
			return array( 'code' => 'invalid_tier' );
		}
		if ( $variation->is_in_stock() && ( ! $variation->managing_stock() || $variation->get_stock_quantity() > 0 ) ) {
			return array( 'code' => 'tier_available' );
		}
		$tier = (string) $variation->get_attribute( Tier::ATTRIBUTE );

		$existing = $this->repo->find_unique( $request->show_id, $request->variation_id, $request->email );
		if ( $existing ) {
			return $this->rejoin( $existing, $request );
		}

		$token = $this->links->signer()->new_token();
		$id    = $this->repo->insert( $request, $tier, $this->links->signer()->hash_token( $token ) );
		if ( ! $id ) {
			// Lost a race with an identical submit: treat as existing.
			$existing = $this->repo->find_unique( $request->show_id, $request->variation_id, $request->email );
			return $existing ? $this->rejoin( $existing, $request ) : array( 'code' => 'error' );
		}

		Scheduler::schedule_pending_expiry( $id );
		$this->send_confirmation( $id, $token );
		return array( 'code' => 'created' );
	}

	/**
	 * Confirm via the emailed token (single use).
	 *
	 * @param int    $id    Entry ID.
	 * @param string $token Token.
	 * @return string confirmed | already_confirmed | invalid
	 */
	public function confirm( int $id, string $token ): string {
		$entry = $this->repo->find( $id );
		if ( ! $entry ) {
			return 'invalid';
		}
		if ( Status::PendingConfirmation->value !== $entry['status'] ) {
			return in_array( $entry['status'], array( Status::Waiting->value, Status::Notified->value ), true ) ? 'already_confirmed' : 'invalid';
		}
		if ( ! $this->links->signer()->verify_token( $token, (string) $entry['token_hash'] ) ) {
			return 'invalid';
		}

		$ok = $this->repo->transition(
			$id,
			Status::Waiting,
			'fan',
			'confirmed email',
			array(
				'confirmed_at' => gmdate( 'Y-m-d H:i:s' ),
				'token_hash'   => '', // Single use: clearing the hash kills the link.
			),
			Status::PendingConfirmation
		);
		if ( ! $ok ) {
			return 'invalid';
		}

		$entry = (array) $this->repo->find( $id );
		EmailRegistry::send(
			EmailRegistry::JOINED,
			(string) $entry['email'],
			WaitlistNotifier::email_vars( $entry, $this->links ) + array( 'position' => $this->repo->position( $entry ) )
		);

		// Tickets may already be back (restocked while this fan was confirming).
		Scheduler::enqueue_notify( (int) $entry['variation_id'] );
		return 'confirmed';
	}

	/**
	 * One-click unsubscribe.
	 *
	 * @param int    $id  Entry ID.
	 * @param string $sig Signature.
	 * @return string unsubscribed | invalid
	 */
	public function unsubscribe( int $id, string $sig ): string {
		if ( ! $this->links->verify_unsubscribe( $id, $sig ) ) {
			return 'invalid';
		}
		$entry = $this->repo->find( $id );
		if ( ! $entry ) {
			return 'invalid';
		}
		if ( Status::Unsubscribed->value === $entry['status'] ) {
			return 'unsubscribed';
		}
		$was_notified = Status::Notified->value === $entry['status'];
		if ( ! $this->repo->transition( $id, Status::Unsubscribed, 'fan', 'unsubscribe link', array( 'token_hash' => '' ) ) ) {
			return 'invalid';
		}
		// Unsubscribing during a claim window releases the tickets to the next fan.
		if ( $was_notified ) {
			Scheduler::enqueue_notify( (int) $entry['variation_id'] );
		}
		return 'unsubscribed';
	}

	/**
	 * Claim link: put the tickets in the cart.
	 *
	 * @param int    $id      Entry ID.
	 * @param int    $expires Expiry from the link.
	 * @param string $sig     Signature.
	 * @return string added | expired | invalid | unavailable
	 */
	public function claim( int $id, int $expires, string $sig ): string {
		if ( ! $this->links->verify_claim( $id, $expires, $sig ) ) {
			return 'invalid';
		}
		$entry = $this->repo->find( $id );
		if ( ! $entry || Status::Notified->value !== $entry['status'] ) {
			return Status::Converted->value === ( $entry['status'] ?? '' ) ? 'invalid' : 'expired';
		}
		if ( $expires < time() ) {
			return 'expired';
		}

		$variation = wc_get_product( (int) $entry['variation_id'] );
		if ( ! $variation instanceof WC_Product_Variation || ! WC()->cart ) {
			return 'unavailable';
		}

		// Through the normal cart API, so stock, limits and cancellation rules all apply.
		$key = WC()->cart->add_to_cart(
			$variation->get_parent_id(),
			(int) $entry['qty'],
			$variation->get_id(),
			$variation->get_variation_attributes(),
			array( 'encore_waitlist_entry' => $id )
		);
		return $key ? 'added' : 'unavailable';
	}

	/**
	 * Existing entry: report position, resend confirmation, or re-open.
	 *
	 * @param array<string, mixed> $entry   Existing row.
	 * @param JoinRequest          $request New request.
	 * @return array{code: string, position?: int|null}
	 */
	private function rejoin( array $entry, JoinRequest $request ): array {
		$id     = (int) $entry['id'];
		$status = Status::from( (string) $entry['status'] );

		if ( Status::Waiting === $status || Status::Notified === $status ) {
			return array(
				'code'     => 'already_waiting',
				'position' => $this->repo->position( $entry ),
			);
		}
		if ( Status::Converted === $status ) {
			return array( 'code' => 'already_converted' );
		}

		$token = $this->links->signer()->new_token();
		$hash  = $this->links->signer()->hash_token( $token );

		if ( Status::PendingConfirmation === $status ) {
			// Re-submitting before confirming: issue a fresh link (the old one stops working).
			$this->repo->set_token_hash( $id, $hash );
			$this->send_confirmation( $id, $token );
			return array( 'code' => 'resent' );
		}

		// Expired or unsubscribed: a fresh double opt-in, at the back of the queue.
		$ok = $this->repo->transition(
			$id,
			Status::PendingConfirmation,
			'fan',
			'joined again',
			array(
				'token_hash'       => $hash,
				'name'             => $request->name,
				'phone'            => $request->phone,
				'qty'              => $request->qty,
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
				'confirmed_at'     => null,
				'notified_at'      => null,
				'claim_expires_at' => null,
			)
		);
		if ( ! $ok ) {
			return array( 'code' => 'error' );
		}
		Scheduler::schedule_pending_expiry( $id );
		$this->send_confirmation( $id, $token );
		return array( 'code' => 'created' );
	}

	/**
	 * Email the double opt-in link.
	 *
	 * @param int    $id    Entry ID.
	 * @param string $token Plain token (exists only in this email; never stored).
	 */
	private function send_confirmation( int $id, string $token ): void {
		$entry = (array) $this->repo->find( $id );
		EmailRegistry::send(
			EmailRegistry::CONFIRMATION,
			(string) $entry['email'],
			WaitlistNotifier::email_vars( $entry, $this->links ) + array( 'confirm_url' => $this->links->confirm_url( $id, $token ) )
		);
	}
}
