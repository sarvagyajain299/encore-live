<?php
/**
 * Tests for waitlist sign-up validation and email masking.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Waitlist;

use Brain\Monkey\Functions;
use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Waitlist\EmailMask;
use EncoreCore\Waitlist\JoinRequest;

final class JoinRequestTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	/**
	 * Valid input, overridable.
	 *
	 * @param array<string, mixed> $override Overrides.
	 * @return array<string, mixed>
	 */
	private function input( array $override = array() ): array {
		return array_merge(
			array(
				'show_id'      => '12',
				'variation_id' => '40',
				'name'         => '  Layla   Fan ',
				'email'        => ' Layla.Fan@Example.com ',
				'phone'        => '050 123 4567',
				'qty'          => '2',
				'consent'      => '1',
			),
			$override
		);
	}

	public function test_valid_input_is_normalised(): void {
		$request = JoinRequest::from_array( $this->input() );

		$this->assertInstanceOf( JoinRequest::class, $request );
		$this->assertSame( 'Layla Fan', $request->name );
		$this->assertSame( 'layla.fan@example.com', $request->email );
		$this->assertSame( '+971501234567', $request->phone );
		$this->assertSame( 2, $request->qty );
	}

	public function test_phone_is_optional(): void {
		$this->assertSame( '', JoinRequest::from_array( $this->input( array( 'phone' => '' ) ) )->phone );
	}

	public function test_collects_every_error(): void {
		$errors = JoinRequest::from_array(
			array(
				'name'    => '',
				'email'   => 'not-an-email',
				'phone'   => '12345',
				'qty'     => '5',
				'consent' => '',
			)
		);

		$this->assertIsArray( $errors );
		$this->assertSame( array( 'variation_id', 'name', 'email', 'phone', 'qty', 'consent' ), array_keys( $errors ) );
	}

	public function test_html_is_stripped_from_name(): void {
		$this->assertSame( 'Bold', JoinRequest::from_array( $this->input( array( 'name' => '<b>Bold</b>' ) ) )->name );
	}

	public function test_email_mask(): void {
		$this->assertSame( 'l********@e******.com', EmailMask::mask( 'layla.fan@example.com' ) );
		$this->assertSame( 'a*@e*****.test', EmailMask::mask( 'ab@encore.test' ) );
		$this->assertSame( '***', EmailMask::mask( 'x' ) );
	}
}
