<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader;
use Clockwork_Offloader_Settings_Helper;
use Clockwork_Offloader_Tracker;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionProperty;

class AttachmentHookTest extends TestCase {

	private Clockwork_Offloader $plugin;

	protected function setUp(): void {
		parent::setUp();
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'clockwork-offloader.php';
		Clockwork_Offloader_Settings_Helper::clear_cache();

		// Reset singleton
		$ref = new ReflectionProperty( Clockwork_Offloader::class, 'instance' );
		$ref->setValue( null, null );

		$this->plugin = Clockwork_Offloader::get_instance();
	}

	protected function tearDown(): void {
		Clockwork_Offloader_Settings_Helper::clear_cache();
		parent::tearDown();
	}

	public function test_get_attachment_files_returns_original_and_sizes(): void {
		$upload_dir = sys_get_temp_dir() . '/wp-content/uploads/2026/09';
		@mkdir( $upload_dir, 0777, true );
		$main_file = $upload_dir . '/test-photo.jpg';
		$thumb_file = $upload_dir . '/test-photo-150x150.jpg';
		touch( $main_file );
		touch( $thumb_file );

		Functions\when( 'get_attached_file' )->alias( function( $id ) use ( $main_file ) {
			return $main_file;
		} );

		$metadata = array(
			'width'  => 1200,
			'height' => 800,
			'file'   => '2026/09/test-photo.jpg',
			'sizes'  => array(
				'thumbnail' => array(
					'file'      => 'test-photo-150x150.jpg',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
				),
			),
		);

		$files = Clockwork_Offloader::get_attachment_files( 123, $metadata );

		$this->assertIsArray( $files );
		$this->assertArrayHasKey( '', $files );
		$this->assertEquals( $main_file, $files[''] );
		$this->assertArrayHasKey( 'thumbnail', $files );
		$this->assertEquals( $thumb_file, $files['thumbnail'] );

		@unlink( $main_file );
		@unlink( $thumb_file );
		@rmdir( $upload_dir );
	}

	public function test_delete_local_file_rejects_paths_outside_uploads_dir(): void {
		$uploads_base = sys_get_temp_dir() . '/wp-content/uploads';
		@mkdir( $uploads_base, 0777, true );

		Functions\when( 'wp_upload_dir' )->justReturn( array(
			'basedir' => $uploads_base,
		) );

		// Malicious or outside path
		$outside_file = sys_get_temp_dir() . '/sensitive_outside.txt';
		touch( $outside_file );

		$deleted = Clockwork_Offloader::delete_local_file( $outside_file );

		$this->assertFalse( $deleted );
		$this->assertFileExists( $outside_file );

		@unlink( $outside_file );
		@rmdir( $uploads_base );
	}

	public function test_delete_local_file_deletes_valid_file_in_uploads(): void {
		$uploads_base = sys_get_temp_dir() . '/wp-content/uploads';
		@mkdir( $uploads_base . '/2026/09', 0777, true );
		$valid_file = $uploads_base . '/2026/09/delete-me.jpg';
		touch( $valid_file );

		Functions\when( 'wp_upload_dir' )->justReturn( array(
			'basedir' => $uploads_base,
		) );

		$deleted = Clockwork_Offloader::delete_local_file( $valid_file );

		$this->assertTrue( $deleted );
		$this->assertFileDoesNotExist( $valid_file );

		@rmdir( $uploads_base . '/2026/09' );
		@rmdir( $uploads_base );
	}

	public function test_handle_attachment_metadata_bails_when_auto_offload_disabled(): void {
		Functions\when( 'get_option' )->alias( function( $name, $default = false ) {
			if ( 'clockwork_offloader_settings' === $name ) {
				return array( 'auto_offload' => false );
			}
			return $default;
		} );

		$meta = array( 'file' => '2026/09/sample.jpg' );
		$result = $this->plugin->handle_attachment_metadata( $meta, 456 );

		$this->assertEquals( $meta, $result );
	}

	public function test_get_attachment_files_rejects_path_traversal_sizes(): void {
		$upload_dir = sys_get_temp_dir() . '/wp-content/uploads/2026/09';
		@mkdir( $upload_dir, 0777, true );
		$main_file = $upload_dir . '/test-photo.jpg';
		touch( $main_file );

		Functions\when( 'get_attached_file' )->alias( function( $id ) use ( $main_file ) {
			return $main_file;
		} );

		$metadata = array(
			'width'  => 1200,
			'height' => 800,
			'file'   => '2026/09/test-photo.jpg',
			'sizes'  => array(
				'evil' => array(
					'file' => '../../wp-config.php',
				),
				'evil_nested' => array(
					'file' => 'subdir/../../secret.txt',
				),
			),
			'original_image' => '../../wp-config.php',
		);

		$files = Clockwork_Offloader::get_attachment_files( 123, $metadata );

		$this->assertArrayNotHasKey( 'evil', $files );
		$this->assertArrayNotHasKey( 'evil_nested', $files );
		$this->assertArrayNotHasKey( 'original_image', $files );

		@unlink( $main_file );
		@rmdir( $upload_dir );
	}

	public function test_is_valid_upload_path_rejects_sibling_directories(): void {
		$uploads_base = sys_get_temp_dir() . '/wp-content/uploads';
		$sibling_base = sys_get_temp_dir() . '/wp-content/uploads-evil';
		@mkdir( $uploads_base, 0777, true );
		@mkdir( $sibling_base, 0777, true );

		Functions\when( 'wp_upload_dir' )->justReturn( array(
			'basedir' => $uploads_base,
		) );

		$evil_file = $sibling_base . '/trojan.php';
		touch( $evil_file );

		$this->assertFalse( Clockwork_Offloader::is_valid_upload_path( $evil_file ) );
		$this->assertFalse( Clockwork_Offloader::delete_local_file( $evil_file ) );

		@unlink( $evil_file );
		@rmdir( $sibling_base );
		@rmdir( $uploads_base );
	}
}
