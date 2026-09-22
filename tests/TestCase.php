<?php
namespace ClockworkOffloader\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Base Test Case class for Clockwork Offloader tests
 */
abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Set default WordPress mock functions
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( function( $args, $defaults ) {
			return array_merge( (array) $defaults, (array) $args );
		} );
		Functions\when( 'get_option' )->alias( function( $name, $default = false ) {
			return $default;
		} );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'delete_transient' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}
}
