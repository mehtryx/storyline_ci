<?php
/*
Plugin Name: Storyline
Plugin URI: http://github.com/Postmedia/storyline
Description: Supports mobile story elements
Author: Postmedia Network Inc.
Version: 0.2.3
Author URI: http://github.com/Postmedia
License: MIT    
*/

/*
Copyright (c) 2013 Postmedia Network Inc.

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

/**
 * WordPress plugin to support mobile story elements
 *
 * @package Storyline
 */
define( 'SMRT_STORYLINE_VERSION', '0.2.3' );

/**
 * Main Storyline Class contains registration and hooks
 */
class SMRT_Storyline {

	/**
	 * Class construct registers image sizes, actions, and filters
	 *
	 * @since 0.1.0
	 *
	 * @uses add_image_size() to register mobile thumbnails and feature images
	 * @uses add_action()
	 * @uses add_filter()
	 */
	public function __construct() {
		// register new image sizes
		add_image_size( 'smrt-phone-thumb', 95, 95, true );
		add_image_size( 'smrt-phone-thumb-x2', 190, 190, true );
		add_image_size( 'smrt-phone-feature', 320, 192, true );
		add_image_size( 'smrt-phone-feature-x2', 640, 384, true );
		
		// register hooks and filters	
		add_action( 'init', array( $this, 'register_storyline' ) );
		add_action( 'publish_storyline', array( $this, 'set_date_sort' ) );
		add_filter( 'the_content', array( $this, 'modified_post_view' ) ); 
		add_filter( 'json_feed_item',  array( $this ,'json_feed_items_with_slides' ), 10, 4 );
		
		// register ajax handler for topics
		add_action( 'wp_ajax_smrt_topics', array( $this, 'smrt_topics_callback' ) );
		add_action( 'wp_ajax_nopriv_smrt_topics', array( $this, 'smrt_topics_callback' ) );
	}
	
	/**
	 * Adds additional meta data to json feed item
	 *
	 * @since 0.2.0 
	 *
	 * @uses get_the_modified_time()
	 * @uses get_post_thumbnail_id()
	 * @uses wp_get_post_terms()
	 * 
	 * @param object @item The json feed item
	 * @return object The updated json feed item
	 */
	public function json_feed_items_with_slides( $item, $id, $query_args, $json_feed ) {
		
		// only update posts of type storyline
		if ( 'storyline' !== $item['type'] )
			return $item;
		
		$item['content'] = $this->split_content( $item['content'], true );
		$item['last_modified'] = get_the_modified_time( json_feed_date_format() );
		
		$date_sort = get_post_meta( $id, '_date_sort', true );
		if ( false === $date_sort ) {
			$item['date_sort'] = $item['date'];
		}
		else {
			$new_date = new DateTime( $date_sort );
			$item['date_sort'] = $new_date->format( json_feed_date_format() );
		}
		
		// include post format
		$format = get_post_format();
		if ( false !== $format )
			$item['post_format'] = $format;
		
		// calculate index of post
		static $offset;
		if ( !isset( $offset ) ) {
			$paged = ( $json_feed->get( 'paged' ) ) ? $json_feed->get( 'paged' ) : 1;
			$posts_per_page = $json_feed->get( 'posts_per_page' );
			$offset = ( $paged - 1 ) * $posts_per_page;
		}
		$position = $json_feed->current_post + $offset;
		$item['position'] = $position;
		
		// include total number of posts and query vars on first post
		if ( 0 === $position ) {
			$item['post_count'] = $json_feed->found_posts;
			$item['query'] = $json_feed->query;
		}
		
		// specify thumbnail and overwrite featured image url
		$thumbnail_id = get_post_thumbnail_id();
		if ( !empty( $thumbnail_id ) ) {
			$item = $this->add_image_url( $item, $thumbnail_id, 'thumbnail_url', 'smrt-phone-thumb' );
			$item = $this->add_image_url( $item, $thumbnail_id, 'thumbnail_url_x2', 'smrt-phone-thumb-x2' );
			$item = $this->add_image_url( $item, $thumbnail_id, 'featured_image_url', 'smrt-phone-feature' );
			$item = $this->add_image_url( $item, $thumbnail_id, 'featured_image_url_x2', 'smrt-phone-feature-x2' );
		}
		
		// return topics
		$item['topics'] = Array();
		$topics = wp_get_post_terms( 'smrt-topic' );
		if ( is_array( $topics ) ) {
			foreach( $topics as $topic ) {
				$item['topics'][] = array (
					'id' => (int) $topic->term_id,
					'title' => $topic->name,
					'slug' => $topic->slug
				);
			}
		}
		
		return $item;
	}
	
	/**
	 * Adds the specified featured thumbnail size to a json feed item
	 *
	 * @since 0.2.2
	 *
	 * @uses wp_get_attachment_image_src()
	 *
	 * @param object $item The json feed item
	 * @param int $thumbnail_id The id of the featured image
	 * @param string $field The name of the field to store within json feed item
	 * @param string $size The image size to look for
	 * @return object The updated json feed item
	 */
	function add_image_url( $item, $thumbnail_id, $field, $size ) {
		$image = wp_get_attachment_image_src( $thumbnail_id, $size );
		if ( !empty( $image ) ) {
			$item[$field] = $image[0];
		}
		return $item;
	}
	
	/**
	 * Converts a selection of content into an array of content items
	 *
	 * @since 0.1.1
	 * @param string $content The text to convert
	 * @return string[] An array of slides
	 */
    function split_content( $content, $apply_filters = false ) {
		if ( empty( $content ) ) {
			return array();
		}
		
		$content = preg_replace( '/<span id=\"more-.*\"><\/span>/u', "<!--more-->", $content );
		$slides = explode( "<!--more-->", $content );
		if ( $apply_filters ) {
			for ( $index = 0, $len = count( $slides ); $index < $len; $index ++) {
				$slides[$index] = apply_filters( 'the_content', $slides[$index] );
			}
		}
		return $slides;
	}
	
	/**
	 * Registers a new post type of "Storyline" and a new taxonomy of "Topics" (smrt-topic)
	 *
	 * @since 0.1.0
	 *
	 * @uses register_post_type()
	 * @uses register_taxonomy()
	 */
	public function register_storyline() {
		register_post_type( 'storyline', array(
			'public' => true,
			'label' => 'Storyline',
			'description' => 'Storyline',
			'menu_position' => 5,
			'has_archive' => true,
			'rewrite' => array( 'slug' => 'storyline' ),
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt','post-formats', 'page-attributes' ),
			'taxonomies' => array( 'category' )
		) );
		
		$labels = array(
			'name'                        => _x( 'Topics', 'taxonomy general name' ),
			'singular_name'               => _x( 'Topic', 'taxonomy singular name' ),
			'search_items'                => __( 'Search Topics' ),
			'popular_items'               => __( 'Popular Topics' ),
			'all_items'                   => __( 'All Topics' ),
			'parent_item'                 => null,
			'parent_item_colon'           => null,
			'edit_item'                   => __( 'Edit Topic' ),
			'update_item'                 => __( 'Update Topic' ),
			'add_new_item'                => __( 'Add New Topic' ),
			'new_item_name'               => __( 'New Topic' ),
			'separate_items_with_commas'  => __( 'Separate topics with commas' ),
			'add_or_remove_items'         => __( 'Add or remove topics' ),
			'choose_from_most_used'       => __( 'Choose from the most used topics' ),
			'not_found'                   => __( 'No topics found.' ),
			'menu_name'                   => __( 'Topics' ),
		);
		
		register_taxonomy( 'smrt-topic', 'storyline', array(
			'labels' => $labels,
			'rewrite' => array( 'slug' => 'smrt-topic' ),
			'hierarchical' => false,
			'show_admin_column' => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var' => true
		) );
	}
	
	/**
	 * Render content slides for preview
	 *
	 * @since 0.1.0
	 *
	 */
	public function render_content_slides( $slides ) {        
		$statusBar = "<div class='x-unsized x-label bottom-pagination x-dock-item x-docked-bottom x-has-width' id='ext-label-1'><div class='x-innerhtml' id='ext-element-102'>1/3</div></div>";
		$slides_content = "";
		$counter = 1;
		foreach ( $slides as $slide ) {
			if ( $counter > 1 ) {
				$slide = trim( $slide );
				$slides_content .= "<div class='swiper-slide'><div class='story_item'>" . $slide . "<div class='statusbar sub'>" . $counter . "/" . ( count( $slides ) ) . "</div></div></div>";
			}
			$counter ++;
		}
		return $slides_content;
	}
	
	/**
	 * Render storyline
	 *
	 * @since 0.1.0
	 *
	 * @uses wp_register_style
	 * @uses wp_register_script
	 * @uses wp_enqueue_style
	 * @uses wp_enqueue_script
	 * @uses has_post_thumbnail
	 * @uses get_the_post_thumbnail
	 * @uses esc_html
	 */
	public function modified_post_view( $content ) {
		global $post;
		
		if( $post->post_type != 'storyline' || is_feed() )
			return $content;
		
		$header = "<div class='x-container x-toolbar-dark x-toolbar x-navigation-bar x-stretched x-paint-monitored x-layout-box-item' id='ext-titlebar-1'><div class='x-body' id='ext-element-13'><div class='x-center' id='ext-element-14'><div class='x-unsized x-title x-floating' id='ext-title-1' style='z-index: 8 !important;'><div class='x-innerhtml' id='ext-element-12'></div></div></div><div class='x-inner x-toolbar-inner x-horizontal x-align-stretch x-pack-start x-layout-box' id='ext-element-9'><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-1' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-10' style=''><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='nav_btn' style='width: 50px !important; height: 50px !important; background-image: url(/wp-content/plugins/storyline/img/toolbar/icon-menu.png);'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div></div><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='nav_icon' style='width: 42px !important; height: 48px !important; background-image: url(/wp-content/plugins/storyline/img/logo.png);'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 43px; height: 49px;'></div></div><div class='shrink'><div style='width: 42px; height: 48px;'></div></div></div></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 93px; height: 51px;'></div></div><div class='shrink'><div style='width: 92px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-size-monitored x-paint-monitored x-layout-box-item x-flexed x-stretched' id='ext-component-1' style='position: relative; -webkit-box-flex: 1;'><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 1128.71875px; height: 51px;'></div></div><div class='shrink'><div style='width: 1127.71875px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-2' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-11' style=''><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='bt_alert' style='width: 50px !important; height: 50px !important; background-image: url(/wp-content/plugins/storyline/img/toolbar/icon-alerts-active.png);'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div></div></div><div class='x-paint-monitor overflowchange'></div></div>";
		$adBanner = "<div class='adBanner x-img x-img-image x-sized x-paint-monitored x-size-monitored bottom_ad x-img-background x-dock-item x-docked-bottom' id='ext-image-2' style='width: 100% !important; height: 50px !important;'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 402px; height: 51px;'></div></div><div class='shrink'><div style='width: 100%; height: 50px;'></div></div></div></div>";
		
		wp_register_style( 'storylinestyle', plugins_url( 'css/style.css', __FILE__ ) );
		wp_register_style( 'swipercss', plugins_url( 'css/idangerous.swiper.css', __FILE__ ) );
		wp_register_script( 'storylinescript', plugins_url ( 'js/script.js', __FILE__ ), '', '', TRUE );
		wp_register_script( 'swiperscript', plugins_url( 'js/idangerous.swiper-2.1.min.js', __FILE__ ), '', '', TRUE );
		
		wp_enqueue_style( 'storylinestyle' );
		wp_enqueue_style( 'swipercss' );
		wp_enqueue_script( 'swiperscript' );
		wp_enqueue_script( 'storylinescript' );
		
		$slides = $this->split_content( $content );
		return "<div class='smart-device-preview'>
                    <div class='select-device'> 
                        <label for='phoneselector'>Select a device</label><br>
                        <select name='phoneselector' id='phoneselector'>                            
                        </select>
                    </div>
                    <div class='device-wrapper'>
                        <div class='viewport-wrapper' >
                            " . $header . "
                            <div class='swiper-container'>                                
                                <div class='swiper-wrapper'>
                                    <div class='swiper-slide'>
                                        <div class='story_item'>" .
                                            ( has_post_thumbnail( $post->ID ) ? "<div class='story_image'>". get_the_post_thumbnail( $post->ID, array( 315, 175 ) ) . "</div>" : "" ) . "
                                            <div class='first_story_content'><div class='story_headline'><h3>". esc_html( $post->post_title ) . "</h3></div>
                                            <div class='timestamp'><span class='updated'>Updated: </span><span>11:40 AM</span></div>
                                            <div class='story_abstract'>" . esc_html( $slides[0] ) . "</div></div>
                                        </div><div class='statusbar'>1/" . ( count( $slides ) ) . "</div>" . $adBanner .
                                    "</div>"
                                    . esc_html( $this->render_content_slides( $slides ) ) .
                                "</div>
                                <div class='pagination'></div>
                            <div>
                        </div>
                    </div>
                </div>";
	}
	
	/**
	 * Saves the date only (no time) in custom field for sorting
	 *
	 * @since 0.2.3
	 *
	 * @uses get_post
	 * @uses update_post_meta
	 */
	public function set_date_sort( $post_id ) {
		$post = get_post( $post_id );
		$date_sort = new DateTime( $post->post_date );
		$date_sort->setTime( 0, 0, 0 );
		if ( !empty( $post->menu_order ) ) {
			$date_sort->add( new DateInterval( 'P1D' ) );
			$date_sort->sub( new DateInterval( 'PT' . $post->menu_order . 'M' ) );
		}
		update_post_meta( $post_id, '_date_sort', $date_sort->format( 'Y-m-d H:i:s' ) );
	}
	
	/**
	 * AJAX hook to return list of topics as JSON
	 *
	 * @since 0.2.2
	 *
	 * @uses get_terms()
	 * @uses wp_die()
	 * @uses sanitize_text_field()
	 */
	public function smrt_topics_callback() {
		$topics = get_terms( 'smrt-topic', array( 'orderby' => 'count' ) );
		
		header( 'Content-Type: application/javascript', true );
		echo sanitize_text_field( $_GET[ 'topics' ] ) . '(' . json_encode( $topics ) . ')';
		wp_die();
	}
}
$smrt_storyline = new SMRT_Storyline();