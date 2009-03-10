<?php

/*  Copyright 2009  Carlos Pena  (email : contact@creamscoop.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
Plugin Name: Feed2tweet
Plugin URI: http://feed2tweet.com/
Description: Tweets your published posts.
Version: 0.2
Author: Carlos Pena
Author URI: http://creamscoop.com/about/
Compatible: WordPress 2.0+
*/

// When activiting, create options with no value
register_activation_hook( __FILE__, 'f2t_install');
function f2t_install() {
	
	add_option("f2t_tuser", "");
	add_option("f2t_tpass", "");
	add_option("f2t_message", "%title% %shorturl%");
	add_option("f2t_urlshort", "tinyurl.com");
	
}

// Intercept $_POST to update Tweet2feed settings
add_action("init", "f2t_update", 999);
function f2t_update() {
	
	if ( isset($_POST['f2t_update']) && @$_POST['f2t_update'] == "y" ) {
		
		if ( $_POST['f2t_tuser'] != "" || $_POST['f2t_tpass'] != "" ) {
			update_option("f2t_tuser", $_POST['f2t_tuser']);
			update_option("f2t_tpass", $_POST['f2t_tpass']);
			update_option("f2t_message", $_POST['f2t_message']);
			update_option("f2t_urlshort", $_POST['f2t_urlshort']);
			header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=feed2tweet.php&updated=true' );
		} else {
			header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=feed2tweet.php');
		}
		
	}
	
}

// After a Post is published, post it to twitter
add_action('transition_post_status', 'f2t_post', 1, 3);
function f2t_post($new_status = NULL, $old_status = NULL, $post = NULL) {
	
	if ( $new_status == "publish" ) { // If post was published
	if ( get_option('f2t_tuser') != '' && get_option('f2t_tpass') != '' ) { // If tuser AND tpass aren't empty
		
		$post_permalink = get_permalink($post->ID);
		//$post_permalink = $post->guid;
		$post_title = $post->post_title;

		// URL Shortening
		$urlshort = get_option("f2t_urlshort");
		$urlerror = false;
		
		switch ( $urlshort ) {
		  case 'tinyurl.com':
		    // TinURL the permalink to include in Twitter message
    		$tinyurl_opts = array(
    		  'http'=>array(
    		    'method'=>"GET",
    		    'header'=>"Accept-language: en\r\n"
    		  )
    		);
    		$tinyurl_context = stream_context_create($tinyurl_opts);
    		$shorturl = @file_get_contents("http://tinyurl.com/api-create.php?url=$post_permalink", false, $tinyurl_context);
		    break;
		  case 'is.gd':
		    $post_permalink = urlencode($post_permalink);
    		$shorturl = @file_get_contents("http://is.gd/api.php?longurl=$post_permalink");
    		if ( strpos($shorturl, 'Error:') == false ) { $urlerror = true; } // If is.gd returned an error
		    break;
		  case 'bit.ly':
		    $post_permalink = urlencode($post_permalink);
        $shorturl = @file_get_contents("http://api.bit.ly/shorten?version=2.0.1&login=cfpg&apiKey=R_f57af1bd0f2bc6107476debaa71f35a1&longUrl=$post_permalink");
        if ( strpos($shorturl, '"statusCode": "OK"') == false ) { $error = true; } else {
          preg_match('/"shortUrl": "(.*)"/', $shorturl, $match);
          $shorturl = $match[1];
        }
		    break;
		  default:
		    // TinURL the permalink to include in Twitter message
    		$tinyurl_opts = array(
    		  'http'=>array(
    		    'method'=>"GET",
    		    'header'=>"Accept-language: en\r\n"
    		  )
    		);
    		$tinyurl_context = stream_context_create($tinyurl_opts);
    		$shorturl = @file_get_contents("http://tinyurl.com/api-create.php?url=$post_permalink", false, $tinyurl_context);
		}
		
		// If any of the providers above returned an error, default to TinyURL
		if ( $error ) {
		  // TinURL the permalink to include in Twitter message
  		$tinyurl_opts = array(
  		  'http'=>array(
  		    'method'=>"GET",
  		    'header'=>"Accept-language: en\r\n"
  		  )
  		);
  		$tinyurl_context = stream_context_create($tinyurl_opts);
  		$shorturl = @file_get_contents("http://tinyurl.com/api-create.php?url=$post_permalink", false, $tinyurl_context);
		}

		// Start with twitter API
		$f2t_message = get_option('f2t_message'); // get message format from WP
		if ( $f2t_message == "" ) { // If $f2t_message is empty
			$f2t_message = "%title% %shorturl%"; // Use default value
			update_option("f2t_message", $f2t_message); // Save new/default value
		}
		
		$twitter_message_vars2 = array( '%title%', '%shorturl%' );
		$twitter_message_replace2 = array( '', $shorturl );
		$twitter_mslen = strlen(str_replace($twitter_message_vars2, $twitter_message_replace2, $f2t_message));
		$twitter_mslen = ( 140 - $twitter_mslen );
		
		$post_title = chunk_split($post_title, $twitter_mslen, '%$&&$%'); // Split Post  in length
		$post_title = explode("%$&&$%", $post_title); // Explode post_title by separator
		$post_title = trim($post_title[0]); // Post title wrapped
		
		$twitter_message_vars = array( '%title%', '%shorturl%' ); // Message format variables to replace
		$twitter_message_replace = array( $post_title, $shorturl ); // Message format variables value to replace
		$twitter_message = str_replace($twitter_message_vars, $twitter_message_replace, $f2t_message); // Replace format variables with final text
		
		$twitter_auth = get_option('f2t_tuser').':'.get_option('f2t_tpass');
		$twitter_data = array ('status' => $twitter_message);
		$twitter_data = http_build_query($twitter_data);
		$twitter_url = 'http://'.$twitter_auth.'@twitter.com/statuses/update.xml?'.$twitter_data;
		$twitter_opts = array(
		  'http'=>array(
		    'method' => "POST",
		    'header' => "Host: twitter.com\r\n"
			. "Accept-language: en-us,en;q=0.5\r\n"
		    . "Content-Length: " . strlen($twitter_data) . "\r\n",
			'content' => $twitter_data
		  )
		);
		$twitter_context = stream_context_create($twitter_opts);
		$twitter = @file_get_contents($twitter_url, false, $twitter_context);
		
	} // Close if tuser AND tpass aren't empty
	} // Close if post wasn't published
	
}

// Add menu option under WP Admin -> Settings
add_action('admin_menu', 'feed2tweet_menu');
function feed2tweet_menu() {
  add_options_page('Feed2tweet Options', 'Feed2tweet', 8, __FILE__, 'feed2tweet_options');
}
function feed2tweet_options() {
  
  $urlshort = array('tinyurl.com', 'is.gd', 'bit.ly');
  $urlshort_inputs = '';
  $f2t_urlshort = get_option('f2t_urlshort');
  $i = 1;
  
  foreach ( $urlshort as $url ) {
    if ( $url == $f2t_urlshort  ) {
      $urlshort_inputs .= '<label for="url'.$i.'"><input type="radio" name="f2t_urlshort" id="url'.$i.'" value="'.$url.'" checked="checked" /> '.$url.'</label><br />';
    } else {
      $urlshort_inputs .= '<label for="url'.$i.'"><input type="radio" name="f2t_urlshort" id="url'.$i.'" value="'.$url.'" /> '.$url.'</label><br />';
    }
    $i++;
  }
  
	wp_nonce_field('update-options');
  	echo '
		<div class="wrap">
			<h2>Feed2tweet</h2>
			<form id="ak_feed2tweet" name="ak_sharethis" action="'.get_bloginfo('wpurl').'/wp-admin/index.php" method="post">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="f2t_tuser">Twitter Username</label>
						</th>
						<td>
							<input type="text" name="f2t_tuser" value="'.get_option('f2t_tuser').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_tpass">Twitter Password</label>
						</th>
						<td>
							<input type="text" name="f2t_tpass" value="'.get_option('f2t_tpass').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_tpass">Format your Message</label>
							<p>Available variables are <i>%title%</i> and <i>%shorturl%</i>.</p>
						</th>
						<td>
							<input type="text" name="f2t_message" value="'.get_option('f2t_message').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_urlshort">URL Shortening Sevice:</label>
						</th>
						<td>
							'.$urlshort_inputs.'
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="hidden" name="f2t_update" value="y" />
					<input type="submit" name="Submit" value="Save Changes" />
				</p>
			</form>
			<p><i>Information: Your username and password are stored in your WordPress database unencrypted due to Twitter needing them unencrypted each time you make a post. Blame Twitter API for this.</i></p>
		</div>
	';
}

// When deactivating, delete options from database
register_deactivation_hook(__FILE__, "f2t_uninstall");
function f2t_uninstall() {
	
	delete_option("f2t_tuser");
	delete_option("f2t_tpass");
	delete_option("f2t_message");
	delete_option("f2t_urlshort");
	
}

?>