<?php
defined( 'ABSPATH' ) or die;

global $corona_test_results_cfg;

/**
 * default config
 * IMPORTANT: The current language is not yet available here,
 * but we still use the translation functions for the translator tool to pick them up.
 * The default values have to be used as parameters for translation functions again
 * in order to have them translated to the actually set language.
 */
$corona_test_results_cfg = $corona_test_results_cfg_defaults = array(
	'license_key' => '',
    'code_length' => CORONA_TEST_RESULTS_MIN_CODE_LENGTH, // maxmimum defined in constant CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH (or adapt database VARCHAR column and update constant!)
    'code_blocklist' => '',
	'assignation_entries_per_page' => 15,
	'template_papersize' => 'a5',
	'template_tb_topright' => "created with\n<strong>Corona Test Results\nfor WordPress</strong>",
	'template_tb_topleft' => "{{customfield}}\n{{surname}}\n{{firstname}}",
	'template_tb_salutation' => __( 'Dear patient,', 'corona-test-results' ),
	'template_tb_before' => __( 'the result for your test taken on {{testdate}} will be ready within 24 hours to up to 3 days and can then be retrieved at', 'corona-test-results' ),
	'template_tb_after' => __( 'using the following code. Alternatively, you can scan the QR code with your mobile device to get directly to your result.', 'corona-test-results' ),
	'template_tb_bottom' => __( "Data Privacy Notice:\nThe security of your data is important to us. Therefore, it is not possible to draw any conclusions about your person using the online result check.", 'corona-test-results' ),
	'template_tb_bottom_cert' => __( "Data Privacy Notice:\nThe security of your data is important to us. Personal data will be stored in encrypted form and will not show up on the online result check. When you receive your certificate by email, you will need the PIN provided on this document in order to open the PDF file.", 'corona-test-results' ),
	'template_tb_bottom_page2' => '',
	'template_poweredby' => 'on',
	'printlabel_width' => 60,
	'printlabel_height' => 40,
	'printlabel_fontsize' => '',
	'security_codes_access_users' => array(),
	'assignation_autotrash' => 0,
	'assignation_autodelete' => 0,
	'certificates_tb_dataprotection' => __( 'SARS-CoV-2 is an infection with a pathogen that may be reportable depending on your local regulatory requirements. If this is the case, the test-executing agency is obligated to immediately notify the responsible public health department in the event of a positive test result. This includes forwarding the personal data collected in this form to the competent health authority. The legal basis and the data protection regulations applicable to you vary depending on the country and/or state.', 'corona-test-results' ),
	'certificates_tb_legaltext' => __( 'Altering this certificate or attempting to perform verification with false identification documents is a misdemeanor punishable by a fine.', 'corona-test-results' ),
	'certificates_tb_mail_subject' => __( "Your Corona test certificate", 'corona-test-results' ),
	'certificates_tb_mail_text' => __( "Hello,\n\nplease find attached your Corona test certificate. You will find the PIN required to open the PDF file on the document you received when you took the test.\n\n-----\n{{testingsite}}", 'corona-test-results' ),
	'booking_futuredays' => 0,
	'quickcheckin_check_email_repeat' => 'on',
	'quickcheckin_check_confirmation' => 'off',
	'quickcheckin_check_confirmation_text' => __( 'Data is not being transferred to any systems via this form and stays encoded in the QR Code on your device or on paper. I agree that my data will be transferred and stored when the code is scanned at the testing station to fulfill the testing process.', 'corona-test-results' ),
	'quickcheckin_poster_headline' => __( 'Quick Check-In', 'corona-test-results' ),
	'quickcheckin_poster_text' => __( 'In order to speed up the testing process, please use your mobile device to visit the following page. This will allow you to save your personal data as a QR code that can be scanned by the testing personnel.', 'corona-test-results' ),
);

$saved_options = get_option( 'corona_test_results_options' );
if (is_array($saved_options)) {

	$saved_options = array_filter( $saved_options, function($k) {
		return 'page_' === substr( $k, 0, 5 ) || 'security_' === substr( $k, 0, 9 ) || in_array( $k, array( 'license_key' ) );
	}, ARRAY_FILTER_USE_KEY);

	if ( is_ssl() && isset( $saved_options['template_logoimage'] ) && substr( $saved_options['template_logoimage'], 0, 7 ) === "http://"
		) {
		$saved_options['template_logoimage'] = preg_replace( '/^http:/', 'https:', $saved_options['template_logoimage'] );
	}
	$corona_test_results_cfg = array_merge($corona_test_results_cfg_defaults, $saved_options);
} else {
	$corona_test_results_cfg = $corona_test_results_cfg_defaults;
}

$corona_test_results_page_cache = array();

/**
 * get current configuration
 */
function corona_test_results_get_options() {
	global $corona_test_results_cfg;
    return $corona_test_results_cfg;
}

/**
 * return the capability required for displaying (and accessing) the plugin pages
 */
function corona_test_results_get_required_capability( $area = 'settings' ) {
	// settings page is always restricted to 'manage_options'
	// and 'manage_options' should be able to access all plugin pages
	if ( 'settings' === $area || current_user_can( 'manage_options' ) ) {
		return 'manage_options';
	}

	if ( 'menu' === $area ) {
		return 'corona_test_results_display_menu';
	}

	if ( 'codes' === $area ) {
		return 'corona_test_results_manage_codes';
	}

	if ( 'register' === $area ) {
		return 'corona_test_results_register_codes';
	}

	return 'manage_options';
}

/**
 * add our custom ability if the current user ought to have code access
 */
function corona_test_results_capability_filter( $allcaps, $cap, $args ) {
	$options = corona_test_results_get_options();
	$capability = $args[0];
	$user_id = $args[1];

	if ( !isset( $options['security_codes_access_users'] ) || !is_array( $options['security_codes_access_users'] ) ) {
		$options['security_codes_access_users'] = array();
	}

	if ( !isset( $options['security_codes_register_access_users'] ) || !is_array( $options['security_codes_register_access_users'] ) ) {
		$options['security_codes_register_access_users'] = $options['security_codes_access_users'];
	}

	$can_access_registration = in_array( $user_id, $options['security_codes_register_access_users'] );
	$can_access_assignation = in_array( $user_id, $options['security_codes_access_users'] );

	$capable = false;

	if( $capability === 'corona_test_results_display_menu' && !isset( $allcaps[ $capability ] ) ) {
		if ( $can_access_assignation || $can_access_registration ) {
			$capable = true;
		}
	} else if ( $capability === 'corona_test_results_register_codes' && !isset( $allcaps[ $capability ] ) ) {
		if ( $can_access_registration ) {
			$capable = true;
		}
	} else if ( $capability === 'corona_test_results_manage_codes' && !isset( $allcaps[ $capability ] ) ) {
		if ( $can_access_assignation ) {
			$capable = true;
		}
	}

	if ( $capable ) {
		$allcaps[ $capability ] = true;
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'corona_test_results_capability_filter', 10, 3 );

/**
 * Returns the internal slugs for our different result pages.
 * This is also used to iterate over all the different page types to filter or generate content.
 */
function corona_test_results_get_result_pages_slugs() {
	return ['result_retrieval', 'result_pending', 'result_positive', 'result_negative', 'result_invalid'];
}

/**
 * Returns the internal slugs for all of our different special pages (result pages and others)
 */
function corona_test_results_get_special_pages_slugs() {
    return array_merge(
        corona_test_results_get_result_pages_slugs(),
        array()
    );
}

/**
 * result page (ID, permalink) retrieval
 */
function corona_test_results_get_page( $pagename, $include_trashed = false ) {
	global $corona_test_results_cfg, $corona_test_results_page_cache;

	$found_page = null;
	$returnValue = null;

	if ( isset( $corona_test_results_page_cache[$pagename] ) ) {
		$found_page = $corona_test_results_page_cache[$pagename];
	} else if (
		isset( $corona_test_results_cfg['page_' . $pagename] )
		&& !empty( $corona_test_results_cfg['page_' . $pagename] )
	) {
		$corona_test_results_page_cache[$pagename] = $found_page = get_post( $corona_test_results_cfg['page_' . $pagename] );
	}

	if ($found_page && ( $include_trashed || $found_page->post_status !== 'trash' ) ) {
		$returnValue = $found_page;
	}

	return $returnValue;
}

function corona_test_results_get_page_id( $pagename = null) {
	$page = corona_test_results_get_page( $pagename );
	return $page ? $page->ID : null;
}

function corona_test_results_get_page_url( $pagename = 'result_retrieval' ) {
	$page = corona_test_results_get_page( $pagename );
	return $page ? get_permalink($page) : null;
}

function corona_test_results_get_page_state( $page_slug, $as_int = false ) {
	$states = array(
		'result_retrieval' => null,
		'result_pending' => $as_int ? 0 : __( 'Pending', 'corona-test-results' ),
		'result_positive' => $as_int ? 1 : __( 'Positive', 'corona-test-results' ),
		'result_negative' => $as_int ? 2 : __( 'Negative', 'corona-test-results' ),
		'result_invalid' => $as_int ? 3 : __( 'Invalid', 'corona-test-results' ),
	);

	if ( isset($states[$page_slug]) ) {
		return $states[$page_slug];
	}

	return null;
}

/**
 * default result page contents
 */
function corona_test_results_create_default_pages( $pages = array() ) {
	global $corona_test_results_cfg, $corona_test_results_page_cache;

	if ( empty($pages) ) {
		$pages = corona_test_results_get_special_pages_slugs();
	}

	foreach ( $pages as $page_slug ) {
		if ( !corona_test_results_get_page( $page_slug ) ) {
			$is_resultpage = in_array( $page_slug, corona_test_results_get_result_pages_slugs() );
			$is_retrieval = $page_slug === 'result_retrieval';

			$suffix = '';
			$shortcode = '';
			$status = 'draft';
			$title = '';

			if ( $is_resultpage ) {
				$title = __('Test Result', 'corona-test-results');
				$suffix = $is_retrieval ? '' : ( ': ' . corona_test_results_get_page_state( $page_slug ) );
				$shortcode = $is_retrieval ? '[testresults_form]' : '[testresults_code formatted]';
				$status = $is_retrieval ? 'publish' : 'draft';

			}

			$post_details = array(
				'post_title'    => $title . $suffix,
				'post_content'  => "<!-- wp:shortcode -->\n$shortcode\n<!-- /wp:shortcode -->",
				'post_status'   => $status,
				'post_type'		=> 'page'
			);

			if ( $is_retrieval ) {
				// translators: default results retrieval page URL slug
				$post_details['post_name'] = sanitize_title(__('testresult', 'corona-test-results'));
			}

			$new_page_id = wp_insert_post( $post_details );
			if ($new_page_id) {
				$corona_test_results_cfg['page_' . $page_slug] = $new_page_id;
				$corona_test_results_page_cache[ $page_slug ] = get_post( $new_page_id );
			}
		}
	}
}

/**
 * makes sure that pages that were not in the initial set of special pages (added later via updates) are created,
 * because the activation hook does not run on plugin updates
 */
function corona_test_results_create_upgrade_default_pages() {
	$page_created = false;
	$pages = array( 'result_invalid' );
	foreach ( $pages as $page ) {
		if( ! corona_test_results_get_page_id( $page ) ) {
			$page_created = get_option( 'corona_test_results_' . $page . '_page_created' );
			if ( empty( $page_created ) ) {
				corona_test_results_create_default_pages( array( $page ) );
				$page_created = true;
			}
		}
	}

	if ( $page_created ) {
		global $corona_test_results_cfg;
		update_option( 'corona_test_results_options', $corona_test_results_cfg );
	}
}

/*
 * activation hook (create DB and default pages)
 */
function corona_test_results_activation() {
    global $corona_test_results_cfg;

	require_once(__DIR__ . '/corona-test-results-admin.php');

	corona_test_results_conditionally_create_table();

	corona_test_results_create_default_pages();

	update_option( 'corona_test_results_options', $corona_test_results_cfg );

	$activationtime = get_option( 'corona_test_results_activationtime' );
	if (!$activationtime) {
		update_option( 'corona_test_results_activationtime', time() );
	}

}
register_activation_hook( corona_test_results__mainfile(), 'corona_test_results_activation');

function corona_test_results_uninstall() {
	global $wpdb;
	$tableName = corona_test_results_get_table_name();

	if ( defined( 'CORONA_TEST_RESULTS_LITE_REMOVAL' ) ) {
		// Do not delete anything if we're upgrading from Lite to Premium
		return;
	}

	if( corona_test_results_check_checkbox_option( 'security_deletion_data' ) ) {
		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");

	} else {
		$pluginData = get_plugin_data( corona_test_results__mainfile() );
		$pluginName = $pluginData['Name'];
		$adminEmail = get_option( 'admin_email' );
		$siteName = get_bloginfo();

		add_filter( 'wp_mail_from_name', 'corona_test_results_mail_sender_name' );

		wp_mail(
			$adminEmail,
			// translators: %s: 1) name of the site 2) name of the plugin
			sprintf( __( "[%s] %s plugin has been uninstalled, but data has not been deleted", 'corona-test-results' ), $siteName, $pluginName) ,
			// translators: %s: 1) name of the plugin, 2) name of the site, 3) "Security" tab name, 4) database table name
			sprintf( __( "The plugin \"%s\" has been uninstalled from your WordPress site \"%s\", but to protect against data loss, no data has been deleted from the database. In order to erase all associated data from the database, you can either install the plugin again and check the checkbox for data deletion in the \"%s\" tab of the plugin settings before you delete the plugin again, or you can manually drop the table `%s` from the database.", 'corona-test-results' ),
				$pluginName,
				$siteName,
				__( 'Security', 'corona-test-results' ),
				$tableName
			)
		);

		remove_filter( 'wp_mail_from_name', 'corona_test_results_mail_sender_name' );
	}

	if( corona_test_results_check_checkbox_option( 'security_deletion_pages' ) ) {
		foreach( corona_test_results_get_special_pages_slugs() as $pageSlug ) {
			$pageId = corona_test_results_get_page_id( $pageSlug );
			if ( !empty($pageId) ) {
				wp_trash_post( $pageId );
			}
		}
	}

	if( corona_test_results_check_checkbox_option( 'security_deletion_settings' ) ) {
		delete_option( 'corona_test_results_options' );
		delete_option( 'corona_test_results_activationtime' );
		delete_option( 'dismissed_corona_test_results_rating_nag' );
		delete_option( 'corona_test_results_license_error' );
		delete_option( 'corona_test_results_quickcheckin_page_created' );
		delete_option( 'corona_test_results_result_invalid_page_created' );

	}

	if ( corona_test_results_check_checkbox_option( 'security_deletion_key' ) && corona_test_results_check_checkbox_option( 'security_deletion_data' ) ) {
		$cfgPath = ABSPATH . 'ctr-config.php';
		if ( is_file( $cfgPath ) ) {
			@chmod( $cfgPath, 0755 );
			unlink( $cfgPath );
		}
	}
}
register_uninstall_hook( corona_test_results__mainfile(), 'corona_test_results_uninstall');

/**
 * table, code generation and result related functions called from frontend and admin pages
 */
function corona_test_results_get_table_name( $suffix = '' ) {
    global $wpdb;
    return $wpdb->prefix . "va_testresults" . ( ! empty( $suffix ) ? '_' . $suffix : '' );
}
function corona_test_results_get_code_chars() {
    $codeAlphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $codeAlphabet.= "2345689";
    return $codeAlphabet;
}
function corona_test_results_get_code_regex() {
	$regEx = '/^[' . corona_test_results_get_code_chars() . ']{' . CORONA_TEST_RESULTS_MIN_CODE_LENGTH . ',' . CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH . '}$/';
	return apply_filters( 'corona_test_results_filter_code_regex', $regEx );
}

function corona_test_results_get_states( $as_slug = false ) {
    $default_states = array(
        0 => $as_slug ? 'pending' : __('Pending', 'corona-test-results'),
        1 => $as_slug ? 'positive' : __('Positive', 'corona-test-results'),
        2 => $as_slug ? 'negative' : __('Negative', 'corona-test-results'),
        3 => $as_slug ? 'invalid' : __('Invalid', 'corona-test-results')
    );

	return apply_filters( 'corona_test_results_filter_test_states', $default_states, $as_slug );
}

function vt_helper__is_flag( $flag, $atts ) {
	if ( empty($atts) ) return false;
    foreach ( $atts as $key => $value )
        if ( $value === $flag && is_int( $key ) ) return true;
    return false;
}

function corona_test_results_check_checkbox_option( $name, $base = null ) {
	if ( ! $base ) {
		$base = corona_test_results_get_options();
	}
	return isset( $base[ $name ] ) && $base[ $name ] === 'on';
}

function corona_test_results_sanitize_local_image_url( $input ) {
	if ('' === trim( $input ) ) {
		return '';
	} else if ( substr( $input, 0, 7 ) !== "http://" && substr( $input, 0, 8 ) !== "https://" ) {
		return '';
	} else if ( attachment_url_to_postid( $input ) < 1 ) {
		// image is not in our media gallery
		return '';
	} else if ( is_ssl() && substr( $input, 0, 7 ) === "http://") {
		// http => https
		return preg_replace( "/^http:/", 'https:', $input );
	} else if ( ! is_ssl() && substr( $input, 0, 8 ) === "https://") {
		// https => http
		return preg_replace( "/^https:/", 'http:', $input );
	}

	return $input;
}

/**
 * wrappers for strings from default textdomain,
 * so translation tools like PoEdit ignore these
 */
function vt_helper__default_i18n__( $string ) {
    return __( $string );
}
function vt_helper__default_i18n_e( $string ) {
    return _e( $string );
}

/**
 * as we set the default options as early as possible,
 * the locale is not yet available at that time, so default options with i18n strings will always show in English.
 * This fixes the issue by re-translating those strings.
 */
function vt_helper__retranslate_option( $option_name ) {
	global $corona_test_results_cfg_defaults;
	$options = corona_test_results_get_options();

	if ( isset( $options[$option_name] ) ) {
		if ( $options[$option_name] === $corona_test_results_cfg_defaults[$option_name] ) {
			return __( $options[$option_name], 'corona-test-results' );
		} else {
			return $options[$option_name];
		}
	}
	return null;
}

/**
 * allow only styling HTML tags
 */
function vt_helper__esc_html( $input ) {
	return wp_kses( $input, array(
		'br' => array(),
		'em' => array(),
		'strong' => array(),
		'i' => array(),
		'b' => array(),
	) );
}

/**
 * Returns the plugin information from the main file
 */
function corona_test_results_get_plugin_info() {
	return get_plugin_data( corona_test_results__mainfile(), false );
}

/**
 * Returns a link to the plugin author
 */
function corona_test_results_get_author_link() {
	$pluginData = corona_test_results_get_plugin_info();
	return "<a href='" . esc_attr( esc_url( $pluginData['AuthorURI'] ) ) . "' target='_blank'>" . $pluginData['Author'] . "</a>";
}

/**
 * returns a link to the shop URL for the premium version
 */
function corona_test_results_premium_shop_url() {
	return 'https://shop.48design.com/produkt/wordpress/corona-testergebnis-plugin-premium/';
}

/**
 * Use the site name as mail sender name (use with filter 'wp_mail_from_name')
 */
function corona_test_results_mail_sender_name( $original_email_address ) {
	return html_entity_decode( get_bloginfo() );
}

function corona_test_results_plugin_dir_url() {
	return plugin_dir_url( corona_test_results__mainfile() );
}

function corona_test_results_plugin_dir_path() {
	return plugin_dir_path( corona_test_results__mainfile() );
}

