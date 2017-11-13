<?php
/*
Plugin Name: WSUWP Content Syndicate Events
Plugin URI: https://web.wsu.edu/wordpress/plugins/wsuwp-content-syndicate/
Description: Retrieve events published on other WordPress sites.
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
Version: 1.1.0
*/

namespace WSU\ContentSyndicate\Events;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'wsuwp_content_syndicate_shortcodes', 'WSU\ContentSyndicate\Events\activate_shortcodes' );
/**
 * Activates the wsuwp_events shortcode.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-events.php';

	// Add the [wsuwp_events] shortcode to pull calendar events.
	new \WSU_Syndicate_Shortcode_Events();
}
