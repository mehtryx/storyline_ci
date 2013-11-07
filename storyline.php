<?php
/*
Plugin Name: Storyline
Plugin URI: http://github.com/Postmedia/storyline
Description: Supports mobile story elements
Author: Postmedia Network Inc.
Version: 0.1.0
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

define( 'SMRT_STORYLINE_VERSION', '0.1.0');

class SMRT_Storyline {


	public function __construct() {

		// register new content type
	    add_action( 'init', array( $this, 'register_storyline' ) );
        add_filter( 'the_content', array( $this, 'modified_post_view' )); 
    }
    
	public function register_storyline() {
		register_post_type( 'storyline', array(
			'public' => true,
			'label' => 'Storylines',
			'description' => 'Storylines',
			'menu_position' => 5,
			'has_archive' => true,
			'rewrite' => array( 'slug' => 'storyline' ),
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt'),
			'taxonomies' => array( 'category')
		) );
	}
    public function startsWith($haystack, $needle)
    {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }
    public function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }
    public function split_content_intoslides($slides)
    {        
        $statusBar = "<div class='x-unsized x-label bottom-pagination x-dock-item x-docked-bottom x-has-width' id='ext-label-1'><div class='x-innerhtml' id='ext-element-102'>1/3</div></div>";
        $slides_content = "";
        $counter = 2;
        foreach($slides as $slide){
            $slide = trim($slide);
            
            if($this->startsWith($slide, "<br>"))
                $slide = substr($slide, 4);
            if($this->endsWith($slide, "<br>"))
                $slide = substr($slide, 0, -4);
            $slides_content .= "<div class='swiper-slide'><div class='story_item'>".$slide."<div class='statusbar sub'>".$counter."/".(count($slides)+1)."</div></div></div>";
            $counter ++;
        }
        return $slides_content;
    }
    public function modified_post_view($content)
    {
        global $post;
        if($post->post_type != 'storyline')
            return $content;
        
        $header = "<div class='x-container x-toolbar-dark x-toolbar x-navigation-bar x-stretched x-paint-monitored x-layout-box-item' id='ext-titlebar-1'><div class='x-body' id='ext-element-13'><div class='x-center' id='ext-element-14'><div class='x-unsized x-title x-floating' id='ext-title-1' style='z-index: 8 !important;'><div class='x-innerhtml' id='ext-element-12'></div></div></div><div class='x-inner x-toolbar-inner x-horizontal x-align-stretch x-pack-start x-layout-box' id='ext-element-9'><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-1' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-10' style=''><div class='x-button x-iconalign-center x-button-plain x-layout-box-item x-stretched' id='nav_btn'><span id='ext-element-15' class='x-badge' style='display: none;'></span><span class='x-button-icon x-shown list' id='ext-element-17'></span><span id='ext-element-16' class='x-button-label' style='display: none;'></span></div><div class='x-img x-img-image x-img-background x-sized x-paint-monitored x-size-monitored x-layout-box-item' id='nav_icon' style='width: 42px !important; height: 48px !important; background-image: url(http://localhost:58879/pheme/resources/icons/android/ldpi.png);'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 43px; height: 49px;'></div></div><div class='shrink'><div style='width: 42px; height: 48px;'></div></div></div></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 95.859375px; height: 49px;'></div></div><div class='shrink'><div style='width: 94.859375px; height: 48px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-size-monitored x-paint-monitored x-layout-box-item x-flexed x-stretched' id='ext-component-1' style='position: relative; -webkit-box-flex: 1;'><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 247px; height: 49px;'></div></div><div class='shrink'><div style='width: 246px; height: 48px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div><div class='x-container x-size-monitored x-paint-monitored x-layout-box-item x-stretched' id='ext-container-2' style='position: relative;'><div class='x-inner x-horizontal x-align-center x-pack-start x-layout-box' id='ext-element-11'><div class='x-button x-iconalign-center x-button-plain x-layout-box-item x-stretched' id='bt_alert'><span class='x-badge' style='display: none;'></span><span class='x-button-icon x-shown info' id='ext-element-18'></span><span class='x-button-label' style='display: none;'></span></div></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 53.859375px; height: 49px;'></div></div><div class='shrink'><div style='width: 52.859375px; height: 48px;'></div></div></div><div class='x-paint-monitor overflowchange'></div></div></div></div><div class='x-paint-monitor overflowchange'></div></div>";
        $adBanner = "<div class='x-img x-img-image x-sized x-paint-monitored x-size-monitored bottom_ad x-img-background x-dock-item x-docked-bottom' id='ext-image-2' style='width: 100% !important; height: 50px !important; background-image: url(http://localhost:58879/pheme/resources/images/bottom-ad.gif);'><div class='x-paint-monitor overflowchange'></div><div class='x-size-monitors overflowchanged'><div class='expand'><div style='width: 402px; height: 51px;'></div></div><div class='shrink'><div style='width: 401px; height: 50px;'></div></div></div></div>";
       
        wp_register_style( 'storylinestyle', plugins_url('css/style.css', __FILE__) );
        wp_register_style( 'swipercss', plugins_url('css/idangerous.swiper.css', __FILE__) );
        wp_register_script( 'storylinescript', plugins_url('js/script.js', __FILE__), '', '', TRUE );
        wp_register_script( 'swiperscript', plugins_url('js/idangerous.swiper-2.1.min.js', __FILE__), '', '', TRUE );
   
        wp_enqueue_style( 'storylinestyle' );
        wp_enqueue_style( 'swipercss' );
        wp_enqueue_script( 'swiperscript' );
        wp_enqueue_script( 'storylinescript' );
        
        $slides = explode("<!--more-->", str_replace("<span id=\"more-4\"></span>", "<!--more-->", $content));
        return "<div class='smart-device-preview'>
                    <div class='select-device'> 
                        <label for='phoneselector'>Select a device</label><br>
                        <select name='phoneselector' id='phoneselector'>                            
                        </select>
                    </div>
                    <div class='device-wrapper'>
                        <div class='viewport-wrapper' >
                            ".$header."
                            <div class='swiper-container'>                                
                                <div class='swiper-wrapper'>
                                    <div class='swiper-slide'>
                                        <div class='story_item'>".
                                            (has_post_thumbnail($post->ID) ? "<div class='story_image'>".get_the_post_thumbnail($post->ID, array(315, 175))."</div>" : "")."
                                            <div class='first_story_content'><div class='story_headline'><h3>". $post->post_title. "</h3></div>
                                            <div class='timestamp'><span class='updated'>Updated: </span><span>11:40 AM</span></div>
                                            <div class='story_abstract'>". $post->post_excerpt ."</div></div>
                                        </div><div class='statusbar'>1/".(count($slides) + 1)."</div>".$adBanner.
                                    "</div>"
                                    .$this->split_content_intoslides($slides).
                                "</div>
                                <div class='pagination'></div>
                            <div>
                        </div>
                    </div>
                </div>";                                
        
    }
}
$smrt_storyline = new SMRT_Storyline();