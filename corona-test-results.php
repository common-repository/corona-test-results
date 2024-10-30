<?php
/*
 * Plugin Name: Corona Test Results
 * Plugin URI: https://shop.48design.com/produkt/wordpress/corona-testergebnis-plugin-premium/
 * description: 🦠 <strong>Management of Corona/COVID-19 test results</strong> with online retrieval for test subjects 🦠 <strong>For medical practices, testing centers and laboratories:</strong> Generate random codes and print out a handout containing the URL and QR code for online retrieval of the result, as well as an on-site record containing the personal data and code to assign the test results when ready. 🦠 <strong>Inherent data protection:</strong> Personal data is not stored on the server if the certificate generation feature is not activated or no certificate is requested. Otherwise, all personal data is stored encrypted.
 * Version: 1.11.6
 * Author: 48DESIGN GmbH
 * Author URI: https://www.48design.com/
 * Text Domain: corona-test-results
 * Domain Path: /languages/
 * Tested up to: 5.9.0
 * Requires at least: 4.8
 * Requires PHP: 5.6.40
 *
 * Copyright (C) 2021, 48DESIGN GmbH (email: wordpress@48design.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) or die;

/**
 * when the premium version is installed, deactivate and remove the free version
 */
function corona_test_results_check_upgrading( $upgrader, $options  ) {
	if ( $options['action'] == 'install' && $options['type'] == 'plugin' ) {
		if ( isset( $upgrader->result ) && $upgrader->result['destination_name'] === 'corona-test-results-premium') {
			deactivate_plugins( corona_test_results__mainfile(), true );

			define( "CORONA_TEST_RESULTS_LITE_REMOVAL", true );
			delete_plugins( array( corona_test_results__mainfile( true ) ) );

			activate_plugins( 'corona-test-results-premium/corona-test-results-premium.php' );
		}
	}
}
add_action( 'upgrader_process_complete' , 'corona_test_results_check_upgrading', 10, 2 );

/**
 * Return the path or basename of the plugin main file
 */
if (!function_exists( 'corona_test_results__mainfile' )) {
	define( 'CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH', 16 );
	define( 'CORONA_TEST_RESULTS_MIN_CODE_LENGTH', 6 );

	function corona_test_results__mainfile( $basename = false ) {
		return $basename ? plugin_basename( __FILE__ ) : __FILE__;
	}

	global $corona_test_results_cfg;

	if ( is_file( ABSPATH . 'ctr-config.php' ) ) {
		require_once( ABSPATH . 'ctr-config.php' );
	}

	require_once( __DIR__ . '/corona-test-results-global.php' );

	/*
	* init
	*/
	function corona_test_results_init() {
		/*
		* i18n
		*/
		load_plugin_textdomain( 'corona-test-results', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		/*
		* admin
		*/
		if ( is_admin() && is_user_logged_in() ) {
			if (
				current_user_can( corona_test_results_get_required_capability( 'codes' ) )
				|| current_user_can( corona_test_results_get_required_capability( 'register' ) )
			) {
				require_once(__DIR__ . '/corona-test-results-admin.php');
				require_once(__DIR__ . '/corona-test-results-admin-settings-general.php');
			}
			if ( current_user_can( corona_test_results_get_required_capability( 'settings' ) ) ) {
				require_once(__DIR__ . '/corona-test-results-admin-settings.php');
			}
		}
		require_once(__DIR__ . '/corona-test-results-shortcodes.php');
	}
	add_action('init', 'corona_test_results_init');

	function corona_test_results_check_crypto_keys() {
		return defined( 'CTR_ENCRYPTION_KEY' ) && '' !== constant('CTR_ENCRYPTION_KEY');
	}

	function corona_test_results_check_crypto_activated() {
		return corona_test_results_check_crypto_keys() && corona_test_results_check_checkbox_option( 'security_encryption_consent' );
	}
}
