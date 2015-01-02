<?php
/*
Plugin Name: Kim and Todd's photo tools
Plugin URI: http://www.kimandtodd.com
Description: Incorporates the Awkward Group's slider implementation and cycle lite
 * for the home page. 
Version: 0.2
Author: Todd Rowan
Author URI: http://www.kimandtodd.com
License: GPL2
*/

// Our meta key for excerpt cyclers
define("GALLERY_META_KEY", "gallery_meta");

// We add a filter to the gallery shortcode and override it. 
add_filter("post_gallery", "gallery_shortcode_kandt", 10, 2);
// We add JS and CSS to the page. This will need to be enhanced to be smarter about 
// what the client loads into the page. 
add_action('wp_enqueue_scripts', 'setup_kandt_environment' );
// This is our processing step where we analyze post content on publish.
add_action('transition_post_status', 'save_shortcode_on_publish', 10,3);

// Collect the first gallery shortcode so it can be used in an excerpt.
function save_shortcode_on_publish($new_status, $old_status, $post)
{
    if ($new_status == "publish")
    {       
        //Grab our shortcode if it exists
        $rgx = "(\[(\s*?)gallery [^\]]*\])";
        $matches = array();
        $num = preg_match($rgx, $post->post_content, $matches);
        if ($num > 0)
        {
            for ($inx=0;$inx<$num;$inx++)
            {
                // save $matches[$inx] to post meta
                update_post_meta($post->ID, GALLERY_META_KEY, $matches[$inx]); 
            }
        }
        else 
        {
            // No gallery info found, clear any out that might have been saved before.
            delete_post_meta($post->ID, GALLERY_META_KEY);
        }    
    }
}

// Retrieve the code we set in the above function.
function get_gallery_shortcode_for_post($post_id)
{
    // returns an empty string if no shortcode found.
    return get_post_meta($post_id, GALLERY_META_KEY, true);
}

function get_post_gallery_thumb($post_id)
{
    $shortcode = get_gallery_shortcode_for_post($post_id);
    $result = null;
    
    if ($shortcode!="")
    {
        $rgx = '/\d+/';
        $res = preg_match($rgx,$shortcode,$matches);
        if($res)
        {
            $img_arr = wp_get_attachment_image_src($matches[0], 'medium');
            if ($img_arr!==false)
            {
                $result = $img_arr[0];
            }
        }
    }    
    return $result;
}

// Build our custom shortcode to force cycling in an excerpt.
function get_the_cycle_shortcode($id = -1)
{
    if ($id < 0) return '';
    $start = get_gallery_shortcode_for_post($id);
    if (strlen($start) > 0)
    {
        $cycle = ' cycle="true"';
        $start = substr(trim($start), 0, -1);
        $start .= $cycle . ']';
    }
    return $start;
}

// Process the shortcode and select the correct output function.
function gallery_shortcode_kandt($wtf, $attr) {
    // $attr is the list of attributes and values on the shortcode. keys are attribute names, values are values.
    // $wtf is always an empty string. I'm not sure why. 

    // If this is feed content, just get out. 
    if ( is_feed() ) {
        return "\n";
    }

    // Keep track of multiple instances on a single rendering. 
    static $instance = 0;
    $instance++;

    // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
    // I doubt I'll ever use an order by other than that set in the short code. Dump this.
    if ( isset( $attr['orderby'] ) ) {
    	$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
    	if ( !$attr['orderby'] )
            unset( $attr['orderby'] );
    }

    // Maybe change logic here to just read the ones from above that I need directly from the array,
    // then do the extract in the appropriate rendering function. I'll prolly wanna do some logic
    // that hides the images on a mobile setting, so that we don't download them all on page load. 
    $attachments = array();
    if ( !empty($attr['include']) ) {
    	$_attachments = get_posts( array('include' => $attr['include'], 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'orderby' => $attr['orderby']) );
        foreach ( $_attachments as $key => $val ) {
		$attachments[$val->ID] = $_attachments[$key];
	}
    }
    // Need to return more than just an empty string, else the core shortcode
    // will proceed with it's original logic, which is something we don't want. 
    if ( empty($attachments) )
    	return ' '; 
	
    // Here we select an output function. 
    // If is_single() or is_page() then we do the whole gallery.
    // Otherwise we're in an excerpt list and we want to just show thumbs in a cycler. 
    $force_cycle = (isset($attr['cycle']) && strtolower($attr['cycle'])=='true');
    if($force_cycle || !(is_single() || is_page()) )
        return output_cycle($attr, $attachments, $instance);
    else 
        return output_slideshow($attr, $attachments, $instance);
}

// If we are in the cycle, we only want thumbs and attachment titles.
function output_cycle($attr, $attachments, $instance)
{
    // Output for cycle are DIV elements with one child IMG element. First DIV is visible,
    // subsequent children are tagged display:none (or some hidden class).
    
    // I'll need different defaults, especially for the elements. 
    // I can't think of any reason why i'd want anything other than the defaults for most of the other crap.
    // I won't be doing funky ordering, I'll have custom elements, I won't be doing columns, and I'll have custom logic for sizes. 
    
    extract(shortcode_atts(array(
    	'id'         => $post->ID,
    	'itemtag'    => 'div',
	'icontag'    => 'img',
        'captiontag' => 'div',
    	'size'       => 'thumbnail',
        'ids'        => '',
        'include'    => '',
        'orderby'    => '',
        'limit'      => 5 // Let's limit the cycle to five imgs to reduce downloads. 
    ), $attr));    
   
    $i = 0;
    $link = get_permalink() . "#startshow"; // Let them start the slide show automatically
    $output = "<div class=\"cycle_imgs_box\">\n";
    $output .= "<a href=\"$link\">";
    $output .= "<div class=\"cycle_imgs\">";
    foreach ( $attachments as $id => $attachment ) {
        $imgSrc = wp_get_attachment_image_src($id, $size);
	$output .= "<{$itemtag} class='cycle_img_div' " . ($i++!=0?" style=\"display:none\" ":"") . ">";
        $output .= "<img src=\"$imgSrc[0]\" class=\"cycle_img\"></img>";
	$output .= "</{$itemtag}>"; 
        if ( ($limit > 0) && ($i == $limit)) break;
    }
    $output .= "</div>\n";
    $output .= "<img src=\"" . plugin_dir_url(__FILE__) . "images/slides.gif" ."\" class=\"cycle_overlay\"></img>";
    $output .= "</a>\n </div>\n";
    
    return $output;
}

// If we are in full mode, we build the big slideshow. 
function output_slideshow($attr, &$attachments, $instance)
{
    $post = get_post();
    
    extract(shortcode_atts(array(
    	'id'         => $post->ID,
    	'itemtag'    => 'a',
	'icontag'    => 'img',
	'captiontag' => 'div',
    	'size'       => 'full',
        'link'       => 'file',
        'ids'        => '',
        'include'    => '',
        'orderby'    => ''
    ), $attr));
    
    $selector = "gallery-{$instance}"; // This is an element ID, not a classname.

    $gallery_style = $gallery_div = ''; 
    
    $size_class = sanitize_html_class( $size );
    $gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-size-{$size_class}'>";
    $output = "\n\t\t" . $gallery_div; 

    $i = 0;
    $coords_exist = false;
    foreach ( $attachments as $id => $attachment ) {
        $link = wp_get_attachment_image_src($id);
        $linkFull = wp_get_attachment_image_src($id, $size);
        $caption = trim($attachment->post_content);
        $title = trim($attachment->post_title);
        $coords = false;
        // only exists if my gps plug-in is also in the house.
        if (function_exists('get_coords_for_attachment')) {
          $coords = get_coords_for_attachment($id);
        }
        
        if ($coords)
        {
            $latLng = "data-lat=\"". $coords['latitude'] ."\" data-lng=\"". $coords['longitude'] ."\"";
            $coords_exist=true;
        }
        else
        {
            $latLng="";
        }
        
        if ($caption) {
            $caption = htmlspecialchars($caption, ENT_QUOTES | ENT_HTML401, 'UTF-8', false);
        }
        
        if ($title) {
            $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML401, 'UTF-8', false);
        }
        $output .= "\n<{$itemtag} class='gallery-thumbnail' href='" . ($linkFull ? $linkFull[0] : "/wordpress/noimg") ."' title='$caption' data-caption='$caption' data-title='$title' $latLng>";
        $output .= "
                  <{$icontag} class='gallery-content'  alt='$title' title='$title' src='" .
                      ($link ? $link[0] : "/wordpress/noimg")
                  ."'></{$icontag}>";

        $output .= "</{$itemtag}>"; 
    }

    $output .= "
	<br style='clear: both;' />
        </div>\n";
    
    return ($coords_exist?"<div id=\"maplink\" style=\"display:none;\"><img class=\"globe\" src=\"" . plugin_dir_url(__FILE__) . "images/globe.png\">&nbsp;Click here to view these on a map</div>":"").$output;
}

// Register our CSS and JS for inclusion in the finished page.
function setup_kandt_environment()
{
    // We should definitely register here
    wp_register_style('kandtcss', plugins_url('css/kandt.css', __FILE__), array(), "82214");
    wp_register_style('tosruscss', plugins_url('css/jquery.tosrus.css', __FILE__));
    wp_register_script('cycle2', plugins_url('js/jquery.cycle2.min.js', __FILE__), array('jquery'));
    wp_register_script('hammer', plugins_url('js/jquery.hammer.min.js', __FILE__), array('jquery'));
    wp_register_script('flameportviewscale', plugins_url('js/FlameViewportScale.js', __FILE__), array('jquery'));
    wp_register_script('tosrusjs', plugins_url('js/jquery.tosrus.min.js', __FILE__), array('hammer','flameportviewscale'));    
    wp_register_script('kandtjs', plugins_url('js/kandt.js', __FILE__), array('jquery'), false, true);
    
    // TODO: But we might be able to hold off on enqueuing until we're in output function. Try that.
    wp_enqueue_style('tosruscss');
    wp_enqueue_style('kandtcss');
    wp_enqueue_script('cycle2');
    wp_enqueue_script('tosrusjs');
    wp_enqueue_script('kandtjs');
}
?>
