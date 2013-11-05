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
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'post-formats' ),
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
        $slides_content = "";
        foreach($slides as $slide){
            $slide = trim($slide);
            if($this->startsWith($slide, "<br>"))
                $slide = substr($slide, 4);     
                           
            if($this->endsWith($slide, "<br>"))
                $slide = substr($slide, 0, -4);
            $slides_content .= "<div class='swiper-slide'>".$slide."</div>";
        }
        return $slides_content;
    }
    public function modified_post_view($content)
    {
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
                    <div class='content-slides'>
                        <div class='swiper-container'>
                            <div class='swiper-wrapper'>".$this->split_content_intoslides($slides)."</div>
                            <div class='pagination'></div>
                        </div>
                    </div>
                </div>";
    }
    

}
$smrt_storyline = new SMRT_Storyline();