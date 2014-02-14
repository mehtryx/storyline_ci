<?php
/*
Plugin Name: Storyline
Plugin URI: http://github.com/Postmedia/storyline
Description: Supports mobile story elements
Author: Postmedia Network Inc.
Version: 0.3.3
Author URI: http://github.com/Postmedia
License: MIT    
*/

/*
Copyright (c) 2014 Postmedia Network Inc.

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
define( 'SMRT_STORYLINE_VERSION', '0.3.3' );

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
	 * @uses add_action
	 * @uses add_filter
	 */
	public function __construct() {
		
		// register hooks and filters
		add_action( 'after_setup_theme', array( $this, 'add_custom_image_sizes' ) );
		add_action( 'init', array( $this, 'register_storyline' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_alerts_meta_box' ) );
		// the intent in the priority '999' is to push this to the very last, this view is specialized to a simulated
		// mobile preview, which needs to account for any and all changes by other plugins, themes.  999 seemed like a good number.
		add_filter( 'the_content', array( $this, 'modified_post_view' ), 999 ); 
		add_filter( 'the_content', array( $this, 'refactor_images' ), 99 ); 
		add_filter( 'json_feed_item',  array( $this ,'json_feed_items_with_slides' ), 10, 4 );
		
		// register ajax handler for topics
		add_action( 'wp_ajax_smrt_topics', array( $this, 'smrt_topics_callback' ) );
		add_action( 'wp_ajax_nopriv_smrt_topics', array( $this, 'smrt_topics_callback' ) );
		
		// register ajax handler for urban airship update
		add_action( 'wp_ajax_smrt_push_ua_update', array ( $this, 'smrt_push_ua_update_callback' ) );
		add_action( 'wp_ajax_smrt_alert_check_update', array ( $this, 'smrt_alert_check_update_callback' ) );
		
		// custom sorting by date and menu_order
		add_action( 'pre_get_posts', array( $this, 'pre_get_storylines_sort' ) );
		
		// allow query of multiple posts by id
		add_filter( 'query_vars', array( $this, 'add_following_query_var' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_following' ) );
		
		// adds order (menu order) to dashboard
		if ( is_admin() ) {
			add_filter( 'manage_storyline_posts_columns', array( $this, 'add_order_column') );
			add_action( 'manage_storyline_posts_custom_column' , array( $this, 'custom_columns' ), 10, 2 );
		}
		
		// Add admin hooks for urban airship settings
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'create_settings_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
	}
	
	/**
	 * Adds the custom image sizes used by storyline
	 *
	 * @since 0.2.9
	 *
	 * @uses add_image_size
	 */
	function add_custom_image_sizes() {
		// register new image sizes
		add_image_size( 'smrt-phone-thumb', 95, 95, true );
		add_image_size( 'smrt-phone-thumb-x2', 190, 190, true );
		add_image_size( 'smrt-phone-feature', 320, 192, true );
		add_image_size( 'smrt-phone-feature-x2', 640, 384, true );
		add_image_size( 'smrt-phone-embedded', 640, 9999, false );
	}
	
	/**
	 * Adds additional meta data to json feed item
	 *
	 * @since 0.2.0 
	 *
	 * @uses get_the_modified_time
	 * @uses get_post_thumbnail_id
	 * @uses wp_get_post_terms
	 * 
	 * @param object @item The json feed item
	 * @return object The updated json feed item
	 */
	public function json_feed_items_with_slides( $item, $id, $query_args, $json_feed ) {
				
		// only update posts of type storyline
		if ( 'storyline' !== $item['type'] )
			return $item;
		
		$item['content'] = $this->split_content( apply_filters( 'the_content', get_the_content() ) );
		$item['last_modified'] = get_the_modified_time( json_feed_date_format() );
		
		$item['updated'] = (bool) get_post_meta( $id, '_smrt_update_alert_sent' , true );
		$item['no_thumbnail'] = (bool) get_post_meta( $id, 'no_thumbnail' , true );
		
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
		
		// include custom sort parameter
		$item['date_sort'] = $this->calc_date_sort( $id, $position )->format( json_feed_date_format() );
		
		// include total number of posts, query vars, and index ad code on first post
		if ( 0 === $position ) {
			$item['post_count'] = $json_feed->found_posts;
			$item['query'] = $json_feed->query;
			$item['main_ad_codes'] = $this->get_main_ad_codes();
		}
		
		// specify ad codes
		$item['ad_codes'] = $this->get_ad_codes( $id );
		
		// specify thumbnail and overwrite featured image url
		$thumbnail_id = get_post_thumbnail_id();
		if ( !empty( $thumbnail_id ) ) {
			$item['thumbnail_url']         = $this->add_image_url( $thumbnail_id, 'smrt-phone-thumb' );
			$item['thumbnail_url_x2']      = $this->add_image_url( $thumbnail_id, 'smrt-phone-thumb-x2' );
			$item['featured_image_url']    = $this->add_image_url( $thumbnail_id, 'smrt-phone-feature' );
			$item['featured_image_url_x2'] = $this->add_image_url( $thumbnail_id, 'smrt-phone-feature-x2' );
			$item['caption'] = esc_html( get_post( $thumbnail_id )->post_excerpt );
		}
		
		// return topics
		$item['topics'] = array();
		$topics = get_the_terms( $id, 'smrt-topic' );
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
	 * calculates a sorted date field for JSON based on pub date and menu_order
	 *
	 * @since 0.3.2
	 *
	 * @uses get_post()
	 */
	function calc_date_sort( $post_id, $position ) {
		$post = get_post( $post_id );
		$date_sort = new DateTime( $post->post_date );
		$date_sort->setTime( 23 , 59 , 59);
		$date_sort->sub( new DateInterval( 'PT'. $position . 'M' ) );
		return $date_sort;
	}
	
	/**
	 * calculates ad codes for a given story
	 *
	 * @since 0.3.3
	 *
	 * @uses get_post()
	 */
	function get_ad_codes( $post_id ) {
		$ad_codes = array(
			'ad' => 'news/story',
			'nk' => 'print',
			'pr' => 'oc',
			'page' => 'story',
			'loc' => 'top'
		);
		
		$cat = get_the_category( $post_id );
		if ( !empty( $cat ) ) {
			$ad_codes['ad'] = get_category_parents( $cat[0]->term_id, false, '/', true ) . 'story';
		}
		
		$slugs = explode( '/', $ad_codes['ad'] );
		$count = count( $slugs );
		
		$ad_codes['ck'] = $slugs[0];
		if ( $count > 2 ) {
			$ad_codes['sck'] = array_slice( $slugs, 1, $count - 2 );
		}
		$ad_codes['page'] = $slugs[$count - 1];

		return $ad_codes;
	}
	
	/**
	 * calculates ad codes for a given feed
	 *
	 * @since 0.3.3
	 */
	function get_main_ad_codes() {
		$ad_codes = array(
			'ad' => 'index',
			'nk' => 'print',
			'pr' => 'oc',
			'ck' => 'index',
			'imp' => 'index',
			'page' => 'index',
			'loc' => 'top'
		);
		
		return $ad_codes;
	}
	
	/**
	 * Returns the specified featured thumbnail size image url
	 *
	 * @since 0.2.9
	 *
	 * @uses wp_get_attachment_image_src
	 *
	 * @param int $thumbnail_id The id of the featured image
	 * @param string $size The image size to look for
	 * @return image url
	 */
	function add_image_url( $thumbnail_id, $size ) {
		$image = wp_get_attachment_image_src( $thumbnail_id, $size );
		return $image[0];
	}
	
	/**
	 * Converts a selection of content into an array of content items
	 *
	 * @since 0.1.1
	 * @param string $content The text to convert
	 * @return string[] An array of slides
	 */
    function split_content( $content ) {
		if ( empty( $content ) ) {
			return array();
		}
		$content = preg_replace( '/<span id=\"more-.*\"><\/span>/uim', "<!--more-->", $content );
		$content = preg_replace( '/<!--more-->\\s*<\/p>/uim', '</p><!--more-->', $content );
		$slides = explode( "<!--more-->", $content );
		
		// clean up leading empty paragraphs, leading end paragraphs, trailing open paragraphs, and spacing
		$slides = preg_replace( "/(^\\s*<p>\\s*&nbsp;\\s*<\/p>\\s*)|(^\\s*<\/p>\\s*)|(^\\s*)|(\\s*<p>\\s*$)|(\\s*$)/ui", "", $slides );
		return $slides;
	}
	
	/**
	 * Registers a new post type of "Storyline" and a new taxonomy of "Topics" (smrt-topic)
	 *
	 * @since 0.1.0
	 *
	 * @uses register_post_type
	 * @uses register_taxonomy
	 */
	public function register_storyline() {
		register_post_type( 'storyline', array(
			'public' => true,
			'label' => 'Storyline',
			'description' => 'Storyline',
			'menu_position' => 5,
			'has_archive' => true,
			'rewrite' => array( 'slug' => 'storyline' ),
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt','post-formats', 'page-attributes', 'custom-fields', 'zoninator_zones' ),
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
				$slides_content .= "<div class='swiper-slide'><div class='story_item_2'>" . $slide . "<div class='statusbar sub'>" . $counter . "/" . ( count( $slides ) ) . "</div></div></div>";
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
	 * @uses get_the_post_thumbnail_id
	 * @uses wp_get_attachment_image_src
	 * @uses esc_url
	 */
	public function modified_post_view( $content ) {
		global $post;
		
		if( $post->post_type !== 'storyline' || !is_preview() )
			return $content;
		
		// This header is hardcoded, there is no variables in it to escape
		$hardcoded_header = "<div class='x-container x-toolbar-dark x-toolbar x-navigation-bar x-stretched x-paint-monitored x-layout-box-item' id='ext-titlebar-1'><div class='x-body' id='ext-element-13'><div class='x-center' id='ext-element-14'><div class='x-unsized x-title x-floating' id='ext-title-1' style='z-index: 8 !important;'><div class='x-innerhtml' id='ext-element-12'></div></div></div><div class='x-inner x-toolbar-inner x-horizontal x-align-stretch x-pack-start x-layout-box' id='ext-element-9'><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-1' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-10' style=''><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='nav_btn' style='width: 50px !important; height: 50px !important; background-image: url('/wp-content/plugins/storyline/img/toolbar/icon-menu.png');'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div></div><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='nav_icon' style='width: 42px !important; height: 48px !important; background-image: url('/wp-content/plugins/storyline/img/logo.png');'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 43px; height: 49px;'></div></div><div class='shrink'><div style='width: 42px; height: 48px;'></div></div></div></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 93px; height: 51px;'></div></div><div class='shrink'><div style='width: 92px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-size-monitored x-paint-monitored x-layout-box-item x-flexed x-stretched' id='ext-component-1' style='position: relative; -webkit-box-flex: 1;'><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 1128.71875px; height: 51px;'></div></div><div class='shrink'><div style='width: 1127.71875px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-2' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-11' style=''><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='bt_alert' style='width: 50px !important; height: 50px !important; background-image: url('/wp-content/plugins/storyline/img/toolbar/icon-alerts-active.png');'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 51px; height: 51px;'></div></div><div class='shrink'><div style='width: 50px; height: 50px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div></div></div><div class='x-paint-monitor overflowchange'></div></div>";
		
		// Hardcoded, no variables to escape
		$hardcoded_adbanner = "<div class='adBanner'></div>";	
		
		wp_register_style( 'storylinestyle', plugins_url( 'css/style.css', __FILE__ ) );
		wp_register_style( 'swipercss', plugins_url( 'css/idangerous.swiper.css', __FILE__ ) );
		wp_register_script( 'storylinescript', plugins_url ( 'js/script.js', __FILE__ ), '', '', TRUE );
		wp_register_script( 'swiperscript', plugins_url( 'js/idangerous.swiper-2.1.min.js', __FILE__ ), '', '', TRUE );
		
		wp_enqueue_style( 'storylinestyle' );
		wp_enqueue_style( 'swipercss' );
		wp_enqueue_script( 'swiperscript' );
		wp_enqueue_script( 'storylinescript' );
		
		$slides = $this->split_content( $content );
		$slide_image = null;
		
		if ( has_post_thumbnail( $post->ID ) ) {
			$slide_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'smrt-phone-feature-x2' );
		}
		
		return "<div class='smart-device-preview'>
					<div class='select-device'> 
						<label for='phoneselector'>Select a device</label><br>
						<select name='phoneselector' id='phoneselector'>
						</select>
					</div>
					<div class='device-wrapper'>
						<div class='viewport-wrapper' >
							" . $hardcoded_header . "
							<div class='swiper-container'>
								<div class='swiper-wrapper'>
									<div class='swiper-slide'>
										<div class='story_item'>
											<div class='flex-container'>" .
												( is_array( $slide_image ) ? "<div class='story_image' style='background-image: url(" . esc_url( $slide_image[0] ) . ");'></div>" : "" ) . "
												<div class='first_story_content'>
													<div class='story_headline'><h3>" . esc_html( $post->post_title )  . "</h3></div>
													<div class='timestamp'><span class='updated'>Updated: </span><span>11:40 AM</span></div>
													<div class='story_abstract'>" .	$slides[0] . "</div>
												</div>
												<div class='adBannerPagination'><div class='statusbar'>1/" . ( count( $slides ) ) . "</div>" . $hardcoded_adbanner .
											"</div></div>
										</div>
									</div>"
									. $this->render_content_slides( $slides ) .
								"</div>
								<div class='pagination'></div>
							<div>
						</div>
					</div>
				</div>";
	}
	
	/**
	 * Replaces embeded images with divs with background image
	 * so that they can be clipped and fit on any mobile screen
	 *
	 * @since 0.2.7
	 */
	public function refactor_images( $content ) {
		global $post;
		
		// only refactor for storylines
		if( $post->post_type !== 'storyline' )
			return $content;
		
		// only refactor for feeds and preview
		if ( !is_feed() && !is_preview() )
			return $content;
		
		$content = preg_replace_callback(
			'/^.*<img[^>]+ wp-image-(\\d+)[^>]+>.*$/um',
			function( $matches ) {
				$html = $matches[0];
				$id = $matches[1];
				
				// get url for embedded image, regardless of size actually embedded in content
				$src =  wp_get_attachment_image_src( $id, 'smrt-phone-embedded' );
				if( $src ) {
					
					// find optional link to indicate gallery image
					preg_match( '/href=\"(.+?)\"/u', $html, $href );
					
					// find optional caption
					preg_match( '/<p class=\"wp-caption-text\">(.+)<\/p>/u', $html, $caption );
					
					// create the new div tag
					return '<div class="'
						. ( $href ? 'story-gallery-image' : 'story-image' )
						. '"' 
						. ' style="background-image: url(\'' . esc_url( $src[0] ) . '\');"'
						. ( $href ? ' data-href="' . esc_url( $href[1] ) . '"' : '' )
						. '>'
						. ( $caption && $href ? '<p class="caption">'. esc_html( $caption[1] ) . '</p>' : '' )
						. '</div>'
						. ( $caption && !$href? '<p class="caption">'. esc_html( $caption[1] ) . '</p>' : '' );
				} else {
					return '<!-- missing image ' . esc_html( $id ) . ' -->';
				}
			},
			$content
		);
		
		return $content;
	}
	
 	/**
 	 * Registers support for a custom query var named following
 	 *
 	 * @since 0.3.3
 	 */
 	public function add_following_query_var( $qvars ) {
 		$qvars[] = 'following';
 		return $qvars;
 	}
	
	/**
	 * support querying by multple post ids
	 *
	 * @since 0.3.3
	 */
	public function pre_get_following( $query ) {
		if ( isset( $query->query['following'] ) ) {
			$following = explode( ',', $query->query['following'] );
			$query->set( 'post__in', $following );
			$query->set( 'ignore_sticky_posts', true);
			$query->set( 'posts_per_page', 100);
		}
	}
	
	/**
	 * automatically implement secondary sort by menu_order
	 *
	 * @since 0.3.2
	 */
	public function pre_get_storylines_sort( $query ) {
		
		// only apply to storylines
		if ( isset( $query->query_vars['post_type'] ) && 'storyline' !== $query->query_vars['post_type'] )
			return; 
		
		// only apply when sorting by date
		if ( isset( $query->query_vars['orderby'] ) && 'date' !== $query->query_vars['orderby'] )
			return;
		
		// register the filter based on direction
		if ( isset( $query->query_vars['order'] ) && 'asc' === $query->query_vars['order'] )
			add_filter( 'posts_orderby', array( $this, 'storyline_order_asc' ) );
		else
			add_filter( 'posts_orderby', array( $this, 'storyline_order_desc' ) );
	}
	
	/**
	 * sort by date (without time) ASC and menu order DESC
	 * note: we need to remove the time via a cast, otherwise
	 *       the menu_order is negligible unless they all have the exact same time
	 *
	 * @since 0.3.2
	 *
	 * @uses remove_filter()
	 */
	public function storyline_order_asc( $orderby ) {
		global $wpdb;
		remove_filter( 'posts_orderby', array( $this, 'storyline_order_asc' ) );
		return "CAST($wpdb->posts.post_date as date) ASC, $wpdb->posts.menu_order = 0 DESC, $wpdb->posts.menu_order DESC";
	}
	
	/**
	 * sort by date (without time) DESC and menu order ASC
	 * note: we need to remove the time via a cast, otherwise
	 *       the menu_order is negligible unless they all have the exact same time
	 *
	 * @since 0.3.2
	 *
	 * @uses remove_filter()
	 */
	public function storyline_order_desc( $orderby ) {
		global $wpdb;
		remove_filter( 'posts_orderby', array( $this, 'storyline_order_desc' ) );
		return "CAST($wpdb->posts.post_date as date) DESC, $wpdb->posts.menu_order = 0 ASC, $wpdb->posts.menu_order ASC";
	}
	
	/**
	 * Adds a new order column on storyline posts dashboard page
	 *
	 * @since 0.3.2
	 */
	public function add_order_column( $columns ) {
		return array_merge( $columns, array( 'menu_order' => __( 'Order' ) ) );
	}
	
	/**
	 * Adds value for order column on storyline posts dashboard page
	 *
	 * @since 0.3.2
	 *
	 * @uses get_post()
	 */
	public function custom_columns( $column, $post_id ) {
		if ( 'menu_order' === $column ) {
			$post = get_post( $post_id );
			echo $post->menu_order;
		}
	}	
	
	/**
	 * AJAX hook to return list of topics as JSON
	 *
	 * @since 0.2.2
	 *
	 * @uses get_terms
	 * @uses wp_die
	 * @uses sanitize_text_field
	 */
	public function smrt_topics_callback() {
	
		if ( false === ( $topics = get_transient( 'smrt_topics_callback_results' ) ) ) {
			$topics = get_terms( 'smrt-topic', array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 6 ) );
			
			// cache for 5 mins
			set_transient( 'smrt_topics_callback_results', $topics, 300 );
		}
		
		if ( isset( $_GET[ 'jsonp' ] ) ) {
			$callback = $_GET[ 'jsonp' ];
		} elseif ( isset( $_GET[ 'topics' ] ) ) {
			$callback = $_GET[ 'topics' ];
		}
		
		if ( empty( $callback ) ) {
			header( 'Content-Type: application/json', true );
			echo json_encode( $topics );
		} else {
			if ( preg_match( '/[^\\.a-zA-Z0-9]/um', $callback ) ) {
				// if $callback contains a non-word character, other than a dot
				// this could be an XSS attack.
				header('HTTP/1.1 400 Bad Request');
			}
			else {
				header( 'Content-Type: application/javascript', true );
				echo esc_attr( $callback ) . '(' . json_encode( $topics ) . ')';
			}
		}
		wp_die();
	}
	
	/*
		-------- Urban Airship support for storyline below this comment ----------
	*/
	
	/**
	 * Create the settings page, contains fields for urban airship application.
	 *
	 * @since 0.2.8
	 *
	 * @uses add_options_page
	 */
	function create_settings_menu() {
		$plugin_page = add_options_page( 'Storyline', 'Storyline', 'manage_options', 'storyline-settings', array( $this, 'settings_page' ) );
	}
	
	/**
	 * Register the Storyline settings, sections and fields
	 *
	 * @since 0.2.8
	 * 
	 * @uses register_setting
	 * @uses add_settings_section
	 * @uses add_settings_field
	 */
	function register_settings() {
		register_setting( 'smrt_storyline_settings', 'smrt_storyline_settings', array( $this, 'sanitize_settings' ) );
		add_settings_section( 'smrt_storyline_main', 'Storyline Plugin Settings', array( $this, 'settings_help' ), 'storyline-settings' );
		add_settings_field( 'smrt_ua_app_id', 'Application ID', array( $this, 'render_app_id_setting'), 'storyline-settings', 'smrt_storyline_main' );
		add_settings_field( 'smrt_ua_master_secret', 'Master Secret', array( $this, 'render_master_secret_setting') , 'storyline-settings', 'smrt_storyline_main' );
	}
	
	/**
	 * Enqueue the admin scripts, passing post ID parameter for update action
	 *
	 * @ since 0.2.8
	 * 
	 * @uses wp_enqueue_script
	 * @uses plugin_url
	 * @uses wp_localize_script
	 */
	function enqueue_admin_scripts() {
		// Edit Page script for storyline
		global $pagenow;
		global $post;
		
		if ( 'post.php' === $pagenow && 'storyline' === $post->post_type ) {
			wp_enqueue_script( 'smrt-storyline-ua-alerts', plugins_url( 'js/alerts.js', __FILE__ ) );
			
			//pass dynamic parameters
			$nonce = wp_create_nonce( 'smrt_storyline_ajax_nonce' );
			$params = array( 'nonce' => $nonce, 'postID' => $post->ID );
			wp_localize_script( 'smrt-storyline-ua-alerts', 'smrt_alerts', $params );
		}
	}
	
	/**
	 * Render the Storyline settings page
	 *
	 * @since 0.2.8
	 *
	 * @uses settings_fields
	 * @uses do_settings_sections
	 */
	function settings_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>Storyline Settings</h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'smrt_storyline_settings' );
				do_settings_sections( 'storyline-settings' );
				?>
				<input name="submit" type="submit" value="Save Changes" />
			</form>
		</div>
		<?php
	}
	
	/**
	 * Render help text for Storyline settings page
	 * 
	 * @since 0.2.8
	 */
	function settings_help() {
		echo '<p>Supply the AppID and Master secret for the Urban Airship application you wish to send update notices too.</p>';
	}
	
	/**
	 * Render application ID settings field
	 *
	 * @since 0.2.8
	 *
	 * @uses esc_attr
	 */
	function render_app_id_setting() {
		$options = get_option( 'smrt_storyline_settings' );
		$app_id = ( isset( $options['app_id'] ) ? $options['app_id'] : '' );
		echo '<input id="smrt_ua_app_id" type="text" class="regular-text" name="smrt_storyline_settings[app_id]" value="' . esc_attr( $app_id ) . '"/>';
	}
	
	/**
	 * Render master secret settings field
	 *
	 * @since 0.2.8
	 *
	 * @uses esc_attr
	 */
	function render_master_secret_setting() {
		$options = get_option( 'smrt_storyline_settings' );
		$master_secret = ( isset( $options['master_secret'] ) ? $options['master_secret'] : '' );
		echo '<input id="smrt_ua_master_secret" type="text" class="regular-text" name="smrt_storyline_settings[master_secret]" value="' . esc_attr( $master_secret ) . '"/>';
	}
	
	/**
	 * Sanitize input on settings form
	 *
	 * @since 0.2.8
	 * 
	 * @uses sanitize_text_field
	 */
	function sanitize_settings( $input ) {
		$valid = array();
		
		if ( isset( $input['app_id'] ) ) {
			$valid['app_id'] = sanitize_text_field( $input['app_id'] );
		}
		
		if ( isset( $input['master_secret'] ) ) {
			$valid['master_secret'] = sanitize_text_field( $input['master_secret'] );
		}
		
		return $valid;
	}
	
	/**
	 * Display the Urban Airship update notification interface in Edit Post screen
	 *
	 * @since 0.2.8
	 *
	 * @uses current_user_can
	 * @uses add_meta_box
	 */
	function add_alerts_meta_box( $post_type ) {
		global $post;
		
		// Exit if not a storyline post, or not published yet
		if ( 'storyline' !== $post_type || 'publish' !== $post->post_status )
			return;
		
		// ensure only those who can publish, can see this box
		if ( current_user_can( 'publish_posts' ) )
			add_meta_box( 'update_alert_meta_box', 'Urban Airship Update Alert', array( $this, 'update_alert_meta_box' ), $post_type, 'side', 'low' );
	}
		
	/**
	 * Generating the update notification meta box
	 *
	 * @since 0.2.8
	 */
	function update_alert_meta_box(){
		global $post;
		$updated = (bool) get_post_meta( $post->ID, '_smrt_update_alert_sent' , true );
		?>
			<p id="update-status"></p>
			<input name="submit_update" type="submit" value="Push Update" onClick="smrt_storyline_alerts_send_update(event);"/>
			<br />
			<input id="check-update" type="checkbox" <?php checked( $updated ) ?> onChange="smrt_storyline_alerts_check_update(event);" /> Updated
		<?php
	}
	
	/**
	 * Ajax function to send push notification to Urban Airship
	 *
	 * @since 0.2.8
	 *
	 * @uses current_user_can
	 * @uses get_option
	 * @uses sanitize_text_field
	 * @uses apply_filters
	 * @uses wp_remote_post
	 * @uses us_wp_error
	 * @uses wp_die
	 */
	public function smrt_push_ua_update_callback() {
		// check nonce
		$nonce = 0;
		$result = '';
		
		if ( isset( $_POST['nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'smrt_storyline_ajax_nonce' ) )
				$nonce = wp_create_nonce( 'smrt_storyline_ajax_nonce' );
		}
		
		if ( current_user_can( 'publish_posts' ) && $nonce ) {
			$postID = intval( urldecode( $_POST['postID'] ) );
			if ( $postID ) {
				$options = get_option( 'smrt_storyline_settings' );
				$auth_combo = sanitize_text_field( $options['app_id'] ) . ':' . sanitize_text_field( $options['master_secret'] );
				
				// build push body as per v3 of Urban Airship push API 
				// http://docs.urbanairship.com/reference/api/v3/push.html#push-object
				$contents = array();
				$contents['alert'] = 'Updated story';
				$notification = array();
				$notification['ios'] = $contents;
				$notification['android'] = $contents;
				$platforms = array( 'ios', 'android' );
				$audience = array();
				$audience['tag'] = strval( $postID );
				
				$push = array( 'audience' => $audience, 'notification' => $notification, 'device_types' => $platforms );
				
				// allow external modification of push data if required ever.
				$push = apply_filters( 'smrt_storyline_update_push', $push );
				
				$json = json_encode ( $push );
				$args = array(
					'headers' => array( 
						'Content-Type' => 'application/json',
						'Accept' => 'application/vnd.urbanairship+json; version=3;',
						'Authorization' => 'Basic ' . base64_encode( $auth_combo ) ),
					'body' => $json
				);
				
				// Send the push
				$response = wp_remote_post( 'https://go.urbanairship.com/api/push', $args );
				
				if( is_wp_error( $response ) ) {
					// typically this is the result of incorrect or missing app_id and master_secret
					$error_message = $response->get_error_message();
				 	$result = 'Error: ' . $error_message;
				} else {
					update_post_meta( $postID, '_smrt_update_alert_sent', true );
					$result = $response['response']['code'];
				}
			}
			else {
				$result = 'No post ID found';
			}
		}
		$response = array( 'nonce' => $nonce, 'result' => $result );
		echo json_encode( $response );
		wp_die();
	}
	function smrt_alert_check_update_callback() {
		// check nonce
		$nonce = 0;
		$result = '';
		
		if ( isset( $_POST['nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'smrt_storyline_ajax_nonce' ) )
				$nonce = wp_create_nonce( 'smrt_storyline_ajax_nonce' );
		}
		
		if ( current_user_can( 'publish_posts' ) && $nonce ) {
			$postID = intval( urldecode( $_POST['postID'] ) );
			if ( $postID ) {
				// current setting
				$updated = (bool) get_post_meta( $postID, '_smrt_update_alert_sent' , true );
				$updated = ! $updated; // invert for the toggle
				
				update_post_meta( $postID, '_smrt_update_alert_sent', $updated );
				$result = ( $updated ) ? 'true' : 'false';
			}
			else {
				$result = 'false - no post ID'; // this will output on console if we failed
			}
		}
		$response = array( 'nonce' => $nonce, 'result' => $result );
		echo json_encode( $response );
		wp_die();
	} 
}
$smrt_storyline = new SMRT_Storyline();