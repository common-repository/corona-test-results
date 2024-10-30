<?php
( defined( 'ABSPATH' )
&& is_admin()
&& is_user_logged_in()
&& current_user_can( corona_test_results_get_required_capability( 'settings' ) )
) or die;

/**
 * check for setting errors and display error messages
 */
function corona_test_results_settings_check_errors() {
	$options = corona_test_results_get_options();

	$license_error = get_transient( 'corona_test_results_license_error' );
	$license_success = get_transient( 'corona_test_results_license_activated' );

	if ( $license_error ) {
		add_settings_error(
			'corona-test-results-settings',
			'corona-test-results-settings',
			$license_error,
			'error'
		);
		delete_transient( 'corona_test_results_license_error' );
	} else if ( !empty( $options['license_key'] ) ) {
		add_settings_error(
			'corona-test-results-settings',
			'corona-test-results-settings',
			__( 'Your license key was activated successfully!', 'corona-test-results' )
			. ' '
			. sprintf(
				// translators: %s: Link to the plugin upload page
				__( 'Visit the %s page in order to upgrade the plugin, uploading the file provided to you after purchase.', 'corona-test-results' ),
				'<a href="' . esc_attr( esc_url( corona_test_results_get_add_plugins_url() ) ) . '">' . vt_helper__default_i18n__( 'Add Plugins' ) . '</a>'
			),
			'success'
		);
	}/*%remove_light%*/ else if ( $license_success) {
		add_settings_error(
			'corona-test-results-settings',
			'corona-test-results-settings',
			$license_success,
			'success'
		);
	}/*%/remove_light%*/

	$result_pages = array( 'result_retrieval' );

	$duplicates = array();
	$messageCounter = 0;

	corona_test_results_create_upgrade_default_pages();

	foreach ( $result_pages as $page) {
		$pp = corona_test_results_get_page( $page, true );
		$pid = $pp && $pp->post_status !== 'trash' ? $pp->ID : 0;

		if ( empty( $pid ) ) {
			$message = null;

			if ( $pp && $pp->post_status === 'trash' ) {
				// translators: %s: result page name
				$message = __( 'The page for &quot;%s&quot; has been moved to trash.', 'corona-test-results' );
			} else if ( !empty( $options[ 'page_' . $page ] ) ) {
				// translators: %s: result page name
				$message = __( 'The page for &quot;%s&quot; does no longer exist.', 'corona-test-results' );
			} else {
				if( !empty( $options['license_key'] ) || $page === 'result_retrieval' ) {
					// translators: %s: result page name
					$message = __( 'The page for &quot;%s&quot; is not set.', 'corona-test-results' );
				}
			}

			if ( isset($message) && !empty( $message ) ) {
				$messageCounter++;
				add_settings_error(
					'corona-test-results-settings',
					'corona-test-results-settings-' . $messageCounter,
					sprintf(
						$message,
						corona_test_results_get_page_name( $page )
					),
					'error'
				);
			}

		} else foreach ( $result_pages as $page_compare) {
			if ( $page === $page_compare ) continue;

			if ( $pid === corona_test_results_get_page_id( $page_compare )
				&& !empty( $pid )
				&& !in_array( $page_compare, $duplicates )
			) {
				$duplicates[] = $page;

				$messageCounter++;
				add_settings_error(
					'corona-test-results-settings',
					'corona-test-results-settings-' . $messageCounter,
					// translators: %s: the two result page names that are set to the same page
					sprintf(
						__( '&quot;%s&quot; is set to the same page as &quot;%s&quot;.', 'corona-test-results' ),
						corona_test_results_get_page_name( $page_compare ),
						corona_test_results_get_page_name( $page )
					),
					'error'
				);
			}
		}
	}

}

/**
 * register setting page sections and options
 */
function corona_test_results_register_options() {
	$pageslug = 'corona_test_results_settings';
	$premiumLabel = corona_test_results_get_premium_label();

	$premiumlabelTag = ' ' . $premiumLabel;

	$section = 'vt_code_generation';
    add_settings_section( $section, esc_html__('Code Generation', 'corona-test-results') . $premiumlabelTag, 'corona_test_results_opts_code_description', $pageslug );
    add_settings_field( 'corona_test_results_opts_code_length', __('Code Length', 'corona-test-results'), 'corona_test_results_opts_code_length', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_code_blocklist', __('Additional block list', 'corona-test-results'), 'corona_test_results_opts_code_blocklist', $pageslug, $section );

	$section = 'vt_pdf_template';
	add_settings_section( $section, esc_html__('PDF Template', 'corona-test-results') . $premiumlabelTag, 'corona_test_results_opts_template_description', $pageslug );

	$customFieldNames = corona_test_results_get_custom_fields();
	foreach ( $customFieldNames as $i => $fieldName ) {
		add_settings_field(
			'corona_test_results_opts_template_' . $fieldName,
			__('Custom Field', 'corona-test-results') . ( $i > 0 ? ' ' . ( $i + 1 ) : ''),
			function() use ( $fieldName, $i ) {
					corona_test_results_opts_template_customfield( $fieldName, !$i );
			},
			$pageslug,
			$section
		);
	}

    add_settings_field( 'corona_test_results_opts_template_tb_topleft', __('Text block top left', 'corona-test-results'), 'corona_test_results_opts_template_tb_topleft', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_logoimage', __('Logo Image', 'corona-test-results'), 'corona_test_results_opts_template_logoimage', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_tb_topright', __('Text block top right', 'corona-test-results'), 'corona_test_results_opts_template_tb_topright', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_tb_salutation', __('Text block salutation', 'corona-test-results'), 'corona_test_results_opts_template_tb_salutation', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_tb_before', __('Text block before URL', 'corona-test-results'), 'corona_test_results_opts_template_tb_before', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_tb_after', __('Text block after URL', 'corona-test-results'), 'corona_test_results_opts_template_tb_after', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_tb_bottom', __('Text block bottom', 'corona-test-results'), 'corona_test_results_opts_template_tb_bottom', $pageslug, $section );

	add_settings_field( 'corona_test_results_opts_template_tb_bottom_page2', __('Additional text on page 2', 'corona-test-results'), 'corona_test_results_opts_template_tb_bottom_page2', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_template_poweredby', __('Show &quot;powered by&quot; Notice', 'corona-test-results'), 'corona_test_results_opts_template_poweredby', $pageslug, $section );

	$section = 'vt_printlabel';
	add_settings_section( $section, esc_html__('Label Layout', 'corona-test-results') . $premiumlabelTag, null, $pageslug );
	add_settings_field( 'corona_test_results_opts_printlabel_width', __('Width', 'corona-test-results'), 'corona_test_results_opts_printlabel_width', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_printlabel_height', __('Height', 'corona-test-results'), 'corona_test_results_opts_printlabel_height', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_printlabel_fontsize', __('Font size', 'corona-test-results'), 'corona_test_results_opts_printlabel_fontsize', $pageslug, $section );

	$section = 'vt_assignation';
	add_settings_section( $section, esc_html__('Assignation', 'corona-test-results') . $premiumlabelTag, null, $pageslug );
    add_settings_field( 'corona_test_results_opts_assignation_entries_per_page', __('Entries per page', 'corona-test-results'), 'corona_test_results_opts_assignation_entries_per_page', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_assignation_autotrash', __('Automatically move assigned codes to trash', 'corona-test-results'), 'corona_test_results_opts_assignation_autotrash', $pageslug, $section );
    add_settings_field( 'corona_test_results_opts_assignation_autodelete', __('Automatically delete trashed codes permanently', 'corona-test-results'), 'corona_test_results_opts_assignation_autodelete', $pageslug, $section );

	$section = 'vt_result_pages';
	add_settings_section( $section, esc_html__('Result Page & Content', 'corona-test-results'), 'corona_test_results_opts_pages_description', $pageslug );
	$result_pages = corona_test_results_get_result_pages_slugs();
	foreach ($result_pages as $result_page_slug) {
		$result_page_premiumlabel = ( $result_page_slug === 'result_retrieval' ) ? '' : ( '<br>' . $premiumlabelTag );
		add_settings_field( 'corona_test_results_opts_pages_' . $result_page_slug, corona_test_results_get_page_name( $result_page_slug ) . $result_page_premiumlabel, 'corona_test_results_opts_pages_' . $result_page_slug, $pageslug, $section );
	}

	corona_test_results_settings_add_section_certificates();

	$section = 'vt_booking';
	add_settings_section( $section, esc_html__( 'Appointment booking', 'corona-test-results' ) . $premiumlabelTag, 'corona_test_results_opts_booking_description', $pageslug );
	add_settings_field( 'corona_test_results_opts_booking_futuredays', __('Future appointments', 'corona-test-results'), 'corona_test_results_opts_booking_futuredays', $pageslug, $section );

	$section = 'vt_booking--integrations';
	add_settings_section( $section, esc_html__( 'Booking integrations', 'corona-test-results' ) . $premiumlabelTag, 'corona_test_results_opts_booking_integrations_description', $pageslug );
	$bookingIntegrations = corona_test_results_get_supported_booking_integrations();
	foreach ( $bookingIntegrations as $shortname => $integration ) {
		add_settings_field(
			'corona_test_results_opts_booking_enabled_' . $shortname,
			sprintf(
				// translators: %s: name of the booking tool
				__('Enable %s integration', 'corona-test-results'),
				$integration['name']
			),
			function() use ( $integration, $shortname ) {
				corona_test_results_opts_booking_enabled( $integration, $shortname );
			},
			$pageslug,
			$section
		);
	}

	$section = 'vt_quickcheckin';
	add_settings_section( $section, esc_html__( 'Quick Check-In', 'corona-test-results' ) . $premiumlabelTag, 'corona_test_results_opts_quickcheckin_description', $pageslug );
	add_settings_field( 'corona_test_results_opts_quickcheckin_page', corona_test_results_get_page_name( 'quickcheckin' ), 'corona_test_results_opts_quickcheckin_page', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_quickcheckin_check_email_repeat', __( 'Repeat email address input', 'corona-test-results' ), 'corona_test_results_opts_quickcheckin_check_email_repeat', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_quickcheckin_check_confirmation', __( 'Show additional checkbox', 'corona-test-results' ), 'corona_test_results_opts_quickcheckin_check_confirmation', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_quickcheckin_poster', __( 'Info Poster', 'corona-test-results' ), 'corona_test_results_opts_quickcheckin_poster', $pageslug, $section );

	$section = 'vt_datatransfer';
	add_settings_section( $section, esc_html__( 'Data Transfer', 'corona-test-results' ) . $premiumlabelTag, 'corona_test_results_opts_datatransfer_description', $pageslug );
	add_settings_field( 'corona_test_results_opts_datatransfer_cwa', __( 'Corona-Warn-App (Germany)', 'corona-test-results' ), 'corona_test_results_opts_datatransfer_cwa', $pageslug, $section );

	$section = 'vt_security';
	add_settings_section( $section, esc_html__( 'Security', 'corona-test-results' ), null, $pageslug );
	add_settings_field( 'corona_test_results_opts_security_codes_register_access_users', __( 'Users with access to code registration', 'corona-test-results' ), 'corona_test_results_opts_security_codes_register_access_users', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_security_codes_access_users', __( 'Users with access to code assignation', 'corona-test-results' ), 'corona_test_results_opts_security_codes_access_users', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_security_encryption_key', __( 'Encryption', 'corona-test-results' ), 'corona_test_results_opts_security_encryption_key', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_security_encryption_consent', __( 'Data Protection Consent', 'corona-test-results' ), 'corona_test_results_opts_security_encryption_consent', $pageslug, $section );
	add_settings_field( 'corona_test_results_opts_security_deletion', __( 'Data Deletion', 'corona-test-results' ), 'corona_test_results_opts_security_deletion', $pageslug, $section );

	$section = 'vt_license';
	// translators: %s: formatted string "Premium"
	add_settings_section( $section, sprintf( esc_html__('%s License Activation', 'corona-test-results'), $premiumLabel ), 'corona_test_results_opts_license_description', $pageslug );
	add_settings_field( 'corona_test_results_opts_license_key', __('License Key', 'corona-test-results'), 'corona_test_results_opts_license_key', $pageslug, $section );
}
add_action( 'admin_init', 'corona_test_results_register_options' );

/**
 * code generation options
 */
function corona_test_results_opts_code_description() {
    echo '<p>' . sprintf(esc_html__(
        'The codes for assignation and retrieval of test results are generated automatically using uppercase alphanumeric characters, with the exception of the easily confused letters and numbers %s.', 'corona-test-results'),
        '<code>O</code>, <code>0</code>, <code>I</code>, <code>1</code>'
        ) .
        '<br>' . esc_html__(
        'Some easy-to-guess combinations and an internal list of bad words will also be avoided, which further reduces the number of available combinations.', 'corona-test-results'
        ) . '</p>';
}

function corona_test_results_opts_code_length() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo "<input id='corona_test_results_opts_code_length' name='$option_name' type='number' value='" . esc_attr( $options['code_length'] ) . "' min='" . CORONA_TEST_RESULTS_MIN_CODE_LENGTH . "' max='" . CORONA_TEST_RESULTS_CODE_COLUMN_LENGTH . "' class='small-text' />";
}

function corona_test_results_opts_code_blocklist() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo '<p>'. esc_html__('If a generated code would include one of the strings entered in the field below (separated by comma, space or line break), that code will be discarded and a new one will be generated instead.', 'corona-test-results') . '<br>';
    echo '<strong>' . esc_html__('Note:', 'corona-test-results'). '</strong> ' . esc_html__('Blocking too many short strings or single characters can greatly reduce the number of available combinations or even result in failing code generation.', 'corona-test-results') . '</p>';
    echo "<textarea class='large-text code' id='corona_test_results_opts_code_blocklist' name='$option_name' rows='5' cols='50'>" . esc_html( $options['code_blocklist'] ) . "</textarea>";
}

/**
 * PDF template options
 */
function corona_test_results_opts_template_description() {
	$placeholders = array_merge( array(
		'surname',
		'firstname',
		'dateofbirth',
		'testdate'
	), corona_test_results_get_custom_fields() );

    echo '<p>' . esc_html__(
        'Change the text blocks and logo appearing on the generated PDF documents.', 'corona-test-results'
        ) . '<br>';
    echo esc_html__(
        'Available placeholders in text blocks:', 'corona-test-results'
        ) . ' <code>{{' . implode( '}}</code>, <code>{{', $placeholders ) . '}}</code></p>';
}

function corona_test_results_opts_template_customfield( $fieldName, $showDescription = true ) {
    $options = corona_test_results_get_options();

	$option_name = '';

	$value = isset( $options['template_' . $fieldName] ) ? $options['template_' . $fieldName] : '';

	echo "<p><input id='corona_test_results_opts_template_$fieldName' name='$option_name' type='text' value='" . esc_attr( $value ) . "' class='regular-text'></p>";
	if ( $showDescription ) {
		echo "<p class='description'>" . esc_html__( "Leave empty if you don't need an additional field, e.g. for a patient ID. Otherwise, this will be the label for the field in the test registration form.", 'corona-test-results' ) . "</p>";
	}
}

function corona_test_results_opts_template_tb_topleft() {
    global $corona_test_results_cfg_defaults;
	$options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_topleft'
			name='$option_name'>"
			. esc_html( $options['template_tb_topleft'] ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_topleft'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Usually used to display the test subject's data as entered in the test registration form.", 'corona-test-results' ) . ' ' . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_logoimage() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$currentUrl = isset( $options['template_logoimage'] ) ? $options['template_logoimage'] : '';

	?>
	<input id="corona_test_results_opts_template_logoimage" data-vt-image-picker="<?php echo esc_attr( $currentUrl ) ?>" type="text" name="<?php echo $option_name ?>" value="<?php echo esc_attr( $currentUrl ) ?>" class="regular-text" />
	<?php
}

function corona_test_results_opts_template_tb_topright() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_topright'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_topright' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_topright'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Usually used to provide information about the testing location, e.g. a medical practice's name and contact data.", 'corona-test-results' ) . ' ' . esc_html__( "Displayed on both the handout and the on-site record.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_salutation() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<p><input id='corona_test_results_opts_template_tb_salutation'
			name='$option_name'
			type='text' value='" . esc_attr( vt_helper__retranslate_option( 'template_tb_salutation' ) )
			. "' class='regular-text'>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_salutation'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' ) . "</button>";
	echo "<p class='description'>" . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_before() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_before'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_before' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_before'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_after() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_after'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_after' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_after'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_bottom() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_bottom'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_bottom' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_bottom'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Usually used for a data privacy statement.", 'corona-test-results' ) . ' ' . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_bottom_cert() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='7' class='regular-text' id='corona_test_results_opts_template_tb_bottom_cert'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_bottom_cert' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_bottom_cert'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" . esc_html__( "Usually used for a data privacy statement.", 'corona-test-results' ) . ' ' . esc_html__( "Displayed only on the handout for the tested person.", 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_tb_bottom_page2() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_template_tb_bottom_page2'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'template_tb_bottom_page2' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['template_tb_bottom_page2'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<p class='description'>" .
		sprintf(
			esc_html(
				// translators: %s: markup for underscore character wrapped in <code></code>
				__( "You can use this to add a text block to the bottom of the second page (for on-site safekeeping), e.g. to have the patient sign off on data protecion, consent, or anything else that may be required by applicable law or your local health authority. You can use multiple underscore characters (%s) to make a line for the signature.", 'corona-test-results' )
			),
			'<code>_</code>'
		) . " " . esc_html__( 'When this text block is not empty, the font size of the data table on the second page will be reduced in order to save space.', 'corona-test-results' ) . "</p>";
}

function corona_test_results_opts_template_poweredby() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$checked = ( isset( $options['template_poweredby'] ) && $options['template_poweredby'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_template_poweredby' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_template_poweredby'>" . esc_html__('Support plugin developer', 'corona-test-results') . "</label></p>";
}

/**
 * label layout options
 */
function corona_test_results_opts_printlabel_width() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo "<input id='corona_test_results_opts_printlabel_width' name='$option_name' type='number' value='" . esc_attr( $options['printlabel_width'] ) . "' min='1' max='1000' class='small-text' /> " . esc_html__( 'millimeters', 'corona-test-results' );
}

function corona_test_results_opts_printlabel_height() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo "<input id='corona_test_results_opts_printlabel_height' name='$option_name' type='number' value='" . esc_attr( $options['printlabel_height'] ) . "' min='1' max='1000' class='small-text' /> " . esc_html__( 'millimeters', 'corona-test-results' );
}

function corona_test_results_opts_printlabel_fontsize() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo "<input id='corona_test_results_opts_printlabel_fontsize' name='$option_name' type='number' value='" . esc_attr( $options['printlabel_fontsize'] ) . "' step='0.5' min='1' max='50' class='small-text' /> "
		// translators: font size unit for pdfmake
		. esc_html__( 'pt', 'corona-test-results' );
	echo "<p class='description'>"
		. esc_html__( 'Leave blank to determine automatically.', 'corona-test-results' )
		. "</p>";
}

/**
 * assignation page options
 */
function corona_test_results_opts_assignation_entries_per_page() {
    $options = corona_test_results_get_options();

	$option_name = '';

    echo "<input id='corona_test_results_opts_assignation_entries_per_page' name='$option_name' type='number' value='" . esc_attr( $options['assignation_entries_per_page'] ) . "' min='1' class='small-text' />";
}

function corona_test_results_opts_assignation_autotrash() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$input = "<input id='corona_test_results_opts_assignation_autotrash' name='$option_name' type='number' value='" . esc_attr( $options['assignation_autotrash'] ) . "' min='0' class='small-text' /> ";

	printf(
		__( 'after %s days', 'corona-test-results' ),
		$input
	);

	echo '<p class="description">'
		. __( 'Based on the time of the status change. 0 = deactivated', 'corona-test-results' )
		. '</p>';

}

function corona_test_results_opts_assignation_autodelete() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$input = "<input id='corona_test_results_opts_assignation_autodelete' name='$option_name' type='number' value='" . esc_attr( $options['assignation_autodelete'] ) . "' min='0' class='small-text' /> ";

	printf(
		__( 'after %s days', 'corona-test-results' ),
		$input
	);

	echo '<p class="description">'
		. __( 'Based on the time when the code was moved to trash. 0 = deactivated', 'corona-test-results' )
		. '</p>';

}

/**
 * result page and content pages
 */
function corona_test_results_opts_pages_description() {
    echo '<p>' . esc_html__(
        'The result page should contain a form for checking the test result for a given code. Its URL is displayed on the generated PDF document.', 'corona-test-results'
        ) . '<br>';
    echo esc_html__(
        'The content pages only provide the contents to be displayed depending on the test status and therefore do not have to be published. It is recomended to leave them in draft status, so they are not publicly accessible without entering a valid code.', 'corona-test-results'
        ) . '</p>';
}

function corona_test_results_opts_pages_result__helper( $page_slug ) {
    $options = corona_test_results_get_options();

	$option_name = ( $selected_page = ( $page_slug === 'result_retrieval' ) ? 'corona_test_results_options[page_' . $page_slug . ']' : '');
	$selected_page = ( $page_slug === 'result_retrieval' ) ? corona_test_results_get_page_id( $page_slug ) : 0;

	$result_pages_slugs = corona_test_results_get_result_pages_slugs();
	$is_result_page = in_array( $page_slug, $result_pages_slugs );

	$pages_selection = wp_dropdown_pages(
		array(
			'name'              => $option_name,
			'class'				=> 'vt-select-page',
			'show_option_none'  => vt_helper__default_i18n__( '&mdash; Select &mdash;' ),
			'option_none_value' => '0',
			'selected'          => $selected_page,
			'post_status'       => array( 'draft', 'publish' ),
		)
	);
	if ( !$pages_selection ) {
		echo '<p><select disabled><option>' . esc_html__( 'No pages available' ) . '</option></p>';
	} else {
		echo "<span style='" . (empty( $selected_page ) ? 'display:none;' : '') . "'><a href='" . esc_attr( get_site_url() . '?page_id=' . ($selected_page ? $selected_page : 0) . '&preview=true'
			. ( $is_result_page ? '&force_result_status=' . corona_test_results_get_page_state( $page_slug, true ) : '') )
			. "' target='_blank'>" . esc_html( vt_helper__default_i18n__( 'Preview' ) ) . "</a>";
		echo " | <a href='" . esc_attr( get_admin_url() . 'post.php?post=' . ($selected_page ? $selected_page : 0) . '&action=edit' ) . "' target='_blank'>" . esc_html( vt_helper__default_i18n__( 'Edit' ) ) . "</a></span>";
	}

	if ( 'result_retrieval' === $page_slug ) {
		echo '<p class="description">'
			. sprintf(
				// translators: %s: self-closing shortcode for the retrieval form
				esc_html__( 'Use the shortcode %s to embed the result retrieval form.', 'corona-test-results' ),
				'<code>[testresults_form]</code>'
			)
			. '<br>'
			. sprintf(
				// translators: %s: encapsulating shortcode for the retrieval form
				esc_html__( 'If you want to use a custom-styled input and button, put your markup inside %s, making sure to set the following attributes on the <input> element:', 'corona-test-results' ),
				'<code>[testresults_form open]&hellip;[testresults_form close]</code>'
			)
			. '<br><code>name="testresult_code" id="testresult_code"</code>'
			. ' '
			. sprintf(
				// translators: %s: (1) noerrors attribute (2) shortcode for form errors
				esc_html__( 'To influence where error messages appear, set the %s attribute on the form shortcode and use the shortcode %s where you want the error messages to appear.', 'corona-test-results' ),
				'<code>noerrors</code>',
				'<code>[testresults_errors]</code>'
			)
			. '</p>';
	} else if ( $is_result_page ) {
		echo '<p class="description">';
		echo sprintf(
			// translators: %s: (1,2) formatted shortcodes
			esc_html__( 'Use the shortcode %s to display the code that was entered, and %s to display a pre-formatted, color-coded box with the result.', 'corona-test-results' ),
			'<code>[testresults_code]</code>',
			'<code>[testresults_code formatted]</code>'
		);
		echo '</p>';
	} else if ( 'quickcheckin' === $page_slug ) {
		echo '<p class="description">';
		echo sprintf(
			// translators: %s: (1,2) formatted shortcodes
			esc_html__( 'Use the shortcode %s to display the form for generating the vCard QR code.', 'corona-test-results' ),
			'<code>[testresults_quickcheckin]</code>'
		);
		echo '</p>';
	}
}

function corona_test_results_opts_pages_result_retrieval() {
	corona_test_results_opts_pages_result__helper( 'result_retrieval' );
}

function corona_test_results_opts_pages_result_pending() {
	corona_test_results_opts_pages_result__helper( 'result_pending' );
}

function corona_test_results_opts_pages_result_positive() {
	corona_test_results_opts_pages_result__helper( 'result_positive' );
}

function corona_test_results_opts_pages_result_negative() {
	corona_test_results_opts_pages_result__helper( 'result_negative' );
}

function corona_test_results_opts_pages_result_invalid() {
	corona_test_results_opts_pages_result__helper( 'result_invalid' );
}

/**
 * certificates
 */
function corona_test_results_opts_certificates_enabled() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$checked = is_ssl() && ( isset( $options['certificates_enabled'] ) && $options['certificates_enabled'] === 'on' ) ? ' checked' : '';
	$disabled = is_ssl() ? '' : ' disabled';
	if ( ! is_ssl() ) {
		echo '<div class="notice notice-warning inline"><p>';
		echo __( 'To ensure data security, this functionality can only be used when accessed via HTTPS.', 'corona-test-results' );
		echo '</p></div>';
	}
	echo "<p><input type='checkbox' id='corona_test_results_opts_certificates_enabled' name='$option_name' value='on'$checked$disabled /> <label for='corona_test_results_opts_certificates_enabled'>" . esc_html__( 'Activate certificate generation functionality' , 'corona-test-results') . "</label></p>";
}

function corona_test_results_opts_certificates_default() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$checked = ( isset( $options['certificates_default'] ) && $options['certificates_default'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_certificates_default' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_certificates_default'>" . esc_html__( 'Automatically check the checkbox in the registration form to store personal data.' , 'corona-test-results') . "</label></p>";
	echo "<p class='description'>" . nl2br( esc_html__( "This should only be set if your testing location requires all tested persons to give their consent to data storage beforehand.\nOtherwise, keep this off and get the person's consent each time before checking the checkbox in the registration form.", 'corona-test-results' ) ) . "</p>";
}

function corona_test_results_opts_certificates_testingsite() {
	$options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_certificates_testingsite'
			name='$option_name'>"
			. esc_html( isset( $options['certificates_testingsite'] ) ? $options['certificates_testingsite'] : '' ) . "</textarea>";
}

function corona_test_results_opts_certificates_conducted_by_user_base() {
    $options = corona_test_results_get_options();

	$optionKey = 'certificates_conducted_by_user_base';
	$option_name = '';

	if ( ! isset( $options[ $optionKey ] )
		|| ! in_array( $options[ $optionKey ], array('registration', 'assignation' ) )
	) {
		$option_value = 'assignation';
	} else {
		$option_value = $options[ $optionKey ];
	}
	?>
	<label><input type="radio" name="<?php echo $option_name ?>" value="registration"<?php echo ( 'registration' === $option_value ? ' checked="checked"' : ''); ?>> <?php echo __( 'the user who registered the test', 'corona-test-results' ); ?></label><br>
	<label><input type="radio" name="<?php echo $option_name ?>" value="assignation"<?php echo ( 'assignation' === $option_value ? ' checked="checked"' : ''); ?>> <?php echo __( 'the user who sends the certificate', 'corona-test-results' ); ?></label><br>
	<?php
}

function corona_test_results_opts_certificates_testdetailstpl() {
	$options = corona_test_results_get_options();

	$option_name = '';

	$placeholders = corona_test_results_get_custom_fields();

	echo "<textarea rows='2' class='regular-text' id='corona_test_results_opts_certificates_testdetailstpl'
			name='$option_name' placeholder='" . esc_attr__( 'e.g.: Ct value E-Gen XX.XX, Ct value ORF1 XX.XX', 'corona-test-results' ) . "'>"
			. esc_html( isset( $options['certificates_testdetailstpl'] ) ? $options['certificates_testdetailstpl'] : '' ) . "</textarea>";
	echo "<p class='description'>"
		. nl2br( esc_html__( "This text will be displayed by default in the optional test details field in the certificate generation form.\nYou can use it for example if you always supply the Ct values with the result, so you just have to fill in the values instead of the whole text.", 'corona-test-results' ) )
		. '<br>'
		. esc_html__(
			'Custom fields are available as placeholders:', 'corona-test-results'
			) . ' <code>{{' . implode( '}}</code>, <code>{{', $placeholders ) . '}}</code>'
	. "</p>";
}

function corona_test_results_opts_certificates_tradenames() {
	$options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_certificates_tradenames'
			name='$option_name'>"
			. esc_html( isset( $options['certificates_tradenames'] ) ? $options['certificates_tradenames'] : '' ) . "</textarea>";
	echo "<p class='description'>" . esc_html__( "One trade name per line. These names will appear as a select list when generating a certificate, with the first one selected by default.", 'corona-test-results' ) . "</p>";

}

function corona_test_results_opts_certificates_stampimage() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$currentUrl = isset( $options['certificates_stampimage'] ) ? $options['certificates_stampimage'] : '';

	?>
	<input id="corona_test_results_opts_certificates_stampimage" data-vt-image-picker="<?php echo esc_attr( $currentUrl ) ?>" type="text" name="<?php echo $option_name ?>" value="<?php echo esc_attr( $currentUrl ) ?>" class="regular-text" />
	<p class="description">
		<?php printf(
			// translators: %s: 1) aspect ratio wrapped in <code></code> 2) width x height wrapped in <code></code>
			__( 'Aspect ratio about %s, optimal size %s or larger with same ratio. SVG or PNG with transparency preferred.', 'corona-test-results' ),
			'<code>3:1</code>',
			'<code>434x148</code>'
		); ?>
	</p>
	<?php
}

function corona_test_results_opts_certificates_tb_dataprotection() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_certificates_tb_dataprotection'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'certificates_tb_dataprotection' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['certificates_tb_dataprotection'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<div class='notice notice-info inline'><p>"
		. nl2br( esc_html__( "Depending on laws and regulations applicable to you, you may be legally required to state the actual legal basis and data protection regulations here.", 'corona-test-results' ) )
		. '<br>'
		. nl2br( esc_html__( "You should adapt this text block to completely reflect your local laws and regulatory requirements.", 'corona-test-results' ) )
	. "</p></div>";
}

function corona_test_results_opts_certificates_tb_legaltext() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_certificates_tb_legaltext'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'certificates_tb_legaltext' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['certificates_tb_legaltext'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
	echo "<div class='notice notice-info inline'><p>"
		. nl2br( esc_html__( "You should adapt this text block to completely reflect your local laws and regulatory requirements.", 'corona-test-results' ) )
	. "</p></div>";
}

function corona_test_results_opts_certificates_tb_mail_subject() {
    global $corona_test_results_cfg_defaults;
	$options = corona_test_results_get_options();

	$option_name = '';

	echo "<input type='text' class='regular-text' id='corona_test_results_opts_certificates_tb_mail_subject'
			name='$option_name' value='" . esc_attr( vt_helper__retranslate_option( 'certificates_tb_mail_subject' ) ) . "'>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['certificates_tb_mail_subject'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";
}

function corona_test_results_opts_certificates_tb_mail_text() {
    global $corona_test_results_cfg_defaults;
	$options = corona_test_results_get_options();

	$option_name = '';

	echo "<textarea rows='8' class='regular-text' id='corona_test_results_opts_certificates_tb_mail_text'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'certificates_tb_mail_text' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['certificates_tb_mail_text'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";

	echo "<p class='description'>"
		// translators: %s: <code>{{testingsite}}</code>
		. sprintf(
			esc_html__( 'You can use the placeholder %s to insert the content of the "testing site" field above.', 'corona-test-results' ),
			'<code>{{testingsite}}</code>'
		)
		. "</p>";
}

/**
 * Quick Check-In
 */
function corona_test_results_opts_quickcheckin_description() {
    echo '<p>' . esc_html__(
        'The Quick Check-In page helps to speed up the process of registering the personal data of the persons about to be tested. While they are wating for their turn, they can create a QR code containing their personal data, which can then be scanned by the testing personnel when registering the test.',
		'corona-test-results'
	) . '</p>';
}

function corona_test_results_opts_quickcheckin_page() {
	corona_test_results_opts_pages_result__helper( 'quickcheckin' );
}

function corona_test_results_opts_quickcheckin_check_email_repeat() {
    $options = corona_test_results_get_options();

	$option_name = '';

	$checked = ( isset( $options['quickcheckin_check_email_repeat'] ) && $options['quickcheckin_check_email_repeat'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_quickcheckin_check_email_repeat' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_quickcheckin_check_email_repeat'>" . esc_html__('Display and compare two email address inputs to prevent typos', 'corona-test-results') . "</label></p>";
}

function corona_test_results_opts_quickcheckin_check_confirmation() {
	global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	$option_name = '';

	$checked = ( isset( $options['quickcheckin_check_confirmation'] ) && $options['quickcheckin_check_confirmation'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_quickcheckin_check_confirmation' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_quickcheckin_check_confirmation'>" . esc_html__('Display a required checkbox with the following text, e.g. for data protection agreement', 'corona-test-results') . "</label></p>";

	$option_name = '';

	echo "<textarea rows='4' class='regular-text' id='corona_test_results_opts_quickcheckin_check_confirmation_text'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'quickcheckin_check_confirmation_text' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['quickcheckin_check_confirmation_text'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";

	echo '<p class="description">'
		. sprintf(
			// translators: list of allowed HTML tags
			__( 'Allowed HTML tags: %s', 'corona-test-results' ),
			'<code>&lt;strong&gt, &lt;em&gt, &lt;a&gt</code>'
		)
	. '</p>';

	echo "<div class='notice notice-info inline'><p>"
		. nl2br( esc_html__( "You should adapt this text block to completely reflect your local laws and regulatory requirements.", 'corona-test-results' ) )
	. "</p></div>";

}

function corona_test_results_opts_quickcheckin_poster() {
    global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();

	echo '<p class="description">'
		. __( 'You can print and put up this poster in the waiting area. It contains the URL and a QR code to the page set above.', 'corona-test-results' )
		. '<br>' . '<strong>' . esc_html__('Note:', 'corona-test-results'). '</strong> '
		. __( 'After changing the target page above, the settings have to be saved first before the new URL appears on the generated poster.', 'corona-test-results' )
	. '</p>';

	$option_name = '';

	echo "<p><strong>" . __( 'Headline', 'corona-test-results' ) . "</strong></p><p><input id='corona_test_results_opts_quickcheckin_poster_headline'
			name='$option_name'
			type='text' value='" . esc_attr( vt_helper__retranslate_option( 'quickcheckin_poster_headline' ) )
			. "' class='regular-text'>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['quickcheckin_poster_headline'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' ) . "</button>";

	$option_name = '';

	echo "<p><strong>" . __( 'Text block', 'corona-test-results' ) . "</strong></p>
			<textarea rows='4' class='regular-text' id='corona_test_results_opts_quickcheckin_poster_text'
			name='$option_name'>"
			. esc_html( vt_helper__retranslate_option( 'quickcheckin_poster_text' ) ) . "</textarea>
			<button type='button'
				onclick='this.previousElementSibling.value = \""
				. preg_replace( '/\n/', '\\\n', esc_attr__( $corona_test_results_cfg_defaults['quickcheckin_poster_text'], 'corona-test-results' ) )
				. "\";' class='button'>" . esc_html__( 'Reset To Default', 'corona-test-results' )
			. "</button>";

	$disabled = ' disabled';

	echo "<p>";
	echo "<button type='button' id='corona-test-results-quickcheckin-poster' class='button'$disabled>" . esc_html__( 'Print Poster', 'corona-test-results' ) . "</button>";
	echo "</p>";
}

/**
 * Data Transfer
 */
function corona_test_results_opts_datatransfer_description() {
    echo '<p>' . esc_html__(
        'Enable services or interfaces for data transfer, e.g. official contact tracing apps.',
		'corona-test-results'
	) . '</p>';

	echo "<div class='notice notice-warning inline'><p>"
		. nl2br( esc_html__( "Depending on laws and regulations applicable to you, you may be legally required to inform all tested persons about any data being shared with third parties and/or get their written consent by signing a privacy policy.", 'corona-test-results' ) )
	. "</p></div>";
	?>
<style>
.picker-wrapper {
	display: flex;
}
.picker-wrapper + .picker-error {
	color: red;
}
</style>
	<?php
}

function corona_test_results_opts_datatransfer_certsection__helper( $env, $option_value ) {
    $options = corona_test_results_get_options();

	$env_suffix = ( $env === 'test' ? 'wru' : $env );
?>
	<div data-vt-if-integration-enabled="<?php echo $env; ?>"<?php echo ( 'enable_' . $env !== $option_value ? ' style="display:none;" aria-hidden="true"' : '' ); ?>>

	<p>
	<?php
		$option_name = '';
		$option_value = isset( $options['datatransfer_cwa_' . $env . '_file_cer'] ) && is_file( $options['datatransfer_cwa_' . $env . '_file_cer'] ) ? $options['datatransfer_cwa_' . $env . '_file_cer'] : '';
	?>
	<label for="<?php echo $option_name; ?>"><strong><?php
		// translators %s: file extension
		echo esc_html( sprintf(
			__( 'Absolute path to %s file:' , 'corona-test-results' ),
			'.cer'
		) );
	?></strong></label><br>
	<span class="picker-wrapper"><input type="text" class="large-text" placeholder="/path/to/cwa_certificate-<?php echo $env_suffix; ?>.cer" value="<?php echo $option_value; ?>" name="<?php echo $option_name; ?>" id="<?php echo $option_name; ?>" data-vt-upload-picker='{"type":"application/x-x509-ca-cert","accept":".cer,application/x-x509-ca-cert","begin":"-----BEGIN CERTIFICATE-----","end":"-----END CERTIFICATE-----"}'></span>
	</p>

	<p>
	<?php
		$option_name = '';
		$option_value = isset( $options['datatransfer_cwa_' . $env . '_file_key'] ) && is_file( $options['datatransfer_cwa_' . $env . '_file_key'] ) ? $options['datatransfer_cwa_' . $env . '_file_key'] : '';
	?>
	<label for="<?php echo $option_name; ?>"><strong><?php
		// translators %s: file extension
		echo esc_html( sprintf(
			__( 'Absolute path to %s file:' , 'corona-test-results' ),
			'.key'
		) );
	?></strong></label><br>
	<span class="picker-wrapper"><input type="text" class="large-text" placeholder="/path/to/cwa_key-<?php echo $env_suffix; ?>.key" value="<?php echo $option_value; ?>" name="<?php echo $option_name; ?>" id="<?php echo $option_name; ?>" data-vt-upload-picker='{"accept":".key","begin":["-----BEGIN ENCRYPTED PRIVATE KEY-----","-----BEGIN RSA PRIVATE KEY-----"],"end":["-----END ENCRYPTED PRIVATE KEY-----","-----END RSA PRIVATE KEY-----"]}'></span>
	</p>

	<p>
	<?php
		$option_name = '';
		$option_value = isset( $options['datatransfer_cwa_' . $env . '_key_pass'] ) ? $options['datatransfer_cwa_' . $env . '_key_pass'] : '';
	?>
	<label for="<?php echo $option_name; ?>"><strong><?php esc_html_e( 'Passphrase for .key file:' , 'corona-test-results' ); ?></strong></label><br>
	<input type="password" autocomplete="new-password" class="regular-text" value="<?php echo $option_value; ?>" name="<?php echo $option_name; ?>" id="<?php echo 'datatransfer_cwa_' . $env . '_key_pass'; ?>">
	</p>

	<p><?php
	$option_name = '';

	$checked = ! isset( $options['datatransfer_cwa_' . $env . '_key_pass_clientside'] ) || corona_test_results_check_checkbox_option('datatransfer_cwa_' . $env . '_key_pass_clientside') ? ' checked' : '';
	echo "<input type='checkbox' id='corona_test_results_opts_datatransfer_cwa_${env}_key_pass_clientside' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_datatransfer_cwa_${env}_key_pass_clientside'>" . esc_html__('Store key passphrase clientside', 'corona-test-results') . "</label>";
	?>
	</p>

	<p class='description'>
	<?php esc_html_e( 'Storing the passphrase clientside instead of in the settings is more secure than storing all authentification details together on the server. You will have to enter the key passphrase in every new client when transferring the data for the first time.', 'corona-test-results' ); ?>
	</p>

	<div id="ctr-datatransfer-authcheck-cwa-<?php echo $env; ?>-checking" class="notice notice-info inline" style="display: none">
		<p><?php echo __( 'Verifying authentication data…', 'corona-test-results' ) ; ?></p>
	</div>
	<div id="ctr-datatransfer-authcheck-cwa-<?php echo $env; ?>-error" class="notice notice-error inline" style="display: none"><p></p></div>
	<div id="ctr-datatransfer-authcheck-cwa-<?php echo $env; ?>-success" class="notice notice-success inline" style="display: none"><p></p></div>

	</div>
<?php
}

function corona_test_results_opts_datatransfer_cwa() {

	if ( ! is_ssl() ) {
		echo '<div class="notice notice-warning inline"><p>';
		echo __( 'To ensure data security, this functionality can only be used when accessed via HTTPS.', 'corona-test-results' );
		echo '</p></div>';
		return;
	}

	if ( ! function_exists( 'corona_test_results_check_certificates_enabled' ) || ! corona_test_results_check_certificates_enabled() ) {
		echo "<div class='notice notice-warning inline'><p>"
		. nl2br( sprintf( esc_html(
			// translators: opening and closing tags linking to the certificate settings tab
			__( "The %scertificate generation functionality%s must be enabled in order to be able to use this integration.", 'corona-test-results' ) ),
			'<a href="' . corona_test_results_get_settings_link( '&tab=vt_certificates', null, true ) . '">',
			'</a>' ) )
		. "</p></div>";
		return;
	}

    $options = corona_test_results_get_options();

	if ( ! isset( $options[ 'datatransfer_enable_cwa' ] )
		|| ! in_array( $options[ 'datatransfer_enable_cwa' ], array('enable_test', 'enable_production' ) )
	) {
		$option_value = 'disabled';
	} else {
		$option_value = $options[ 'datatransfer_enable_cwa' ];
	}

	$option_name = '';

	if (
		$option_value === 'disabled'
		|| ! function_exists( 'corona_test_results_check_datatransfer_integration_enabled' )
		|| ! corona_test_results_check_datatransfer_integration_enabled( 'cwa', true )
	) {
		echo '<div class="notice notice-error inline"><p>';
		_e( 'For reasons incomprehensible to us, despite working functionality and already having successfully connected users, T-Systems is currently refusing further integrations when using our plug-in. We will try to clarify this issue, but for the time being we can only advise you to manually use the alternative Corona-Warn-App portal solution in addition to our plug-in.', 'corona-test-results' );
		echo '</p></div>';
	}

	$phpVersion = phpversion();
	$minVersionForCWA = '7.3.0';
	$feature_is_disabled = version_compare( $phpVersion, $minVersionForCWA, '<' );
	if ( $feature_is_disabled ) {
		echo '<div class="notice notice-' . ( $option_value !== 'disabled' ? 'error' : 'warning' ) . ' inline"><p>';
		printf(
			// translators: %s: 1) PHP version needed to activate this feature 2) current PHP version
			__( 'PHP version %s or higher is needed in order to be able to use this feature. You are currently running on PHP %s and will need to ugprade.', 'corona-test-results' ),
			'<code>' . $minVersionForCWA . '</code>',
			'<code>' . $phpVersion . '</code>'
		);
		echo '</p></div>';
		$option_value = 'disabled';
	}
?>
<fieldset data-vt-toggle="cwa"><legend class="screen-reader-text"><span><?php echo __( 'Corona-Warn-App (Germany)', 'corona-test-results' ); ?></span></legend>
	<label><input type="radio" name="<?php echo $option_name ?>" value="disabled"<?php echo ( 'disabled' === $option_value ? ' checked="checked"' : ''); ?>> <?php echo __( 'disabled', 'corona-test-results' ); ?></label><br>
	<label><input type="radio" name="<?php echo $option_name ?>" value="enable_test"<?php echo ( 'enable_test' === $option_value ? ' checked="checked"' : ''); ?> <?php echo $feature_is_disabled ? ' disabled' : ''; ?>> <?php echo __( 'test environment ("WRU")', 'corona-test-results' ); ?></label><br>
	<label><input type="radio" name="<?php echo $option_name ?>" value="enable_production"<?php echo ( 'enable_production' === $option_value ? ' checked="checked"' : ''); ?> <?php echo $feature_is_disabled ? ' disabled' : ''; ?>> <?php echo __( 'production environment', 'corona-test-results' ); ?></label><br>

<?php
	echo "<div class='notice notice-info inline' data-vt-if-integration-enabled='*'" . ( 'disabled' === $option_value ? ' style="display:none;" aria-hidden="true"' : '' ) . "><p>"
	. sprintf(
		// translators: %s: the opening and closing tags for linking to the documentation
		esc_html__( "Please follow the %sofficial onboarding documentation, option 2 (German)%s in order to register with T-Sytems (the service provider for the integration of test results with the CWA), inform yourself about all requirements and obtain your key and certificate files needed for authentication with their servers. You may skip the section \"Entwicklung QR-Code & Backend-Integration\", as this is the part that will be fullfilled by the plugin.", 'corona-test-results' ),
		'<a href="https://github.com/corona-warn-app/cwa-quicktest-onboarding/wiki/#option-2-cwa-schnittstellen-l%C3%B6sung" target="_blank" rel="noopener noreferrer">',
		'</a>'
	)
	. ' '
	. __( "Please understand that we as the plugin author do not have any influence on this third-party process and can therefore not provide any support.", 'corona-test-results' )
	. "</p></div>"
	. '<p class="description">'
	. __( "You can either upload the files to your server manually, to a directory outside of the web root with read-only access for the PHP process, and specify absolute paths to the files. Or you can upload them here for storage in a directory protected by htaccess restriction (less secure).", 'corona-test-results' )
	. '</p>';

	corona_test_results_opts_datatransfer_certsection__helper( 'test', $option_value );
	corona_test_results_opts_datatransfer_certsection__helper( 'production', $option_value );

?>
</fieldset>
<?php
}

/**
 * security
 */
function corona_test_results_opts_security_acces_users__helper( $area ) {
    $options = corona_test_results_get_options();

	$users = get_users( array(
		'fields' => array( 'ID', 'user_login', 'display_name' ),
		'orderby' => 'display_name',
		'role__not_in' => array( 'subscriber' )
	) );

	if ( !isset( $options['security_' . $area . '_access_users'] ) || !is_array( $options['security_' . $area . '_access_users'] ) ) {
		$options['security_' . $area . '_access_users'] =
			$area === 'codes_register' && isset( $options[ 'security_codes_access_users' ] ) && is_array( $options[ 'security_codes_access_users' ] )
			? $options[ 'security_codes_access_users' ]
			: array();
	}

	echo '<p><select name="corona_test_results_options[security_' . $area . '_access_users][]" multiple>';
	foreach ( (array)$users as $user ) {
		$optionLabel = $user->display_name . ( $user->display_name !== $user->user_login ? " (" . $user->user_login . ")" : '' );
		$selected = in_array( $user->ID, $options['security_' . $area . '_access_users']) ? ' selected' : '';
		echo '<option value="' . $user->ID . '"' . $selected . '>' . esc_html( $optionLabel ) . '</option>';
	}
	echo "</select></p>";
	echo '<p class="description">'
	. nl2br( sprintf(
			// tanslators: %s: code tags with 1) manage_options 2) subscriber
			__( "Administrators (or more precisely: users with the capability %s) will always have access to all plugin pages, including settings.\nYou can select additional users here (except with role %s) to give them access to the code registration and assignation pages as well as some individual settings (but not all of the global plugin settings).", 'corona-test-results' ) . '</p>',
			'<code>manage_options</code>',
			'<code>subscriber</code>'
		)
	);
}

function corona_test_results_opts_security_codes_register_access_users() {
	corona_test_results_opts_security_acces_users__helper( 'codes_register' );
}

function corona_test_results_opts_security_codes_access_users() {
	corona_test_results_opts_security_acces_users__helper( 'codes' );
}

function corona_test_results_opts_security_encryption_key() {
	require_once( corona_test_results_plugin_dir_path() . 'lib/DataEncryption.class.php' );

	$consent_given = corona_test_results_check_checkbox_option( 'security_encryption_consent' );

	$encryptionActiveHint = $consent_given ?
		__( 'You can now use all features based on encryption functionality.', 'corona-test-results' )
		: __( 'You need to give your consent to the Data Protection terms below in order to activate all features based on encryption functionality.', 'corona-test-results' );

	$wpVersion = get_bloginfo( 'version' );
	$minVersionForEncryption = '5.2';
	if ( version_compare( $wpVersion, $minVersionForEncryption, '<' ) ) {
		echo '<div class="notice notice-error inline"><p>';
		printf(
			// translators: %s: 1) WordPress version needed to activate encryption 2) current WordPress version
			__( 'WordPress version %s or higher is needed in order to be able to use encryption functionality. You are currently running on WordPress %s and will need to ugprade.', 'corona-test-results' ),
			'<code>' . $minVersionForEncryption . '</code>',
			'<code>' . $wpVersion . '</code>'
		);
		echo '</p></div>';
	} else {
		if ( corona_test_results_check_crypto_keys() ) {
			echo '<div class="notice notice-success inline"><p>';
				_e( 'The encryption key is present!', 'corona-test-results' );
				echo ' ' . $encryptionActiveHint;
			echo '</p></div>';
		} else {
			$result = corona_test_results_generate_crypto_keys();

			if ( true === $result ) {
				$status = 'success';
				$text = '<strong>' . __( 'The key has been generated successfully!', 'corona-test-results' ) . '</strong>';
				$text .=  ' ' . $encryptionActiveHint;
			} else {
				$status = 'warning';
				$text = sprintf(
					// translators: %s: wp-config.php wrapped in <code></code>
					__( 'The key could not be saved automatically. Please add the following lines to your %s file in the root directory of your WordPress installation.', 'corona-test-results' ),
					'<code>wp-config.php</code>'
				);

				$text .= '<details><summary class="button-link">' . __( 'Display code (Do not show this to anyone else!)', 'corona-test-results' ) . '</summary><pre><code style="display:block;padding:0.5em;">' . esc_html( $result ) . '</code></pre></details>';
			}

			echo '<div class="notice notice-' . $status . ' inline"><p>';
				echo $text;
			echo '</p></div>';
		}
	}
}

function corona_test_results_generate_crypto_keys() {
	if ( corona_test_results_check_crypto_keys() ) return;

	$keylines = '/**
 * This auto-generated key is needed for encryption and decryption of sensitive data related to the Corona Test Results plugin.
 *
 * When it is changed or this file is deleted, already encrypted data can no longer be decrypted.
**/
define( \'CTR_ENCRYPTION_KEY\', base64_decode( \'' . base64_encode( DataEncryption::getRandomKey() ) . '\' ) );';

	$template = '<?php
defined( \'ABSPATH\' ) or die;

' . $keylines;

	$cfgPath = ABSPATH . 'ctr-config.php';
	@file_put_contents( $cfgPath, $template );
	@chmod( $cfgPath, 0400 );
	if ( is_file( $cfgPath ) ) {
		require_once( $cfgPath );
	}

	return is_file( $cfgPath ) ? true : $keylines;
}

function corona_test_results_opts_security_encryption_consent() {
    $options = corona_test_results_get_options();

	$option_name ='corona_test_results_options[security_encryption_consent]';

	$checked = ( isset( $options['security_encryption_consent'] ) && $options['security_encryption_consent'] === 'on' ) ? ' checked' : '';
	$checkboxMarkup = "<input type='checkbox' id='corona_test_results_opts_security_encryption_consent' name='$option_name' value='on'$checked" . ( $checked ? ' disabled' : '') . " /> <label for='corona_test_results_opts_security_encryption_consent'>";

	// translators: %s: checkbox and label markup
	printf( wpautop( __( "In an inherently open system like WordPress, it is difficult to store data securely without imposing on the end user the need to supply a security key with every data request. We do our best to protect the personal data collected through the plugin by encrypting it. However, there can be no absolute guarantee of data security within this environment. There is always the possibility of an attacker or a malicious plugin or theme trying to steal and decrypt the stored data. The plugin author, 48DESIGN GmbH, rejects any liability claims in the event of data theft. You should only install plugins that are absolutely necessary and trustworthy and always keep all plugins, themes and the WordPress core version up-to-date.

	%s I understand these security implications, accept the terms and am aware that I must inform the end user (tested person) about the storage of their data and its scope in accordance with the data protection regulations applicable to me. I would like to enable all plugin functionality that involves the storage of personal data.%s", 'corona-test-results' ) ),
	$checkboxMarkup,
	"</label></p>"
	);
}

function corona_test_results_opts_security_deletion() {
    $options = corona_test_results_get_options();

	print '<p>' . __( 'When the plugin is deleted:', 'corona-test-results' ) . '</p>';

	$option_name = 'corona_test_results_options[security_deletion_data]';
	$checked = ( isset( $options['security_deletion_data'] ) && $options['security_deletion_data'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_security_deletion_data' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_security_deletion_data'>" . esc_html__( 'Delete all associated data from the database', 'corona-test-results' ) . "</label></p>";

	$option_name = 'corona_test_results_options[security_deletion_key]';
	$checked = ( isset( $options['security_deletion_key'] ) && $options['security_deletion_key'] === 'on' && isset( $options['security_deletion_data'] ) && $options['security_deletion_data'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_security_deletion_key' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_security_deletion_key'>" . esc_html__( 'Delete encryption key', 'corona-test-results' ) . "</label></p>";

	$option_name = 'corona_test_results_options[security_deletion_settings]';
	$checked = ( isset( $options['security_deletion_settings'] ) && $options['security_deletion_settings'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_security_deletion_settings' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_security_deletion_settings'>" . esc_html__( 'Delete plugin settings', 'corona-test-results' ) . "</label></p>";

	$option_name = 'corona_test_results_options[security_deletion_pages]';
	$checked = ( isset( $options['security_deletion_pages'] ) && $options['security_deletion_pages'] === 'on' ) ? ' checked' : '';
	echo "<p><input type='checkbox' id='corona_test_results_opts_security_deletion_pages' name='$option_name' value='on'$checked /> <label for='corona_test_results_opts_security_deletion_pages'>" . esc_html__( 'Move associated pages to trash', 'corona-test-results' ) . "</label></p>";
}

/**
 * booking
 */

function corona_test_results_opts_booking_futuredays() {
	global $corona_test_results_cfg_defaults;
    $options = corona_test_results_get_options();
	$option_prefix = function_exists('corona_test_results_options') ? corona_test_results_get_options_option_name() : 'corona_test_results_options';
	$option_name = '';

	$input = "<input id='corona_test_results_opts_booking_futuredays' name='$option_name' type='number' value='" . esc_attr( $options['booking_futuredays'] ) . "' min='0' class='small-text' /> ";

	printf(
		__( 'show appointments for %s additional day(s)', 'corona-test-results' ),
		$input
	);

	echo '<p class="description">'
		. __( '0 = only show appointments for the current day', 'corona-test-results' )
		. '</p>';

}

function corona_test_results_opts_booking_description() {
    echo '<p>' .
		esc_html__(
			'These settings take effect if you activated at least one booking integration below and apply to all the active integrations.',
			'corona-test-results'
        )
	 . '</p>';
}

function corona_test_results_opts_booking_integrations_description() {
    echo '<p>' .
		esc_html__(
			'You can select third-party booking tools here to integrate into the test registration form. You will then be able to select bookings and transfer the personal data into the form with one click. Please note that we do not endorse and cannot provide support for the usage of any third-party software. You should make yourself familiar with the possible implications on data protection regulations, as the data entered during the booking process may or may not be stored in the clear, depending on the third-party software.',
			'corona-test-results'
        )
	 . '</p>';

    echo '<p>' . sprintf(
		esc_html__(
			// translators: opening and closing tags of mailto link
			'Currently, we only support bookly, but you can %slet us know%s which plugin or tool you\'re using and we\'ll look into it and might even be able to make you an offer for prioritized integration.',
			'corona-test-results'
        ),
		'<a href="mailto:wordpress@48design.com?subject=appointment%20booking%20integration">',
		'</a>'
	) . '</p>';
}

function corona_test_results_opts_booking_enabled( $integration, $shortname ) {
    $options = corona_test_results_get_options();

	$option_name = '';

	$disabled = $integration[ 'is_active' ] ? '' : ' disabled';
	$checked = !$disabled && ( isset( $options['booking_enabled_' . $shortname ] ) && $options['booking_enabled_' . $shortname ] === 'on' ) ? ' checked' : '';
	if ( !!$disabled ) {
		$pluginLink = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $integration['mainfile'] ) );
		$cblabel = sprintf(
			// translators: %s: booking tool name, linking to either the plugin details, or the plugin overview page
			__( '%s is not installed or activated', 'corona-test-results' ),
			'<a href="' . $pluginLink . '" target="_blank">' . $integration[ 'name' ] . '</a>'
		);
	} else {
		$cblabel = sprintf(
			// translators: %s: booking tool name
			__( 'Show %s appointments in test registration form', 'corona-test-results' ),
			$integration[ 'name' ]
		);
	}
	echo "<p><input type='checkbox' id='corona_test_results_opts_booking_enabled_" . $shortname . "' name='$option_name' value='on'$checked$disabled /> <label for='corona_test_results_opts_booking_enabled_" . $shortname . "'>" . $cblabel . "</label></p>";

	if ( isset( $integration['hint'] ) && !empty( $integration['hint'] ) ) {
		echo '<p class="description">' . $integration['hint'] . '</p>';
	}
}

/**
 * license
 */
function corona_test_results_opts_license_description() {
    echo '<p>' . sprintf(
		esc_html__(
		// translators: %s: (1) opening link tag, (2) formatted string "Premium", (3) closing link tag
        'After you %sbuy the %s version%s, enter your license key here to register it to this domain.', 'corona-test-results'
        ),
		'<a href="' . corona_test_results_premium_shop_url() . '" target="_blank">',
		'<strong><em>Premium</em></strong>',
		'</a>'
	)
	 . '</p>';
}

function corona_test_results_opts_license_key() {
    $options = corona_test_results_get_options();

	$option_name = empty( $options['license_key'] ) ? 'corona_test_results_options[license_key]' : '';

	$readonly = $option_name ? '' : ' readonly';

	echo "<p><input id='corona_test_results_opts_license_key' name='$option_name' type='text' value='" . esc_attr( $options['license_key'] ) . "' class='regular-text'$readonly></p>";
	echo "<p class='description'><strong>" . esc_html__('Note:', 'corona-test-results')
		. "</strong> " . esc_html__( 'The license will be bound to the current domain of this WordPress installation. Once activated, it will only be possible to activate it on this domain again in the future and cannot be transferred to a different domain.', 'corona-test-results' )
		. "<br><em>"
		.	sprintf(
			// translators: Plugin author name linking to the plugin author URI
			esc_html__( 'By trying to activate a license, you agree to share the entered license key and the current domain of this WordPress installation with %s.', 'corona-test-results' ),
			corona_test_results_get_author_link()
		)
		. "</em></p>";
}

/**
 * Add a post display state for special plugin pages in the page list table.
 *
 * @param array   $post_states An array of post display states.
 * @param WP_Post $post        The current post object.
 */
function corona_test_results_add_post_states( $post_states, $post ) {
	$result_pages = corona_test_results_get_special_pages_slugs();

	foreach ( $result_pages as $page_slug ) {
		if ( corona_test_results_get_page_id( $page_slug ) === $post->ID ) {
			if ( ! in_array( $page_slug, array( 'result_retrieval', 'quickcheckin' ) ) ) {
				unset($post_states['draft']);
			}
			$post_states['vt_page_for' . $page_slug] = corona_test_results_get_page_name( $page_slug );
		}
	}

    return $post_states;
}
add_filter( 'display_post_states', 'corona_test_results_add_post_states', 10, 2 );
