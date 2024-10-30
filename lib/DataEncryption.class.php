<?php
require_once( ABSPATH . WPINC . '/sodium_compat/autoload.php' );
/**
 * Class responsible for encrypting and decrypting data.
 *
 * Uses libsodium (PHP >= 7.2), or sodium_compat that comes with WordPress >= 5.2
 *
 * @access private
 * @ignore
 */
final class DataEncryption {

	/**
	 * The key to use for encryption and decryption.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		$this->key  = $this->getDefaultKey();
	}

	/**
	 * Use random number generator to return a random numeric PIN as a string
	 *
	 * ( wp_rand() uses random_int() if available, which should always be the case with WP >= 5.2 )
	 *
	 * @param int length of the string to generate
	 * @return string
	 */
	public static function getRandomPIN( $length = 6 ) {
		if ( !is_numeric( $length ) || empty( $length ) ) {
			$length = 5;
		} else {
			$length = (int)$length;
		}

		$pin = (string)str_pad( wp_rand( 0, (int)str_repeat( '9', $length ) ), $length, '0', STR_PAD_LEFT);

		if ( substr_count( $pin, '0' ) >= $length - 2 || count( array_unique( str_split( $pin ) ) ) <= ceil( $length / 2 ) ) {
			return self::getRandomPIN( $length );
		}

		return $pin;
	}

	/**
	 * Generates a random key.
	 *
	 * @return string
	 */
	public static function getRandomKey() {
		return sodium_crypto_secretbox_keygen();
	}

	/**
	 * Encrypts a value.
	 *
     * @param string $data data to be encrypted
     * @param string $pin optional PIN to modify the used key
     * @param int $paddingBlockSize byte size of chunks used for padding
     * @return string
     */
    public function encrypt( $data, $pin = '', $paddingBlockSize = 64 )
    {
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

        // add padding in chunks up to $paddingBlockSize byte
        $paddedData = sodium_pad( $data, $paddingBlockSize <= 512 ? $paddingBlockSize : 512 );

        // prepend nonce, encrypt and encode with base64
        $encrypted = base64_encode( $nonce . sodium_crypto_secretbox( $paddedData, $nonce, $pin | $this->key ) );

        // free memory, if sodium_memzero() is implemented
		try {
			sodium_memzero( $data );
			sodium_memzero( $pin );
		} catch ( Exception $e ) { }

        return $encrypted;
	}

	/**
	 * Decrypts a value.
	 *
     * @param string $encrypted cipher to be decrypted
     * @param string $key optional pin to modify the used key
     * @param int $paddingBlockSize byte size of chunks used for padding
     * @return string
	 */
	public function decrypt( $encrypted, $pin = '', $paddingBlockSize = 64  ) {
        $decoded = base64_decode( $encrypted );

        if ( false === $decoded ) {
            return false;
        }

        if ( ! defined( 'CRYPTO_SECRETBOX_MACBYTES' ) ) {
			$macBytes = defined( "ParagonIE_Sodium_Compat::CRYPTO_SECRETBOX_MACBYTES" )
				? ParagonIE_Sodium_Compat::CRYPTO_SECRETBOX_MACBYTES
				: 16;
		} else {
			$macBytes = CRYPTO_SECRETBOX_MACBYTES;
		}

        if ( mb_strlen( $decoded, '8bit' ) < ( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + $macBytes ) ) {
            return false;
        }

        // extract nonce and encrypted data
        $nonce = mb_substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
        $encryptedData = mb_substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

        // decrypt data and remove padding
        $paddedData = sodium_crypto_secretbox_open( $encryptedData, $nonce, $pin | $this->key ) ;

		if ( false === $paddedData ) {
			return false;
		}

        $data = sodium_unpad( $paddedData, $paddingBlockSize <= 512 ? $paddingBlockSize : 512 ) ;

        if ( false === $data ) {
            return false;
        }

        // free memory, if sodium_memzero() is implemented
		try {
			sodium_memzero( $encrypted );
			sodium_memzero( $pin );
		} catch ( Exception $e ) { }

        return $data;
	}

	/**
	 * Gets the default encryption key to use.
	 *
	 * @return string Default (not user-based) encryption key.
	 */
	private function getDefaultKey() {
		if ( defined( 'CTR_ENCRYPTION_KEY' ) && '' !== CTR_ENCRYPTION_KEY ) {
			return CTR_ENCRYPTION_KEY;
		}

		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return LOGGED_IN_KEY;
		}

		throw new \Exception( 'Could not get default key' );
	}

	/**
	 * Gets the default encryption salt to use.
	 *
	 *
	 * @return string Encryption salt.
	 */
	private function getDefaultSalt() {
		if ( defined( 'CTR_ENCRYPTION_SALT' ) && '' !== CTR_ENCRYPTION_SALT ) {
			return CTR_ENCRYPTION_SALT;
		}

		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			return LOGGED_IN_SALT;
		}

		// If this is reached, you're either not on a live site or have a serious security issue.
		return hash( 'sha512', __FILE__ );
	}
}
?>
