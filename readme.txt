=== Corona Test Results ===
Contributors: 48design
Donate link: https://shop.48design.com/en/product/wordpress/corona-test-results-plugin-premium/
Tags: Corona Virus, COVID-19, test results, Testergebnisse, Onlineabfrage, test center, smear tests, rapid tests, coronavirus, SARS-CoV-2, COVID19
Requires at least: 4.8
Tested up to: 5.9.0
Requires PHP: 5.6.40
Stable tag: 1.11.6
License: GPL 2 or later

🦠 Management of Corona/COVID-19 test results with online check for the tested patients/citizens. Make the quick smear test procedure easier for both yourself and the person being tested and transmit the test result (positive/negative) via online query. Regardless of whether you're a family doctor’s practice, municipal testing center, pharmacy, laboratory, … 🦠

== Description ==

Management of Corona/COVID-19 test results with online check for the patients/citizens. Make the quick smear test procedure easier for both yourself and the person being tested and transmit the test result (positive/negative) via online query. Regardless of whether you're a family doctor’s practice, municipal testing center, pharmacy, laboratory, …

🦠 **For medical practices, test centers and laboratories:** Generate random codes and print out an information sheet with a URL and QR code for online querying of results, as well as a document for linking the code and the fixed result at the test location.
🦠 **Reduced conversation effort:** (Premium) Individual information and recommendations for action can be displayed for each status page (result pending/positive/negative). This significantly reduces conversation times with patients/citizens. In this way, information can be read at home without stress.
🦠 **No endless attempts to reach test subjects by phone:** There is no need to contact test subjects by telephone to report the test results.
🦠 **Data protection included:** Personal data is not stored on the server if the certificate generation feature is not activated or no certificate is requested. Otherwise, all personal data is stored encrypted.

Intuitive operation, great time savings, comfort for patients/citizens. No queues with an increased risk of infection and no confusion when communicating the results. The patient / citizen is assigned to the smear test result using a unique code.

**FEATURES**

* Generation of unique random codes for assigning and retrieving test results
* Generation of document for the testing location and the test subjects
* QR code for easy access to the test result by the patient/citizen
* No storage of sensitive data on the server when certificate functionality is not used

**[PREMIUM](https://shop.48design.com/en/product/wordpress/corona-test-results-plugin-premium/) FEATURES**

* Individually adaptable to the design of the website
* Custom content on the different result pages (e.g. for further information and instructions)
* Customizable texts and settings if needed
* Batch generation of codes and documents
* CSV export of codes
* Generation of test certificates that can be printed or sent by email ([contact us](mailto:wordpress@48design.com?subject=CTR:%20customized%20certificate%20template) for a template customization offer)
* Scanning of vCard data from a QR code in the test registration form (e.g. provided by our Quick Check-In feature or some official contact tracing apps like "Corona-Warn-App" used in Germany) ¹ ²
* Import personal data from appointment booking tools/plugins upon test regitration ¹ ³
* Quick Check-In: Print out a poster to lead about-to-be tested persons to a page where they can create a QR code containing their personal data while waiting, to further speed up the registration process
* (Data transfer: Send test results to official contact tracing apps) ⁴
* Label printing for easier assignation of test kits and results

**Important:** If you are using any caching plugin, make sure that the page containing the result retrieval form is added as an exception so it is never being cached, as this will otherwise lead to the form no longer working once the security nonce expires.

¹ access via HTTPS protocol required
² webcam or compatible scanning device required
³ currently supported: [Bookly](https://wordpress.org/plugins/bookly-responsive-appointment-booking-tool/) plugin ([Bookly Pro](https://1.envato.market/KeBoGn) is required to make use of additional fields for address and birth date)
⁴ currently supported: (Corona-Warn-App (Germany) (requires PHP >=7.3.0))
> **For reasons incomprehensible to us, despite working functionality and already having successfully connected users, T-Systems is currently refusing further integrations when using our plug-in. We will try to clarify this issue, but for the time being we can only advise you to manually use the alternative Corona-Warn-App portal solution in addition to our plug-in.**


**FEATURE IDEAS** ([become a sponsor](mailto:wordpress@48design.com?subject=CTR:%20sponsoring%20a%20feature) for prioritized implementation)

* Customized certificate layouts
* Generate data protection consent form with the other documents
* Multi-site/license management
* Multi language support
* Support of more booking plugins

**Watch our video to see the installation and testing process as well as the activation and certificate generation process of the Premium version in action:**
[youtube https://www.youtube.com/watch?v=buk8abJzbs0&cc_load_policy=1]

== Installation ==

Simply install via the WordPress plugin library.
For manual installation, go to *Plugins > Installation > Upload Plugin* and upload the .zip file.

Watch our video to see the installation and testing process in action:
[youtube https://www.youtube.com/watch?v=buk8abJzbs0&cc_load_policy=1]

== Screenshots ==

1. Test registration page
2. Test assignation page
3. Plugin settings page
4. Page 1 of the generated document, for the tested person (German translation)
5. Page 2 of the generated document, for on-site safekeeping and result assignation (German translation)
6. Workflow: 1) Create code and print documents 2) Conduct the test 3) Assign the result 4) User can check the result 5) and optionally receive a certificate 6) for shopping and travel
7. Test certificate (German translation)

== Frequently Asked Questions ==

= Does Corona Test Results respect data privacy? =

Yes, it does! If you're not using the certificate generation feature, or a tested person does not request a certificate, private data will not be stored on the server at all.
Private data needed for certificate generation is stored in encrypted form. But please check what additional data privacy regulations and requirements might apply in your area.

= Is this system tested and being actively used? =

Yes, it is approved by regular use in a family doctor's practice that we're in contact with, as well as several testing locations located in Germany and around the world.

= Isn't it too much effort? =

To the contrary, it saves a lot of time for the testing personnel!

= How does the testing process work with the plug-in? =

There are different ways how you can use the plugin. Currently it is designed for the following workflow:
1. **Create code:** You generate a unique code via "Register test" and enter the data (full name and date of birth) of the person being tested.
2. **Generate PDF:** You print out the provided PDF, which consists of two A5 pages. One part is given to the tested person, the other part is processed at the test site.
3. **Corona test:** Once the test has been completed and the result is known, use "Assign result" to assign the test result to the code registered.
4. **Test result:** The person tested gets to the results page via the code on their printout (manual entry or via QR code) and can read the result.
5. **Attestation/Certificate:** Upon request, the person tested will receive a certificate of the test result by email.

With the premium version, additional information such as instructions can be offered on the results pages (pending/positive/negative).

= Does ist come with an integrated booking system? =

While there is no integrated booking functionality, you're able to read bookings from the third-party plugin called "Bookly" and copy the data over to the registration form. Mind though that we have no control over how Bookly handles the user data and you may need Bookly Pro and additional Bookly Add-Ons to make use of specific features.

= Does the plugin create a certificate about the test result? =

Yes, as of version 1.4.0 of the plug-in, certificates can be generated when assigning the test results, which can then either be printed out or sent by e-mail.

= Will my external Camera / QR Scanner work with the plugin? =

Any device that is detected as a camera in the web browser should work. Please check if your device is recognized as a camera on this page, which is a demo of the library that we use for QR code scanning: https://nimiq.github.io/qr-scanner/demo/

We try to also support scanning devices that are detected as a keyboard by the operating system. Simply scan the code while the registration form is open and the browser window or tab is focused. This implementation is currently experimental and we're looking forward to your feedback.
Starting with version 1.10.2, we successfully tested this input method with a Tera HW0002 handheld scanner. (Set the data format to Unicode/UTF-8 and the keyboard language to "International Keyboard", or "ALT method" for some devices)
USB connection is recommended for fastest and reliable scan results. 2.4G also works, but Bluetooth seems to be quite unreliable and should be avoided.

= Keeping track of and having to enter PINs for each test upon certificate generation is quite time-consuming - is it really necessary? =

The PIN, which is not stored in the system and should therefore only be available offline in printed form, is an additional security measure to protect the patient data. This is a trade-off between data protection and using an open, online system like WordPress instead of a local software solution.

== Upgrade Notice ==

Simply use the update mechanism of WordPress. Your existing data will remain intact.

== Changelog ==

= 1.11.6 =
* Add notice about current CWA integration issues

= 1.11.5 =
* Fix QR Code Scan camera detection/selection on some Android devices
* force file downloads on iOS devices due to Safari preventing opening a new tab, resulting in loss of registration form data
* Quick-CheckIn: Switch from system date picker to three dropdowns for birth date selection due to unintuitive UX for end customers
* fix timestamp offset in registratioin document on some iOS devices
* added option for adding a text block to the bottom of the second page generated during registration
* auto-focus PIN input

= 1.11.4 =
* CWA integration: fix endpoint switching from WRU to PROD system and error handling

= 1.11.3 =
* Quick-CheckIn: CWA radio buttons may not have the default one checked, declarations of consent texts have to be always visible (according to requirements)

= 1.11.2 =
* fix iOS bug: after generating a certificate PDF in the browser and navigating back, Safari would lose the PIN, resulting in the certificate PDF having no opening password when mailed afterwards

= 1.11.1 =
* fix CWA integration timestamp again per request of T-Systems (using last status change minus 15 minutes)

= 1.11.0 =
* added option for displaying an additional required checkbox in the Quick Check-In form
* added option to show appointments for additional days in the future
* accept key files in PKCS#1 format for Corona-Warn-App integration (only PKCS#8 keys were accepted so far)
* prevent possible PHP Notice during plugin update hook
* Prevent hardware scanner timeout when scanning via 2.4G wireless
* fix scan/certificate modal positioning with some themes or plugins
* fix appointment search in some WP environments
* fix CWA integration timestamp timezone issue
* fix CWA integration timestamp using current time instead of registration timestamp as requested by T-Systems

= 1.10.2 =
* Refactored and improved hardware QR scanning device input detection (now works without camera scanner overlay open)
* decode HTML entities in email sender name
* fix appointment data when using the Bookly Group Bookings Add-On
* fix saving code status changes when certificate functionality has not yet been enabled before
* fix oder of empty address lines on batch PDFs or when address is empty
* fix birth date in certificates still being off one day on some devices/browsers

= 1.10.1 =
* Quick Check-In: prevent themes or plugins minifying the markup causing JavaScript parsing errors
* Lite version only: fix saving code status changes not working

= 1.10.0 =
* experimental support for scanning devices that are detected as a keyboard instead of a webcam
* fix fetching appointments when the database table prefix contains uppercase letters
* improved support for rear-facing cameras on mobile devices
* added icons to registration form buttons for better discernability
* registration: allow filtering by booking id by entering # followed by a booking number
* fix escaping of single quotes in patient data (e.g. in surnames)
* fix timestamp issues when updating code data without updating the status
* Quick Check-In: added option to display a repeat email address input to prevent typos
* Quick Check-In: Validate email address format if entered
* fix test trade name select breaking into a new line when containing a rather long name
* Prevent third-party caching plugins "WP Fastest Cache" and "WP Super Cache" from caching the status check form
* fix JS errors during certificate generation in Safari
* disable interactive elements on the assignation page while the modal is open, to prevent accidental changes
* implemented minimum PHP version check for CWA integration feature
* fix: checkboxes checked before additional rows finished loading were not regarded for bulk actions
* fix: better prevent overlapping accidental status updates
* fix: prevent accidental status updates for codes with already sent certificate
* fix: use UTC timestamp when comparing for auto-deletion/auto-trash functionality
* dropped support for Internet Explorer 11

= 1.9.0 =
* show outstanding payment status for Bookly appointments
* separate access rights for registration and assignation
* fix birth date in certificates being off one day under specific circumstances
* fix label printing failing when certificates aren't enabled
* fix certificate mail texts defaulting to English for German locales
* fix several language strings
* added more customization filters

= 1.8.0 =
* Added passport number field (registration, certificate, Quick Check-In)
* Added label printing functionality and settings (Premium)
* Added an internal custom field count filter for future customization
* Added internal filters for appointment data

= 1.7.0 =
* Implemented result status "invalid"
* Implemented integration of Corona-Warn-App data transfer (Premium)
* fix JS errors in code table when certificate functionality was disabled

= 1.6.2 (Premium only) =
* fix SQL issue when creating table layout for new installations

= 1.6.1 =
* Security fix regarding possible XSS during document generation

= 1.6.0 =
* Implement integration of Bookly appointment booking plugin
* Implement Quick Check-In functionality
* Added settings for deletion of plugin data on plugin removal
* Added notice to WordPress data export/erasure core features
* Added some internal hooks for future costumizations

= 1.5.1 =
* Added demonstration video to plugin readme and a help tab on the settings page

= 1.5.0 =
* Added scanning of vCard data from a QR code in the test registration form
* fixed two German language translation strings

= 1.4.1 =
* Update screenshots and plugin description

= 1.4.0 =
* Implemented two additional custom fields
* Implemented generation of certificates (Premium feature, WP 5.2 or higher required)

= 1.3.4 =
* Fixed pagination and code filter for mobile view of assignation tablenav
* Rewrite logo image URL to https if WordPress is running on https

= 1.3.3 =
* Improved mobile view of assignation table
* Display time of test on generated PDF if not in batch generation

= 1.3.2 =
* fix list of users with code access being reset when saving another settings tab

= 1.3.1 =
* Updated plugin metadata
* Added screenshots of the generated document

= 1.3.0 =
* Added option to override the date for batch generated codes (Premium feature)
* Rate limit for code retrieval
* Bulk actions, Trash and permanent deletion for codes
* Setting to give access to code registration and assignation to non-admin users
* Changed plugin settings sections to tabs for better overview
* Fixed untranslated German string

= 1.2.3 (Premium only) =
* HOTFIX: solve an issue where the code retrieval would result in a 404 page under specific circumstances

= 1.2.2 (Premium only) =
* Improved content of the plugin details popup

= 1.2.1 =
* fix search field vanishing in mobile view

= 1.2.0 =
* Added CSV export to assignation table (Premium feature)
* Implemented pagination on assignation page
* Completely revamped PDF generation mechanism for better performance (now also works in IE11)
* Implemented batch generation of codes (Premium feature)
* Prevent some more PHP Notices
* Some minor bugfixes

= 1.1.1 =
* change code creation date column type from datetime to timestamp to prevent issues with MySQL versions < 5.6.5

= 1.1.0 =
* ask before leaving the assignation page when code statuses have been changed
* updated readme texts

= 1.0.3 =
* fixed several more notices on systems with strict error reporting settings
* do not display "page not set" messages for the three page types not used in the lite version when not in premium version

= 1.0.2 =
* fixed a bug that would cause a PHP error message in the backend on systems with strict error reporting settings

= 1.0.1 =
* delete transient that would otherwise cause a never vanishing message after switching back from the premium version (however unlikely that may be)

= 1.0.0 =
* initial release
