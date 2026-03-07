<?php
/**
 * Class Test_Shortcode_Registration
 *
 * @package Wp_Ds_Faq_Plus
 */

class Test_Shortcode_Registration extends WP_UnitTestCase {

	public function test_dsfaq_shortcode_is_registered() {
		global $shortcode_tags;

		$this->assertArrayHasKey( 'dsfaq', $shortcode_tags );
	}
}