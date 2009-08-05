<?php

/*  Copyright 2009  Carlos Pena  (email : carlos@creamscoop.com)

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
Version: 1.0.3
Author: Carlos Pena
Author URI: http://creamscoop.com/about/
*/

// When activiting, create options with no value
register_activation_hook( __FILE__, 'f2t_install');
function f2t_install() {
	
	add_option("f2t_tuser", "");
	add_option("f2t_tpass", "");
	add_option("f2t_message", "%title% %shorturl%");
	add_option("f2t_urlshort", "tinyurl.com");
	add_option("f2t_tweetposts", "yes");
	add_option("f2t_tweetpages", "yes");
	// Advanced Options
	add_option("f2t_trimuser", ""); // tr.im username
	add_option("f2t_trimpass", ""); // tr.im password
	add_option("f2t_bitlylogin", ""); // bit.ly login/username
	add_option("f2t_bitlykey", ""); // bit.ly API Key
	add_option("f2t_gaactive", "no"); // Is GA Tracking active?
	add_option("f2t_gasource", "Twitter"); // GA Campaign Source
	add_option("f2t_gamedium", "link"); // GA Campaign Medium
	add_option("f2t_ganame", "Feed2tweet Auto-Tweet"); // GA Campaign Name
	
}

// Intercept $_POST to update Tweet2feed settings
add_action("init", "f2t_update", 999);
function f2t_update() {
	
	if ( isset($_POST['f2t_update']) && @$_POST['f2t_update'] == "y" ) {
		
		if ( $_POST['f2t_tuser'] != "" || $_POST['f2t_tpass'] != "" ) {
		  
		  // What to Tweet? Checkboxes
		  if($_POST['f2t_tweetposts'] == '') {
		    $_POST['f2t_tweetposts'] = 'no';
		  }
		  if( $_POST['f2t_tweetpages'] == '' ) {
		    $_POST['f2t_tweetpages'] = 'no';
		  }
		  
		  // GA Checkbox
		  if( $_POST['f2t_gaactive'] == '' ) {
		    $_POST['f2t_gaactive'] = 'no';
		  }
		  
			update_option("f2t_tuser", $_POST['f2t_tuser']);
			update_option("f2t_tpass", $_POST['f2t_tpass']);
			update_option("f2t_message", $_POST['f2t_message']);
			update_option("f2t_urlshort", $_POST['f2t_urlshort']);
			update_option("f2t_tweetposts", $_POST['f2t_tweetposts']);
			update_option("f2t_tweetpages", $_POST['f2t_tweetpages']);
			// Advanced Options
    	update_option("f2t_trimuser", $_POST['f2t_trimuser']);
    	update_option("f2t_trimpass", $_POST['f2t_trimpass']);
    	update_option("f2t_bitlylogin", $_POST['f2t_bitlylogin']);
    	update_option("f2t_bitlykey", $_POST['f2t_bitlykey']);
    	update_option("f2t_gaactive", $_POST['f2t_gaactive']);
    	update_option("f2t_gasource", $_POST['f2t_gasource']);
    	update_option("f2t_gamedium", $_POST['f2t_gamedium']);
    	update_option("f2t_ganame", $_POST['f2t_ganame']);
		
			header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=feed2tweet/feed2tweet.php&updated=true');
		} else {
			header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=feed2tweet/feed2tweet.php');
		}
		
	}
	
}

// After a Post is published, post it to twitter
add_action('transition_post_status', 'f2t_post', 1, 3);
function f2t_post($new_status = NULL, $old_status = NULL, $post = NULL) {
  
  // Check post_type and if it's supposed to be Tweeted
  $tweet_allowed = false;
  switch ( $post->post_type ) {
    case 'post':
      if ( get_option("f2t_tweetposts") == 'yes' ) {
        $tweet_allowed = true;
      }
    end;
    case 'page':
      if ( get_option("f2t_tweetpages") == 'yes' ) {
        $tweet_allowed = true;
      }
    end;
  }
	
	if ( $new_status == "publish" && $old_status != "publish" ) { // If post was published
	if ( get_option('f2t_tuser') != '' && get_option('f2t_tpass') != '' ) { // If tuser AND tpass aren't empty
	if ( $tweet_allowed ) {
	
		
		$post_permalink = get_permalink($post->ID);
		//$post_permalink = $post->guid;
		$post_title = $post->post_title;
		
		// GA Tracking activated?
		if ( get_option("f2t_gaactive") == 'yes' ) {
		  if ( get_option('permalink_structure') == '' ) { // Are they using custom permalinks or default?
		    $ga_sep = '&';
		  } else {
		    $ga_sep = '?';
		  }
		  $ga_str = 'utm_source='.urlencode(get_option('f2t_gasource'));
		  $ga_str .= '&utm_medium='.urlencode(get_option('f2t_gamedium'));
		  $ga_str .= '&utm_campaign='.urlencode(get_option('f2t_ganame')).'';
		  
		  $post_permalink = $post_permalink.$ga_sep.$ga_str;
		}

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
		    $api_key = 'R_f57af1bd0f2bc6107476debaa71f35a1'; // Default API Key
		    $login = 'cfpg'; // Default login
		    $history = '';
		    
		    if ( get_option("f2t_bitlylogin") != '' && get_option('f2t_bitlykey') != '' ) {
		      $api_key = get_option("f2t_bitlykey");
		      $login = get_option("f2t_bitlylogin");
		      $history = '&history=1';
		    }
		    
		    $post_permalink = urlencode($post_permalink);
        $shorturl = @file_get_contents("http://api.bit.ly/shorten?version=2.0.1&login=$login&apiKey=$api_key&longUrl=$post_permalink".$history);
        if ( strpos($shorturl, '"statusCode": "OK"') == false ) { $error = true; } else {
          preg_match('/"shortUrl": "(.*)"/', $shorturl, $match);
          $shorturl = $match[1];
        }
		    break;
		  case 'tr.im':
		    if ( get_option("f2t_trimuser") != '' && get_option('f2t_trimpass') != '' ) {
		      $append = '&username='.get_option("f2t_trimuser").'&password='.get_option('f2t_trimpass');
		    }
		    $shorturl = @file_get_contents("http://api.tr.im/api/trim_simple?url=".$post_permalink.$append);
		    if ( $shorturl == '' ) { $error = true; }
		    break;
		case 'digg.com':
			
			$ctx = array(
	  		  'http'=>array(
	  		    'method'=>"GET",
'header'=>"GET /url/short/create?url=".urlencode($post_permalink)."&appkey=http%3A%2F%2Ffeed2tweet.com%2F HTTP/1.1\r\n
Accept: text/xml\r\n
Content-Type: text/xml;charset=UTF-8\r\n
User-Agent:Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_6; en-us) AppleWebKit/528.16 (KHTML, like Gecko) Version/4.0 Safari/528.16\r\n"
	  		  )
	  		);
			$context = stream_context_create($ctx);
			ini_set('user_agent', 'My-Application/2.5');
			$shorturl = @file_get_contents("http://services.digg.com/url/short/create?url=".urlencode($post_permalink)."&appkey=http%3A%2F%2Ffeed2tweet.com%2F");
			$shorturl = preg_match('/short_url="(.*)" view_count="/', $shorturl, $match);
			$shorturl = $match[1];
			if ( $shorturl == '' ) { $error = true; }
			
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
	
	} // Close if $tweet_allowed
	} // Close if tuser AND tpass aren't empty
	} // Close if post wasn't published
	
}

// Add menu option under WP Admin -> Settings
add_action('admin_menu', 'feed2tweet_menu');
function feed2tweet_menu() {
  add_options_page('Feed2tweet Options', 'Feed2tweet', 8, __FILE__, 'feed2tweet_options');
}
function feed2tweet_options() {
  
  $urlshort = array('tinyurl.com', 'is.gd', 'bit.ly', 'tr.im', 'digg.com');
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
  
  // What to Tweet? Checkboxes
  if ( get_option("f2t_tweetposts") == 'yes' ) {
    $posts_checked = ' checked="checked" ';
  } elseif ( get_option("f2t_tweetposts") == '' ) {
    update_option("f2t_tweetposts", "yes");
    $posts_checked = ' checked="checked" ';
  } else {
    $posts_checked = '';
  }
  if ( get_option("f2t_tweetpages") == 'yes' ) {
    $pages_checked = ' checked="checked" ';
  } elseif ( get_option("f2t_tweetpages") == '' ) {
    update_option("f2t_tweetpages", "yes");
    $pages_checked = ' checked="checked" ';
  } else {
    $pages_checked = '';
  }
  
  // Google Analytics checkbox
  if ( get_option("f2t_gaactive") == 'yes' ) {
    $f2t_gaactive_checked = ' checked="checked" ';
  } elseif ( get_option("f2t_gaactive") == '' ) {
    update_option("f2t_gaactive", "no");
    $f2t_gaactive_checked = '';
  } else {
    $f2t_gaactive_checked = '';
  }
  
	wp_nonce_field('update-options');
	echo '
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js" type="text/javascript" charset="utf-8"></script>
	<script type="text/javascript" charset="utf-8">
	 $(document).ready(function() {
       $("#f2t_advtable").hide();
       $("#f2t_advopen").click(function() {
         if ( $("#f2t_advopen").html() == "Open" ) {
          $("#f2t_advtable").show("500");
          $("#f2t_advopen").html("Close");
         } else {
           $("#f2t_advtable").hide("500");
           $("#f2t_advopen").html("Open");
         }
         return false;
       })
   });
	</script>
	';
	
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
							<input type="text" name="f2t_tuser" id="f2t_tuser" value="'.get_option('f2t_tuser').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_tpass">Twitter Password</label>
						</th>
						<td>
							<input type="password" name="f2t_tpass" id="f2t_tpass" value="'.get_option('f2t_tpass').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_message">Format your Message</label>
							<p>Available variables are <i>%title%</i> and <i>%shorturl%</i>.</p>
						</th>
						<td>
							<input type="text" name="f2t_message" id="f2t_message" value="'.get_option('f2t_message').'" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="f2t_tweetonly">What to Tweet?</label>
						</th>
						<td>
							<label for="f2t_tweetposts"><input type="checkbox" name="f2t_tweetposts" id="f2t_tweetposts" value="yes"'.$posts_checked.' /> Posts</label>
					    &nbsp;&nbsp;
							<label for="f2t_tweetpages"><input type="checkbox" name="f2t_tweetpages" id="f2t_tweetpages" value="yes"'.$pages_checked.' /> Pages</label>
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
				<h3>Advanced Options <a href="#" id="f2t_advopen" style="font-size: 80%;">Open</a></h3>
				<div id="f2t_advtable">
				  <table class="form-table">
  				  <tr valign="top">
  				    <th>tr.im Account</th>
  				    <td>
  				      Use your tr.im account to keep track of your links and statistics. <a href="http://tr.im/signup" target="_blank">More info & Signup</a>.
  				    </td>
  				  </tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_trimuser">tr.im Username</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_trimuser" id="f2t_trimuser" value="'.get_option('f2t_trimuser').'" size="40" />
  						</td>
  					</tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_trimpass">tr.im Password</label>
  						</th>
  						<td>
  							<input type="password" name="f2t_trimpass" id="f2t_trimpass" value="'.get_option('f2t_trimpass').'" size="40" />
  						</td>
  					</tr>
  				</table>
  				
  				<p>&nbsp;</p>
  				
  				<table class="form-table">
  					<tr valign="top">
  				    <th>bit.ly Account</th>
  				    <td>
  				      Use your bit.ly account to keep track of your links and statistics. You need to <a href="http://bit.ly/" target="_blank">register for an account</a> and then go to <a href="http://bit.ly/account/" target="_blank">bit.ly Account Settings</a> to get an API Key, this is different from your password.
  				    </td>
  				  </tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_bitlylogin">bit.ly Username</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_bitlylogin" id="f2t_bitlylogin" value="'.get_option('f2t_bitlylogin').'" size="40" />
  						</td>
  					</tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_bitlykey">bit.ly API Key</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_bitlykey" id="f2t_bitlykey" value="'.get_option('f2t_bitlykey').'" size="40" />
  						</td>
  					</tr>
  				</table>
  				
  				<p>&nbsp;</p>
  				
  				<table class="form-table">
  					<tr valign="top">
  				    <th>
  				      <label for="f2t_gaactive">Track links using Google Analytics?</label>
  				    </th>
  				    <td>
  				      <label for="f2t_gaactive"><input type="checkbox" name="f2t_gaactive" id="f2t_gaactive" value="yes"'.$f2t_gaactive_checked.' /> Yes</label> - You can use Google Analytics to track visitors coming from your twitter posts. This will append a couple of URL variables to your permalinks before being shortened. <a href="http://www.google.com/support/googleanalytics/bin/answer.py?hl=en&answer=55518" target="_blank">More info</a>.
  				    </td>
  				  </tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_gasource">Campaign Source</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_gasource" id="f2t_gasource" value="'.get_option('f2t_gasource').'" size="40" />
  						</td>
  					</tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_gamedium">Campaign Medium</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_gamedium" id="f2t_gamedium" value="'.get_option('f2t_gamedium').'" size="40" />
  						</td>
  					</tr>
  					<tr valign="top">
  						<th scope="row">
  							<label for="f2t_ganame">Campaign Name</label>
  						</th>
  						<td>
  							<input type="text" name="f2t_ganame" id="f2t_ganame" value="'.get_option('f2t_ganame').'" size="40" />
  						</td>
  					</tr>
  				</table>
				</div>
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
	delete_option("f2t_tweetposts");
	delete_option("f2t_tweetpages");
	delete_option("f2t_trimuser");
	delete_option("f2t_trimpass");
	delete_option("f2t_bitlylogin");
	delete_option("f2t_bitlykey");
	delete_option("f2t_gaactive");
	delete_option("f2t_gaactive");
	delete_option("f2t_gasource");
	delete_option("f2t_gamedium");
	delete_option("f2t_ganame");
	
}

?>
