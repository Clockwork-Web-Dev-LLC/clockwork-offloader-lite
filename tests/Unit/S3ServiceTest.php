<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader_S3_Service;
use Clockwork_Offloader_Settings_Helper;
use Brain\Monkey\Functions;
use Aws\S3\S3Client;
use Aws\MockHandler;
use Aws\Result;
use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Mockery;
use ReflectionProperty;

class S3ServiceTest extends TestCase {

	private Clockwork_Offloader_S3_Service $service;

	protected function setUp(): void {
		parent::setUp();
		Clockwork_Offloader_Settings_Helper::clear_cache();
		$this->service = new Clockwork_Offloader_S3_Service();
	}

	protected function tearDown(): void {
		Clockwork_Offloader_Settings_Helper::clear_cache();
		parent::tearDown();
	}

	public function test_get_provider_defaults_to_aws(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array() );

		$this->assertEquals( 'aws', $this->service->get_provider() );
	}

	public function test_get_provider_supports_custom_and_cloud_providers(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );

		$providers = array( 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' );

		foreach ( $providers as $provider ) {
			Clockwork_Offloader_Settings_Helper::clear_cache();
			Functions\when( 'get_option' )->justReturn( array( 'provider' => $provider ) );
			$service = new Clockwork_Offloader_S3_Service();
			$this->assertEquals( $provider, $service->get_provider() );
		}
	}

	public function test_get_provider_falls_back_to_aws_if_invalid(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array( 'provider' => 'invalid_provider' ) );

		$this->assertEquals( 'aws', $this->service->get_provider() );
	}

	public function test_get_credentials_returns_expected_structure(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'digitalocean',
			's3_access_key' => 'DO_ACCESS_KEY',
			's3_secret_key' => 'DO_SECRET_KEY',
			's3_bucket'     => 'assets-bucket',
			's3_region'     => 'nyc3',
		) );

		$creds = $this->service->get_credentials();

		$this->assertEquals( 'DO_ACCESS_KEY', $creds['access_key'] );
		$this->assertEquals( 'DO_SECRET_KEY', $creds['secret_key'] );
		$this->assertEquals( 'assets-bucket', $creds['bucket'] );
		$this->assertEquals( 'nyc3', $creds['region'] );
		$this->assertEquals( 'digitalocean', $creds['provider'] );
		$this->assertEquals( 'https://nyc3.digitaloceanspaces.com', $creds['endpoint'] );
	}

	public function test_get_credentials_auto_configures_wasabi_endpoint(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'wasabi',
			's3_access_key' => 'WASABI_KEY',
			's3_secret_key' => 'WASABI_SECRET',
			's3_bucket'     => 'wasabi-bucket',
			's3_region'     => 'us-east-2',
		) );

		$creds = $this->service->get_credentials();

		$this->assertEquals( 'https://s3.us-east-2.wasabisys.com', $creds['endpoint'] );
	}

	public function test_get_client_returns_wp_error_when_credentials_missing(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array() );

		$client = $this->service->get_client();

		$this->assertTrue( is_wp_error( $client ) );
		$this->assertEquals( 's3_not_configured', $client->get_error_code() );
	}

	public function test_get_client_returns_s3_client_when_configured(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'AKIAIOSFODNN7EXAMPLE',
			's3_secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			's3_bucket'     => 'my-site-uploads',
			's3_region'     => 'us-west-2',
		) );

		$client = $this->service->get_client();

		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'us-west-2', $client->getRegion() );
	}

	public function test_list_buckets_parses_buckets_using_mock_handler(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( fn( $a, $d ) => array_merge( $d, (array) $a ) );
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'TEST_KEY',
			's3_secret_key' => 'TEST_SECRET',
			's3_bucket'     => 'test-bucket',
			's3_region'     => 'us-east-1',
		) );
		Functions\when( 'set_transient' )->justReturn( true );

		$mock = new MockHandler();
		$mock->append( new Result( array(
			'Buckets' => array(
				array( 'Name' => 'bucket-alpha', 'CreationDate' => '2026-01-01T00:00:00Z' ),
				array( 'Name' => 'bucket-bravo', 'CreationDate' => '2026-01-02T00:00:00Z' ),
			),
		) ) );

		$client = new S3Client( array(
			'version'     => 'latest',
			'region'      => 'us-east-1',
			'credentials' => array( 'key' => 'k', 'secret' => 's' ),
			'handler'     => $mock,
		) );

		// Inject mock client into service
		$ref = new ReflectionProperty( Clockwork_Offloader_S3_Service::class, 's3_client' );
		$ref->setValue( $this->service, $client );

		$buckets = $this->service->list_buckets();

		$this->assertIsArray( $buckets );
		$this->assertCount( 2, $buckets );
		$this->assertEquals( 'bucket-alpha', $buckets[0] );
		$this->assertEquals( 'bucket-bravo', $buckets[1] );
	}

	public function test_file_exists_returns_true_when_object_exists(): void {
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'TEST_KEY',
			's3_secret_key' => 'TEST_SECRET',
			's3_bucket'     => 'test-bucket',
			's3_region'     => 'us-east-1',
		) );

		$mock = new MockHandler();
		$mock->append( new Result( array(
			'ContentLength' => 2048,
			'ContentType'   => 'image/webp',
		) ) );

		$client = new S3Client( array(
			'version'     => 'latest',
			'region'      => 'us-east-1',
			'credentials' => array( 'key' => 'k', 'secret' => 's' ),
			'handler'     => $mock,
		) );

		$ref = new ReflectionProperty( Clockwork_Offloader_S3_Service::class, 's3_client' );
		$ref->setValue( $this->service, $client );

		$this->assertTrue( $this->service->file_exists( 'uploads/2026/09/sample.webp' ) );
	}

	public function test_file_exists_returns_false_when_object_not_found(): void {
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'TEST_KEY',
			's3_secret_key' => 'TEST_SECRET',
			's3_bucket'     => 'test-bucket',
			's3_region'     => 'us-east-1',
		) );

		$mock = new MockHandler();
		$cmd = Mockery::mock( CommandInterface::class );
		$mock->append( new S3Exception( 'Not Found', $cmd, array( 'code' => 'NotFound', 'status_code' => 404 ) ) );

		$client = new S3Client( array(
			'version'     => 'latest',
			'region'      => 'us-east-1',
			'credentials' => array( 'key' => 'k', 'secret' => 's' ),
			'handler'     => $mock,
		) );

		$ref = new ReflectionProperty( Clockwork_Offloader_S3_Service::class, 's3_client' );
		$ref->setValue( $this->service, $client );

		$this->assertFalse( $this->service->file_exists( 'uploads/missing.png' ) );
	}

	public function test_bucket_requires_path_style_for_dots(): void {
		$this->assertTrue( Clockwork_Offloader_S3_Service::bucket_requires_path_style( 'media.example.com' ) );
		$this->assertFalse( Clockwork_Offloader_S3_Service::bucket_requires_path_style( 'simple-bucket' ) );
	}

	public function test_build_public_url_formats_per_provider(): void {
		// Standard AWS virtual-hosted
		$aws_url = Clockwork_Offloader_S3_Service::build_public_url( 'my-bucket', '2026/09/pic.jpg', 'us-west-2', 'aws' );
		$this->assertEquals( 'https://my-bucket.s3.us-west-2.amazonaws.com/2026/09/pic.jpg', $aws_url );

		// AWS dotted bucket (path-style for wildcard cert compatibility)
		$dotted_aws = Clockwork_Offloader_S3_Service::build_public_url( 'cdn.site.com', '2026/09/pic.jpg', 'us-east-1', 'aws' );
		$this->assertEquals( 'https://s3.us-east-1.amazonaws.com/cdn.site.com/2026/09/pic.jpg', $dotted_aws );

		// DigitalOcean Spaces
		$do_url = Clockwork_Offloader_S3_Service::build_public_url( 'my-space', '2026/09/pic.jpg', 'nyc3', 'digitalocean' );
		$this->assertEquals( 'https://my-space.nyc3.digitaloceanspaces.com/2026/09/pic.jpg', $do_url );

		// Wasabi
		$wasabi_url = Clockwork_Offloader_S3_Service::build_public_url( 'wasabi-bucket', '2026/09/pic.jpg', 'us-central-1', 'wasabi' );
		$this->assertEquals( 'https://s3.us-central-1.wasabisys.com/wasabi-bucket/2026/09/pic.jpg', $wasabi_url );

		// Backblaze B2
		$b2_url = Clockwork_Offloader_S3_Service::build_public_url( 'b2-bucket', '2026/09/pic.jpg', 'us-west-004', 'backblaze' );
		$this->assertEquals( 'https://s3.us-west-004.backblazeb2.com/b2-bucket/2026/09/pic.jpg', $b2_url );

		// Custom endpoint
		$custom_url = Clockwork_Offloader_S3_Service::build_public_url( 'minio-bucket', '2026/09/pic.jpg', 'us-east-1', 'custom', 'https://minio.example.com' );
		$this->assertEquals( 'https://minio.example.com/minio-bucket/2026/09/pic.jpg', $custom_url );
	}

	public function test_upload_file_successful_put_object_via_mock_handler(): void {
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'TEST_KEY',
			's3_secret_key' => 'TEST_SECRET',
			's3_bucket'     => 'test-bucket',
			's3_region'     => 'us-east-1',
		) );

		$tmp_file = tempnam( sys_get_temp_dir(), 's3_test_' );
		file_put_contents( $tmp_file, 'test file contents' );

		$mock = new MockHandler();
		$mock->append( new Result( array(
			'ObjectURL' => 'https://test-bucket.s3.amazonaws.com/uploads/sample.jpg',
			'@metadata' => array( 'statusCode' => 200 ),
		) ) );

		$client = new S3Client( array(
			'version'     => 'latest',
			'region'      => 'us-east-1',
			'credentials' => array( 'key' => 'k', 'secret' => 's' ),
			'handler'     => $mock,
		) );

		$ref = new ReflectionProperty( Clockwork_Offloader_S3_Service::class, 's3_client' );
		$ref->setValue( $this->service, $client );

		$res = $this->service->upload_file( $tmp_file, 101, '', 'uploads/sample.jpg' );

		unlink( $tmp_file );

		$this->assertIsArray( $res );
		$this->assertEquals( 'test-bucket', $res['bucket'] );
		$this->assertEquals( 'uploads/sample.jpg', $res['s3_key'] );
	}

	public function test_upload_file_handles_403_access_denied_exception(): void {
		Functions\when( 'get_option' )->justReturn( array(
			'provider'      => 'aws',
			's3_access_key' => 'TEST_KEY',
			's3_secret_key' => 'TEST_SECRET',
			's3_bucket'     => 'test-bucket',
			's3_region'     => 'us-east-1',
		) );

		$tmp_file = tempnam( sys_get_temp_dir(), 's3_test_' );
		file_put_contents( $tmp_file, 'test file contents' );

		$mock = new MockHandler();
		$cmd = Mockery::mock( CommandInterface::class );
		$mock->append( new S3Exception( 'Access Denied', $cmd, array( 'code' => 'AccessDenied', 'status_code' => 403 ) ) );

		$client = new S3Client( array(
			'version'     => 'latest',
			'region'      => 'us-east-1',
			'credentials' => array( 'key' => 'k', 'secret' => 's' ),
			'handler'     => $mock,
		) );

		$ref = new ReflectionProperty( Clockwork_Offloader_S3_Service::class, 's3_client' );
		$ref->setValue( $this->service, $client );

		$res = $this->service->upload_file( $tmp_file, 101, '', 'uploads/sample.jpg' );

		unlink( $tmp_file );

		$this->assertTrue( is_wp_error( $res ) );
		$this->assertEquals( 'upload_failed', $res->get_error_code() );
	}
}
