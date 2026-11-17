<?php
/**
 * Shared sign-up pipeline for the REST and no-JS entry points.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * Order matters:
 *   1. honeypot   – bots fill every field; humans never see this one.
 *                   Pretend success so the bot learns nothing.
 *   2. rate limit – per hashed IP, before any database work.
 *   3. validate   – JoinRequest collects every field error.
 *   4. service    – unique key, double opt-in, emails.
 */
final class JoinHandler {

	/**
	 * Create.
	 *
	 * @param WaitlistService $service Service.
	 * @param RateLimiter     $limiter Rate limiter.
	 */
	public function __construct(
		private readonly WaitlistService $service,
		private readonly RateLimiter $limiter
	) {}

	/**
	 * Handle a submission.
	 *
	 * @param array<string, mixed> $input Raw fields.
	 * @param string               $ip    Client IP.
	 * @return array{status: int, code: string, message: string, position?: int|null, errors?: array<string, string>}
	 */
	public function handle( array $input, string $ip ): array {
		if ( '' !== trim( (string) ( $input['website'] ?? '' ) ) ) {
			return $this->result( 200, 'created' );
		}

		if ( ! $this->limiter->attempt( $ip, time() ) ) {
			return $this->result( 429, 'rate_limited' );
		}

		$request = JoinRequest::from_array( $input );
		if ( is_array( $request ) ) {
			return $this->result( 400, 'invalid' ) + array( 'errors' => $request );
		}

		$outcome = $this->service->join( $request );
		$status  = match ( $outcome['code'] ) {
			'created', 'resent', 'already_waiting', 'already_converted' => 200,
			'tier_available', 'invalid_tier' => 409,
			default => 500,
		};

		$result = $this->result( $status, $outcome['code'] );
		if ( array_key_exists( 'position', $outcome ) ) {
			$result['position'] = $outcome['position'];
		}
		return $result;
	}

	/**
	 * Fan-facing message for a code.
	 *
	 * @param string   $code     Result code.
	 * @param int|null $position Queue position.
	 */
	public static function message( string $code, ?int $position = null ): string {
		return match ( $code ) {
			'created', 'resent'  => __( 'Almost there: check your email and click the link to confirm your place.', 'encore-core' ),
			'already_waiting'    => $position
				/* translators: %d: position in line. */
				? sprintf( __( 'You’re already on this waitlist: number %d in line.', 'encore-core' ), $position )
				: __( 'You’re already on this waitlist.', 'encore-core' ),
			'already_converted'  => __( 'You’ve already bought tickets for this tier from the waitlist.', 'encore-core' ),
			'tier_available'     => __( 'Good news: this tier has tickets available right now. Choose it above to buy.', 'encore-core' ),
			'invalid_tier'       => __( 'Please choose a sold-out tier for this show.', 'encore-core' ),
			'rate_limited'       => __( 'Too many attempts. Please wait a few minutes and try again.', 'encore-core' ),
			'invalid'            => __( 'Please check the highlighted fields.', 'encore-core' ),
			'confirmed'          => __( 'Your place is confirmed. We’ve emailed you your position in line.', 'encore-core' ),
			'already_confirmed'  => __( 'Your place was already confirmed.', 'encore-core' ),
			'unsubscribed'       => __( 'You’ve been removed from this waitlist. We won’t email you about it again.', 'encore-core' ),
			'expired'            => __( 'This link has expired.', 'encore-core' ),
			'bad_link'           => __( 'This link is invalid or has already been used.', 'encore-core' ),
			default              => __( 'Something went wrong. Please try again.', 'encore-core' ),
		};
	}

	/**
	 * Build a result.
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Code.
	 * @return array{status: int, code: string, message: string}
	 */
	private function result( int $status, string $code ): array {
		return array(
			'status'  => $status,
			'code'    => $code,
			'message' => self::message( $code ),
		);
	}
}
