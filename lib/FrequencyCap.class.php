<?php
if ( ! class_exists( 'WP_Frequency_Cap' ) ) {
	/**
	 * Frequency Capping implementation based on the anonymized client IP
	 *
	 * @author Constantin Groß, 48DESIGN GmbH
	 * @version 1.0.0
	 */
	class WP_Frequency_Cap
	{
		/**
		 * Unique instance
		 *
		 * @var WP_Frequency_Cap
		 */
		private static $instance;

		/**
		 * The hashing algorithm to use for the anonymized IP
		 */
		private static $algo = 'sha384';

		/**
		 * The mask to use for IPv4 anonymization (using 255 for all blocks would ultimately deactivate anonymization)
		 */
		private static $maskIPv4 = '255.255.255.0';

		/**
		 * The mask to use for IPv6 anonymization (using ffff for all blocks would ultimately deactivate anonymization)
		 */
		private static $maskIPv6 = 'ffff:ffff:ffff:ffff:0000:0000:0000:0000';

		/**
		 * Maximum length of identifier names
		 * Initially the maximum length of transient names in WordPress, will be reduced by the prefix and IP hash
		 */
		private static $maxIdentifierLength = 167;

		/**
		 * Prefix for transient names
		 */
		private static $transientNamePrefix = 'wpfreqc_';

		/**
		 * Whether to set the http status code to 429 (if headers have not been sent yet)
		 */
		public static $setHttpStatus = true;

		/**
		 * Return the unique instance after creating it if it didn't already exist
		 *
		 * @return WP_Frequency_Cap
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			self::$transientNamePrefix .= self::getHashedIP() . '_';
			self::$maxIdentifierLength -= mb_strlen( self::$transientNamePrefix );
		}

		/**
		 * returns the anonymized client IP address
		 */
		private static function getAnonymizedIP() {
			$IPpacked = inet_pton( $_SERVER['REMOTE_ADDR'] );
			$isV4 = strlen( $IPpacked ) === 4;
			$mask = $isV4 ? self::$maskIPv4 : self::$maskIPv6;
			return inet_ntop( $IPpacked & inet_pton( $mask ) );
		}

		/**
		 * returns the anonymized and sha256 hashed client IP address
		 */
		private static function getHashedIP() {
			return hash( self::$algo, self::getAnonymizedIP() );
		}

		/**
		 * make sure that the capping identifier fits into the transient name
		 */
		private static function validateIdentifier( $identifier ) {
			$maxLength = self::$maxIdentifierLength;

			if ( empty( $identifier ) ) {
				throw new \Exception( 'Capping identifier not provided' );
			} else if ( mb_strlen( $identifier ) > $maxLength ) {
				throw new \Exception( "Capping identifier must be $maxLength characters or less" );
			}
		}

		/**
		 * returns the transient name consisting of prefix, hashed IP and capping identifier
		 */
		private static function getTransientName( $identifier ) {
			self::validateIdentifier( $identifier );

			return self::$transientNamePrefix . $identifier;
		}

		/**
		 * Gets the data for an identifier, either from an existing transient or by initializing it
		 */
		private static function getCappingData( $identifier ) {
			$transientValue = get_transient( self::getTransientName( $identifier ) );

			if ( false === $transientValue || !is_array( $transientValue ) ) {
				$transientValue = array(
					'accessed' => array(),
					'locked' => false
				);
			}

			return $transientValue;
		}

		/**
		 * Check whether an action should be prevented or allowed
		 *
		 * @param string $identifier Identifier for this action
		 * @param int $max_calls Maximum calls to be executed before the action is locked
		 * @param int $within_seconds Time frame in seconds in which the calls are being counted
		 * @param int $cooldown_seconds Time to wait before releasing the lock (0 to just check within the time frame; otherwise should be higher than the time frame to take effect)
		 *
		 * @return true if the action is allowed, or false if the action should be prevented
		 */
		public static function executeCappedAction( $identifier, $max_calls, $within_seconds, $cooldown_seconds = 0 ) {
			$cappingData = self::getCappingData( $identifier );

			if ( true === $cappingData['locked'] ) {
				if ( self::$setHttpStatus ) {
					status_header( 429 );
				}
				return false;
			}

			if ( count( $cappingData['accessed'] ) ) {
				$cappingData['accessed'] = array_filter( $cappingData['accessed'], function( $ts ) use ( $within_seconds ) {
					return ( time() - $ts ) <= $within_seconds;
				} );
			}

			$cappingData['accessed'][] = time();

			$maximumReached = count( $cappingData['accessed'] ) > $max_calls;

			if ( $maximumReached && $cooldown_seconds ) {
				$cappingData['locked'] = true;
			}

			set_transient( self::getTransientName( $identifier ), $cappingData, max( $within_seconds, $cooldown_seconds ) );

			if ( $maximumReached && self::$setHttpStatus ) {
				status_header( 429 );
			}

			return ! $maximumReached;
		}

		/**
		 * Implements multiple levels of time frames and cooldowns when checking an identifier. If either of the checks returns false, the function returns false as well.
		 *
		 * @param array $levels An array of arrays, where each array holds values for the arguments of executeCappedAction().
		 */
		public static function executeCappedActionMultilevel( $levels ) {
			$isLocked = false;

			foreach( $levels as $level ) {
				$levelLocked = ! self::executeCappedAction( $level[0], $level[1], $level[2], $level[3] );
				if ( $levelLocked ) {
					$isLocked = true;
				}
			}

			return ! $isLocked;
		}
	}
}

$wpFrequencyCap = WP_Frequency_Cap::get_instance();
