<?php
defined( 'ABSPATH' ) or die;

global $corona_test_results_cfg;

/**
 * Prevents caching of the page this function is called from, by setting constants and calling functions of some known caching plugins
 */
function corona_test_results_prevent_caching() {
	// disable caching of the page when the Comet Cache plugin (or other plugins supporting the DONOTCACHEPAGE constant) is active
	// this does not always seem to reliably take effect, so the page with the retrieval form should also be added as an exception

	$_SERVER['COMETCACHE_ALLOWED'] = false;

	if ( ! defined( 'COMETCACHE_ALLOWED' ) ) {
		define( 'COMETCACHE_ALLOWED', false );
	} else {
		if ( false !== COMETCACHE_ALLOWED ) {
			throw new \Exception( 'The constant COMETCACHE_ALLOWED is defined and not set to false. This will lead to the retrieval form being cached, including the WordPress nonce, which will eventually lead to failing code retrieval.' );
		}
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	} else {
		if ( true !== DONOTCACHEPAGE ) {
			throw new \Exception( 'The constant DONOTCACHEPAGE is defined and not set to true. This will lead to the retrieval form being cached, including the WordPress nonce, which will eventually lead to failing code retrieval.' );
		}
	}

	// caching exception for WP Super Cache
	if ( ! defined( 'WPSC_SERVE_DISABLED' ) ) {
		define( 'WPSC_SERVE_DISABLED', true );
	} else {
		if ( true !== WPSC_SERVE_DISABLED ) {
			throw new \Exception( 'The constant WPSC_SERVE_DISABLED is defined and not set to true. This will lead to the retrieval form being cached, including the WordPress nonce, which will eventually lead to failing code retrieval.' );
		}
	}

	// caching exception for WP Fastest Cache
	if ( function_exists( 'wpfc_exclude_current_page' ) ) {
		wpfc_exclude_current_page();
	}
}

/**
 * Output errors that might occurr durint result retrieval
 */
function corona_test_results_sc_errors() {
	global $corona_test_results_result_error;
	if ($corona_test_results_result_error) {
		return "<p class='corona-test-results__error-message'>" . esc_html( $corona_test_results_result_error ) . "</p>";
	}
	$corona_test_results_result_error = null;
	return '';
}
add_shortcode('testresults_errors', 'corona_test_results_sc_errors');

/**
 * Output the form for result retrieval for a code
 */
function corona_test_results_sc_form( $atts, $content = null ) {
	global $corona_test_results_cfg;
	$codeLength = (int)$corona_test_results_cfg['code_length'];

	// because the code length setting might be changed, we don't use
	// the pattern attribute or $codeLength for the maxlength attribute,
	// as that would prevent older/newer codes from being entered
	ob_start();

	$noOpenCloseAtts = !vt_helper__is_flag( 'open', $atts ) && !vt_helper__is_flag( 'close', $atts );
	$openAtt = vt_helper__is_flag( 'open', $atts );
	$closeAtt = vt_helper__is_flag( 'close', $atts );

	if ( $noOpenCloseAtts || $openAtt ) {
		if ( !vt_helper__is_flag( 'noerrors', $atts ) ) {
			echo corona_test_results_sc_errors();
		}

		corona_test_results_prevent_caching();

	?>
	<form action="#result" method="post" id="result_form" autocomplete="off" class="corona-testresults-retrieval-form">
		<input type="hidden" name="action" value="corona_test_results_check">
		<input type="hidden" name="corona_test_results_nonce" value="<?php echo wp_create_nonce( 'corona_test_results_check_nonce' );  ?>" />
	<?php
	}

	if ( $noOpenCloseAtts ) {
	?>
		<input
			name="testresult_code"
			id="testresult_code"
			type="text"
			placeholder="<?php echo esc_attr( str_repeat('X', $codeLength) ); ?>"
			maxlength="<?php echo esc_attr( CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH ); ?>"
			value="<?php echo !empty($_POST) && isset($_POST['testresult_code']) ? esc_attr( stripslashes( $_POST['testresult_code'] ) ) : ''; ?>"
			class="corona-testresults-retrieval-form__code-input"
			style="
				display: inline-block;
				text-transform: uppercase;
				font-family: monospace;
				font-size: 1.2em;
				letter-spacing: 0.1em;
				width: <?php echo $codeLength + 4 ?>ch;
				max-width: <?php echo $codeLength ?>em;
				text-align: center;
				vertical-align: middle;"
				required
		> <button
			type="submit"
			class="corona-testresults-retrieval-form__submit-button"
			style="
				display: inline-block;
				vertical-align: middle;
			"
		><?php esc_html_e('check', 'corona-test-results') ?></button>
	<?php
	}

	if ( $noOpenCloseAtts || $closeAtt ) {
	?>
	</form>
	<script>
		if (window.location.hash) {
			var cleanHash = window.location.hash.replace(/^#/, '');

			if (cleanHash === 'result') {
				document.addEventListener('DOMContentLoaded', function() {
					var result = document.getElementById('result');
					if (result && document.documentElement.scrollTop <= 10 && result.scrollIntoView && typeof result.scrollIntoView === 'function') {
						result.scrollIntoView();
					}
				});
			} else if (<?php echo corona_test_results_get_code_regex() ?>.test(cleanHash)) {
				if (window.history.replaceState && typeof window.history.replaceState === 'function') {
					window.history.replaceState(null, null, window.location.href.split('#')[0]);
				}

				document.getElementById('testresult_code').value = cleanHash;
				document.getElementById('result_form').submit();
			}
		}
	</script>
	<?php
	}

	return ob_get_clean();
}
add_shortcode('testresults_form', 'corona_test_results_sc_form');

/**
 * populate the testresult with dummy data for display in page preview
 */
function corona_test_results_get_preview_result() {
	global $corona_test_results_cfg;

	$page_id = get_the_ID();
	$status_slugs = corona_test_results_get_states( true );
	$preview_status = null;

	if ( isset($_GET['force_result_status']) && ( is_numeric( $_GET['force_result_status'] ) || empty( $_GET['force_result_status'] ) ) ) {
		$preview_status = is_numeric( $_GET['force_result_status'] ) ? (int)$_GET['force_result_status'] : '';
	} else {
		foreach ($status_slugs as $statusValue => $slug) {
			if ($page_id == corona_test_results_get_page_id( 'result_' . $slug )) {
				$preview_status = $statusValue;
				break;
			}
		}
	}

	$codeLength = $corona_test_results_cfg['code_length'];
	return (object)array(
		'code' => str_repeat('X', $codeLength),
		'status' => $preview_status
	);
}

/**
 * Shortcode to display the entered Code (either on its own or pre-formatted)
 */
function corona_test_results_sc_code( $atts, $content = null ) {
	global $corona_test_results_result;

	if ( isset( $_GET['preview'] ) ) {
		$corona_test_results_result = corona_test_results_get_preview_result();
	}

	$result = $corona_test_results_result;

	$states = corona_test_results_get_states();
	$state = __( 'Unknown', 'corona-test-results' );

	$stateColor = '#969696';
	$stateBGColor = '#fafafa';

	if ( isset( $result->status ) ) {
		switch((int)$result->status) {
			case 1:
				$stateColor = 'red';
				$stateBGColor = '#fff2f2';
				break;
			case 2:
				$stateColor = 'green';
				$stateBGColor = '#f8fad7';
				break;
			case 3:
				$stateColor = 'orange';
				$stateBGColor = '#fff2db';
				break;
		}
	}

	if ( isset( $result->status ) && isset( $states[$result->status] ) ) {
		$state = $states[$result->status];
	}

	$output = '<h2 style="text-align: center;padding: 15px 0;background-color:' . $stateBGColor . ';">'
		// translators: %s: entered code
		. sprintf( esc_html__( 'Status for your code %s:', 'corona-test-results' ), '<span style="font-family: monospace;letter-spacing:0.1em;font-size:1.2em;">' . esc_html( isset( $result->code ) ? $result->code : '' ) . '</span>' )
		. '<br>
	<span style="color:' . $stateColor . ';text-transform:uppercase;">' . $state . '</span></h2>';

	return $output;

}
add_shortcode('testresults_code', 'corona_test_results_sc_code');

/**
 * Handle the display of the result pages according to the status of the retrieved code
 */
function corona_test_results_handle_result_retrieval( $results ) {
	global $wpdb, $wp_query, $corona_test_results_result, $corona_test_results_result_error;

	if ( is_page()
		&& count($results) === 1
		&& $results[0]->ID === corona_test_results_get_page_id( 'result_retrieval' )
		&& !empty( $_POST )
		&& isset( $_POST['testresult_code'] )
	) {
		if ( isset( $_POST['corona_test_results_nonce'] ) && wp_verify_nonce( $_POST['corona_test_results_nonce'], 'corona_test_results_check_nonce' ) ) {
			$testresult_code = strtoupper( sanitize_key( $_POST['testresult_code'] ) );

			$input_error = false;

			$default_error = __( 'The code you entered is invalid. Please check that you typed it correctly.', 'corona-test-results' );
			$frequency_cap_error = __( 'The maximum number of requests has been exceeded. Please wait for some time before trying again.', 'corona-test-results' );

			require_once( 'lib/FrequencyCap.class.php' );

			// frequency capping for all code requests
			if ( ! $wpFrequencyCap::executeCappedActionMultilevel([
				[ 'code_queried_1', 25, 60, 60 * 5 ], // 25 requests in 60 seconds => 5 minutes ban
				[ 'code_queried_2', 8, 10, 60 * 60 * 4 ], // 8 requests in 10 seconds => 4 hours ban
			]) ) {
				$input_error = $frequency_cap_error;
			} else if (empty($testresult_code) || !preg_match(corona_test_results_get_code_regex(), $testresult_code)) {
				$input_error = $default_error;
			} else {

				$tableName = corona_test_results_get_table_name();
				$result = $wpdb->get_results ( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `code` = %s AND `trash` = 0 LIMIT 1", $testresult_code) );

				if (count($result) !== 1) {
					// frequency capping for invalid code requests
					if ( ! $wpFrequencyCap::executeCappedActionMultilevel([
						[ 'invalid_code_entered_1', 5, 60, 60 ], // 5 failed requests in 60 seconds => 1 minute ban
						[ 'invalid_code_entered_2', 10, 60 * 30, 60 * 60 ], // 10 failed requests in 30 minutes => 1h ban
						[ 'invalid_code_entered_3', 10, 15, 60 * 60 * 24 ], // 10 failed requests in 15 seconds => 24h ban
					])) {
						$input_error = $frequency_cap_error;
					} else {
						$input_error = $default_error;
					}
				} else {
					$result = $result[0];

					$corona_test_results_result = $result;

					$state_slugs = corona_test_results_get_states( true );

					$results[0]->post_content = '[testresults_code formatted]';

				}
			}

			$corona_test_results_result_error = $input_error;
		} else {
			wp_die( __( 'Request could not be verified', 'corona-test-results' ), __( 'Access denied', 'corona-test-results' ), array(
				'response' 	=> 403,
				'back_link' => true
			) );
		}

	}

	return $results;
}
// we don't use the admin_post hooks, because we want the user to be able to refresh the page without having to enter the code again
// add_action( 'admin_post_nopriv_corona_test_results_check', 'corona_test_results_handle_result_retrieval' );
// add_action( 'admin_post_corona_test_results_check', 'corona_test_results_handle_result_retrieval' );
add_filter( 'posts_results', 'corona_test_results_handle_result_retrieval' );

