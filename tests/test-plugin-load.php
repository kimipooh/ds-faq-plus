<?php

class Test_Plugin_Load extends WP_UnitTestCase {

	public function test_plugin_loaded() {

		$this->assertTrue(
			function_exists( 'add_shortcode' )
		);

	}
}
