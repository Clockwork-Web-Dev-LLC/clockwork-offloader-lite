<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader_Tracker;
use Brain\Monkey\Functions;
use Mockery;
use stdClass;

class OffloadTrackerTest extends TestCase {

	private Clockwork_Offloader_Tracker $tracker;
	private $mockWpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->mockWpdb = Mockery::mock( 'wpdb' );
		$this->mockWpdb->prefix = 'wp_';
		$this->mockWpdb->shouldReceive( 'get_var' )
			->with( "SHOW TABLES LIKE 'wp_clockwork_offloads'" )
			->andReturn( 'wp_clockwork_offloads' )
			->byDefault();
		$GLOBALS['wpdb'] = $this->mockWpdb;

		// Mock table existence check
		Functions\when( 'get_option' )->alias( function( $name, $default = false ) {
			if ( 'clockwork_offloader_table_exists' === $name ) {
				return true;
			}
			return $default;
		} );

		$this->tracker = new Clockwork_Offloader_Tracker();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_is_offloaded_returns_true_when_record_exists(): void {
		$this->mockWpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'PREPARED_QUERY' );

		$this->mockWpdb->shouldReceive( 'get_var' )
			->with( 'PREPARED_QUERY' )
			->once()
			->andReturn( '1' );

		$this->assertTrue( $this->tracker->is_offloaded( 123, 'thumbnail' ) );
	}

	public function test_is_offloaded_returns_false_when_not_found(): void {
		$this->mockWpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'PREPARED_QUERY' );

		$this->mockWpdb->shouldReceive( 'get_var' )
			->with( 'PREPARED_QUERY' )
			->once()
			->andReturn( null );

		$this->assertFalse( $this->tracker->is_offloaded( 999 ) );
	}

	public function test_get_offload_record_returns_row_object(): void {
		$mockRecord = new stdClass();
		$mockRecord->id = 1;
		$mockRecord->attachment_id = 123;
		$mockRecord->bucket = 'my-bucket';
		$mockRecord->s3_key = 'uploads/2026/09/sample.jpg';
		$mockRecord->status = 'offloaded';

		$this->mockWpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'PREPARED_QUERY' );

		$this->mockWpdb->shouldReceive( 'get_row' )
			->with( 'PREPARED_QUERY' )
			->once()
			->andReturn( $mockRecord );

		$record = $this->tracker->get_offload_record( 123 );

		$this->assertNotNull( $record );
		$this->assertEquals( 'my-bucket', $record->bucket );
		$this->assertEquals( 'uploads/2026/09/sample.jpg', $record->s3_key );
	}

	public function test_get_record_url_uses_cdn_domain_when_set(): void {
		Functions\when( 'get_option' )->alias( function( $name, $default = false ) {
			if ( 'clockwork_offloader_settings' === $name ) {
				return array( 'cdn_domain' => 'https://cdn.example.com' );
			}
			return $default;
		} );

		$record = new stdClass();
		$record->bucket = 'my-bucket';
		$record->s3_key = 'wp-content/uploads/2026/09/header.png';

		$url = $this->tracker->get_record_url( $record );

		$this->assertEquals( 'https://cdn.example.com/wp-content/uploads/2026/09/header.png', $url );
	}
}
