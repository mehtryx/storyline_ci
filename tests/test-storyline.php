<?php
error_reporting( E_ERROR & ~E_DEPRECATED & ~E_STRICT );

class StorylineTest extends WP_UnitTestCase {

    function setUp() {
        $this->storyline_plugin = new SMRT_Storyline();
        $business = wp_insert_term( 'business', 'smrt-topic' );
        $technology = wp_insert_term( 'technology', 'smrt-topic' );
        $sports = wp_insert_term( 'sports', 'smrt-topic' );
        $arts = wp_insert_term( 'arts', 'smrt-topic' );
        $entertainment = wp_insert_term( 'entertainment', 'smrt-topic' );

        if ( is_wp_error( $business ) ) {
            $business = get_term_by( 'name',  'business', 'smrt-topic', ARRAY_A );
        }

        if ( is_wp_error( $technology ) ) {
            $technology = get_term_by( 'name',  'technology', 'smrt-topic', ARRAY_A );
        }

        if ( is_wp_error( $sports ) ) {
            $sports = get_term_by( 'name',  'sports', 'smrt-topic', ARRAY_A );
        }

        if ( is_wp_error( $arts ) ) {
            $arts = get_term_by( 'name',  'arts', 'smrt-topic', ARRAY_A );
        }

        if ( is_wp_error( $entertainment ) ) {
            $entertainment = get_term_by( 'name',  'entertainment', 'smrt-topic', ARRAY_A );
        }

        wp_update_term( $business[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 1 ) );
        wp_update_term( $technology[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 2 ) );
        wp_update_term( $sports[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 3 ) );
        wp_update_term( $arts[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 4 ) );
        wp_update_term( $entertainment[ 'term_id' ], 'smrt-topic',  array( 'term_group' => 5 ) );

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

    function testGettingTopicOrderPrioritesAsArray() {
        $priorities = $this->storyline_plugin->get_topic_order_as_array( $this->terms );

        $this->assertEquals( 5, count( $priorities ) );

        $this->assertEquals( 1, $priorities[ 0 ] );
        $this->assertEquals( 2, $priorities[ 1 ] );
        $this->assertEquals( 3, $priorities[ 2 ] );
        $this->assertEquals( 4, $priorities[ 3 ] );
        $this->assertEquals( 5, $priorities[ 4 ] );
    }

    function testFindNextAvailableOrderNumber() {
        $new_spot = $this->storyline_plugin->find_next_available_order_number( $this->terms );
        $this->assertEquals( 6, $new_spot );
    }

    function testUpdatingTermWithValidOrderNumber() {

        $startups = wp_insert_term( 'startups', 'smrt-topic' );
        $_POST[ 'term_group' ] = 6;
        $this->storyline_plugin->save_topic_order( $startups[ 'term_id' ] );
        $new_startup = get_term( $startups[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 6,  $new_startup->term_group );
    }

    function testUpdatingTermWithInvalidOrderNumber() {
        $gaming = wp_insert_term( 'gaming', 'smrt-topic' );
        $_POST[ 'term_group' ] = 6;
        $this->storyline_plugin->save_topic_order( $gaming[ 'term_id' ] );
        $new_gaming = get_term( $gaming[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 7,  $new_gaming->term_group );
    }

    function testDisableTopic() {
        $technology = get_term_by( 'name',  'technology', 'smrt-topic', ARRAY_A );
        $disabled_topic = $this->storyline_plugin->disable_topic( $technology[ 'term_id' ] );
        $disable_topic_to_test = get_term( $disabled_topic[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 0, $disable_topic_to_test->term_group );
    }

    function testEnableTopic() {
        $sports = get_term_by( 'name',  'sports', 'smrt-topic', ARRAY_A );
        $topic = $this->storyline_plugin->disable_topic( $sports[ 'term_id' ] );
        $topic_to_test = get_term( $topic[ 'term_id' ], 'smrt-topic' );
        $topic_enabled = $this->storyline_plugin->enable_topic( $topic_to_test->term_id );
        $enable_topic_to_test = get_term( $topic_enabled[ 'term_id' ], 'smrt-topic' );
        $this->assertEquals( 3, $enable_topic_to_test->term_group );
    }

    function testPDShortcodeWithYouTubeReturnsYouTubeEmbed() {
        $url = "https://www.youtube.com/watch?v=d0K436vUM4w";

        $youtube_embed = do_shortcode( '[pd source="YouTube" url="' . $url . '"]' );

        $this->assertEquals( "http://youtube.com/embed/d0K436vUM4w", $youtube_embed );
    }

    function testPDShortcodeWithVineReturnsVineEmbed() {
        $url = "https://vine.co/v/O6ainAM5nbO";
        $params = "/embed/simple";

        $vine_embed = do_shortcode( '[pd source="Vine" url="' . $url . '" params="' . $params . '"]' );

        $this->assertEquals( "https://vine.co/v/O6ainAM5nbO/embed/simple", $vine_embed );
    }

    function testPDShortcodeWithInstagramReturnsInstagramEmbed() {
        $url = "https://instagram.com/p/wzImN9EXCE";
        $params = "/embed";

        $instagram_embed = do_shortcode( '[pd source="Instagram" url="' . $url . '" params="' . $params . '"]' );

        $this->assertEquals( "https://instagram.com/p/wzImN9EXCE/embed", $instagram_embed );
    }

    function testPDShortcodeWithSoundCloudReturnsSoundCloudEmbed() {
        $url = "https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/92212665";
        $params = "&amp;color=ff6600&amp;auto_play=false&amp;show_artwork=true";

        $soundcloud_embed = do_shortcode( '[pd source="SoundCloud" url="' . $url . '" params="' . $params . '"]' );

        $this->assertEquals( "https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/92212665&amp;color=ff6600&amp;auto_play=false&amp;show_artwork=true", $soundcloud_embed );
    }

    function testPDShortcodeWithVimeoReturnsVimeoEmbed() {
        $url = "http://player.vimeo.com/video/114536933";
        $vimeo_embed = do_shortcode( '[pd source="Vimeo" url="' . $url . '"]' );

        $this->assertEquals( "http://player.vimeo.com/video/114536933", $vimeo_embed );
    }

    function tearDown() {
        if( isset( $_POST[ 'term_group' ] ) ) {
            unset( $_POST[ 'term_group' ] );
        }

        $business = get_term_by( 'name',  'business', 'smrt-topic', ARRAY_A );
        $technology = get_term_by( 'name',  'technology', 'smrt-topic', ARRAY_A );
        $sports = get_term_by( 'name',  'sports', 'smrt-topic', ARRAY_A );
        $arts = get_term_by( 'name',  'arts', 'smrt-topic', ARRAY_A );
        $entertainment = get_term_by( 'name',  'entertainment', 'smrt-topic', ARRAY_A );
        $startups = get_term_by( 'name',  'startups', 'smrt-topic', ARRAY_A );
        $gaming = get_term_by( 'name',  'gaming', 'smrt-topic', ARRAY_A );

        wp_delete_term( $business[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $technology[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $sports[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $arts[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $entertainment[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $startups[ 'term_id' ], 'smrt-topic' );
        wp_delete_term( $gaming[ 'term_id' ], 'smrt-topic' );
    }
}

