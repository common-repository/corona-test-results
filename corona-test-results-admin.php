<?php
( defined( 'ABSPATH' )
&& is_admin()
&& is_user_logged_in()
&& (
	current_user_can( corona_test_results_get_required_capability( 'codes' ) )
	|| current_user_can( corona_test_results_get_required_capability( 'register' ) )
)
) or die;

global $corona_test_results_cfg;

/**
 * Generate a random number
 * @see https://stackoverflow.com/questions/1846202/php-how-to-generate-a-random-unique-alphanumeric-string-for-use-in-a-secret-l
 */
function corona_test_results_rand_secure($min, $max) {
    $range = $max - $min;
    if ($range < 1) return $min; // not so random...
    $log = ceil(log($range, 2));
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd > $range);
    return $min + $rnd;
}

/**
 * generate a random code of characters in our character table
 */
function corona_test_results_get_random_code($length)
{
    $codeAlphabet = corona_test_results_get_code_chars();

    $token = "";
    $max = strlen($codeAlphabet);

    for ($i=0; $i < $length; $i++) {
        $token .= $codeAlphabet[corona_test_results_rand_secure(0, $max-1)];
    }

    return $token;
}

/**
 * Checks if the table used by the plugin exists, and otherwise creates it.
 */
function corona_test_results_conditionally_create_table() {
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $tableName = corona_test_results_get_table_name();
    $main_sql_create = "CREATE TABLE $tableName (
        code varchar(" . CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH . ") NOT NULL,
        status tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
		status_changed timestamp NULL DEFAULT NULL,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		registration_user_id bigint(20) NULL DEFAULT NULL,
		trash tinyint(3) UNSIGNED NOT NULL DEFAULT 0,"
		.  ""
		. "
        PRIMARY KEY  (code)
    );";
	dbDelta( $main_sql_create );

}

/**
 * Register our admin pages in the menu and triggers enqueueing our styles and scripts
 */
function corona_test_results_add_test_results_menu() {
	$page_title = __('Test Result Assignation', 'corona-test-results');
	$menu_title = __('Test Results', 'corona-test-results');
	$capability_main = corona_test_results_get_required_capability( 'menu' );
	$capability_assign = corona_test_results_get_required_capability( 'codes' );
	$capability_register = corona_test_results_get_required_capability( 'register' );
	$menu_slug  = 'corona-test-results';
	$function   = 'corona_test_results_adminpage';
	$icon_url   = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( corona_test_results_plugin_dir_path() . 'assets/corona-test-results-admin-icon.svg' ) );
	$position   = 4;

	if ( ! current_user_can( $capability_assign ) ) {
		$menu_slug = 'corona-test-results-register';
		$function = 'corona_test_results_adminpage_register';
	}

	add_menu_page( $page_title, $menu_title, $capability_main, $menu_slug, $function, $icon_url, $position );
	add_submenu_page(
		$menu_slug,
		__('Test Result Assignation', 'corona-test-results'),
		__('Assign results', 'corona-test-results'),
		$capability_assign,
		'corona-test-results',
		'corona_test_results_adminpage'
	);
	add_submenu_page(
		$menu_slug,
		__('Register test', 'corona-test-results'),
		__('Register test', 'corona-test-results'),
		$capability_register,
		'corona-test-results-register',
		'corona_test_results_adminpage_register'
	);

	add_action( 'admin_enqueue_scripts', 'corona_test_results_assets' );
}
add_action( 'admin_menu', 'corona_test_results_add_test_results_menu' );

/**
 * Returns a human-readable and translatable label from the internal slugs of our different result pages
 */
function corona_test_results_get_page_name( $page_slug ) {
	$name = null;

	switch ($page_slug) {
		case 'result_retrieval':
			$name = __( 'Test Results Retrieval Page', 'corona-test-results' );
			break;
		case 'result_pending':
		case 'result_positive':
		case 'result_negative':
		case 'result_invalid':
			// translators: %s: test result status
			$name = sprintf( __( 'Result Page Content: %s', 'corona-test-results' ), corona_test_results_get_page_state( $page_slug ) );
			break;
		case 'quickcheckin':
			$name = __( 'Quick Check-In Page', 'corona-test-results' );
			break;
	};

	return $name;
}

/**
 * returns the slug prefix of admin pages, because hooks are using the translated value
 */
function corona_test_results_admin_page_slug($page = 'settings') {
	return sanitize_title(__('Test Results', 'corona-test-results')) . '_page_corona-test-results' . ( $page === '' ? '' : '-' ) . $page;
}

/**
 * returns the top level admin page slug, which differs depending on access rights
 */
function corona_test_results_admin_toplevel_page_slug( $hook ) {
	$prefix = 'toplevel_page_corona-test-results';

	if (substr($hook, 0, strlen($prefix)) == $prefix) {
		$subpage = trim( substr($hook, strlen($prefix)), '-' );
		return corona_test_results_admin_page_slug( $subpage );
	}

	return $hook;
}

/**
 * Enque all our styles and scripts when and where needed
 */
function corona_test_results_assets($hook) {
	$hook = corona_test_results_admin_toplevel_page_slug( $hook );

	if ( in_array( $hook, array( 'erase-personal-data.php', 'export-personal-data.php' ) ) ) {
		add_settings_error(
			'corona_test_results_privacy_erasure_hint',
			'corona_test_results_privacy_erasure_hint',
			sprintf(
				__( 'Data related to the %s plugin cannot be exported or erased using this WordPress core functionality, as the personal data (including the email address) is stored in encrypted form. You will need to identify the data by its unique code and export/delete it manually.', 'corona-test-results' ),
				corona_test_results_get_plugin_info()['Name']
			),
			'info'
		);
		return;
	}

	if(
		$hook === corona_test_results_admin_page_slug( '' )
		|| $hook === corona_test_results_admin_page_slug( 'register' )

	) {
		wp_register_script( 'corona-test-results-pdfmake', corona_test_results_plugin_dir_url() . 'assets/js/pdfmake/pdfmake.min.js', [], filemtime( corona_test_results_plugin_dir_path() . 'assets/js/pdfmake/pdfmake.min.js' ) );
		wp_enqueue_script( 'corona-test-results-pdfmake' );
		wp_register_script( 'corona-test-results-pdfmake-fonts', corona_test_results_plugin_dir_url() . 'assets/js/pdfmake/vfs_fonts_custom.min.js', [], filemtime( corona_test_results_plugin_dir_path() . 'assets/js/pdfmake/vfs_fonts_custom.min.js' ) );
		wp_enqueue_script( 'corona-test-results-pdfmake-fonts' );
	}

	/**
	 * custom scripts BEFORE
	 */
	if ( $hook === corona_test_results_admin_page_slug( '' ) ) {
		$scripts = apply_filters( 'corona_test_results_custom_scripts_admin_results_before', array() );
		for ( $i = 0; $i < count( $scripts ); $i++) {
			$handle = 'ctr-custom-results-before-' . $i;
			$pluginFile = array_keys( $scripts )[$i];
			$pluginScriptFile = array_values( $scripts)[$i];
			wp_register_script( $handle, plugin_dir_url( $pluginFile ) . $pluginScriptFile, [], filemtime( plugin_dir_path( $pluginFile ) . $pluginScriptFile ) );
			wp_enqueue_script( $handle );
		}
	} else if ( $hook === corona_test_results_admin_page_slug( 'register' ) ) {
		$scripts = apply_filters( 'corona_test_results_custom_scripts_admin_register_before', array() );
		for ( $i = 0; $i < count( $scripts ); $i++) {
			$handle = 'ctr-custom-register-before-' . $i;
			$pluginFile = array_keys( $scripts )[$i];
			$pluginScriptFile = array_values( $scripts)[$i];
			wp_register_script( $handle, plugin_dir_url( $pluginFile ) . $pluginScriptFile, [], filemtime( plugin_dir_path( $pluginFile ) . $pluginScriptFile ) );
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * main plugin scripts
	 */
	if( $hook === corona_test_results_admin_page_slug( '' ) || $hook === corona_test_results_admin_page_slug( 'register' ) ) {

		wp_register_script( 'corona-test-results', corona_test_results_plugin_dir_url() . 'assets/js/corona-test-results.js', [], filemtime( corona_test_results_plugin_dir_path() . 'assets/js/corona-test-results.js' ) );
		wp_enqueue_script( 'corona-test-results' );

		$filtered_cfg = array_filter(
			corona_test_results_get_options(),
			function ($key) {
				return
					   strpos($key, 'template_') === 0
					|| strpos($key, 'printlabel_') === 0
					|| strpos($key, 'certificates_') === 0
					|| $key === 'license_key'
					|| strpos($key, 'datatransfer_enable_') === 0
					|| ( strpos($key, 'datatransfer_') === 0 && strpos($key, '_clientside') === strlen($key) - strlen('_clientside') )
					|| $key === 'booking_futuredays'
				;
			},
			ARRAY_FILTER_USE_KEY
		);

		$filtered_cfg['license_key'] = !!$filtered_cfg['license_key'];

		// auto-switch image settings URL protocols
		// and optionally rename the option key
		$imageSettings = corona_test_results_get_image_options();
		foreach ( $imageSettings as $key => $imgSetting ) {
			if ( is_numeric( $key ) ) {
				$keyOut = $imgSetting;
			} else {
				$keyOut = $imgSetting;
				$imgSetting = $key;
			}

			$filtered_cfg[$keyOut] = isset( $filtered_cfg[$imgSetting] ) ? corona_test_results_sanitize_local_image_url( $filtered_cfg[$imgSetting] ) : '';
		}

		// additional renamed options
		$renamedOptions = corona_test_results_get_renamed_options();
		foreach ( $renamedOptions as $key => $renamedKey ) {
			if ( is_numeric( $key ) ) {
				$keyOut = $renamedKey;
			} else {
				$keyOut = $renamedKey;
				$renamedKey = $key;
			}

			$filtered_cfg[$keyOut] = isset( $filtered_cfg[$renamedKey] ) ? $filtered_cfg[$renamedKey] : '';
		}

		wp_localize_script( 'corona-test-results', 'corona_test_results',
			array(
				'resultsBaseUrl' => corona_test_results_get_page_url(),
				'premiumFeatureNotice' => __( 'This feature is only available in the Premium version of the plugin.', 'corona-test-results' ),
				'rows_loading' => __( 'Loading codes…', 'corona-test-results' ),
				'rows_loading_failed' => __( 'Failed fetching additional table rows!', 'corona-test-results' ),
				'columnHeaders' => array(
					__('Code', 'corona-test-results'),
					__('Status', 'corona-test-results'),
					__('Test Date', 'corona-test-results')
				),
				'stati' => corona_test_results_get_states(),
				'cfg' => $filtered_cfg,
				'documentTexts' => array(
					'onsite_notice' => __('[for on-site safekeeping!]', 'corona-test-results'),
					'labels' => array(
						'surname' => __('Surname', 'corona-test-results'),
						'firstname' => __('First Name', 'corona-test-results'),
						'dateofbirth' => __('Date of Birth', 'corona-test-results'),
						'testdate' => __('Test Date', 'corona-test-results'),
						'email' => __( 'Email address', 'corona-test-results' ),
						'tradename' => __('Test kit', 'corona-test-results' )
					),
					'pin' => __('PIN', 'corona-test-results'),
					'powered_by' => sprintf(__('Online test results powered by %s', 'corona-test-results'), '<strong>48DESIGN</strong>.com | VISIONS.PROGRAMMING.DESIGN')
				),
				'certificates_enabled' => false,
				'certificateTexts' => array(
					// translators: File name for the Certificate PDF, %s: test code; file extension is appended automatically
					'generate' => __( 'Generate certificate', 'corona-test-results' ),
					'filename' => __( 'Certificate_%s', 'corona-test-results' ),
					'nosave' => __( 'The personal data cannot be saved, because the tested person didn\'t request a certificate and consent to the storage of data when the test was taken.', 'corona-test-results' ),
					'sent' => __( 'Certificate has already been sent', 'corona-test-results' ),
					'before' => __( 'Certificate of a', 'corona-test-results' ),
					'status_inflected' => array(
						// translators: Inflected form: (Certificate of a) positive (antigen test result certified for)
						1 => __( 'positive', 'corona-test-results' ),
						// translators: Inflected form: (Certificate of a) negative (antigen test result certified for)
						2 => __( 'negative', 'corona-test-results' ),
						// translators: Inflected form: (Certificate of a) invalid (antigen test result certified for)
						3 => __( 'invalid', 'corona-test-results' ),
					),
					// translators: %s: (Certificate of a) positive (antigen test result certified for)
					'result' => __( '%s antigen test result', 'corona-test-results' ),
					'headline' => __( 'Certificate on the presence of a SARS-CoV-2 antigen test', 'corona-test-results' ),
					'labels' => array(
						'phone' => __( 'Phone number', 'corona-test-results' ),
						'phone_details' => __( '(and email address, if applicable)', 'corona-test-results' ),
						'certified_for' => __( 'certified for', 'corona-test-results' ),
						'conducted_by' => __( 'The test was conducted by', 'corona-test-results' ),
						'address' => __( 'Address', 'corona-test-results' ),
						'passport' => __( 'Passport number', 'corona-test-results' ),
						'address_details' => __( '(house no., street, zip code, city)', 'corona-test-results' ),
						'testingsite' => __( 'Testing Site', 'corona-test-results' ),
						'testingsite_details' => __( '(designation, address, phone)', 'corona-test-results' ),
						'fullname' => __( 'Full Name', 'corona-test-results' ),
						'tradename' => __( 'Trade name of test kit used', 'corona-test-results' ),
						'testdate' => __('Test date / time', 'corona-test-results'),
						'signature' => __( 'Signature', 'corona-test-results' ),
						'signature_details' => __( '(executing person)', 'corona-test-results' ),
						'stamp' => __( 'Stamp', 'corona-test-results' ),
						'stamp_details' => __( '(if available)', 'corona-test-results' ),
					),
					'dataprotection' => __('Data protection notice', 'corona-test-results'),
				),
				'appointments' => array(
					'none' => __( 'No booked appointments', 'corona-test-results' )
				),
				'table_icons' => array(
					'certificate' => array(
						'sent' => __( 'Certificate has already been sent', 'corona-test-results' ),
						'not_sent' => __( 'Certificate has not yet been sent', 'corona-test-results' )
					),
					'datatransfer' => array(
						'transferred' => __( 'Data transferred to %s', 'corona-test-results' ),
						'not_transferred' =>  __( 'Data not yet transferred to %s', 'corona-test-results' ),
						'integrations' => array(
							'cwa' => __( 'Corona-Warn-App', 'corona-test-results')
						)
					)
				),
				'license_inactive' => __( 'In order to use this Premium feature, you have to activate your license in the plugin settings first.', 'corona-test-results' ),
				'batch_override_date_max' => corona_test_results_batch_override_get_date_max_days(),
				'qrWorkerUrl' => isset( $qrWorkerUrl ) ? $qrWorkerUrl : '',
				// translators: Address format when reading ADR field without LABEL attribute from a vCard QR code. Available placeholders: %streetandnumber, %city, %postcode, %state, %country
				'address_template' => apply_filters( 'corona_test_results_address_template', __( "%streetandnumber\n%city, %state %postcode", 'corona-test-results' ) ),
				'qr' => array(
					'camera' => array(
						'back' => __( 'rear-facing camera', 'corona-test-results' ),
						'front' => __( 'front-facing camera', 'corona-test-results' )
					)
				),
				'_customize' => (object)array(
					'customfields_count' => corona_test_results_get_customfields_count(),
					'document' => null,
					'certificates' => null,
					'code_readonly' => apply_filters( 'corona_test_results_result_code_readonly', true ),
					'bookings' => array(),
					'export' => array(),
					'pins_disabled' => apply_filters( 'corona_test_results_pins_disabled', false ),
					'pins_bday' => apply_filters( 'corona_test_results_pins_bday', false ),
					'allow_cert_resend' => apply_filters( 'corona_test_results_allow_cert_resend', false ),
					'stati_disable_certs' => array_keys( array_filter( apply_filters( 'corona_test_results_filter_test_states_disable_certs', array() ) ) )
				),
				'nonces' => array(
					'fetch_rows' => wp_create_nonce( 'corona_test_results_fetch_rows_handler' ),
					'getdata' => wp_create_nonce( 'corona_test_results_ajax_getdata' ),
					'send_certificate' => wp_create_nonce( 'corona_test_results_ajax_send_certificate' ),
					'datatransfer_submit' => wp_create_nonce( 'corona_test_results_ajax_datatransfer_submit' ),
					'getcode' => wp_create_nonce( 'corona_test_results_ajax_getcode' ),
					'get_current_bookings' => wp_create_nonce( 'corona_test_results_ajax_get_current_bookings' ),
				)
			)
		);
	} else if ( $hook === corona_test_results_admin_page_slug( 'settings' ) ) {
		wp_register_script( 'corona-test-results-settings', corona_test_results_plugin_dir_url() . 'assets/js/corona-test-results-settings.js', [], filemtime( corona_test_results_plugin_dir_path() . 'assets/js/corona-test-results-settings.js' ) );
		wp_enqueue_script( 'corona-test-results-settings' );

		corona_test_results_create_upgrade_default_pages();

		$options = corona_test_results_get_options();

		wp_localize_script( 'corona-test-results-settings', 'corona_test_results_settings',
			array(
				'picker_btn_select' => __( 'Select Image', 'corona-test-results' ),
			)
		);
	}

	/**
	 * custom scripts AFTER
	 */
	if ( $hook === corona_test_results_admin_page_slug( '' ) ) {
		$scripts = apply_filters( 'corona_test_results_custom_scripts_admin_results_after', array() );
		for ( $i = 0; $i < count( $scripts ); $i++) {
			$handle = 'ctr-custom-results-after-' . $i;
			$pluginFile = array_keys( $scripts )[$i];
			$pluginScriptFile = array_values( $scripts)[$i];
			wp_register_script( $handle, plugin_dir_url( $pluginFile ) . $pluginScriptFile, [], filemtime( plugin_dir_path( $pluginFile ) . $pluginScriptFile ) );
			wp_enqueue_script( $handle );
		}
	} else if ( $hook === corona_test_results_admin_page_slug( 'register' ) ) {
		$scripts = apply_filters( 'corona_test_results_custom_scripts_admin_register_after', array() );
		for ( $i = 0; $i < count( $scripts ); $i++) {
			$handle = 'ctr-custom-register-after-' . $i;
			$pluginFile = array_keys( $scripts )[$i];
			$pluginScriptFile = array_values( $scripts)[$i];
			wp_register_script( $handle, plugin_dir_url( $pluginFile ) . $pluginScriptFile, [], filemtime( plugin_dir_path( $pluginFile ) . $pluginScriptFile ) );
			wp_enqueue_script( $handle );
		}
	}
}

/**
 * update the data for a code
 *
 */
function corona_test_results_ajax_update_codedata() {
	$code = sanitize_text_field($_POST['code']);
	$code = apply_filters( 'corona_test_results_filter_manual_code', $code );
	$code_update = ( ! apply_filters( 'corona_test_results_result_code_readonly', true ) ) && isset( $_POST['code_update'] ) ? sanitize_text_field($_POST['code_update']) : null;
	$code_update = apply_filters( 'corona_test_results_filter_manual_code', $code_update );
	$pin = sanitize_text_field($_POST['pin']);
	$meta = array();

	$meta['is_update'] = isset( $_POST['is_update'] ) && (int)$_POST['is_update'] === 1;

	corona_test_results_security_check_ajax_auth( array( 'register', 'codes' ), __FUNCTION__ . '-' . $code );

	if ( isset( $_POST['created_at'] ) ) {
		$meta['created_at'] = sanitize_text_field( $_POST['created_at'] );
	}

	if (
		! corona_test_results_check_crypto_activated()
		|| ! corona_test_results_check_certificates_enabled()
		|| ! preg_match( corona_test_results_get_code_regex(), $code )
		|| ( isset( $meta['created_at'] ) && ! ( ( $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $meta['created_at'] ) ) && $d->format( 'Y-m-d H:i:s' ) == $meta['created_at'] ) )
	) {
		wp_send_json_error( null, 400 );
	}

	if ( ! isset( $_POST[ 'certificate_data' ] ) || empty( $_POST[ 'certificate_data' ] )) {
		$data = array();
	} else {
		$data = $_POST[ 'certificate_data' ];
	}

	$returnData = corona_test_results_update_code_data( $code, $data, $pin, $meta );

	if ( !empty( $integrationErrors ) ) {
		wp_send_json_error( implode( "\n", $integrationErrors ), 500 );
	}

	if ( isset( $returnData['error'] ) && ! empty( $returnData['error'] ) ) {
		wp_send_json_error( $returnData, 500 );
	}

	wp_send_json_success( $returnData );
}
add_action( 'wp_ajax_corona_test_results_ajax_update_codedata', 'corona_test_results_ajax_update_codedata' );

function corona_test_results_get_entries( $stati, $offset = 0 ) {
	global $wpdb;

	$tableName = corona_test_results_get_table_name();

	$limitPhrase = '';

	if ( $offset > 0 ) {
		$limitPhrase = ' LIMIT ' . intval($offset) . ', 18446744073709551615';
	}

	$certRelatedFields = '';

	$dataRequested = apply_filters( 'corona_test_results_fetch_data_requested', false ) && is_ssl();
	$dataColumn = $dataRequested ? ', `data`' : '';
	// get codes in trash
	if ( count( $stati ) === 1 && $stati[0] === 'trash' ) {

		$rowsSQL = "SELECT `code`, `status`, `created_at`".$dataColumn.$certRelatedFields." FROM `$tableName` WHERE `trash` = 1 ORDER BY `created_at` DESC" . $limitPhrase;
	// get other codes
	} else {

		$rowsSQL = $wpdb->prepare("SELECT `code`, `status`, `created_at`,`registration_user_id`".$dataColumn.$certRelatedFields." FROM `$tableName` WHERE `status` IN(" . implode(', ', array_fill( 0, count( $stati ), '%d' ) ) . ") AND `trash` = 0 ORDER BY `created_at` DESC" . $limitPhrase, $stati );
	}

    $rowsQuery = $wpdb->get_results ( $rowsSQL );

	return $rowsQuery;
}

/**
 * Outputs the content of the test assignation admin page
 */
function corona_test_results_adminpage() {
    global $wpdb, $corona_test_results_cfg;

    if( !current_user_can( corona_test_results_get_required_capability( 'codes' ) ) ) {
        wp_die( __( 'Sorry, you are not allowed to access this page.' ) );
    }

    corona_test_results_conditionally_create_table();
    $tableName = corona_test_results_get_table_name();

	$page_param = '';
	$status_param = '';

	if ( isset( $_GET['page'] ) ) {
		$page_param = sanitize_key( $_GET['page'] );
	}

	if ( isset( $_GET['status'] ) ) {
		$status_param = sanitize_text_field( $_GET['status'] );
	}

    $filterStatus = ( is_numeric($status_param) ? (int)$status_param : $status_param ) ?: 0;

    $currentAttrs = ' class="current" aria-current="page"';

	$result_states = corona_test_results_get_states();
	$stati_disable_certs = array_keys( array_filter( apply_filters( 'corona_test_results_filter_test_states_disable_certs', array() ) ) );
	$evaluated_states = array_filter( array_filter( array_keys( $result_states ) ), function( $status ) use ( $stati_disable_certs ) {
		return ! in_array( $status, $stati_disable_certs );
	});

	$evaluated_states_key = implode( '|', $evaluated_states );

    $statusCount = array_combine( array_keys( $result_states ), array_fill(0, count( $result_states ), 0 ) );

    $statusCountQuery = $wpdb->get_results ( "SELECT status, COUNT(*) as count FROM `$tableName` WHERE `trash` = 0 GROUP BY `status` ORDER BY count DESC" );
    foreach ($statusCountQuery as $row) {
        $statusCount[$row->status] = $row->count;
    }

	// insert 'Evaluated' into second tab position
	$insertPosition = 1;
	$statusTabs = $result_states;
	$statusTabs = array_slice( $statusTabs, 0, $insertPosition, true )
					+ array($evaluated_states_key => __( 'Evaluated' , 'corona-test-results' ) )
					+ array_slice( $statusTabs, $insertPosition, count( $statusTabs ) - 1, true );
	$evaluatedCount = 0;
	foreach ( $evaluated_states as $state_int ) {
		$evaluatedCount += ( isset( $statusCount[ $state_int ] ) ? $statusCount[ $state_int ] : 0 );
	}
	$statusCount[$evaluated_states_key] = $evaluatedCount;

	$trashCountQuery = $wpdb->get_results ( "SELECT COUNT(*) as count FROM `$tableName` WHERE `trash` = 1" );
	$trashCount = $trashCountQuery[0]->count;

	if ( $trashCount ) {
		$statusTabs['trash'] = vt_helper__default_i18n__( 'Trash' );
		$statusCount['trash'] = $trashCount;
	}

	$certificate_sent_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="display:none"><path id="ctr_certificate_sent_icon" d="M3 4a2 2 0 00-2 1.8L11 12l10-6.2A2 2 0 0019 4zM1 7.8V18c0 1.1.9 2 2 2h9.8l-1.3-1.3a1 1 0 010-1.4l2.8-2.8a1 1 0 011.4 0l2.3 2.3 3-3v-6l-9.5 5.9a1 1 0 01-1 0zM22.6 15L18 19.6l-3-3-1.4 1.4 4.4 4.4 6-6z"/></svg>';
	echo $certificate_sent_icon;
	?>
	<div class="wrap" id="corona_test_results_assign">
		<h1>
			<?php esc_html_e('Test Result Assignation', 'corona-test-results'); ?>
		</h1>
		<hr class="wp-header-end">
		<?php
			if ( function_exists( 'corona_test_results_user_settings_check_errors' ) ) {
				corona_test_results_user_settings_check_errors();
			}

			settings_errors( 'corona-test-results-user-settings' );

			corona_test_results_check_review_nag();
		?>
        <ul class="subsubsub">
        <?php
            $n=0;
            foreach($statusTabs as $status => $tab) {
                $n++;
                ?>
            <li><a href="?page=<?php echo urlencode( $page_param ) ?>&status=<?php echo urlencode( $status ) ?>"<?php echo $filterStatus === $status ? $currentAttrs : '' ?>>
                <?php echo esc_html_e($tab, 'corona-test-results');
					$display_code_count = apply_filters( 'corona_test_results_display_code_count', true );
					if ( $display_code_count ) {
				?> <span class="count">(<?php echo $statusCount[$status] ?>)</span>
				<?php
					}
				?>
            </a><?php echo $n < count($statusTabs) ? ' |' : ''?></li>
                <?php
            }
        ?>
        </ul>

    <div class="tablenav top">
		<div class="alignleft">
			<label for="corona_test_results_search"><?php _e('Filter codes:', 'corona-test-results'); ?></label> <input type="search" id="corona_test_results_search" disabled>
			<span class="divider">|</span> <button type="button" id="corona_test_results_export" class="button button-secondary" disabled><?php _e('Export to CSV', 'corona-test-results'); ?></button>
		</div>

		<?php
			$itemsPerPage = $corona_test_results_cfg['assignation_entries_per_page'];
			$itemCount = isset( $statusCount[$filterStatus] ) ? $statusCount[$filterStatus] : 0;
			$pageCount = (int)ceil($itemCount / $itemsPerPage);
			$currentPage = 1;

			if ( isset( $_GET['paged'] ) ) {
				$currentPage = (int)$_GET['paged'];

				if ( $currentPage < 1 ) {
					$currentPage = 1;
				} else if ( $currentPage > $pageCount ) {
					$currentPage = $pageCount;
				}
			}

			if ($itemCount > $itemsPerPage) {

		?>
		<div class="tablenav-pages">
			<input type="hidden" id="pagination-items-per-page" value="<?php echo $itemsPerPage; ?>" />
			<span class="displaying-num"><?php echo sprintf( '%d ' . vt_helper__default_i18n__( 'items' ), $itemCount ); ?></span>
			<button type="button" class="first-page button" disabled><span class="screen-reader-text"><?php vt_helper__default_i18n_e( 'First page' ); ?></span><span aria-hidden="true">«</span></button>
			<button type="button" class="prev-page button" disabled><span class="screen-reader-text"><?php vt_helper__default_i18n_e( 'Previous page' ); ?></span><span aria-hidden="true">‹</span></button>
			<span id="table-paging" class="paging-input"><label for="current-page-selector" class="screen-reader-text"><?php vt_helper__default_i18n_e( 'Current Page' ); ?></label><input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($currentPage); ?>" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text"> <?php vt_helper__default_i18n_e( 'of' );  ?> <span class="total-pages"><?php echo $pageCount ?></span></span></span>
			<button type="button" class="next-page button"><span class="screen-reader-text"><?php vt_helper__default_i18n_e( 'Next page' ); ?></span><span aria-hidden="true">›</span></button>
			<button type="button" class="last-page button"<?php if( $pageCount < 3) { echo ' disabled'; } ?>><span class="screen-reader-text"><?php vt_helper__default_i18n_e( 'Last page' ); ?></span><span aria-hidden="true">»</span></button>
		</div>
		<?php
		}
		?>
    </div>

	<style>
		#corona_test_results_table code {
			background-color: transparent;
			letter-spacing: 0.1em;
			font-size: 1.1rem;
			display: inline-block;
		}

		#corona_test_results_table .column-date {
			white-space: normal;
			width: 30%;
		}

		#corona_test_results_table select {
			margin: 0 10px 0 10px;
		}

		@media screen and (max-width: 782px) {

			#corona_test_results_table td.column-primary code {
				padding: 0;
			}
			#corona_test_results_table .button-generate-certificate:not(.hidden) {
				padding-left: 0;
				padding-right: 0;
				display: block;
				width: max-content;
			}
		}

		@media screen and (min-width: 783px) {

			#corona_test_results_table tbody .check-column input {
				margin-top: 0.15em;
			}

			#corona_test_results_table select {
				margin-right: 1em;
			}
		}

		@media only screen and (max-width: 960px) {
			.tablenav.top {
				text-align: center;
			}

			.tablenav.top .alignleft {
				width: 100%;
			}

			.tablenav.top .alignleft label {
				display: block;
			}

			.tablenav.top .alignleft .divider {
				display: block;
				overflow: hidden;
				height: 5px;
			}

			#corona_test_results_export {
				margin-bottom: 2em;
			}
		}

		/* make the thickbox responsive... */
		#TB_window {
			width: auto !important;
			height: auto !important;
			margin-left: auto !important;
			margin-top: auto !important;
			transform: translate(-50%, -50%);
			min-width: 250px;
			min-width: min(250px, 100vw);
			max-width: 90vw;
			top: 50% !important;
		}

		#TB_ajaxContent {
			width: auto !important;
			height: auto !important;
			max-height: 80vh;
			overflow: auto;
		}

		#certificate-modal-inner.loading .notice {
			display: none;
		}
		#certificate-modal-inner.loading > * {
			visibility: hidden;
		}

		#certificate-modal-inner.loading::after,
		.button.loading[disabled]::after {
			display: inline-block;
			content: "";
			border: dotted 2px;
			width: 1em;
			height: 1em;
			border-radius: 50%;
			vertical-align: middle;
		}
		.button.loading[disabled]::after {
			margin-left: 0.5em;
			margin-top: -0.2em;
			animation: busy-rotate 2s linear infinite;
		}

		#certificate-modal-inner.loading::after {
			position: absolute;
			left: 50%;
			top: 60%;
			animation: busy-rotate-modal 2s linear infinite;
		}

		#corona_test_results_table .status {
			display: inline-block;
    		margin: 0 10px 0 10px;
			min-height: 30px;
    		line-height: 2.15384615;
		}

		#corona_test_results_table .status-icon {
			display: inline-block;
			width: 1.6em;
			height: 1.6em;
			vertical-align: middle;
			margin-right: 5px;
			fill: #666;
		}

		#corona_test_results_table .status-icon-wrapper {
			opacity: 0.4;
		}

		#corona_test_results_table .status-icon-wrapper--hidden {
			opacity: 0;
		}

		#corona_test_results_table .status-icon-wrapper--done {
			opacity: 1;
		}

		@keyframes busy-rotate {
			0% {
				transform: rotate(0deg);
			}

			100% {
				transform: rotate(360deg);
			}
		}
		@keyframes busy-rotate-modal {
			0% {
				transform: translate(-50%, -50%) rotate(0deg);
			}

			100% {
				transform: translate(-50%, -50%) rotate(360deg);
			}
		}

		#certificate-pin {
			text-align: center;
		}
		#certificate-pin .notice {
			text-align: left;
			text-align: initial;
			text-align: start;
		}

		#certificate-pin-input {
			letter-spacing: 0.1em;
			text-align: center;
		}

		#certificate-form .form-fields {
			display: flex;
			flex-wrap: wrap;
		}

		#certificate-form .form-fields > * {
  			box-sizing: border-box;
			width: 100%;
		}

		#certificate-form label {
			margin-top: 0.3em;
			font-weight: 700;
			align-self: flex-end;
		}

		#certificate-form fieldset {
			display: flex;
			align-items: flex-start;
		}
		#certificate-form fieldset > * {
			flex-grow: 1;
			max-width: 100%;
		}

		#certificate-form select {
			width: 100%;
		}

		@media screen and (min-width: 1200px) {
			#certificate-form .form-fields > * {
				width: 50%;
			}
			#certificate-form .form-fields label:nth-of-type(1),
			#certificate-form .form-fields label:nth-of-type(2) {
				order: 1;
			}
			#certificate-form .form-fields fieldset:nth-of-type(1),
			#certificate-form .form-fields fieldset:nth-of-type(2) {
				order: 2;
			}
			#certificate-form .form-fields label:nth-of-type(3),
			#certificate-form .form-fields label:nth-of-type(4) {
				order: 3;
			}
			#certificate-form .form-fields fieldset:nth-of-type(3),
			#certificate-form .form-fields fieldset:nth-of-type(4) {
				order: 4;
			}
			#certificate-form .form-fields label:nth-of-type(5),
			#certificate-form .form-fields label:nth-of-type(6) {
				order: 5;
			}
			#certificate-form .form-fields fieldset:nth-of-type(5),
			#certificate-form .form-fields fieldset:nth-of-type(6) {
				order: 6;
			}
			#certificate-form .form-fields label:nth-of-type(7),
			#certificate-form .form-fields label:nth-of-type(8) {
				order: 7;
			}
			#certificate-form .form-fields fieldset:nth-of-type(7),
			#certificate-form .form-fields fieldset:nth-of-type(8) {
				order: 8;
			}
			#certificate-form .form-fields label:nth-of-type(9),
			#certificate-form .form-fields label:nth-of-type(10) {
				order: 9;
			}
			#certificate-form .form-fields fieldset:nth-of-type(9),
			#certificate-form .form-fields fieldset:nth-of-type(10) {
				order: 10;
			}
			#certificate-form .form-fields label:nth-of-type(11),
			#certificate-form .form-fields label:nth-of-type(12) {
				order: 11;
			}
			#certificate-form .form-fields fieldset:nth-of-type(11),
			#certificate-form .form-fields fieldset:nth-of-type(12) {
				order: 12;
			}
			#certificate-form .form-fields label:nth-of-type(13),
			#certificate-form .form-fields label:nth-of-type(14) {
				order: 13;
			}
			#certificate-form .form-fields fieldset:nth-of-type(13),
			#certificate-form .form-fields fieldset:nth-of-type(14) {
				order: 14;
			}
			#certificate-form .form-fields label:nth-of-type(15),
			#certificate-form .form-fields label:nth-of-type(16) {
				order: 15;
			}
			#certificate-form .form-fields fieldset:nth-of-type(15),
			#certificate-form .form-fields fieldset:nth-of-type(16) {
				order: 16;
			}
			#certificate-form label:nth-of-type(odd),
			#certificate-form fieldset:nth-of-type(odd) {
				padding-right: 0.5em;
			}
			#certificate-form label:nth-of-type(even),
			#certificate-form fieldset:nth-of-type(even) {
				padding-left: 0.5em;
			}
			#certificate-form fieldset:last-of-type:nth-of-type(odd) {
				margin-right: 50%;
			}
		}
	</style>
    <form method="POST" action="<?php echo admin_url( 'admin-post.php' ) ?>" id="corona-test-results-form" autocomplete="off">
    <input type="hidden" name="action" value="corona_test_results_assign">
    <input type="hidden" name="from_status" value="<?php echo esc_attr( $filterStatus ) ?>">
    <input type="hidden" name="paged" value="<?php echo esc_attr( isset($_GET['paged']) ? (int)$_GET['paged'] : '1' ); ?>">
    <input type="hidden" name="corona_test_results_nonce" value="<?php echo wp_create_nonce( 'corona_test_results_assign_nonce' );  ?>" />
    <table id="corona_test_results_table" class="wp-list-table widefat fixed striped table-view-list pages">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php vt_helper__default_i18n_e( 'Select All' ) ?></label><input id="cb-select-all-1" type="checkbox"></td>
            <th scope="col" class="manage-column column-primary"><?php _e('Code', 'corona-test-results'); ?></th>
            <th scope="col" class="manage-column"><?php _e('Status', 'corona-test-results'); ?></th>
            <th scope="col" class="manage-column column-date"><?php _e('Test Date', 'corona-test-results'); ?></th>
        </tr>
        </thead>
        <tbody>
    <?php
	$stati = explode( '|', $filterStatus );

	$rowsQuery = corona_test_results_get_entries( $stati );

	$hasHiddenRows = false;

    if (empty($rowsQuery)) {
        print '<tr><td colspan="4">' . sprintf(esc_html__('There are no codes with a status of &quot;%s&quot; yet', 'corona-test-results'), $filterStatus === 'trash' ? vt_helper__default_i18n__( 'Trash' ) : $statusTabs[$filterStatus]) . '</td></tr>';
    } else {
		$rowNum = 0;
        foreach ($rowsQuery as $row) {
			$rowNum++;
			if ( $rowNum > $itemsPerPage ) {
				$hasHiddenRows = true;
				break;
			}
			$displayTime = date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime(get_date_from_gmt($row->created_at, 'Y-m-d H:i:s')) );
			$timestamp = get_date_from_gmt($row->created_at, 'Y-m-d H:i:s');

			$certificate_requested = isset( $row->certificate_requested ) && !!$row->certificate_requested;
			$certificate_sent = isset( $row->certificate_sent ) && $row->certificate_sent;
        ?>
        <tr class="is-expanded">
			<th scope="row" class="check-column">
				<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $row->code ) ?>"><?php
        			printf( vt_helper__default_i18n__( 'Select %s' ), $row->code );
				?></label>
				<input id="cb-select-<?php echo esc_attr( $row->code ) ?>" type="checkbox" name="code[]" value="<?php echo esc_attr( $row->code ) ?>">
			</th>
            <td class="column-primary"><code><?php echo esc_html($row->code) ?></code></td>
            <td data-colname="<?php esc_attr_e('Status', 'corona-test-results'); ?>"<?php
				if( isset( $row->nodata ) && $row->nodata ) { echo ' data-vt-nodata'; }
				if( $certificate_sent ) { echo ' data-vt-sent'; }
			?>>
			<?php
			$status_fixed = $certificate_sent;
			if ( property_exists( $row, 'certificate_requested' ) ) {
				$class = 'status-icon-wrapper--certificate';
				$attrs = ! $certificate_requested
						? ' class="status-icon-wrapper--hidden ' . $class . '" aria-hidden="true"'
						: ( $certificate_sent ? ' class="status-icon-wrapper--done ' . $class . '"' : ' class="status-icon-wrapper ' . $class . '"' );
				$label = ( $certificate_requested ? esc_attr(
					// translators: name of the data transfer integration
					$certificate_sent
					? __( 'Certificate has already been sent', 'corona-test-results' )
					: __( 'Certificate has not yet been sent', 'corona-test-results' )
				) : '' );
				echo '<span title="' .  $label . '" aria-label="' .  $label . '"' . $attrs . '><svg viewBox="0 0 24 24" class="status-icon"><use href="#ctr_certificate_sent_icon"></use></svg></span>';
			}

			if ( !$status_fixed && $filterStatus !== 'trash' ) {
				?><select name="corona_test_results_result_status[<?php echo esc_attr( $row->code ) ?>]">
            <?php
                foreach(corona_test_results_get_states() as $status => $label) {
                    ?>
                    <option value="<?php echo esc_attr( $status ) ?>"<?php echo (int)$row->status === (int)$status ? ' selected' : '' ?>><?php echo esc_html( $label ) ?></option>
                    <?php
                }
            ?>
            </select><?php
			} else {
				echo '<p class="status">' . esc_html( corona_test_results_get_states()[(int)$row->status] ) . '</p>';
			}

			?>
            </td>
            <td data-colname="<?php esc_attr_e('Test Date', 'corona-test-results'); ?>" data-datetime="<?php echo $timestamp; ?>"><?php echo $displayTime; ?></td>
        </tr>
        <?php
        }
    }
    ?>
        </tbody>
    </table>

	<?php
	$markAsNegativeLabel = sprintf(
		// translators: %s: Negative
		__( 'Mark as %s', 'corona-test-results' ),
		corona_test_results_get_states()[2]
	);
	?>
	<div class="tablenav bottom">
		<div class="alignleft actions bulkactions">
		<label for="bulkactions" class="screen-reader-text"><?php vt_helper__default_i18n_e( 'Select bulk action' ); ?></label><select name="bulkactions" id="bulkactions">
			<option value="-1"><?php vt_helper__default_i18n_e( 'Bulk actions' ); ?></option>
		<?php if ( $filterStatus !== 'trash' ) {
			if ( $filterStatus === 0 ) { ?>
			<option value="marknegative"><?php
				echo esc_html( $markAsNegativeLabel );
			?></option>
			<?php }
		?>
			<option value="trash"><?php vt_helper__default_i18n_e( 'Move to Trash' ); ?></option>
		<?php } else { ?>
			<option value="untrash"><?php vt_helper__default_i18n_e( 'Restore' ); ?></option>
			<option value="delete"><?php vt_helper__default_i18n_e( 'Delete permanently' ); ?></option>
		<?php } ?>
		</select>
		<input type="submit" id="dobulkaction" name="dobulkaction" class="button" value="<?php vt_helper__default_i18n_e( 'Apply' ); ?>">
		</div>
		<div class="alignleft actions" style="line-height: 200%;">
			<span style="margin-right: 10px;">|</span> <input type="submit" id="submit" class="button button-primary" value="<?php vt_helper__default_i18n_e('Save Changes'); ?>">
		</div>
		<br class="clear">
	</div>

	<p class="description">
	<?php
	if ( $filterStatus === 0 ) {
		printf(
			// translators: %s: "Mark as Negative"
			__( '&quot;%s&quot; will not trigger a database update on its own. You have to confirm this action by saving the changes.', 'corona-test-results' ),
			$markAsNegativeLabel
		);
		echo '<br>';
	}
	_e( 'Codes in the Trash cannot be retrieved from the frontend, but will not be used again when generating a new code. Delete codes permanently from the Trash in order to release them for future reuse.', 'corona-test-results' );
	?>
 	</p>

	<script>
	window.ctr_current_stati = <?php echo json_encode($stati); ?>;
	<?php
	if ( $hasHiddenRows ) {
	?>
	window.ctr_fetch_hidden_rows = <?php echo json_encode($stati); ?>;
	<?php
	}
	?>
	</script>
	<?php
	if ( $filterStatus !== 'trash' ) {
	?>
    <p class="submit">

    </p>
	<?php
	}
	?>
    </form>
	</div>
	<?php

}

/**
 * update the status of one or multiple codes
 */
function corona_test_results_assign_response() {
    global $wpdb;

    if( !current_user_can( corona_test_results_get_required_capability( 'codes' ) ) || !isset( $_POST['corona_test_results_nonce'] ) || !wp_verify_nonce( $_POST['corona_test_results_nonce'], 'corona_test_results_assign_nonce') ) {
        wp_die(__( 'Sorry, you are not allowed to access this page.' ));
    }

    if (
		( isset( $_POST['corona_test_results_result_status'] ) && count( $_POST['corona_test_results_result_status'] ) )
		|| ( isset( $_POST['corona_test_results_result_checked'] ) )
	) {
		$mysql_now = current_time( 'mysql', true );

		$tableName = corona_test_results_get_table_name();

		if( isset( $_POST['dobulkaction'] ) && isset( $_POST['bulkactions'] ) && $_POST['bulkactions'] != '-1' && isset( $_POST['corona_test_results_result_checked'] ) ) {
			$checkedCodes = json_decode( stripslashes( $_POST['corona_test_results_result_checked'] ), true );
			if ( is_array( $checkedCodes ) && count( $checkedCodes ) ) {
				switch ( sanitize_key( $_POST['bulkactions'] ) ) {
					// move to trash
					case 'trash':
						if ( isset( $_POST['from_status']) && $_POST['from_status'] !== 'trash' ) {
							// once support for older MySQL versions is dropped,
							// we can modify corona_test_results_conditionally_create_table()
							// and won't have to set `status_changed` manually here anymore.
							// (OR MAYBE WE WILL, BECAUSE OF THE ISSUES WITH TIME ZONES AND NOW()?)
							$prepareData = array_merge( [$mysql_now], $checkedCodes );
							$trashQuery = $wpdb->prepare("UPDATE `$tableName` SET `trash` = 1, `status_changed` = %s WHERE `code` IN(" . implode(', ', array_fill( 0, count( $checkedCodes ), '%s' ) ) . ")", $prepareData );
							$wpdb->query( $trashQuery );
						}
						break;
					// restore from trash
					case 'untrash':
						if ( isset( $_POST['from_status']) && $_POST['from_status'] === 'trash' ) {
							// once support for older MySQL versions is dropped,
							// we can modify corona_test_results_conditionally_create_table()
							// and won't have to set `status_changed` manually here anymore.
							// (OR MAYBE WE WILL, BECAUSE OF THE ISSUES WITH TIME ZONES AND NOW()?)
							$prepareData = array_merge( [$mysql_now], $checkedCodes );
							$untrashQuery = $wpdb->prepare("UPDATE `$tableName` SET `trash` = 0, `status_changed` = %s WHERE `code` IN(" . implode(', ', array_fill( 0, count( $checkedCodes ), '%s' ) ) . ")", $prepareData );
							$wpdb->query( $untrashQuery );
						}
						break;
						break;
					// delete permanently
					case 'delete':
						if ( isset( $_POST['from_status']) && $_POST['from_status'] === 'trash' ) {
							$inStatement = "`code` IN(" . implode(', ', array_fill( 0, count( $checkedCodes ), '%s' ) ) . ")";
							$delete = $wpdb->prepare("DELETE FROM `$tableName` WHERE `trash` = 1 AND $inStatement", $checkedCodes );
							$wpdb->query( $delete );

						}
						break;
				}
			}
		}

		if ( !isset( $_POST['corona_test_results_result_status'] ) ) {
			$_POST['corona_test_results_result_status'] = array();
		}

		if ( isset( $_POST['corona_test_results_result_status_hidden'] )
			&& ! empty( $_POST['corona_test_results_result_status_hidden'] )
		) {
			$additionalStateChanges = json_decode( stripslashes( $_POST['corona_test_results_result_status_hidden'] ), true );
			if ( !empty( $additionalStateChanges ) && is_array( $additionalStateChanges ) ) {
				// $_POST['corona_test_results_result_status'] = $_POST['corona_test_results_result_status'] + $additionalStateChanges;
				$_POST['corona_test_results_result_status'] = $additionalStateChanges;
			}
		}

		$statusChangeCount = count( $_POST['corona_test_results_result_status'] );
		if ( $statusChangeCount > 0 ) {
			$update_status_string = str_repeat( "WHEN `code` = %s THEN %d \n", $statusChangeCount );

			$codeListSanitized = array_map( function( $code ) {
				return strtoupper( sanitize_key( $code ) );
			}, array_keys( $_POST['corona_test_results_result_status'] ) );

			$certSentStatement = '';

			$update_stmt = "UPDATE `$tableName` SET
				`status` = (CASE
								$update_status_string
								ELSE `status`
							END
							),
				`status_changed` = %s
				WHERE $certSentStatement `code` IN (" . implode(',', array_fill( 0, count( $codeListSanitized ), '%s' ) ) . ")
				";

			$values = array();
			foreach ( $_POST['corona_test_results_result_status'] as $code=>$newStatus ) {
				$values[] = strtoupper( sanitize_key( $code ) );
				$values[] = (int)$newStatus;
			}
			$prepareData = array_merge( $values, [ $mysql_now ], $codeListSanitized );
			// $wpdb->show_errors = true;
			$wpdb->query( $wpdb->prepare( $update_stmt, $prepareData ) );
			// echo $wpdb->last_query;
			// exit;
		}
    }

    wp_safe_redirect(
        admin_url(
			'/admin.php?page=corona-test-results&status='
				. urlencode( sanitize_text_field( $_POST['from_status'] ) )
				. ( isset( $_POST['paged'] ) && (int)$_POST['paged'] > 1 ? '&paged=' . (int)$_POST['paged'] : '' )
		)
    );
    exit;
}
add_action( 'admin_post_corona_test_results_assign', 'corona_test_results_assign_response');

/**
 * returns the plugin's support email address
 */
function corona_test_results_get_support_mail() {
	return 'wordpress@48design.com';
}

/**
 * returns an HTML link to the plugin's support email address
 */
function corona_test_results_get_support_mail_link( $text = null ) {
	return '<a href="mailto:' . esc_attr( corona_test_results_get_support_mail() ) . '">' . ( $text ? $text : __( 'Support', 'corona-test-results' ) ). '</a>';
}
/**
 * returns an HTML link to the plugin's review page
 */
function corona_test_results_get_review_link( $prefix = '' ) {
	// translators: used as link text in the review notice ("... please take some time to _rate and review_ the plugin ...") as well as standalone on the plugin overview page and should fit both cases gramatically
	return "<a href='https://wordpress.org/plugins/corona-test-results/#reviews' target='_blank' rel='noopener'>" . $prefix . __( "rate and review", 'corona-test-results' ) . "</a>";
}

/**
 * returns the URL to the plugin installation page
 */
function corona_test_results_get_add_plugins_url( $show_upload_tab = true ) {
	$baseUrl = get_admin_url() . 'plugin-install.php';
	$settings_url = esc_url( $show_upload_tab ? add_query_arg(
		'tab',
		'upload',
		$baseUrl
	) : $baseUrl );

	return $settings_url;
}

/**
 * returns the URL of the plugin's settings page
 */
function corona_test_results_get_settings_url() {
	$settings_url = esc_url( add_query_arg(
		'page',
		'corona-test-results-settings',
		get_admin_url() . 'admin.php'
	) );

	return $settings_url;
}

/**
 * returns an HTML link to the settings page
 */
function corona_test_results_get_settings_link( $params = '', $text = null, $linkOnly = false ) {
	$settings_url = corona_test_results_get_settings_url();
	$settings_tab_url =  $settings_url . $params;
	if( $linkOnly ) {
		return $settings_tab_url;
	}

	$settings_page_link = '<a href="' . $settings_tab_url . '">' . ( $text ? esc_html( $text ) : esc_html__('Test Results Settings Page', 'corona-test-results') ) . '</a>';

	return $settings_page_link;
}

/**
 * Check whether all the required result pages are set
 */
function corona_test_results_check_all_pages_set() {
	return corona_test_results_get_page_id( 'result_retrieval' );
}

/**
 * show rate and review nag notice
 */
function corona_test_results_check_review_nag() {
	$isSettingsAdmin = current_user_can( corona_test_results_get_required_capability( 'settings' ) );
	if (
		$isSettingsAdmin
		&& !get_option('dismissed_corona_test_results_rating_nag', false )
		&& corona_test_results_check_review_nag_timestamp()
	) {
		echo "<div class='updated notice notice-info inline is-dismissible' data-dismiss-id='rating_nag'><p>"
		// translators: %s "rate and review" linking to the plugin review page on WordPress.com
		. nl2br(
			sprintf(
				__( "You have been using the Corona Test Results plugin for a while now. If you like it or have some feedback to share, please take a minute to %s the plugin.", 'corona-test-results' ),
				corona_test_results_get_review_link()
			)
		)
		. "</p></div>";
	}
}

function corona_test_results_check_review_nag_timestamp() {
	return (
		( $activationtime = get_option( 'corona_test_results_activationtime' ) )
		&& ( time() - $activationtime ) / 60 / 60 / 24 >= 14 // plugin activation has been at least 14 days ago
	);
}

function corona_test_results_batch_override_get_date_max_days( $format = 'days' ) {
	$max_days = 3;
	switch( $format ) {
		case 'days':
			return $max_days;
		case 'seconds':
			return 60 * 60 * 24 * $max_days;
		default:
			throw new \Exception( 'Invalid format parameter "' . $format . '"' );
	}
}

/**
 * Outputs the content for the test registration admin page
 */
function corona_test_results_adminpage_register() {
    corona_test_results_conditionally_create_table();
    $options = corona_test_results_get_options();

	$premium_string = '<strong><em>Premium</em></strong>';
	$upsell_message = nl2br(
		esc_html__( "You are using the free version of the plugin.", 'corona-test-results')
		. ' ' . sprintf(
			// translators: %s: (1) formatted string "Premium", (2,3) opening and closing link tags
			esc_html__( "%sGet the %s version%s in order to customize code generation, documents and result page contents, create certificates, integrate appointment bookings and more.", 'corona-test-results' ),
			'<a href="' . esc_url( corona_test_results_premium_shop_url() ) . '" target="_blank">',
			$premium_string,
			'</a>'
		)
	);
	echo "<div class='notice notice-warning inline'><p>$upsell_message</p></div>";

	if (preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) || preg_match('~Trident/7.0(; Touch)?; rv:11.0~',$_SERVER['HTTP_USER_AGENT'])) {
		echo "<div class='notice notice-warning'><p><strong>"
			. nl2br( __( "You are using an outdated browser that does not perform very well, especially during batch PDF generation. If you experience long processing times, freezing of the browser window or failing PDF generation, please switch to a modern browser.", 'corona-test-results' ) )
		. "</strong></p></div>";
	}

	if( ! current_user_can( corona_test_results_get_required_capability( 'register' ) ) ) {
		wp_die(__( 'Sorry, you are not allowed to access this page.' ));
	}

	$settings_page_link_code = corona_test_results_get_settings_link( '&tab=vt_code_generation' );
	$settings_page_link_pages = corona_test_results_get_settings_link( '&tab=vt_result_pages' );

	$general_error = null;

	corona_test_results_create_upgrade_default_pages();

	if (!corona_test_results_check_all_pages_set()) {
		// translators: %s: "Test Results Settings Page" with link
		$general_error = sprintf( esc_html__('Not all result pages are set. Please check the %s and select or create the pages for the result retrieval form and result contents.', 'corona-test-results'), $settings_page_link_pages );
	} else if (corona_test_results_get_page( 'result_retrieval' )->post_status !== 'publish') {
		$general_error = esc_html__('The test results retrieval page has not yet been published and will not be accessible to visitors of the provided URL.', 'corona-test-results');
	}
	?>
	<style>
		.wrap + .wrap {
			margin-top: 2em;
		}

		#submit[disabled]::after,
		#generate-pdf[disabled]::after,
		#generate-batch.loading[disabled]::after,
		#generate-label.loading[disabled]::after,
		#batch-export-csv[disabled]::after,
		#batch-export-pdf[disabled]::after,
		.ctr-custom-button.loading::after {
			display: inline-block;
			content: "";
			margin-left: 0.5em;
			border: dotted 2px;
			width: 1em;
			height: 1em;
			border-radius: 50%;
			vertical-align: middle;
			margin-top: -0.2em;
			animation: busy-rotate 2s linear infinite;
		}

		button > i.dashicons {
			vertical-align: middle;
			line-height: 80%;
		}

		body.ctr-qr-scanning-active {
			overflow: hidden;
		}
		body.ctr-qr-scanning-active::before {
			white-space: nowrap;
			content: "Reading Hardware Scanner Input...";
			position: fixed;
			top: 50%;
			left: 50%;
			font-size: 1.75em;
			transform: translate(-50%, -2em );
		}

		@keyframes spinner {
			to {transform: rotate(360deg);}
		}

		body.ctr-qr-scanning-active::after {
			content: '';
			box-sizing: border-box;
			position: fixed;
			top: 50%;
			left: 50%;
			width: 4em;
			height: 4em;
			margin-left: -2em;
			border-radius: 50%;
			border: 0.5em solid #ccc;
			border-top-color: #000;
			animation: spinner .6s linear infinite;
		}

		body.ctr-qr-scanning-active table.form-table {
			pointer-events: none;
			opacity: 0.25;
		}

		@keyframes busy-rotate {
			0% {
				transform: rotate(0deg);
			}

			100% {
				transform: rotate(360deg);
			}
		}
		<?php

		?>
	</style>
	<div id="message" class="error" style="display: <?php print !empty($general_error) ? 'block' : 'none' ?>">
		<p class="vt-error-ajax" style="display: none"><?php esc_html_e('An error occurred during code generation. Please try again.', 'corona-test-results'); ?></p>
		<p class="vt-error-codegen" style="display: none"><?php
			// translators: %s: "Test Results Settings Page" with link
			$settingsPageHint = sprintf( esc_html__('Please go to the %s and either increase the code length or reduce your block list.', 'corona-test-results'), $settings_page_link_code );
			if ( apply_filters( 'corona_test_results_result_code_readonly', true ) ) {
				print esc_html__('No valid code could be generated after multiple tries.', 'corona-test-results' )
					. ' ' . $settingsPageHint;
			} else {
				print esc_html__('The code that you entered either already exists, or there was an error entering it into the database.', 'corona-test-results' );
			}
		?></p>
		<p class="vt-error-codegen-batch" style="display: none"><?php
			print esc_html__('Not all codes could be generated after multiple tries.', 'corona-test-results')
				. ' ' . $settingsPageHint;
		?></p>
		<p class="vt-error-general" style="display: <?php print !empty($general_error) ? 'block' : 'none' ?>"><?php print $general_error; ?></p>
		<p class="vt-error-details"></p>
	</div>
	<div class="wrap" id="corona_test_results_register">
		<h1>
			<?php esc_html_e('Test Registration', 'corona-test-results'); ?>
		</h1>
		<hr class="wp-header-end">
		<?php
			if ( function_exists( 'corona_test_results_user_settings_check_errors' ) ) {
				corona_test_results_user_settings_check_errors();
			}

			settings_errors( 'corona-test-results-user-settings' );

			corona_test_results_check_review_nag();
		?>
		<table class="form-table" role="presentation" id="corona-test-results-form">
			<tbody>
			<tr id="qrscanner-row">
				<th scope="row"><?php esc_html_e('Scan from QR Code', 'corona-test-results' ); ?></td>
				<td>
					<?php
					$linkHref = '#';
					$thickBoxClass = '';
					?>
					<a href="<?php echo $linkHref; ?>" id="qrscanner" class="button button-primary<?php echo $thickBoxClass; ?>" title="<?php esc_attr_e('Scan from QR Code', 'corona-test-results' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:text-bottom" width="16" height="16" viewBox="0 0 24 24"><path d="M4 3a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V4a1 1 0 00-1-1zm7 0v2h2V3zm5 0a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V4a1 1 0 00-1-1zM5 5h2v2H5zm12 0h2v2h-2zm-6 2v2h2V7zm-8 4v2h2v-2zm4 0v2h2v-2zm4 0v2h2v-2zm2 2v2h2v-2zm2 0h2v-2h-2zm2 0v2h2v-2zm2 0h2v-2h-2zm0 2v2h2v-2zm0 2h-2v2h2zm0 2v2h2v-2zm-2 0h-2v2h2zm-2 0v-2h-2v2zm-2 0h-2v2h2zm0-2v-2h-2v2zm2 0h2v-2h-2zM4 15a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 00-1-1zm1 2h2v2H5z" fill="#fff"/></svg> <span><?php esc_html_e('Start Scan', 'corona-test-results' ); ?></span></a>
					<p class="description">
					<?php
						echo nl2br( esc_html__( "Scan vCard formatted data from a QR code. Some official contact tracing apps like &quot;Corona-Warn-App&quot; used in Germany support the creation of a QR code containing the tested person's personal data.\nYou can scan this data here instead of having to type in the data manually. (webcam or compatible scanning device required)", 'corona-test-results' ) );
						add_thickbox();
					?>
					</p>
					<?php

					?>
				</td>
			</tr>
			<?php

			?>
			<tr>
				<th scope="row"><label for="surname"><?php esc_html_e('Surname', 'corona-test-results') ?></label></th>
				<td><input name="surname" type="text" id="surname" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><label for="firstname"><?php esc_html_e('First Name', 'corona-test-results') ?></label></th>
				<td><input name="firstname" type="text" id="firstname" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dateofbirth"><?php esc_html_e('Date of Birth', 'corona-test-results') ?></label></th>
				<td><input name="dateofbirth" type="date" id="dateofbirth" class="" autocomplete="off"></td>
			</tr>
			<?php
				$customFieldNames = corona_test_results_get_custom_fields();
				foreach ( $customFieldNames as $fieldName) {
					if ( isset( $options['template_' . $fieldName] ) && !empty( $options['template_' . $fieldName] ) ) {
			?>
			<tr>
				<th scope="row"><label for="<?php echo $fieldName; ?>"><?php echo esc_html( $options['template_' . $fieldName] ) ?></label></th>
				<td><input name="<?php echo $fieldName; ?>" type="text" id="<?php echo $fieldName; ?>" class="regular-text" autocomplete="off"></td>
			</tr>
			<?php
					}
				}

			$readonly = apply_filters( 'corona_test_results_result_code_readonly', true );
			if ( !! $readonly ) {
				$readonlyAttrs = ' readonly placeholder="' . esc_attr('(generated automatically)', 'corona-test-results') . '"';
			} else {
				$readonlyAttrs = '';
			}
			?>
			<tr>
				<th scope="row"><label for="test_result_code"><?php esc_html_e('Code for retrieval', 'corona-test-results') ?></label></th>
				<td><input name="test_result_code" type="text" id="test_result_code" class="regular-text" autocomplete="off"<?php echo $readonlyAttrs ?>></td>
			</tr>
			<?php

			?>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" id="submit" class="button button-primary"><i class="dashicons dashicons-pdf" aria-hidden="true"></i> <?php esc_html_e('Generate code and PDF', 'corona-test-results') ?></button>
			<button type="button" id="generate-pdf" class="button button-primary" style="display: none"><i class="dashicons dashicons-pdf" aria-hidden="true"></i> <?php esc_html_e('Regenerate PDF', 'corona-test-results') ?></button>
			<button type="button" id="generate-label" class="button button-primary" style="display: none"><i class="dashicons dashicons-tag" aria-hidden="true"></i> <?php esc_html_e('Print label', 'corona-test-results') ?></button>
			<button type="button" id="reset-form" class="button" style="display: none"><i class="dashicons dashicons-undo" aria-hidden="true"></i> <?php esc_html_e('Register a new test', 'corona-test-results') ?></button>
		</p>
		<p id="privacy-hint" class="description"><?php
			$privacyLevelHint = __("The personal data is never sent to or stored on the server.", 'corona-test-results');

			echo nl2br(
				$privacyLevelHint . ' '
				. __( "Generation of the document is done completely in your browser.\nImmediately save or print out the document, as it will not be accessible in your browser at a later time.", 'corona-test-results' )
			);

		?></p>
		<p id="popup-hint" class="description" style="display: none"><?php _e('If the generated document does not open, set your browser to allow popups for this website.', 'corona-test-results'); ?></p>
	</div>

	<div class="wrap">
		<h1><?php _e( 'Batch generation', 'corona-test-results' ); ?></h1>
		<p class="description"><?php _e( 'Generate multiple codes and documents, e.g. if there\'s no printer at the testing location, or you make home visits for testing.', 'corona-test-results' ) ?>
		<br>
		<?php
			echo "<strong>" . esc_html__('Note:', 'corona-test-results')
			. "</strong> " . esc_html__( 'PDF generation can take quite some time or even fail for more than a few hundreds of codes. If you plan to export codes to PDF instead of CSV, use a reasonably sized batch size of about 250 and do multiple runs if you need a larger number of codes.', 'corona-test-results' );
		?>
		</p>

		<p><?php
			// translators: %s: input field for numeric value
			echo sprintf( __( 'Generate %s codes', 'corona-test-results'), '<input type="number" class="small-text" min="2" max="9999" value="2" maxlen="4" id="batch-count" />');
		?></p>

		<p><?php
			echo __( 'Override date:', 'corona-test-results' );
		?>
			<input type="date" id="batch-date-override" value="<?php echo date_i18n( 'Y-m-d' ); ?>" min="<?php echo date_i18n( 'Y-m-d' ); ?>" max="<?php echo date_i18n( 'Y-m-d', current_time( 'timestamp' ) + corona_test_results_batch_override_get_date_max_days( 'seconds' ) ); ?>" />
		<?php
			// translators: %d: maximum number of days
			echo sprintf( __( '(max. %d days into the future)', 'corona-test-results' ), corona_test_results_batch_override_get_date_max_days() );
		?></p>

		<p class="submit">
			<button type="button" id="generate-batch" class="button button-primary"><i class="dashicons dashicons-admin-page" aria-hidden="true"></i> <?php esc_html_e('Generate', 'corona-test-results') ?></button>
		</p>

		<p id="batch-actions" style="display: none;">
			<?php
			_e( 'Last generated batch:', 'corona-test-results' );
			?>
			<button type="button" id="batch-export-csv" class="button"><i class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></i> <?php esc_html_e('Export to CSV', 'corona-test-results') ?></button>
			<button type="button" id="batch-export-pdf" class="button"><i class="dashicons dashicons-pdf" aria-hidden="true"></i> <?php esc_html_e('Export to PDF', 'corona-test-results') ?></button>
		</p>
	</div>
	<?php
}

/**
 * returns a list of words that could be offending or otherwise undesirable in the generated codes,
 * including cases where numbers might look like a letter
 */
function corona_test_results_get_badword_list() {
	$blocklist = array(
		'123','18HH','18NS','18SS','4USCHW1TZ','88NS','88SS',
		'ABC','AFD','ARSCH','ARSE','ASDF','AUSCHW1TZ','AUSCHWITZ',
		'B4LLS','BALLS','B4ST4RD','B4STARD','BAST4RD','BASTARD','BL0WJ0B','BLAS','BLOWJ0B','BLOWJOB','BUMS','BUMS3N','BUMSEN','BUTT',
		'C0CK','CDU','COCK','CSU',
		'D00F','D0OF','D4CH4U','D4CHAU','DACH4U','DACHAU','DICK','DO0F','DOEDEL','DOOF','DRECK','DUMB','DUMM',
		'EKEL',
		'F1CK','FDP','FICK','FOTZE','FUCK',
		'G03BB3L','G03BBEL','G0EBB3L','G0EBBEL','G3N0Z1D','GEBER','GEN0Z1D','GEN0ZID','GENOZ1D','GENOZID','GOEBB3L','GOEBBEL',
		'HAESSL1CH','HAESSLICH','H0D3N','H0DEN','H1TL3R','H1TLER','H3IL','HEIL','HH18','HITL3R','HITLER','HOD3N','HODEN','HURE',
		'IDI0T','IDIOT',
		'JEW','JUDE',
		'K0TZ','K4CK','KACK','KOTZ',
		'MORD','MUERT','N1GG32',
		'N1GG3R','N1GGE2','N1GGER','N3G3R','N3GER','N4ZI','NAZI','NEG3R','NEGER','NIGG3R','NPD','NS18','NS88','NSAH','NSD4P','NSDAP','NUTTE',
		'P00P','P0OP','P0RN','P1MM3L','P1MMEL','P3N1S','P3NIS','PEN1S','PENIS','PENNER','PILLER','PIMM3L','PIMMEL','PIRAT','PO0P','POOP','POPEL','POPP','PORN','PR1CK','PRICK','PULLER','PUSSY','PUSSIE','PUSS1E',
		'QWERT',
		'S13G','S1EG','SACK','SAU','SCH31D3','SCH3ID3','SCH3IDE','SCHE1D3','SCHEIDE','SCHEIS','SCHWEIN','SEX','SH1T','SHIT','SI3G','SIEG','SS18','SS88','STERB','STERBEN','STIRB','SUCK',
		'T0SS3R','T0SSER','TITTEN','TOD','TOSS3R','TOSSER','TOT',
		'V1RG1N','V1RGIN','V4G1N4','V4GIN4','V4GINA','VAG1NA','VAGIN4','VAGINA','VERECK','VERRECK','VIRG1N','VIRGIN','VOTZE',
		'WANK','WANK3R','WANKER','WH0R3','WH0RE','WHOR3','WHORE','WICHS',
		'ZECKE'
	);

    $options = get_option( 'corona_test_results_options' );

	if (is_array($options) && isset($options['code_blocklist'])) {
		$blockStrings = preg_split("/[\n,\s]/", $options['code_blocklist']);
		$blockStrings = array_map('trim', $blockStrings);
		$blockStrings = array_filter($blockStrings, function($value) { return !is_null($value) && $value !== ''; });
		$blockStrings = array_map('strtoupper', $blockStrings);

		$blocklist = array_merge($blocklist, $blockStrings);
	}

	return $blocklist;
}

/**
 * check a generated code against our badwords list
 * and for easy-to-guess combinations
 */
function corona_test_results_code_hasBadWord($code) {
    // same character repeated more than 3 times
    if (preg_match("/(.)\\1{2,}/", $code)) {
        return true;
    }

    // same string repeated more than once
    if (preg_match("/(.+)\\1{1,}/", $code)) {
        return true;
    }

    $blockedWords = corona_test_results_get_badword_list();

    foreach ($blockedWords as $blockedWord) {
        if (strpos($code, $blockedWord) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * generate a new random code
 */
function corona_test_results_ajax_get_unique_code($recursion = 0, $forceDate = null) {
    global $corona_test_results_cfg, $wpdb;

	$manualCodesEnabled = ! apply_filters( 'corona_test_results_result_code_readonly', true );

    // give up after 10000 retries to avoid endless recursion freezing the process
    if ($recursion >= 10000 || ( $manualCodesEnabled && $recursion > 0 ) ) {
        return false;
    }

	if ( $manualCodesEnabled ) {
		$code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : null;
		$code = apply_filters( 'corona_test_results_filter_manual_code', $code );
		if ( preg_match( corona_test_results_get_code_regex(), $code ) ) {
			$randomCode = $code;
		} else {
			$randomCode = null;
		}
	} else {
		$randomCode = corona_test_results_get_random_code($corona_test_results_cfg['code_length']);
	}

    if ( ! $manualCodesEnabled && corona_test_results_code_hasBadWord($randomCode) ) {
        return corona_test_results_ajax_get_unique_code($recursion+1, $forceDate);
    }

    $tableName = corona_test_results_get_table_name();
    $result = $wpdb->get_results ( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `code` = %s LIMIT 1", $randomCode) );

    if (count($result)) {
        return corona_test_results_ajax_get_unique_code($recursion+1, $forceDate);
    }

    return $randomCode;
}

/**
 * get a new random code via Ajax
 */
function corona_test_results_ajax_getcode() {
    global $wpdb;

	corona_test_results_security_check_ajax_auth( 'register' );

	$additionalReturnData = array();

    corona_test_results_conditionally_create_table();
    $tableName = corona_test_results_get_table_name();

	$certificates_enabled = false;

	if ( isset( $_POST['batch'] ) ) {
		$batchCount = (int)$_POST['batch'];

		$batchDate = isset( $_POST['date'] ) && preg_match( "/^\d{4}-\d{1,2}-\d{1,2}$/", $_POST['date'] ) ? sanitize_key( $_POST['date'] ) : null;

		$result = array(
			'test_result_codes' => array()
		);

		if ($batchCount) {

			for ($n = 0; $n < $batchCount; $n++) {
				$newCode = corona_test_results_ajax_get_unique_code(0, $batchDate);
				if ($newCode) {
					$codeData = array(
						'code' => $newCode
					);

					$result['test_result_codes'][] = $codeData;
				}
			}

			if ( count($result['test_result_codes']) !== $batchCount ) {
				$result['error'] = 1;
				wp_send_json_error($result, 500);
				exit;
			}

			$insert_lists = array_fill(0, $batchCount, '(%s, %s, %d' . ( $certificates_enabled ? ', %s' : '' ) . ')');
			$insert_lists_string = implode( ', ', $insert_lists );

			$values = array();
			$time_now = current_time('mysql', true);

			$timestamp = !empty($batchDate) ? $batchDate . ' 00:00:00': $time_now;

			foreach ( $result['test_result_codes'] as $uniqueCodeData ) {
				$values[] = $uniqueCodeData['code'];
				$values[] = $timestamp;
				$values[] = get_current_user_id();

			}

			$certRelatedFields = '';

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO `$tableName` (`code`,`created_at`,`registration_user_id`$certRelatedFields) VALUES $insert_lists_string",
					$values
				)
			);

			$result['timestamp'] = $timestamp;
			$result['status'] = __( 'Pending', 'corona-test-results' );
		}
	} else {
		$uniqueCode = corona_test_results_ajax_get_unique_code();
		$result = array(
			'test_result_code' => $uniqueCode
		);

		if (!$uniqueCode) {
			if ( ! apply_filters( 'corona_test_results_result_code_readonly', true ) ) {
				$code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : null;
				$code = apply_filters( 'corona_test_results_filter_manual_code', $code );
				if ( ! preg_match( corona_test_results_get_code_regex(), $code ) ) {
					$result['error'] = __( 'The code that you entered has an invalid format.', 'corona-test-results' );
				} else {
					$result['error'] = 1;
				}
			} else {
				$result['error'] = 1;
			}

			wp_send_json_error($result, 500);
			exit;
		}

		$new_code_timestamp = current_time('mysql', true);
		$result['created_at'] = get_date_from_gmt($new_code_timestamp, 'Y-m-d H:i:s');
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `$tableName` (`code`,`created_at`,`registration_user_id`) VALUES (%s, %s, %d) ",
				$uniqueCode,
				$new_code_timestamp,
				get_current_user_id()
			)
		);

		$additionalReturnData['update_nonce'] = wp_create_nonce( 'corona_test_results_ajax_update_codedata-' . $uniqueCode );
	}

	// we don't use wp_send_json_error() or wp_send_json_success() here,
	// because we might need to clean some data from memory after outputting it

	$result = array_merge( $result, $additionalReturnData );

	if ( ! headers_sent() ) {
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	}

	if ( isset( $result['error'] ) ) {
		if ( ! headers_sent() ) {
			status_header( 500 );
		}
		echo wp_json_encode( array( 'success' => false, 'data' => $result['error'] ) );
	} else {
		echo wp_json_encode( array( 'success' => true, 'data' => $result ) );
	}

	if ( $certificates_enabled ) {
		// free memory, if sodium_memzero() is implemented
		try {
			if ( isset( $uniqueCode ) ) { sodium_memzero( $uniqueCode ); }
			if ( isset( $pin ) ) { sodium_memzero( $pin ); }
			if ( isset( $result['test_result_code'] ) ) { sodium_memzero( $result['test_result_code'] ); }
			if ( isset( $result['test_result_codes'] ) ) {
				foreach ( $result['test_result_codes'] as $uniqueCodeData ) {
					if ( isset( $uniqueCodeData['code'] ) ) {
						sodium_memzero( $uniqueCodeData['code'] );
					}

					if ( isset( $uniqueCodeData['pin'] ) ) {
						sodium_memzero( $uniqueCodeData['pin'] );
					}
				}
			}
			if ( isset( $result['test_result_pin'] ) ) { sodium_memzero( $result['test_result_pin'] ); }
		} catch ( Exception $e ) { }
	}

	exit;
}
add_action( 'wp_ajax_corona_test_results_ajax_getcode', 'corona_test_results_ajax_getcode' );

function corona_test_results_ajax_send_certificate() {
	corona_test_results_security_check_ajax_auth( 'codes' );

	if (
		! isset( $_POST['to'] )
		|| ! isset( $_POST['code'] )
		|| ! isset( $_POST['attachmentData'] )
		|| ! isset( $_POST['attachmentFilename'] )
		|| ! is_email( $_POST['to'] )
		|| ! preg_match( corona_test_results_get_code_regex(), $_POST['code'] )
		|| 'JVBERi0' !== substr( $_POST['attachmentData'], 0, 7 ) // not a PDF
		|| '.pdf' !== substr( $_POST['attachmentFilename'], -4 ) // not a PDF
	) {
		wp_send_json_error( null, 400 );
	}

	global $wpdb;
	$tableName = corona_test_results_get_table_name();

	$allow_resend = apply_filters( 'corona_test_results_allow_cert_resend', false );
	$sentCheck = $allow_resend === true ? '' : 'AND `certificate_sent` = 0';

	$checkCodeSentStatusSQL = $wpdb->prepare( "SELECT `code` FROM `$tableName` WHERE `code` = %s AND `trash` = 0 $sentCheck LIMIT 1", $_POST['code'] );

    $codeData = $wpdb->get_results ( $checkCodeSentStatusSQL );
	if ( count( $codeData ) !== 1 ) {
		wp_send_json_error( null, 403 );
	}

	function corona_test_results_add_pdf_attachment( $phpmailer ) {
		remove_action( 'phpmailer_init', 'corona_test_results_add_pdf_attachment' );
		$phpmailer->addStringAttachment( base64_decode( $_POST['attachmentData'] ), $_POST['attachmentFilename'] );
	}
	add_filter( 'phpmailer_init', 'corona_test_results_add_pdf_attachment' );
	$options = corona_test_results_get_options();

	$bodyText = apply_filters( 'corona_test_results_certificate_mail_body',
		preg_replace( '/\{\{testingsite\}\}/', $options['certificates_testingsite'], $options['certificates_tb_mail_text'] )
	);

	$subject = apply_filters( 'corona_test_results_certificate_mail_subject',
		$options['certificates_tb_mail_subject']
	);

	add_filter( 'wp_mail_from_name', 'corona_test_results_mail_sender_name' );

	$mail_sent = wp_mail( $_POST['to'], $subject, $bodyText );

	remove_filter( 'wp_mail_from_name', 'corona_test_results_mail_sender_name' );

	if ( $mail_sent ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$tableName` SET `certificate_sent` = 1 WHERE `code` = %s",
				$_POST['code']
			)
		);
		wp_send_json_success();
	} else {
		wp_send_json_error( null, 500 );
	}
	exit;

}
add_action( 'wp_ajax_corona_test_results_ajax_send_certificate', 'corona_test_results_ajax_send_certificate' );

/**
 * get all rows for a given status
 */
function corona_test_results_fetch_rows_handler() {
	corona_test_results_security_check_ajax_auth( 'codes' );

	if ( isset( $_POST['stati'] ) && !empty( $_POST['stati'] ) && is_array( $_POST['stati'] ) ) {
		$stati = array();
		foreach( $_POST['stati'] as $id ) {
			$stati[] = $id === 'trash' ? $id : intval( $id );
		}

		$options = corona_test_results_get_options();

		$dataRequested = apply_filters( 'corona_test_results_fetch_data_requested', false );

		$entries_per_page = $dataRequested ? 0 : $options['assignation_entries_per_page'];

		$entries = corona_test_results_get_entries( $stati, $entries_per_page );

		if ( !empty( $entries ) ) {
			$entries = array_map(function( $row ) use ( $dataRequested ) {
				$displayTime = date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime(get_date_from_gmt($row->created_at, 'Y-m-d H:i:s')) );
				$timestamp = get_date_from_gmt($row->created_at, 'Y-m-d H:i:s');

				$returnArray = apply_filters( 'corona_test_results_filter_fetch_return', array(
					'c' => $row->code,
					's' => (int)$row->status,
					't' => $timestamp,
					'd' => $displayTime/*,
					'ru' => (int)$row->registration_user_id*/
				), $row, $dataRequested );

				if ( isset( $row->nodata ) && $row->nodata ) {
					$returnArray['n'] = 1;
				}

				if ( property_exists( $row, 'certificate_requested' ) ) {
					$returnArray['cr'] = (int)$row->certificate_requested;
				}

				if ( isset( $row->certificate_sent ) && $row->certificate_sent ) {
					$returnArray['cs'] = 1;
				}

				return $returnArray;
			}, $entries);
		}

		// TODO: test if gzipping works reliably and doesn't cause any issues
		// if( ! ob_start("ob_gzhandler") ) ob_start();
		wp_send_json( $entries );
	}
}
add_action( 'wp_ajax_corona_test_results_fetch_rows', 'corona_test_results_fetch_rows_handler' );

/**
 * dismissable notices
 */
function corona_test_results_dismiss_notice_handler() {
    $id = sanitize_html_class( $_POST['id'] );
    update_option( 'dismissed_corona_test_results_' . $id, TRUE );
}
add_action( 'wp_ajax_corona_test_results_dismiss_notice', 'corona_test_results_dismiss_notice_handler' );

/**
 * display settings link in plugin overview
 */
function corona_test_results_settings_link($links) {
	$settings_link = '<a href="admin.php?page=corona-test-results-settings">' . esc_html( vt_helper__default_i18n__( 'Settings' ) ) . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter("plugin_action_links_" . corona_test_results__mainfile( true ), 'corona_test_results_settings_link' );

/**
 * display Premium link in plugin overview
 */
function corona_test_results_premium_link( $links, $file ) {
	$options = corona_test_results_get_options();

    if ( corona_test_results__mainfile( true ) == $file ) {

		foreach ( $links as $index => $link ) {
			if ( strip_tags( $link ) === vt_helper__default_i18n__( 'Visit plugin site' ) ) {
				unset($links[$index]);
				break;
			}
		}

		if ( !isset( $options['license_key'] ) || empty( $options['license_key'] ) ) {
			$row_meta = array(
			'premium'    => '<a href="' . esc_url( corona_test_results_premium_shop_url() ) . '" target="_blank" style="color:#aa4800;">Get <strong style="font-style:italic">' . esc_html( 'Premium' ) . '</strong></a>'
			);

			return array_merge( $links, $row_meta );
		}

    }
    return (array) $links;
}
add_filter( 'plugin_row_meta', 'corona_test_results_premium_link', 10, 2 );

/**
 * display Support link in plugin overview
 */
function corona_test_results_support_link( $links, $file ) {
	$options = corona_test_results_get_options();

    if ( corona_test_results__mainfile( true ) == $file ) {

		$row_meta = array();

		if ( isset( $options['license_key'] ) && !empty( $options['license_key'] ) ) {
			$row_meta['support'] = corona_test_results_get_support_mail_link();
		}

		if ( corona_test_results_check_review_nag_timestamp() ) {
			$row_meta['rate_and_review'] = corona_test_results_get_review_link( '★★★★★ ' );
		}

		return array_merge( (array) $links, $row_meta );
    }

    return (array) $links;
}
add_filter( 'plugin_row_meta', 'corona_test_results_support_link', 10, 2 );

/**
 * Get number of custom fields
 */
function corona_test_results_get_customfields_count() {
	return apply_filters( 'corona_test_results_customfields_count', 3 );
}

/**
 * get array of the custom field names
 */
function corona_test_results_get_custom_fields() {
	$cfCount = corona_test_results_get_customfields_count();
	$cfs = array();

	for ( $i = 1; $i <= $cfCount; $i++ ) {
		$cfs[] = 'customfield' . ( $i > 1 ? (string)$i : '' );
	}

	return $cfs;
}

/**
 * get array of supported booking integrations
 */
function corona_test_results_get_supported_booking_integrations() {
	global $wpdb;

	$integrations = array(
		'bookly' => array(
			'name' => 'Bookly',
			'mainfile' => 'bookly-responsive-appointment-booking-tool/main.php',
			'hint' => '<strong>' . __( 'Note:', 'corona-test-results' ) . '</strong> '
				. sprintf(
					esc_html__( '%sBookly Pro%s is required in order to save birth date and address with the booking.', 'corona-test-results' ),
					"<a href='https://1.envato.market/KeBoGn' target='_blank' rel='noopener'>",
					"</a>"
				),
			'db' => array(
				'table_appointments' => 'bookly_appointments',
				'table_payments' => 'bookly_payments',
				'table_data' => 'bookly_customers',
				'table_relation' => 'bookly_customer_appointments',
				'key_appointments' => 'appointment_id',
				'key_payments' => 'payment_id',
				'key_data' => 'customer_id',
				'fields_map' => array(
					'appointment_id' => 'appointment_id',
					'start' => 'start_date',
					'end' => 'end_date',
					'surname' => 'last_name',
					'firstname' => 'first_name',
					'phone' => 'phone',
					'email' => 'email',
					'dateofbirth' => 'birthday',
					'country' => 'country',
					'state' => 'state',
					'postcode' => 'postcode',
					'city' => 'city',
					'street' => 'street',
					'number' => 'street_number',
					'addition' => 'additional_address',
					'custom_fields' => 'custom_fields',
					'payment_status' => $wpdb->prefix . 'bookly_payments.status'
				)
			),
			'payment_status_filter' => function( $status_label ) {
				return $status_label !== 'completed' && $status_label !== null ? __( 'outstanding payment', 'corona-test-results' ) : '';
			}
		)
	);

	foreach( $integrations as $shortname => &$i ) {
		$i['id'] = $shortname;
		$i['is_active'] = is_plugin_active( $i['mainfile'] );
		$i['is_enabled'] = $i['is_active'] && corona_test_results_check_checkbox_option( 'booking_enabled_' . $shortname );
	}

	return $integrations;
}

function corona_test_results_security_check_ajax_auth( $capability_area = null, $action = null, $force_return = false ) {
	if ( empty( $action ) ) {
		$function = debug_backtrace()[1]['function'];
		$action = $function;
	}

	$invalid = false;

	$nonce_result = isset( $_REQUEST[ '_wpnonce' ] ) ? wp_verify_nonce( $_REQUEST[ '_wpnonce' ], $action ) : false;

	if ( $nonce_result !== 1 ) {
		$invalid = true;
	}

	if ( ! $invalid && ! empty( $capability_area ) ) {
		if ( ! is_array( $capability_area ) ) {
			$capability_area = [ $capability_area ];
		}

		// match at least one of the capabilities
		$invalid = true;
		foreach( $capability_area as $area ) {
			if ( current_user_can( corona_test_results_get_required_capability( $area ) ) ) {
				$invalid = false;
				break;
			}
		}
	}

	if ( $force_return === true ) {
		return ! $invalid;
	}

	if ( $force_return !== true && $invalid ) {
		wp_send_json_error( null, 403 );
	}
}

