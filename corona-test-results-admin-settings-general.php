<?php
( defined( 'ABSPATH' )
	&& is_admin()
	&& is_user_logged_in()
	&& (
		current_user_can( corona_test_results_get_required_capability( 'codes' ) )
		|| current_user_can( corona_test_results_get_required_capability( 'register' ) )
	)
) or die;

/**
 * register page
 */
function corona_test_results_register_settings() {
	global $corona_test_results_cfg;

    register_setting( 'corona_test_results_options', 'corona_test_results_options', array(
        'type' => 'array',
        'sanitize_callback' => 'corona_test_results_options_validate',
        'default' => $corona_test_results_cfg
    ));

	if (
		current_user_can( corona_test_results_get_required_capability( 'settings' ) )
		|| false
	) {
		$license_error = get_transient( 'corona_test_results_license_error' );

		$main_menu_slug = 'corona-test-results';

		if( ! current_user_can( corona_test_results_get_required_capability( 'codes' ) ) ) {
			$main_menu_slug .= '-register';
		}

		add_submenu_page(
			$main_menu_slug,
			vt_helper__default_i18n__( 'Settings'),
			vt_helper__default_i18n__( 'Settings' ) . ( $license_error ? " <span class='update-plugins count-1'><span class='update-count'>!</span></span>" : '' ),
			corona_test_results_get_required_capability( 'menu' ),
			'corona-test-results-settings',
			'corona_test_results_settings_page'
		);
	}
}
add_action( 'admin_menu', 'corona_test_results_register_settings' );

/**
 * add certificates section
 */
function corona_test_results_settings_add_section_certificates() {
	$isSettingsAdmin = current_user_can( corona_test_results_get_required_capability( 'settings' ) );

	$pageslug = 'corona_test_results_settings';

	$premiumLabel = corona_test_results_get_premium_label();

	$premiumlabelTag = ' ' . $premiumLabel;

	$section = 'vt_certificates';
	add_settings_section( $section, esc_html__('Certificates', 'corona-test-results') . $premiumlabelTag, function() use ( $isSettingsAdmin ) {
		if ( !$isSettingsAdmin ) {
			return;
		}

		if ( ! corona_test_results_check_crypto_activated() ) {
			echo '<div class="notice notice-info inline"><p>';
			// translators: link to the Security tab
			printf(
				__( 'In order to activate the generation of certificates, encryption functionality must be activated first. (see %s)', 'corona-test-results' ),
				'<a href="' . esc_url( "?page=" . sanitize_key( $_GET['page'] ) . "&tab=vt_security" ) . '">' . __( 'Security', 'corona-test-results' ) . '</a>'
			);
			echo '</p></div>';
		} else {
			echo '<p class"description">';
			echo nl2br( __(
				"With this functionality enabled, additional fields (e.g. address and contact details of the tested person) will be added to the test registration form. "
			  . "All personal data will be stored encrypted with a PIN that is written on the documents generated on test registration.\n"
			  . "A certificate containing the test result and personal data can be generated and optionally sent via email when assigning each test result. The PIN is needed for certificate generation by the testing personnel and also as a password for opening the certificate PDF file.",
			  'corona-test-results'
			) );
			echo '</p>';

			echo '<div class="notice notice-info inline"><p>';
			echo sprintf(
				esc_html__(
					// translators: opening and closing tags of mailto link
					'Do you need a customized certificate template, e.g. one provided by your city/state/country? %sSend us your PDF template%s and we\'ll make you an offer for a customized integration.',
					'corona-test-results'
				),
				'<a href="mailto:wordpress@48design.com?subject=customized%20certificate%20template">',
				'</a>'
			);
			echo '</p></div>';
		}
	}, $pageslug );

	if ( corona_test_results_check_crypto_activated() ) {
		if ( $isSettingsAdmin ) {
			add_settings_field( 'corona_test_results_opts_certificates_enabled', __('Enable integration', 'corona-test-results'), 'corona_test_results_opts_certificates_enabled', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_default', __('Request certificate by default', 'corona-test-results'), 'corona_test_results_opts_certificates_default', $pageslug, $section );
		}

		// settings added for non-admin users also have to be whitelisted in
		// corona_test_results_get_restricted_options()
		add_settings_field( 'corona_test_results_opts_certificates_conducted_by', __('"Conducted by" name', 'corona-test-results'), 'corona_test_results_opts_certificates_conducted_by', $pageslug, $section );
		add_settings_field( 'corona_test_results_opts_certificates_signatureimage', __('Signature Image', 'corona-test-results'), 'corona_test_results_opts_certificates_signatureimage', $pageslug, $section );

		if ( $isSettingsAdmin ) {
			add_settings_field( 'corona_test_results_opts_certificates_conducted_by_user_base',
				sprintf(
					// translators: %s: translated labels of settings above (1) 'Signature Image' (2) '"Conducted by" name'
					__( 'Base %s and %s on', 'corona-test-results' ),
					__('Signature Image', 'corona-test-results'),
					__('"Conducted by" name', 'corona-test-results')
				),
				'corona_test_results_opts_certificates_conducted_by_user_base',
				$pageslug,
				$section
			);

			add_settings_field( 'corona_test_results_opts_certificates_testdetailstpl', __('Test details template', 'corona-test-results'), 'corona_test_results_opts_certificates_testdetailstpl', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_tradenames', __('Trade names of test kits used', 'corona-test-results'), 'corona_test_results_opts_certificates_tradenames', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_testingsite', __('Testing site', 'corona-test-results') . ' ' . __('(designation, address, phone)', 'corona-test-results'), 'corona_test_results_opts_certificates_testingsite', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_stampimage', __('Stamp Image', 'corona-test-results'), 'corona_test_results_opts_certificates_stampimage', $pageslug, $section );

			add_settings_field( 'corona_test_results_opts_certificates_tb_dataprotection', __('Data protection notice', 'corona-test-results'), 'corona_test_results_opts_certificates_tb_dataprotection', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_tb_legaltext', __('Additional (legal) text', 'corona-test-results'), 'corona_test_results_opts_certificates_tb_legaltext', $pageslug, $section );

			add_settings_field( 'corona_test_results_opts_certificates_tb_mail_subject', __('Email subject', 'corona-test-results'), 'corona_test_results_opts_certificates_tb_mail_subject', $pageslug, $section );
			add_settings_field( 'corona_test_results_opts_certificates_tb_mail_text', __('Email text', 'corona-test-results'), 'corona_test_results_opts_certificates_tb_mail_text', $pageslug, $section );
		}
	}
}

/**
 * return an array of options that should hold valid image URLs of this WordPress instance,
 * (renaming is optional, and if not needed, key and value should be the same)
 */
function corona_test_results_get_image_options() {
	$userId = get_current_user_id();
	return array(
		'template_logoimage' => 'template_logoimage',
		'certificates_stampimage' => 'certificates_stampimage',
		'certificates_signatureimage_' . $userId => 'certificates_signatureimage',
	);
}

/**
 * return an array of options that should be renamed for per-user-setting
 */
function corona_test_results_get_renamed_options() {
	$userId = get_current_user_id();
	return array(
		'certificates_conducted_by_' . $userId => 'certificates_conducted_by',
	);
}

/**
 * settings validation
 */
function corona_test_results_options_validate( $input ) {
	global $corona_test_results_cfg, $corona_test_results_cfg_defaults;

	$options = corona_test_results_get_options();

    $output = $input;

	// sanitize image options
	$imageOptions = corona_test_results_get_image_options();
	foreach ( $imageOptions as $optionName => $renameKey ) {
		if ( isset( $output[ $optionName ] ) && ! corona_test_results_sanitize_local_image_url( $output[ $optionName ] ) ) {
			$output[ $optionName ] = '';
		}
	}

	// code length min / max (do not reset when the value is not even sent, e.g. after license deactivation)
	if ( isset( $input['code_length'] ) ) {
		if ((int)$input['code_length'] < CORONA_TEST_RESULTS_MIN_CODE_LENGTH) {
			$output['code_length'] = CORONA_TEST_RESULTS_MIN_CODE_LENGTH;
		} else if ((int)$input['code_length'] > CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH) {
			$output['code_length'] = CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH;
		} else {
			$output['code_length'] = (int)$input['code_length'];
		}
	}

	// checkbox handling (option name => tab name the checkbox appears on, so we can handle the off state correctly)
	$checkbox_options = array(
		'template_poweredby' => 'vt_pdf_template',
		'certificates_enabled' => 'vt_certificates',
		'certificates_default' => 'vt_certificates',
		'security_encryption_consent' => 'vt_security',
		'security_deletion_data' => 'vt_security',
		'security_deletion_key' => 'vt_security',
		'security_deletion_settings' => 'vt_security',
		'security_deletion_pages' => 'vt_security',
	);

	$bookingIntegrations = corona_test_results_get_supported_booking_integrations();
	foreach( $bookingIntegrations as $shortname => $integration ) {
		$checkbox_options[ 'booking_enabled_' . $shortname ] = 'vt_booking';
	}

	$currentTab = null;
	if( isset( $_POST['_wp_http_referer'] ) ) {
		parse_str( parse_url( $_POST['_wp_http_referer'], PHP_URL_QUERY ), $queryArgs );
		$currentTab = isset( $queryArgs[ 'tab' ]) ? preg_replace( '/--.*$/', '', $queryArgs[ 'tab' ] ) : null;
	}

	if ( !empty( $currentTab ) ) {
		foreach ( $checkbox_options as $option_name => $tab_name ) {
			if (
				$currentTab !== $tab_name
				|| (
					! current_user_can( corona_test_results_get_required_capability( 'settings' ) )
					&& ! in_array( $option_name, corona_test_results_get_restricted_options() )
				)
			) continue;
			$output[ $option_name ] = ( isset( $input[ $option_name ] ) && $input[ $option_name ] === 'on') ? 'on' : 'off';
		}
	}

	// label layout values
	if ( isset( $input['printlabel_width'] ) ) {
		if ( ! is_numeric( $input['printlabel_width'] ) || empty( $input['printlabel_width'] ) || $input['printlabel_width'] > 1000 || $input['printlabel_width'] < 0 ) {
			$output['printlabel_width'] = $corona_test_results_cfg_defaults['printlabel_width'];
		}
	}
	if ( isset( $input['printlabel_height'] ) ) {
		if ( ! is_numeric( $input['printlabel_height'] ) || empty( $input['printlabel_height'] ) || $input['printlabel_height'] > 1000 || $input['printlabel_height'] < 0 ) {
			$output['printlabel_height'] = $corona_test_results_cfg_defaults['printlabel_height'];
		}
	}
	if ( isset( $input['printlabel_fontsize'] ) ) {
		$intFloatFormat = str_replace( ',', '.', $input['printlabel_fontsize'] );
		if ( ! is_numeric( $intFloatFormat ) || empty( $input['printlabel_fontsize'] ) || $input['printlabel_fontsize'] > 50 || $input['printlabel_fontsize'] < 0 ) {
			$output['printlabel_fontsize'] = $corona_test_results_cfg_defaults['printlabel_fontsize'];
		} else {
			$output['printlabel_fontsize'] = $intFloatFormat;
		}
	}

	// once encryption consent has been given, it cannot be revoked, so the checkbox needs to always stay checked
	if (
		isset( $corona_test_results_cfg['security_encryption_consent'] )
		&& $corona_test_results_cfg['security_encryption_consent'] !== 'off'
	) {
		$output[ 'security_encryption_consent' ] = 'on';
	}

	if ( !empty( $input['license_key'] ) && ( !isset( $options['license_key'] ) || $options['license_key'] !== $input['license_key'] )) {
		$wp_response = wp_remote_post( 'https://shop.48design.com/vierachtdesign_licensing.php', array(
			'body' => array(
				'key' => trim($input['license_key']),
				'pid' => 'b05bd1e415c8eedd93ed10302f88be8457f73aaa23db1ebd13e06f155b063903', // data needed by the licensing validation process
				'uuid' => $_SERVER[ 'HTTP_HOST' ]
			)
		));

		$general_error = __( 'An error occurred while trying to verify your license key. Please try again in a short while.', 'corona-test-results' );
		$invalid_key_error = __( 'The key that you entered is invalid.', 'corona-test-results' );
		$different_uuid = __( 'This license is already registered to a different domain.', 'corona-test-results' );
		$not_verified = nl2br( sprintf(
			// translators: support link with text "contact us"
			__( "The activation request for the current domain could not be verified. This will usually happen on development servers that are not publically available.\nIf this is happening to you on a live domain, please %s to have this domain whitelisted!", 'corona-test-results' ),
			corona_test_results_get_support_mail_link( __( 'contact us', 'corona-test-results' ) )
		));
		$result_error = null;

		if ( is_wp_error( $wp_response ) ) {
			$result_error = $general_error . "<br>" . $wp_response->get_error_message();
		} else if ( empty($wp_response['body']) ) {
			$result_error = $general_error;
		} else {
			$response = json_decode( $wp_response['body'], true );
			if ( !isset( $response['success'] ) || true !== $response['success'] ) {
				$status = isset( $response['data'] ) && isset( $response['data']['status'] ) ? $response['data']['status'] : null;
				$status_code = isset( $response['code'] ) ? $response['code'] : null;
				switch ($status_code) {
					case 'license_already_activated_for_different_uuid':
						$result_error = $different_uuid;
						break;
					case 'request_server_not_verified':
						$result_error = $not_verified;
						break;
					default:
						switch ($status) {
							case 404:
								$result_error = $invalid_key_error;
								break;
							default:
								$result_error = $general_error;
						}
				}
			}
		}

		if ( !empty( $result_error ) ) {
			set_transient( 'corona_test_results_license_error', $result_error, 10 );
			unset( $output['license_key'] );
		}
	}

	if ( isset( $output['assignation_autotrash'] ) ) {
		$output['assignation_autotrash'] = (int)$output['assignation_autotrash'];

		if ( $output['assignation_autotrash'] < 0 ) {
			$output['assignation_autotrash'] = 0;
		}
	}

	if ( isset( $output['assignation_autodelete'] ) ) {
		$output['assignation_autodelete'] = (int)$output['assignation_autodelete'];

		if ( $output['assignation_autodelete'] < 0 ) {
			$output['assignation_autodelete'] = 0;
		}
	}

	if ( isset( $output['security_codes_access_users'] ) ) {
		if ( is_array( $output['security_codes_access_users'] ) ) {
			$output['security_codes_access_users'] = array_filter( array_map( 'intval', $output['security_codes_access_users'] ) );
		} else {
			$output['security_codes_access_users'] = array();
		}
	}

	if ( isset( $output['security_codes_register_access_users'] ) ) {
		if ( is_array( $output['security_codes_register_access_users'] ) ) {
			$output['security_codes_register_access_users'] = array_filter( array_map( 'intval', $output['security_codes_register_access_users'] ) );
		} else {
			$output['security_codes_register_access_users'] = array();
		}
	}

	// merge with existing settings object, so that options not existing in this form for whatever reason will not be deleted
	$output = array_merge($corona_test_results_cfg, $output);

    return $output;
}

function corona_test_results_get_restricted_options() {
	$userId = get_current_user_id();
	return array(
		'certificates_conducted_by_' . $userId,
		'certificates_signatureimage_' . $userId,
	);
}

function corona_test_results_settings_cap_filter( $allcaps, $cap, $args ) {
	$allowedKeys = corona_test_results_get_restricted_options();
	if (
		isset( $_POST['option_page'] ) && 'corona_test_results_options' === $_POST['option_page']
		&& isset( $_POST['corona_test_results_options'] )
	) {
		$_POST['corona_test_results_options'] = array_filter( $_POST['corona_test_results_options'], function($key) use ($allowedKeys) {
			return in_array( $key, $allowedKeys );
		}, ARRAY_FILTER_USE_KEY);
		$allcaps['manage_options'] = true;
	}
	// only apply this filter once, so we can access options.php but subsequent checks for the
	// capability - e.g. in corona_test_results_options_validate() - will return false again
	remove_filter( 'user_has_cap', 'corona_test_results_settings_cap_filter', 10, 3 );
	return $allcaps;
}

function corona_test_results_settings_allow_restricted_options_access() {
	add_filter( 'user_has_cap', 'corona_test_results_settings_cap_filter', 10, 3 );
}

if ( ! current_user_can( corona_test_results_get_required_capability( 'settings' ) ) ) {
	add_action( 'admin_init', 'corona_test_results_settings_add_section_certificates' );
	add_action( 'load-options.php', 'corona_test_results_settings_allow_restricted_options_access' );
}

function corona_test_results_opts_certificates_conducted_by() {
    $options = corona_test_results_get_options();

	$optionKey = 'certificates_conducted_by_' . get_current_user_id();

	$option_name = '';

	echo "<p><input id='corona_test_results_opts_conducted_by'
			name='$option_name'
			type='text' value='" . ( isset( $options[ $optionKey ] ) ? esc_attr( $options[ $optionKey ] ) :'' )
			. "' class='regular-text'>";
	echo "<p class='description'>"
		. esc_html__( "Should contain the name of the person conducting the test and, if applicable, the person supervising.", 'corona-test-results' )
		. '<br>'
		. esc_html__( "This and the next option will be available to all accounts with access to the plugin so set individually.", 'corona-test-results' )
	. "</p>";
}

function corona_test_results_opts_certificates_signatureimage() {
    $options = corona_test_results_get_options();

	$optionKey = 'certificates_signatureimage_' . get_current_user_id();

	$option_name = '';

	$currentUrl = isset( $options[$optionKey] ) ? $options[$optionKey] : '';

	?>
	<input id="corona_test_results_opts_certificates_signatureimage" data-vt-image-picker="<?php echo esc_attr( $currentUrl ) ?>" type="text" name="<?php echo $option_name ?>" value="<?php echo esc_attr( $currentUrl ) ?>" class="regular-text" />
	<p class="description">
		<?php printf(
			// translators: %s: 1) aspect ratio wrapped in <code></code> 2) width x height wrapped in <code></code>
			__( 'Aspect ratio about %s, optimal size %s or larger with same ratio. SVG or PNG with transparency preferred.', 'corona-test-results' ),
			'<code>4:1</code>',
			'<code>632x158</code>'
		); ?>
	</p>
	<?php
}

/**
 * check for user setting errors and display error messages
 */
function corona_test_results_user_settings_check_errors() {
    $options = corona_test_results_get_options();
	$messageCounter = 0;

}

/**
 * settings page contents
 */
function corona_test_results_settings_page() {
	global $wp_settings_sections, $wp_settings_fields;

	$options = corona_test_results_get_options();
    ?>
	<div class="wrap">
    <h1><?php esc_html_e('Test Results Settings', 'corona-test-results'); ?></h1>
	<hr class="wp-header-end">
	<?php
	corona_test_results_check_review_nag();

	if ( empty( $options['license_key'] ) ) {
		$premium_string = '<strong><em>Premium</em></strong>';
		$upsell_message = nl2br(
			esc_html__( "You are using the free version of the plugin.", 'corona-test-results')
			. ' ' . sprintf(
				// translators: %s: formatted string "Premium"
				esc_html__( "All %s settings will not be saved, and result pages will always display their default content.", 'corona-test-results' ),
				$premium_string
				)
			. '<br>' . sprintf(
				// translators: %s: (1) formatted string "Premium", (2,3) opening and closing link tags
				esc_html__( "%sGet the %s version%s in order to customize code generation, documents and result page contents, create certificates, integrate appointment bookings and more.", 'corona-test-results' ),
				'<a href="' . esc_url( corona_test_results_premium_shop_url() ) . '" target="_blank">',
				$premium_string,
				'</a>'
			)
		);
		echo "<div class='notice notice-warning inline'><p>$upsell_message</p></div>";
	}

	if ( function_exists( 'corona_test_results_settings_check_errors' ) ) {
		corona_test_results_settings_check_errors();
	}
	settings_errors( 'corona-test-results-settings' );
	?>
	<style>
		.premium-label {
			color: #aa4800;
			display: inline-block;
			font-style: italic;
			font-weight: 600;
		}
	</style>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'corona_test_results_options' );

		$page = 'corona_test_results_settings';

		$sections = ! empty( $wp_settings_sections ) && isset( $wp_settings_sections[ $page ] ) ? $wp_settings_sections[ $page ] : array();

		$isSubSection = false;
		$activeSection = null;

		if ( isset( $_GET['tab'] ) && isset( $sections[ sanitize_key( $_GET['tab'] ) ] ) ) {
			$activeSection = $sections[ sanitize_key( $_GET['tab'] ) ];

			$activeSectionBaseName = preg_replace( '/--.*$/', '', $activeSection['id'] );
			$isSubSection = $activeSectionBaseName !== $activeSection['id'];

			if ( $isSubSection ) {
				if ( isset( $sections[ sanitize_key( $activeSectionBaseName ) ] ) ) {
					$activeSection = $sections[ sanitize_key( $activeSectionBaseName ) ];
				}
			}
		}

		if ( ! $activeSection ) {
			$activeSection = reset( $sections );
		}

		if ( count( $sections ) > 1 ) {
			echo '<p>';

			$section_titles = array_filter( array_map( function( $section ) use ( $activeSection ) {

				$sectionBaseName = preg_replace( '/--.*$/', '', $section['id'] );
				$isSubSection = $sectionBaseName !== $section['id'];

				if ( $isSubSection ) {
					return null;
				}

				$tabParam = $section['id'];
				if ( $section['id'] !== 'vt_license' ) {
					$title = str_replace( corona_test_results_get_premium_label(), '', $section['title'] );
				} else {
					$title = $section['title'];
				}

				if ( $section !== $activeSection ) {
					$title = "<a href='" . esc_url( "?page=" . sanitize_key( $_GET['page'] ) . "&tab=$tabParam" ) . "'>$title</a>";
				} else {
					$title = "<strong>$title</strong>";
				}

				return $title;
			}, $sections ) );

			print implode( ' | ', $section_titles );

			echo '</p>';
		}

		$display_sections = array_filter( $sections, function( $section ) use ( $activeSection ) {
			$sectionBaseName = preg_replace( '/--.*$/', '', $section['id'] );
			return $sectionBaseName === $activeSection['id'];
		});

        if ( isset( $wp_settings_fields ) && isset( $wp_settings_fields[ $page ] ) ) {
			foreach ( $display_sections as $section ) {
				if ( $section['title'] ) {
					echo "<h2>{$section['title']}</h2>\n";
				}

				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}

				echo '<table class="form-table" role="presentation">';
				if ( isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					do_settings_fields( $page, $section['id'] );
				}
				echo '</table>';
			}
        }
		?>
        <input name="submit" class="button button-primary" type="submit" value="<?php echo esc_attr( vt_helper__default_i18n__( 'Save Changes' ) ); ?>" />
    </form>
	</div>
    <?php
}

/**
 * get the markup for the Premium label
 */
function corona_test_results_get_premium_label() {
	return '<span class="premium-label">Premium</span>';
}

function corona_test_results_settings_enqueue_media( $page ) {
	if ( $page === corona_test_results_admin_page_slug() ) {
		wp_enqueue_media();
		// wp_register_script('media-uploader', plugins_url('media-uploader.js' , __FILE__ ), array('jquery'));
		// wp_enqueue_script('jquery');
	}
}
add_action('admin_enqueue_scripts', 'corona_test_results_settings_enqueue_media');

function corona_test_results_settings_helptabs() {
	$screen = get_current_screen();

	if( $screen->id !== corona_test_results_admin_page_slug()) {
		return;
	}

	$screen->add_help_tab(array(
		'id' => 'corona-test-results_help-tab_video',
		'title' => 'Video',
		'content' => '<style>#contextual-help-columns .contextual-help-tabs { display: none; } #contextual-help-back { left: 0; } #tab-panel-corona-test-results_help-tab_video { text-align: center; }</style><iframe style="width: 48vw; aspect-ratio: 16/9;" src="'
			// translators: YouTube explanation video URL
			. __( 'https://www.youtube-nocookie.com/embed/buk8abJzbs0?start=22&cc_load_policy=1', 'corona-test-results' )
			. '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
	));
	// $screen->add_help_tab(array(
	// 	'id' => 'help_tab_faq-id',
	// 	'title' => 'FAQ',
	// 	'content' => '<p>A list of plugin faq</p>'
	// ));
	// $screen->set_help_sidebar('<p>External Links</p>');
}
add_action( 'load-' . corona_test_results_admin_page_slug(), 'corona_test_results_settings_helptabs' );
