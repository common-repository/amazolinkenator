<?php
/*
Plugin Name: AmazoLinkenator
Plugin URI: http://cellarweb.com/wordpress-plugins/
Description: Automatically adds your Amazon Affiliate code to any Amazon link in posts/pages/comments. Optionally shortens those URLs. Adds the affiliate code and shortens on content save, even in visitor comments. Shows count of affiliate codes inserted.
Text Domain: AZLNK
Author: Rick Hellewell / CellarWeb.com
Version: 4.21
Tested up to: 6.2 
Requires PHP: 5.6
Author URI: http://CellarWeb.com
License: GPLv2 or later
 */

/*
Copyright (c) 2015-2022 by Rick Hellewell and CellarWeb.com
All Rights Reserved

email: rhellewell@gmail.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA02110-1301USA

 */
// ----------------------------------------------------------------
define("AMAZO_VERSION", "Version  4.21 (26 Mar 2022)");

function AZLNK_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=AZLNK_settings" title="AmazoLinkenator">AmazoLinkenator Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}

$plugin = plugin_basename(__FILE__);

add_filter("plugin_action_links_$plugin", 'AZLNK_settings_link');

// 24 Jan 2022 - not working properly start
// set the donate flag tru on activation or update
register_activation_hook(__FILE__, 'AZLNK_plugin_activate'); // initial activation
add_action('upgrader_process_complete', 'AZLNK_plugin_activate', 10, 2); // plugin updated

// ----------------------------------------------------------------
function AZLNK_plugin_activate() {
	// plugin activation code here...
	if (!get_option('AZLNK_options')) {
		$donate_force = array('AZLNK_donate_flag' => true, 'AZLNK_affiliate_key' => 'azlinkplugin-20');
		add_option("AZLNK_options", $donate_force);
	}
}

// ----------------------------------------------------------------
//  build the class for all of this
class AZLNK_Settings_Page {
	// Holds the values to be used in the fields callbacks
	private $options;
	// start your engines!
	public function __construct() {
		add_action('admin_menu', array($this, 'AZLNK_add_plugin_page'));
		add_action('admin_init', array($this, 'AZLNK_page_init'));
	}

	// add options page
	public function AZLNK_add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			'AmazoLinkenator Settings Admin',
			'AmazoLinkenator Settings',
			'manage_options',
			'AZLNK_settings',
			array($this, 'AZLNK_create_admin_page')
		);
	}

	// options page callback
	public function AZLNK_create_admin_page() {
		// Set class property
		$this->options = get_option('AZLNK_options');
		// sanity check on Settings; displays an admin warning notice if there are problems.
		AZLNK_sanity_check_shorten($this->options) ;
		?>
<div >
    <div class="AZLNK_options">
        <?php AZLNK_info_top();?>
    <form method="post" action="options.php">
        <?php
// This prints out all hidden setting fields
		settings_fields('AZLNK_option_group');
		do_settings_sections('AZLNK_setting_admin');
		submit_button();
		?>
    </form>
<?php
$AZLNK_bitly_token = (isset($AZLNK_options['AZLNK_bitly_token'])) ? $AZLNK_options['AZLNK_bitly_token'] : false;
		AZLNK_show_test_button($AZLNK_bitly_token);
		AZLNK_footer(); // display bottom info stuff
		?>
</div>
    </div>
    <div class='AZLNK_sidebar'>
        <?php AZLNK_sidebar();?>
    </div>

<?php
}

	// Register and add the settings
	public function AZLNK_page_init() {
		// get the CSS for the settings page
		wp_register_style('AZLNK_namespace', plugins_url('/css/settings.css', __FILE__), array(), AMAZO_VERSION);
		wp_enqueue_style('AZLNK_namespace'); // gets the above css file in the proper spot

		register_setting(
			'AZLNK_option_group', // Option group
			'AZLNK_options', // Option name
			array($this, 'AZLNK_sanitize') // Sanitize
		);

		add_settings_section(
			'AZLNK_setting_section_id', // ID
			'', // Title
			array($this, 'AZLNK_print_section_info'), // Callback
			'AZLNK_setting_admin' // Page
		);

		add_settings_field(
			'AZLNK_affiliate_key',
			'Your Amazon Affiliate Key',
			array($this, 'AZLNK_affiliate_key_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'input', 'fieldsize' => '50', 'fieldmax' => '50')
		);

		add_settings_field(
			'AZLNK_enable_affiliator_posts',
			'Enable AmazoLinkenator for post/page content?',
			array($this, 'AZLNK_enable_affiliator_posts_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'checkbox', 'fieldsize' => null, 'fieldmax' => null)
		);

		add_settings_field(
			'AZLNK_AZLNK_enable_comments',
			'Enable AmazoLinkenator for Comments?',
			array($this, 'AZLNK_enable_comments_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'checkbox', 'fieldsize' => null, 'fieldmax' => null)
		);

		add_settings_field(
			'AZLNK_auto_shorten',
			'Enable automatic shortening of the URL? ',
			array($this, 'AZLNK_auto_shorten_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'text', 'fieldsize' => null, 'fieldmax' => null)
		);

		add_settings_field(
			'AZLNK_bitly_token',
			'Enter your Bit.ly Generic Access Token',
			array($this, 'AZLNK_bitly_token_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'input', 'fieldsize' => '50', 'fieldmax' => '50')
		);

		add_settings_field(
			'AZLNK_donate_flag',
			'Check to donate',
			array($this, 'AZLNK_donate_flag_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'input', 'fieldsize' => '50', 'fieldmax' => '50')
		);

		add_settings_field(
			'AZLNK_donate_counter',
			'Affiliate Donated Counter',
			array($this, 'AZLNK_donate_counter_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'input', 'fieldsize' => '50', 'fieldmax' => '50')
		);

		add_settings_field(
			'AZLNK_affiliate_counter',
			'Affiliate Links Inserted',
			array($this, 'AZLNK_affiliate_counter_callback'),
			'AZLNK_setting_admin',
			'AZLNK_setting_section_id', // Section
			array('fieldtype' => 'input', 'fieldsize' => '25', 'fieldmax' => '25')
		);
	}

	// sanitize the settings fields on submit
	//  @param array $input Contains all settings fields as array keys
	public function AZLNK_sanitize($input) {
		global $AZLNK_affiliate_counter;
		global $AZLNK_donate_counter;
		global $AZLNK_options;
		// temp array to get the options for later compare/set
		$AZLNK_options_read = get_option('AZLNK_options');

		$new_input = $AZLNK_options;
		$new_input['AZLNK_affiliate_key'] = ( $input['AZLNK_affiliate_key']) ?  sanitize_text_field($input['AZLNK_affiliate_key']) : "0";
		$new_input['AZLNK_enable_comments'] = ( $input['AZLNK_enable_comments']) ?  sanitize_text_field($input['AZLNK_enable_comments']) : "0";
		$new_input['AZLNK_enable_affiliator_posts'] = ( $input['AZLNK_enable_affiliator_posts']) ?  sanitize_text_field($input['AZLNK_enable_affiliator_posts']) : "0";
		$new_input['AZLNK_auto_shorten'] = ( $input['AZLNK_auto_shorten']) ?  sanitize_text_field($input['AZLNK_auto_shorten']) : "0";
		$new_input['AZLNK_donate_flag'] = ( $input['AZLNK_donate_flag']) ?  sanitize_text_field($input['AZLNK_donate_flag']) : "0";
		$new_input['AZLNK_affiliate_counter'] = ($AZLNK_options_read['AZLNK_affiliate_counter']) ? $AZLNK_options_read['AZLNK_affiliate_counter'] : 0;
		$new_input['AZLNK_donate_counter']    = ($AZLNK_options_read['AZLNK_donate_counter']) ? $AZLNK_options_read['AZLNK_donate_counter'] : 0;
		$new_input['AZLNK_auto_shorten'] = ( $input['AZLNK_auto_shorten']) ?  sanitize_text_field($input['AZLNK_auto_shorten']) : "0";
		$new_input['AZLNK_bitly_token'] = ( $input['AZLNK_bitly_token']) ?  sanitize_text_field($input['AZLNK_bitly_token']) : "0";

		/*
		these are not set, since they are read-only values, and there would be no input
		values for the $new_input is set above.
		if (isset($input['AZLNK_donate_counter'])) {
		$new_input['AZLNK_donate_counter'] = absint($input['AZLNK_donate_counter']);
		}

		if (isset($input['AZLNK_affiliate_counter'])) {
		$new_input['AZLNK_affiliate_counter'] = absint($input['AZLNK_affiliate_counter']);
		}
		 */
		// this sets up those $new_input array values for the counters (26 Jan 2022)
		// check if $AZLNK_options_read value set, if so, use it; otherwise set that option value to 0

		return $new_input;
	}

	// print the Section text
	public function AZLNK_print_section_info() {
		print '<div class="AZLNK_settings_heading"><strong>Settings for AmazoLinkenator</strong></div>';
		print '<p>Save your settings once after upgrading to the latest version.</p>';
	}

	// api key callback
	public function AZLNK_affiliate_key_callback() {
		printf(
			'<table><tr><td><input type="text" id="AZLNK_affiliate_key" name="AZLNK_options[AZLNK_affiliate_key]" size="50"maxlength="50" value="%s" ></td><td valign="top">Enter Your Amazon Affiliate Key. <em>Make sure it is correct; it is not validated</em>.<br>If you need to get an Affiliate code, start <a href="https://affiliate-program.amazon.com/home" target="_blank" title="Sign up for an Amazon Affilaite code">here</a>.<br>Our Affiliate key is used as default, so enter your own Affilate code, and then Save your options. </td></tr></table>',
			isset($this->options['AZLNK_affiliate_key']) ? esc_attr($this->options['AZLNK_affiliate_key']) : ''
		);
	}

	// content checkbox callback
	public function AZLNK_enable_affiliator_posts_callback() {
		printf(
			"<table><tr><td><input type='checkbox' id='AZLNK_enable_affiliator_posts' name='AZLNK_options[AZLNK_enable_affiliator_posts]' value='1' " . checked('1', $this->options['AZLNK_enable_affiliator_posts'], false) . " /></td><td valign='top'>Check if you want to Affiliate the Amazon URLs in page/post content. URLs are affiliated only on content save/update.</td></tr></table> ",
			isset($this->options['AZLNK_enable_affiliator_posts']) ? '1' : '0'
		);
	}

	// comment checkbox callback
	public function AZLNK_enable_comments_callback() {
		printf(
			"<table><tr><td><input type='checkbox' id='AZLNK_enable_comments' name='AZLNK_options[AZLNK_enable_comments]' value='1' " . checked('1', $this->options['AZLNK_enable_comments'], false) . " /></td><td valign='top'>Check if you want to Affiliate the Amazon URLs in post comments. URLs in comments are only affiliated when the comment is saved or updated.</td></tr></table> ",
			isset($this->options['AZLNK_enable_comments']) ? '1' : '0'
		);
	}

	// comment checkbox callback
	public function AZLNK_auto_shorten_callback() {
		printf(
			"<table><tr><td valign='top'><input type='checkbox' id='AZLNK_auto_shorten' name='AZLNK_options[AZLNK_auto_shorten]' value='1' " . checked('1', $this->options['AZLNK_auto_shorten'], false) . " /></td><td valign='top'>Check if you want to automatically shorten the URL (great for 'hiding' your Affiliate codes). URLs are only shortened when a post/page/comment is saved or updated. Requires you to set up an Bit.ly Application here <a href='https://bitly.com/a/create_oauth_app' target='_blank'>https://bitly.com/a/create_oauth_app</a> , and then get a 'Generic Access Token' here <br><a href='https://bitly.com/a/oauth_apps' target='_blank'>https://bitly.com/a/oauth_apps</a>.<br> <strong>Enter your Bit.ly Generic Access Token below</strong>. <br>You can check any shortened Zon URLs <a href='https://affiliate-program.amazon.com/gp/associates/network/tools/link-checker/main.html' target='_blank'>here</a> for a valid affiliate code.<br>Note that compressed links will not be recompressed if you do not enable this option and have a valid bit.ly access code.</td></tr></table> ",
			isset($this->options['AZLNK_auto_shorten']) ? '1' : '0'
		);
	}

	// comment checkbox callback
	public function AZLNK_bitly_token_callback() {
		printf(
			'<table><tr><td><input type="text" id="AZLNK_bitly_token" name="AZLNK_options[AZLNK_bitly_token]" size="50"maxlength="50" value="%s" ></td><td valign="top">Enter Your Bit.ly Generic Access Token. Invalid token codes will not shorten the URL. See above for info on getting your token.<br><em>Check your code with the Validate button below (after saving changes)</em>. </td></tr></table>',
			isset($this->options['AZLNK_bitly_token']) ? esc_attr($this->options['AZLNK_bitly_token']) : ''
		);
	}

	// donate flag callback
	public function AZLNK_donate_flag_callback() {
		printf(
			"<table><tr><td><input type='checkbox' id='AZLNK_donate_flag' name='AZLNK_options[AZLNK_donate_flag]' value='1' " . checked('1', $this->options['AZLNK_donate_flag'], false) . " /></td><td valign='top'>Check if you allow us to use our Affiliate tag every 100 links - this helps support our plugin efforts (default enabled). (Not a big source of revenue - enough to buy another bag of Cherry JellyBellys every once in a while.)</td></tr></table> ",
			isset($this->options['AZLNK_donate_flag']) ? '1' : '0'
		);
	}

	// donate counter
	public function AZLNK_donate_counter_callback() {
		printf(
			"<table class='AZLNK_table_counter'><tr><td  class='AZLNK_counter'><span id='AZLNK_donate_counter' class='AZLNK_back_yellow'>" . number_format($this->options['AZLNK_donate_counter']) . "</span></td><td valign='top'> Count of how many times you let us use our Affiliate code - <strong>thanks for your support!</strong> </td></tr></table> ",
			isset($this->options['AZLNK_donate_counter']) ? esc_attr($this->options['AZLNK_donate_counter']) : ''
		);
	}

	// affiliate counter
	public function AZLNK_affiliate_counter_callback() {
		printf(
			"<table class='AZLNK_table_counter'><tr><td  class='AZLNK_counter'><span id='AZLNK_affiliate_counter' class='AZLNK_back_yellow'>" . number_format($this->options['AZLNK_affiliate_counter']) . "</span></td><td valign='top'> Count of your affiliate links added. Excludes counting duplicate URLs.</strong> </td></tr></table> ",
			isset($this->options['AZLNK_affiliate_counter']) ? esc_attr($this->options['AZLNK_affiliate_counter']) : ''
		);
	}
}
// end of the class stuff

if (is_admin()) {
	$my_settings_page = new AZLNK_Settings_Page();
	// closing bracket after credits

	// ----------------------------------------------------------------------------
	// supporting functions
	// ----------------------------------------------------------------------------
	//  display the top info part of the page
	// ----------------------------------------------------------------------------
	function AZLNK_info_top() {
		$image2 = plugin_dir_url(__FILE__) . '/assets/banner-772x250.png';
		?>
<div class="wrap">
    <h2></h2>
<div align='center' class = 'AZLNK_header'>
     <img src="<?php echo plugin_dir_url(__FILE__); ?>assets/banner-1000x200.jpg" width="100%"  alt="" class='AZLNK_shadow'>
</div>
        <p align='center'> <?php echo AMAZO_VERSION; ?></p>
    <hr />
    <div style="background-color:#9FE8FF;height:auto;padding:10px 15px !important ;">
        <p><strong>AmazoLinkenator will automatically (without any effort on your part) use your Amazon Affiliate code in any Amazon URLs.</strong> This will happen for pages, posts, and comments (depending on your settings below). Because you don't have to do anything special to affiliate a URL, this plugin is great for sites with lots of authors - especially those that might try to sneak in their Affiliate codes (which can deprive you of your Affiliate revenue).</p>
        <p>And no special steps are needed for any Amazon link entered in posts, pages or comments. Just paste the Amazon product link in the page/post/comment, and Publish. Your Amazon Affiliate link will automatically be added to the product URL.</p>
        <p>And <strong>AmazoLinkenator</strong> also works with any Amazon product links that your site commenters might add. If anyone includes a link in their comment that has their Amazon Affiliate code, it will be replaced with your affiliate code. All automatically! (It's your site, so you should get the Affiliate revenue!)</p>
 <p>It's easy to set up. Install and activate the plugin, then go to the Settings page. Add your Amazon Affiliate code, check a few boxes, and save. All done! (If you need to get an Affiliate code, start <a href="https://affiliate-program.amazon.com/home" target="_blank" title="Sign up for an Amazon Affilaite code">here</a>.) </p>
        <p>URLs are auto-affiliated only when posts/pages/comments are saved/updated/submitted. Any prior content will not change, uless you re-save/re-publish the content.A counter on the Settings screen shows the number of times that your Affiliate code is inserted in Amazon URLs.</p>
        <p>All you need is a valid Amazon Affiliate Key; start <a href='https://affiliate-program.amazon.com/gp/associates/network/main.html'target='_blank'>here</a>. There is no check for a valid Amazon Affiliate key. </p>
        <p>Plus, there's an option to automatically shorten the affiliate URL. This is great for 'hiding' your Amazon Affiliate link code. You just need a free Bit.ly Generic Access Token (<a href="https://bitly.com/a/create_oauth_app" target="_blank">start here</a>). Use the Validate button below to for a valid Bit.ly API Key.</p>
<p>Make sure you include this statement on your site if you are using Amazon Affiliate Codes "As an Amazon Associate I earn from qualifying purchases." It's required by the Amazon Affiliate program. And we don't want to make the Zon angry.</p>
    </div>
    <hr />
    <div style="background-color:#9FE8FF;padding:3px 8px 3px 8px;">
        <p><strong>Interested in a plugin that will stop comment spam? Without using captchas, hidden fields, or other things that just don't work (or are irritating)? &nbsp;&nbsp;&nbsp;Check out our nifty <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank">Block Comment Spam Bots</a>!&nbsp;&nbsp;It just works!</strong></p>
    </div>
    <hr>
</div>
<?php
return;
	}

	// ----------------------------------------------------------------------------
	// ``end of admin area
	// ----------------------------------------------------------------------------
} // closing bracket for info/credits is_admin section

// ----------------------------------------------------------------------------
// start of operational area that changes the comments box stuff
// ----------------------------------------------------------------------------

$AZLNK_options           = get_option('AZLNK_options');
$AZLNK_affiliate_key     = (isset($AZLNK_options['AZLNK_affiliate_key'])) ?  $AZLNK_options['AZLNK_affiliate_key'] : "";
$AZLNK_donate_counter    = (isset($AZLNK_options['AZLNK_donate_counter'])) ?  $AZLNK_options['AZLNK_donate_counter'] : "";
$AZLNK_affiliate_counter = (isset($AZLNK_options['AZLNK_affiliate_counter'])) ?  $AZLNK_options['AZLNK_affiliate_counter'] : "";

// set up the filters to process things based on the options settings

// preprocess comment after submitted to Affiliate URLs if enabled
if (isset($AZLNK_options['AZLNK_enable_comments'])) {
	add_filter('preprocess_comment', 'AZLNK_url_affiliate_comment', 121);
}

// preprocess posts/pages after submitted to Affiliate URLs if enabled
if (isset($AZLNK_options['AZLNK_enable_affiliator_posts']))  {
	add_filter('wp_insert_post_data', 'AZLNK_url_affiliate_post', '121', 2);
}
// ----------------------------------------------------------------------------
// end of add_actions and add_filters for posts/pages with comments open
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// here's where we do the work!
// ----------------------------------------------------------------------------

function AZLNK_url_affiliate_comment($commentdata) { // Affiliate URLs in comments

	unset($commentdata['comment_author_url']);
	$AZLNK_text                     = $commentdata['comment_content'];
	$AZLNK_affiliated               = AZLNK_extract_urls_and_fix($AZLNK_text);
	$commentdata['comment_content'] = $AZLNK_affiliated;
	return $commentdata;}

// ----------------------------------------------------------------------------
function AZLNK_url_affiliate_post($data, $postarr) { // Affiliate URLs in posts and pages
	$AZLNK_text = $data['post_content'];
	// do the work, then make all links clickable
	$data['post_content'] = make_clickable(AZLNK_extract_urls_and_fix($AZLNK_text));
	return $data;}

// ----------------------------------------------------------------------------
// Shortens the URL is enabled
// from http://www.sanwebe.com/2013/07/shorten-urls-bit-ly-api-php

function AZLNK_bitly_url_shorten($AZLNK_long_URL, $AZLNK_access_token) {
	$url = 'https://api-ssl.bitly.com/v3/shorten?access_token=' . $AZLNK_access_token . '&longUrl=' . urlencode($AZLNK_long_URL);
	try {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 4);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$output = json_decode(curl_exec($ch));
	} catch (Exception $e) {
	}
	if (isset($output)) {return $output->data->url;} else {return $AZLNK_long_URL . " fail";}
}

// ----------------------------------------------------------------------------
//  unshorten bit.ly URL - 26 Jan 2022
// ----------------------------------------------------------------------------
/**
 * @link http://jonathonhill.net/2012-05-18/unshorten-urls-with-php-and-curl/
 */
function AZLNK_bitly_url_expand($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_FOLLOWLOCATION => TRUE, // the magic sauce
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_SSL_VERIFYHOST => FALSE, // suppress certain SSL errors
		CURLOPT_SSL_VERIFYPEER => FALSE,
	));
	curl_exec($ch);
	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	return $url;
}

// ----------------------------------------------------------------------------
// gets the host name from any url even if a partial host or without protocol
function AZLNK_getHost($url) {
	$parseUrl = parse_url(trim($url));
	return trim($parseUrl['host']);
}

// ----------------------------------------------------------------------------
function AZLNK_show_test_button($AZLNK_bitly_token) {; //  AZLNK_show_test_button($AZLNK_bitly_token);
	?>
<hr />
<h2>Click this button to test for valid Bit.ly API Key (Save Changes First!)</h2>
<form method="post" name="testsettings" id="testsettings" action="" >
    <input type="submit" name="testsettings" id="testsettings" value="Validate Bit.ly API Key" style="background-color:yellow;"/>
</form>
<?php
if ($_POST['testsettings'] == 'Validate Bit.ly API Key') {
		AZLNK_token_test();
		//wp_die();
	}
	return;}

// ----------------------------------------------------------------------------
// testing routine/output
function AZLNK_token_test() {
	global $AZLNK_options;
	$AZLNK_bitly_token = $AZLNK_options['AZLNK_bitly_token'];
	$url_to_smash      = "http://www.cellarweb.com";
	$smashed_url       = AZLNK_bitly_url_shorten($url_to_smash, $AZLNK_bitly_token);
	?>
<hr />
<div style="background-color:#9FE8FF;height:auto;padding:5px 15px;">
    <h2>Results of Bit.ly API Key Validation Test</h2>
    <hr />
    <p><strong>URL before Smashing:</strong> <?php echo make_clickable($url_to_smash); ?></p>
    <p><strong>URL after Smashing :</strong> <?php echo make_clickable($smashed_url); ?></p>
    <?php
if (AZLNK_getHost($smashed_url) == "bit.ly") {
		echo "<h3><strong>Huzzah! Valid Bitly API Key found!</strong> (Hopefully, it's yours!)</h3>";
	} else {echo "<h3><strong>Bummer! Not a valid Bitly API Key!</strong> Check your Bit.ly Generic Access Token <a href=\"https://bitly.com/a/create_oauth_app\" target=\"_blank\">here</a>.</h3>";
	}
	?>
</div>
<?php
return $results;
}


// ----------------------------------------------------------------------------
// processes the string (comment text or post text) and sets/changes tag
//      note: links are already clickable
//  As of version 3.00
// ----------------------------------------------------------------------------
function AZLNK_extract_urls_and_fix($AZLNK_text) {
	 $AZLNK_options = get_option('AZLNK_options');
	$AZLNK_bitly_token       = (isset($AZLNK_options['AZLNK_bitly_token'])) ? $AZLNK_options['AZLNK_bitly_token'] : false;
	$AZLNK_affiliate_key     = (isset($AZLNK_options['AZLNK_affiliate_key'])) ? $AZLNK_options['AZLNK_affiliate_key'] : false;
	$AZLNK_shorten_flag      = (isset($AZLNK_options['AZLNK_auto_shorten'])) ? $AZLNK_options['AZLNK_auto_shorten'] : false;
	$AZLNK_donate_counter    = (isset($AZLNK_options['AZLNK_donate_counter'])) ? $AZLNK_options['AZLNK_donate_counter'] : false;
	$AZLNK_affiliate_counter = (isset($AZLNK_options['AZLNK_affiliate_counter'])) ? $AZLNK_options['AZLNK_affiliate_counter'] : false;
	$AZLNK_donate_flag = (isset($AZLNK_options['AZLNK_donate_flag'])) ? $AZLNK_options['AZLNK_donate_flag'] : false;

	// use default affiliate key if not set
	$AZLNK_affiliate_key = (isset($AZLNK_options['AZLNK_affiliate_key']))  ? "azlinkplugin-20" : $AZLNK_affiliate_key;

	// this from https://stackoverflow.com/a/5690614/1466973
	// works better than wp_extract_urls for some reason
	preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $AZLNK_text, $url_array);
$url_array = array_unique($url_array[0]); 	// get rid of duplicate URLs
	foreach ($url_array as $link) {
		$link = preg_replace('/[^a-z0-9]+\Z/i', '', $link); // removes any punctuation at end
		$link = trim($link);
		// check if amazon link
		if ((strpos(strtolower($link), 'amzn.to')) OR (strpos(strtolower($link), 'amazon.'))) {
			// set up the replace tag; check if time for the 'donate' tag (if enabled)
			if (($AZLNK_affiliate_counter % 100 == 0) AND ($AZLNK_donate_flag == true)) {
				$AZLNK_donate_counter++;
				$AZLNK_affiliate_key = "azlinkplugin-20";
			} else {
				$AZLNK_affiliate_key =(isset($AZLNK_options['AZLNK_affiliate_key'])) ? $AZLNK_options['AZLNK_affiliate_key'] : 'azlinkplugin-20';
			}
			$AZLNK_affiliate_counter++;
			// don't change the original $link, as it is needed to make the replace work
			$the_link = html_entity_decode($link);
			// expand bit.ly urls
			if (strpos(strtolower($link), 'amzn.to')) {
				$the_link = AZLNK_bitly_url_expand($the_link);
			}
			$queryarray = array();
			// get query string
			$query_string = parse_url($the_link, PHP_URL_QUERY);
			// get everything but the query string
			$domain = trim(str_replace($query_string, "", $the_link));
			// get all parameters of query string into an array
			parse_str($query_string, $queryarray);
			// remove duplicates
			$queryarray = array_unique($queryarray);
			// remove the old tag value from the array
			unset($queryarray['tag']);
			// reset the tag element (will add if not there)
			$queryarray['tag'] = $AZLNK_affiliate_key;
			// rebuild the query string
			$newquery = http_build_query($queryarray, '', '&', PHP_QUERY_RFC1738);
			// put the url back together
			$newlink = htmlspecialchars_decode(trim($domain) . "?" . trim($newquery));
			$newlink = trim($domain) . "?" . trim($newquery);
			// replace old link with fixed link  - case-insensitive
			$newlink = trim(str_replace("??", "?", $newlink));
			$newlink = trim($newlink);
	  	if ($AZLNK_shorten_flag) {
				$shortlink = AZLNK_bitly_url_shorten($newlink, $AZLNK_bitly_token);
				$AZLNK_text = str_ireplace($link, $shortlink, $AZLNK_text);
			} else {
			// replace entire URL with the $newlink that includes new parameters
			$AZLNK_text = str_ireplace($link, $newlink, $AZLNK_text);
			}
		}
		$new_options                            = get_option('AZLNK_options');
		$new_options['AZLNK_donate_counter']    = $AZLNK_donate_counter;
		$new_options['AZLNK_affiliate_counter'] = $AZLNK_affiliate_counter;
		$AZLNK_xresults = update_option("AZLNK_options", $new_options);
	} // close for looping through arrays of URLs
	return $AZLNK_text;
}

// ----------------------------------------------------------------------------
//  settings page sidebar content
// ----------------------------------------------------------------------------
function AZLNK_sidebar() {
	?>
    <h3 align="center">But wait, there's more!</h3>
    <p><b>Need to stop Comment spam?</b> We've got a plugin that does that - install and immediately stop comment spam. Very effective. Does not require any other actions or configuration, and your comment form will look the same. It just works! See details here: <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank" title="Block Comment Spam Bots">Block Comment Spam Bots</a> .</p>
    <p>We've got a <a href="https://wordpress.org/plugins/simple-gdpr/" target="_blank"><strong>Simple GDPR</strong></a> plugin that displays a GDPR banner for the user to acknowledge. And it creates a generic Privacy page, and will put that Privacy Page link at the bottom of all pages.</p>
    <p>How about our <strong><a href="https://wordpress.org/plugins/url-smasher/" target="_blank">URL Smasher</a></strong> which automatically shortens URLs in pages/posts/comments?</p>
    <hr />
    <p><strong>To reduce and prevent spam</strong>, check out our <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank" title="Block Comment Spam Bots">Block Comment Spam Bots</a> plugin (see above). Plus these plugins:</p>
    <p><a href="https://wordpress.org/plugins/formspammertrap-for-comments/" target="_blank"><strong>FormSpammerTrap for Comments</strong></a>: reduces spam without captchas, silly questions, or hidden fields - which don't always work. You can also customize the look of your comment form. Uses the same techniques as our Block Comment Spam Bots plugin. </p>
    <p><a href="https://wordpress.org/plugins/formspammertrap-for-contact-form-7/" target="_blank"><strong>FormSpammerTrap for Contact Form 7</strong></a>: reduces spam when you use Contact Form 7 forms. All you do is add a little shortcode to the contact form.</p>
<p>And if you want to block bots from your contact form, head over to our <a href="https://www.FormSpammerTrap.com" target="_blank" title="FormSpammerTrap - blocks Contact form spammers">FormSpammerTrap</a> site. Works on WP and non-WP sites. Takes a bit of programming, but we can help with that. Full docs and implementation instructions. Just request the free code via the site's Contact form. </p>
    <hr />
    <p>For <strong>multisites</strong>, we've got:

    <ul>
        <li><strong><a href="https://wordpress.org/plugins/multisite-comment-display/" target="_blank">Multisite Comment Display</a></strong> to show all comments from all subsites.</li>
        <li><strong><a href="https://wordpress.org/plugins/multisite-post-reader/" target="_blank">Multisite Post Reader</a></strong> to show all posts from all subsites.</li>
        <li><strong><a href="https://wordpress.org/plugins/multisite-media-display/" target="_blank">Multisite Media Display</a></strong> shows all media from all subsites with a simple shortcode. You can click on an item to edit that item. </li>
    </ul>
    </p>
    <hr />
    <p><strong>They are all free and fully featured!</strong></p>
    <hr />
    <p>I don't drink coffee, but if you are inclined to donate any amount because you like my WordPress plugins, go right ahead! I'll grab a nice hot chocolate, and maybe a blueberry muffin. And a nice <a href="https://wordpress.org/plugins/amazolinkenator/#reviews" target="_blank" title="Plugin Reviews page">review on the plugin page</a> is always appreciated!</p>
    <div align="center">
        <?php AZLNK_donate_button();?>
    </div>
    <p align='center'><b>Thanks!  <a href="https://www.RichardHellewell.com" target="_blank" title="Richard Hellewell author site">Richard Hellewell</a>, somewhere opposite Mutiny Bay WA.</b></p>
    <hr />
    <p><strong>Privacy Notice</strong>: This plugin does not store or use any personal information or cookies.</p>
<!--</div> -->
<?php
AZLNK_cellarweb_logo();

	return;
}

// ============================================================================
// footer for settings page
// ----------------------------------------------------------------------------
function AZLNK_footer() {
	?>
    <hr><h3 align='center' class='AZLNK_larger_text'><b>Thanks for using AmazoLinkenator!  Tell your friends!</b></h3>
<hr><div style="background-color:#9FE8FF !important; padding:10px;color:black !important; ">
    <p align="center"><strong>Copyright &copy; 2016- <?php echo date('Y'); ?> by Rick Hellewell and <a href="http://CellarWeb.com" title="CellarWeb" >CellarWeb.com</a> , All Rights Reserved. Released under GPL2 license. <a href="http://cellarweb.com/contact-us/" target="_blank" title="Contact Us">Contact us page</a>.</strong></p><p align="center">As an Amazon Associate I earn from qualifying purchases using our Affiliate code.</p>
</div>
<hr><br>
<?php
return;
}

// ============================================================================
// PayPal donation button for settings sidebar (as of 25 Jan 2022)
// ----------------------------------------------------------------------------
function AZLNK_donate_button() {
	?>
<form action="https://www.paypal.com/donate" method="post" target="_top">
<input type="hidden" name="hosted_button_id" value="TT8CUV7DJ2SRN" />
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>

<?php
return;
}

// ----------------------------------------------------------------------------
function AZLNK_cellarweb_logo() {
	?>
 <p align="center"><a href="https://www.cellarweb.com" target="_blank" title="CellarWeb.com site"><img src="<?php echo plugin_dir_url(__FILE__); ?>assets/cellarweb-logo-2022.jpg"  width="90%" class="AZLNK_shadow" ></a></p>
 <?php
return;
}

// ----------------------------------------------------------------------------
// check for missing bit.ky key when shortening enabled
function AZLNK_sanity_check_shorten($AZLNK_options) {
if (! isset($AZLNK_options['AZLNK_auto_shorten'])) {return false;}
$auto_shorten = ( $AZLNK_options['AZLNK_auto_shorten']) ?  $AZLNK_options['AZLNK_auto_shorten'] : false;
$bitly_token = ($AZLNK_options['AZLNK_bitly_token']) ?  $AZLNK_options['AZLNK_bitly_token'] : false;
if (($auto_shorten) and (! $bitly_token)) {
	$message = "AmazoLinkenator Settings error! Shortening URLs is enabled, but no bit.ly access token specified. URLs will not be shortened.";
	$class = 'notice notice-error is-dismissible ';

	printf( '<div class="%1$s"><p><b>%2$s</b></p></div>', esc_attr( $class ), esc_html( $message ) );
	add_action( 'admin_notices', 'AZLNK_sanity_check_shorten' );
	return true;
} else {return false;}
return false;
}
// ----------------------------------------------------------------------------
// for internal debugging
// ----------------------------------------------------------------------------

function AZLNK_write_log($logtext) {
	$thelogfile = "log.txt";
	// just in case it was blank
	$xlogfile = getcwd() . "/" . $thelogfile;
	$fh       = fopen($xlogfile, 'a');
	if (!$fh) {return;} // error writing log file, just return without error
	fwrite($fh, $logtext . PHP_EOL);
	fclose($fh);
	return;
}

function AZLNK_print_array($the_array = array(), $log = false) {
	$now      = time() - (7 * 60 * 60);
	$now      = date('l jS F Y - h:i:s A', $now);
	$log_head = PHP_EOL . str_repeat("-", 60) . PHP_EOL . $now . PHP_EOL;
	$log_end  = PHP_EOL . str_repeat("-", 60) . PHP_EOL;
	if ($log) {
		$log_data = "xxx" . print_r($the_array, true);
		//$logdata = $log_head . $log_data . $log_end ;
		AZLNK_write_log($log_head . $log_data . $log_end);
	} else {
		echo "<pre>";
		print_r($the_array);
		echo "</pre>";
	}
	return;
}

// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// all done!
// ----------------------------------------------------------------------------
