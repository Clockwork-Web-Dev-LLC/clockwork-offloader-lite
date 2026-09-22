<?php
namespace ClockworkOffloader\Tests\Unit;

use ClockworkOffloader\Tests\TestCase;
use Clockwork_Offloader_Lite_Restrictions;

class LiteRestrictionsTest extends TestCase {

	public function test_pro_active_check_and_feature_gates(): void {
		// When Pro class does not exist (in Lite unit test suite)
		$this->assertFalse( Clockwork_Offloader_Lite_Restrictions::is_pro_active() );
		$this->assertFalse( Clockwork_Offloader_Lite_Restrictions::can_bulk_offload() );
		$this->assertFalse( Clockwork_Offloader_Lite_Restrictions::can_use_queue() );
		$this->assertFalse( Clockwork_Offloader_Lite_Restrictions::can_use_development_mode() );
		$this->assertFalse( Clockwork_Offloader_Lite_Restrictions::can_migrate() );
	}

	public function test_get_upgrade_url(): void {
		$url = Clockwork_Offloader_Lite_Restrictions::get_upgrade_url();
		$this->assertEquals( 'https://clockworkplugins.com/plugins/clockwork-offloader', $url );
	}
}
