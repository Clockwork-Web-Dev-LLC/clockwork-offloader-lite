<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader_Settings_Helper;
use Brain\Monkey\Functions;

class SettingsHelperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Clockwork_Offloader_Settings_Helper::clear_cache();
	}

	protected function tearDown(): void {
		Clockwork_Offloader_Settings_Helper::clear_cache();
		parent::tearDown();
	}

	public function test_get_defaults_returns_expected_structure(): void {
		$defaults = Clockwork_Offloader_Settings_Helper::get_defaults();

		$this->assertIsArray( $defaults );
		$this->assertEquals( 'aws', $defaults['provider'] );
		$this->assertEquals( 'us-east-1', $defaults['s3_region'] );
		$this->assertFalse( $defaults['auto_offload'] );
		$this->assertFalse( $defaults['delete_after_upload'] );
		$this->assertFalse( $defaults['rewrite_urls'] );
		$this->assertEquals( 10, $defaults['queue_batch_size'] );
		$this->assertTrue( $defaults['force_multisite_subsites'] );
	}

	public function test_get_settings_single_site_merges_with_defaults(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( function( $args, $defaults ) {
			return array_merge( $defaults, (array) $args );
		} );
		Functions\when( 'get_option' )->alias( function( $name, $default = array() ) {
			if ( 'clockwork_offloader_settings' === $name ) {
				return array(
					'provider' => 'cloudflare',
					's3_bucket' => 'my-custom-bucket',
					'auto_offload' => true,
				);
			}
			return $default;
		} );

		$settings = Clockwork_Offloader_Settings_Helper::get_settings();

		$this->assertEquals( 'cloudflare', $settings['provider'] );
		$this->assertEquals( 'my-custom-bucket', $settings['s3_bucket'] );
		$this->assertTrue( $settings['auto_offload'] );
		// Should inherit default for unspecified keys
		$this->assertEquals( 'us-east-1', $settings['s3_region'] );
		$this->assertFalse( $settings['rewrite_urls'] );
	}

	public function test_current_user_can_manage_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'current_user_can' )->alias( function( $cap ) {
			return 'manage_options' === $cap;
		} );

		$this->assertTrue( Clockwork_Offloader_Settings_Helper::current_user_can_manage() );
	}
}
