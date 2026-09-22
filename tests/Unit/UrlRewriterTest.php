<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader_URL_Rewriter;
use Clockwork_Offloader_Settings_Helper;
use Clockwork_Offloader_Tracker;
use Brain\Monkey\Functions;
use ReflectionProperty;

class UrlRewriterTest extends TestCase {

	private Clockwork_Offloader_URL_Rewriter $rewriter;

	protected function setUp(): void {
		parent::setUp();
		Clockwork_Offloader_Settings_Helper::clear_cache();

		// Reset singleton instance
		$ref = new ReflectionProperty( Clockwork_Offloader_URL_Rewriter::class, 'instance' );
		$ref->setValue( null, null );

		$this->rewriter = Clockwork_Offloader_URL_Rewriter::get_instance();
	}

	protected function tearDown(): void {
		Clockwork_Offloader_Settings_Helper::clear_cache();
		parent::tearDown();
	}

	public function test_rewrite_attachment_url_returns_original_when_rewrite_disabled(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'rewrite_urls' => false,
		) );

		$original_url = 'https://example.com/wp-content/uploads/2026/09/test.jpg';
		$result = $this->rewriter->rewrite_attachment_url( $original_url, 42 );

		$this->assertEquals( $original_url, $result );
	}

	public function test_replace_s3_domain_with_cdn(): void {
		$refMethod = new \ReflectionMethod( Clockwork_Offloader_URL_Rewriter::class, 'replace_s3_domain_with_cdn' );

		$s3_url = 'https://my-bucket.s3.us-east-1.amazonaws.com/wp-content/uploads/2026/09/hero.jpg';
		$cdn_domain = 'https://cdn.example.com';

		$cdn_url = $refMethod->invoke( $this->rewriter, $s3_url, $cdn_domain );

		$this->assertEquals( 'https://cdn.example.com/wp-content/uploads/2026/09/hero.jpg', $cdn_url );
	}

	public function test_replace_s3_domain_with_cdn_handles_trailing_slashes(): void {
		$refMethod = new \ReflectionMethod( Clockwork_Offloader_URL_Rewriter::class, 'replace_s3_domain_with_cdn' );

		$s3_url = 'https://nyc3.digitaloceanspaces.com/my-space/wp-content/uploads/2026/09/hero.webp';
		$cdn_domain = 'https://media.mysite.com/';

		$cdn_url = $refMethod->invoke( $this->rewriter, $s3_url, $cdn_domain );

		$this->assertEquals( 'https://media.mysite.com/my-space/wp-content/uploads/2026/09/hero.webp', $cdn_url );
	}

	public function test_rewrite_content_returns_unchanged_when_disabled(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'rewrite_urls' => false,
		) );

		$html = '<p><img src="https://example.com/wp-content/uploads/2026/09/pic.jpg" alt="test" /></p>';
		$result = $this->rewriter->rewrite_content( $html );

		$this->assertEquals( $html, $result );
	}

	public function test_rewrite_content_returns_unchanged_for_empty_string(): void {
		$this->assertSame( '', $this->rewriter->rewrite_content( '' ) );
	}

	public function test_rewrite_content_replaces_beaver_builder_css_urls(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->alias( function( $name, $default = false ) {
			if ( 'clockwork_offloader_settings' === $name ) {
				return array(
					'rewrite_urls' => true,
					'provider'     => 'aws',
					's3_bucket'    => 'test-bucket',
					's3_region'    => 'us-east-1',
					'cdn_domain'   => 'https://cdn.example.com',
				);
			}
			if ( 'clockwork_offloader_table_exists' === $name ) {
				return true;
			}
			return $default;
		} );
		Functions\when( 'wp_upload_dir' )->justReturn( array(
			'baseurl' => 'https://example.com/wp-content/uploads',
			'basedir' => '/var/www/html/wp-content/uploads',
		) );

		$mockWpdb = \Mockery::mock( 'wpdb' );
		$mockWpdb->prefix = 'wp_';
		$mockWpdb->shouldReceive( 'get_var' )->andReturn( 'wp_clockwork_offloads' )->byDefault();
		$mockWpdb->shouldReceive( 'prepare' )->andReturn( 'PREPARED' );
		$row = (object) array(
			'id'            => 1,
			'attachment_id' => 19494,
			'bucket'        => 'test-bucket',
			's3_key'        => '2026/04/Lake-George.jpg',
			'original_path' => '/var/www/html/wp-content/uploads/2026/04/Lake-George.jpg',
			'status'        => 'offloaded',
			'size_name'     => 'full',
			'file_size'     => 1024,
			'offload_date'  => '2026-09-22 12:00:00',
		);
		$mockWpdb->shouldReceive( 'get_results' )->andReturn( array( $row ) );
		$GLOBALS['wpdb'] = $mockWpdb;

		$css = '.fl-node-123 > .fl-row-content-wrap { background-image: url(https://example.com/wp-content/uploads/2026/04/Lake-George.jpg); }';
		$result = $this->rewriter->rewrite_content( $css );

		$this->assertStringContainsString( 'https://cdn.example.com/2026/04/Lake-George.jpg', $result );
		unset( $GLOBALS['wpdb'] );
	}
}
