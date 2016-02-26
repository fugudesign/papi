<?php

class Papi_Lib_Core_Meta_Test extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->post_id = $this->factory->post->create();
	}

	public function tearDown() {
		parent::tearDown();
		unset( $this->post_id );
	}

	public function test_papi_get_meta_store() {
		$store = papi_get_meta_store( $this->post_id );
		$this->assertTrue( is_object( $store ) );
		$store = papi_get_meta_store( $this->post_id, 'fake' );
		$this->assertNull( $store );
	}

	public function test_papi_get_meta_type() {
		$this->assertSame( 'post', papi_get_meta_type() );
		$this->assertSame( 'post', papi_get_meta_type( 'post' ) );
		$this->assertSame( 'post', papi_get_meta_type( 'page' ) );
		$this->assertSame( 'term', papi_get_meta_type( 'term' ) );
		$this->assertSame( 'term', papi_get_meta_type( 'taxonomy' ) );
		$this->assertSame( 'option', papi_get_meta_type( 'option' ) );
		$this->assertSame( 'post', papi_get_meta_type( 'hello' ) );
	}
}
