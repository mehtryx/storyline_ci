<?php
error_reporting( E_ERROR & ~E_DEPRECATED & ~E_STRICT );

class StorylineTest extends WP_UnitTestCase {

    function setUp() {
        parent::setUp();

        $this->storyline_plugin = new SMRT_Storyline();
        $business = wp_insert_term( 'business', 'smrt-topic' );
        $technology = wp_insert_term( 'technology', 'smrt-topic' );
        $sports = wp_insert_term( 'sports', 'smrt-topic' );
        $arts = wp_insert_term( 'arts', 'smrt-topic' );
        $entertainment = wp_insert_term( 'entertainment', 'smrt-topic' );
        //$startups = wp_insert_term( 'startups', 'smrt-topic' );
        
        wp_update_term( $business[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 1 ) );
        wp_update_term( $technology[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 2 ) );
        wp_update_term( $sports[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 3 ) );
        wp_update_term( $arts[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 4 ) );
        wp_update_term( $entertainment[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 5 ) );
        //wp_update_term( $startups[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 6 ) );



        $args = array(
            'hide_empty' => 0,
            'orderby' => 'term_group'
        );

        $this->terms = get_terms( 'smrt-topic', $args );
    }

    function testVerifyTopicsTaxonomyExists() {
        $this->assertTrue( taxonomy_exists( 'smrt-topic' ) );
    }

    function testSettingOrderOfTermWithExistingOrderNumber() {
        $order_exists = $this->storyline_plugin->order_num_exists( $this->terms, 5 );
        $this->assertTrue( $order_exists );
    }

    function testFindLastOrderNumber() {
        $new_spot = $this->storyline_plugin->find_next_available_order_number( $this->terms );
        $this->assertEquals( 6, $new_spot );
    }

    function testUpdatingTermWithValidOrderNumber() {

        $startups = wp_insert_term( 'startups', 'smrt-topic' );
        $_POST[ 'term_group' ] = 6;
        $this->storyline_plugin->save_topic_order( $startups[ 'term_id' ] );
        $new_startup = get_term( $startups[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 7,  $new_startup->term_group );
    }

    function testUpdatingTermWithInvalidOrderNumber() {
        $gaming = wp_insert_term( 'gaming', 'smrt-topic' );
        $_POST[ 'term_group' ] = 6;
        $this->storyline_plugin->save_topic_order( $gaming[ 'term_id' ] );
        $new_gaming = get_term( $gaming[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 7,  $new_gaming->term_group );
    }

    function tearDown() {
        parent::tearDown();
        if( isset( $_POST[ 'term_group' ] ) ) {
            unset( $_POST[ 'term_group' ] );
        }
    }
}

