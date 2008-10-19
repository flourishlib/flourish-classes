<?php
/**
 * Provides cryptography functionality, including hashing, symmetric-key encryption and public-key encryption
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fCryptography
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-11-27]
 */
class fCryptography
{
	// The following constants allow for nice looking callbacks to static methods
	const checkPasswordHash   = 'fCryptography::checkPasswordHash';
	const hashPassword        = 'fCryptography::hashPassword';
	const publicKeyDecrypt    = 'fCryptography::publicKeyDecrypt';
	const publicKeyEncrypt    = 'fCryptography::publicKeyEncrypt';
	const publicKeySign       = 'fCryptography::publicKeySign';
	const publicKeyVerify     = 'fCryptography::publicKeyVerify';
	const random              = 'fCryptography::random';
	const randomString        = 'fCryptography::randomString';
	const symmetricKeyDecrypt = 'fCryptography::symmetricKeyDecrypt';
	const symmetricKeyEncrypt = 'fCryptography::symmetricKeyEncrypt';
	
	
	/**
	 * Checks a password against a hash created with ::hashPassword()
	 * 
	 * @param  string $password  The password to check
	 * @param  string $hash      The hash to check against
	 * @return boolean  If the password matches the hash
	 */
	static public function checkPasswordHash($password, $hash)
	{
		$salt = substr($hash, 29, 10);
		
		if (self::hashWithSalt($password, $salt) == $hash) {
			return TRUE;
		}
		
		return FALSE;
	}
	
	
	/**
	 * Create a private key resource based on a filename and password
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return resource  The private key resource
	 */
	static private function createPrivateKeyResource($private_key_file, $password)
	{
		if (!file_exists($private_key_file)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The path to the PEM-encoded private key specified, %s, is not valid',
					fCore::dump($private_key_file)
				)
			);
		}
		if (!is_readable($private_key_file)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The PEM-encoded private key specified, %s, is not readable',
					fCore::dump($private_key_file)
				)
			);
		}
		
		$private_key          = file_get_contents($private_key_file);
		$private_key_resource = openssl_pkey_get_private($private_key, $password);
		
		if ($private_key_resource === FALSE) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The private key file specified, %s, does not appear to be a valid private key or the password provided is incorrect',
					fCore::dump($private_key_file)
				)
			);
		}
		
		return $private_key_resource;
	}
	
	
	/**
	 * Create a public key resource based on a filename
	 * 
	 * @param  string $public_key_file  The path to an X.509 public key certificate
	 * @return resource  The public key resource
	 */
	static private function createPublicKeyResource($public_key_file)
	{
		if (!file_exists($public_key_file)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The path to the X.509 certificate specified, %s, is not valid',
					fCore::dump($public_key_file)
				)
			);
		}
		if (!is_readable($public_key_file)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The X.509 certificate specified, %s, can not be read',
					fCore::dump($public_key_file)
				)
			);
		}
		
		$public_key = file_get_contents($public_key_file);
		$public_key_resource = openssl_pkey_get_public($public_key);
		
		if ($public_key_resource === FALSE) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The public key certificate specified, %s, does not appear to be a valid certificate',
					fCore::dump($public_key_file)
				)
			);
		}
		
		return $public_key_resource;
	}
	
	
	/**
	 * Hashes a password using a loop of sha1 hashes and a salt, making rainbow table attacks infeasible
	 * 
	 * @param  string $password  The password to hash
	 * @return string  An 80 character string of the Flourish fingerprint, salt and hashed password
	 */
	static public function hashPassword($password)
	{
		$salt = self::randomString(10);
		
		return self::hashWithSalt($password, $salt);
	}
	
	
	/**
	 * Performs a large iteration of hashing a string with a salt
	 * 
	 * @param  string $source  The string to hash
	 * @param  string $salt    The salt for the hash
	 * @return string  An 80 character string of the Flourish fingerprint, salt and hashed password
	 */
	static private function hashWithSalt($source, $salt)
	{
		$sha1 = sha1($salt . $source);
		for ($i = 0; $i < 1000; $i++) {
			$sha1 = sha1($sha1 . (($i % 2 == 0) ? $source : $salt));
		}
		
		return 'fCryptography::password_hash#' . $salt . '#' . $sha1;
	}
		
	
	/**
	 * Decrypts ciphertext encrypted using public-key encryption via ::publicKeyEncrypt()
	 * 
	 * A public key (X.509 certificate) is required for encryption and a
	 * private key (PEM) is required for decryption.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $ciphertext        The content to be decrypted
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return string  The decrypted plaintext
	 */
	static public function publicKeyDecrypt($ciphertext, $private_key_file, $password)
	{
		self::verifyPublicKeyEnvironment();
		
		$private_key_resource = self::createPrivateKeyResource($private_key_file, $password);
		
		$elements = explode('#', $ciphertext);
		
		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if (sizeof($elements) != 4 || $elements[0] != 'fCryptography::public') {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The ciphertext provided does not appear to have been encrypted using %s',
					__CLASS__ . '::publicKeyEncrypt()'
				)
			);
		}
		
		$encrypted_key = base64_decode($elements[1]);
		$ciphertext    = base64_decode($elements[2]);
		$provided_hmac = $elements[3];
		
		$plaintext = '';
		$result = openssl_open($ciphertext, $plaintext, $encrypted_key, $private_key_resource);
		openssl_free_key($private_key_resource);
		
		if ($result === FALSE) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'There was an unknown error decrypting the ciphertext provided'
				)
			);
		}
		
		$hmac = hash_hmac('sha1', $encrypted_key . $ciphertext, $plaintext);
		
		// By verifying the HMAC we ensure the integrity of the data
		if ($hmac != $provided_hmac) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The ciphertext provided appears to have been tampered with or corrupted'
				)
			);
		}
		
		return $plaintext;
	}
	
	
	/**
	 * Encrypts the passed data using public key encryption via OpenSSL
	 * 
	 * A public key (X.509 certificate) is required for encryption and a
	 * private key (PEM) is required for decryption.
	 * 
	 * @param  string $plaintext        The content to be encrypted
	 * @param  string $public_key_file  The path to an X.509 public key certificate
	 * @return string  A base-64 encoded result containing a Flourish fingerprint and suitable for decryption using ::publicKeyDecrypt()
	 */
	static public function publicKeyEncrypt($plaintext, $public_key_file)
	{
		self::verifyPublicKeyEnvironment();
		
		$public_key_resource = self::createPublicKeyResource($public_key_file);
		
		$ciphertext     = '';
		$encrypted_keys = array();
		$result = openssl_seal($plaintext, $ciphertext, $encrypted_keys, array($public_key_resource));
		openssl_free_key($public_key_resource);
		
		if ($result === FALSE) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'There was an unknown error encrypting the plaintext provided'
				)
			);
		}
		
		$hmac = hash_hmac('sha1', $encrypted_keys[0] . $ciphertext, $plaintext);
		
		return 'fCryptography::public#' . base64_encode($encrypted_keys[0]) . '#' . base64_encode($ciphertext) . '#' . $hmac;
	}
	
	
	/**
	 * Creates a signature for plaintext to allow verification of the creator
	 * 
	 * A private key (PEM) is required for signing and a public key
	 * (X.509 certificate) is required for verification.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $plaintext         The content to be signed
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return string  The base64-encoded signature suitable for verification using ::publicKeyVerify()
	 */
	static public function publicKeySign($plaintext, $private_key_file, $password)
	{
		self::verifyPublicKeyEnvironment();
		
		$private_key_resource = self::createPrivateKeyResource($private_key_file, $password);
		
		$result = openssl_sign($plaintext, $signature, $private_key_resource);
		openssl_free_key($private_key_resource);
		
		if (!$result) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'There was an unknown error signing the plaintext'
				)
			);
		}
		
		return base64_encode($signature);
	}
	
	
	/**
	 * Checks a signature for plaintext to verify the creator - works with ::publicKeySign()
	 * 
	 * A private key (PEM) is required for signing and a public key
	 * (X.509 certificate) is required for verification.
	 * 
	 * @param  string $plaintext         The content to check
	 * @param  string $signature         The base64-encoded signature for the plaintext
	 * @param  string $public_key_file   The path to an X.509 public key certificate
	 * @return boolean  If the public key file is the public key of the user who signed the plaintext
	 */
	static public function publicKeyVerify($plaintext, $signature, $public_key_file)
	{
		self::verifyPublicKeyEnvironment();
		
		$public_key_resource = self::createPublicKeyResource($public_key_file);
		
		$result = openssl_verify($plaintext, base64_decode($signature), $public_key_resource);
		openssl_free_key($public_key_resource);
		
		if ($result === -1) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'There was an unknown error verifying the plaintext and signature against the public key specified'
				)
			);
		}
		
		return ($result === 1) ? TRUE : FALSE;
	}
	
	
	/**
	 * Generates a random number using [http://php.net/mt_rand mt_rand()] after ensuring a good PRNG seed
	 * 
	 * @param  integer $min  The minimum number to return
	 * @param  integer $max  The maximum number to return
	 * @return integer  The psuedo-random number
	 */
	static public function random($min=NULL, $max=NULL)
	{
		self::seedRandom();
		if ($min !== NULL || $max !== NULL) {
			return mt_rand($min, $max);
		}
		return mt_rand();
	}
	
	
	/**
	 * Returns a random string of the type and length specified
	 * 
	 * @param  integer $length  The length of string to return
	 * @param  string  $type    The type of string to return: `'alphanumeric'`, `'alpha'`, `'numeric'`, or `'hexadecimal'`
	 * @return string  A random string of the type and length specified
	 */
	static public function randomString($length, $type='alphanumeric')
	{
		if ($length < 1) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The length specified, %1$s, is less than the minimum of %2$s',
					$length,
					1
				)
			);
		}
		
		switch ($type) {
			case 'alphanumeric':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
				break;
				
			case 'alpha':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;
				
			case 'numeric':
				$alphabet = '0123456789';
				break;
				
			case 'hexadecimal':
				$alphabet = 'abcdef0123456789';
				break;
				
			default:
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The type specified, %1$s, is invalid. Must be one of: %2$s.',
						fCore::dump($type)
					)
				);
		}
		
		$alphabet_length = strlen($alphabet);
		$output = '';
		
		for ($i = 0; $i < $length; $i++) {
			$output .= $alphabet[self::random(0, $alphabet_length-1)];
		}
		
		return $output;
	}
	
	
	/**
	 * Makes sure that the PRNG has been seeded with a fairly secure value
	 * 
	 * @return void
	 */
	static private function seedRandom()
	{
		static $seeded = FALSE;
		
		if ($seeded) {
			return;
		}
		
		// On linux/unix/solaris we should be able to use /dev/urandom
		if (fCore::getOS() != 'windows' && @$handle = fopen('/dev/urandom', 'rb')) {
			$bytes = fread($handle, 32);
			fclose($handle);
				
		// On windows we should be able to use the Cryptographic Application Programming Interface COM object
		} elseif (fCore::getOS() == 'windows' && class_exists('COM', FALSE)) {
			$capi  = new COM('CAPICOM.Utilities.1');
			$bytes = base64_decode($capi->getrandom(32, 0));
			unset($capi);
		
		// Otherwise we get some of the most unique info we can		
		} else {
			$bytes = microtime(TRUE) . uniqid('', TRUE) . join('', stat(__FILE__)) . disk_free_space(__FILE__);	
		}
		
		$seed = md5($bytes);
		$seed = base_convert($seed, 16, 10);
		$seed = (double) substr($seed, 0, 13) + (double) substr($seed, 14, 13);
		
		mt_srand($seed);
		
		$seeded = TRUE;
	}
	
	
	/**
	 * Decrypts ciphertext encrypted using symmetric-key encryption via ::symmetricKeyEncrypt()
	 * 
	 * Since this is symmetric-key cryptography, the same key is used for
	 * encryption and decryption.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $ciphertext  The content to be decrypted
	 * @param  string $secret_key  The secret key to use for decryption
	 * @return string  The decrypted plaintext
	 */
	static public function symmetricKeyDecrypt($ciphertext, $secret_key)
	{
		self::verifySymmetricKeyEnvironment();
		
		$elements = explode('#', $ciphertext);
		
		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if (sizeof($elements) != 4 || $elements[0] != 'fCryptography::symmetric') {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The ciphertext provided does not appear to have been encrypted using %s',
					__CLASS__ . '::symmetricKeyEncrypt()'
				)
			);
		}
		
		$encrypted_iv  = base64_decode($elements[1]);
		$ciphertext    = base64_decode($elements[2]);
		$provided_hmac = $elements[3];
		
		$hmac = hash_hmac('sha1', $encrypted_iv . $ciphertext, $secret_key);
		
		// By verifying the HMAC we ensure the integrity of the data
		if ($hmac != $provided_hmac) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The ciphertext provided appears to have been tampered with or corrupted'
				)
			);
		}
		
		// Decrypt the IV so we can feed it into the main decryption
		$iv_module = mcrypt_module_open('tripledes', '',  'ecb', '');
		$iv_key    = substr($secret_key, 0, mcrypt_enc_get_key_size($iv_module));
		mcrypt_generic_init($iv_module, $iv_key, '12345678');
		$iv        = mdecrypt_generic($iv_module, $encrypted_iv);
		mcrypt_generic_deinit($iv_module);
		mcrypt_module_close($iv_module);
		
		// Set up the main encryption, we are gonna use AES-256 (also know as rijndael-256) in cipher feedback mode
		$module   = mcrypt_module_open('rijndael-192', '', 'cfb', '');
		$key      = substr(sha1($secret_key), 0, mcrypt_enc_get_key_size($module));
		mcrypt_generic_init($module, $key, $iv);
		$plaintext = mdecrypt_generic($module, $ciphertext);
		mcrypt_generic_deinit($module);
		mcrypt_module_close($module);
		
		return $plaintext;
	}
	
	
	/**
	 * Encrypts the passed data using symmetric-key encryption
	 *
	 * Since this is symmetric-key cryptography, the same key is used for
	 * encryption and decryption.
	 *  
	 * @param  string $plaintext   The content to be encrypted
	 * @param  string $secret_key  The secret key to use for encryption
	 * @return string  An encrypted and base-64 encoded result containing a Flourish fingerprint and suitable for decryption using ::symmetricKeyDecrypt()
	 */
	static public function symmetricKeyEncrypt($plaintext, $secret_key)
	{
		if (strlen($secret_key) < 8) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The secret key specified does not meet the minimum requirement of being at least %s characters long',
					8
				)
			);
		}
		
		self::verifySymmetricKeyEnvironment();
		
		// Set up the main encryption, we are gonna use AES-192 (also know as rijndael-192)
		// in cipher feedback mode. Cipher feedback mode is chosen because no extra padding
		// is added, ensuring we always get the exact same plaintext out of the decrypt method
		$module   = mcrypt_module_open('rijndael-192', '', 'cfb', '');
		$key      = substr(sha1($secret_key), 0, mcrypt_enc_get_key_size($module));
		srand();
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($module), MCRYPT_RAND);
		
		// Encrypt the IV for storage to prevent man in the middle attacks. This uses
		// electronic codebook since it is suitable for encrypting the IV.
		$iv_module = mcrypt_module_open('tripledes', '',  'ecb', '');
		$iv_key    = substr($secret_key, 0, mcrypt_enc_get_key_size($iv_module));
		mcrypt_generic_init($iv_module, $iv_key, '12345678');
		$encrypted_iv = mcrypt_generic($iv_module, $iv);
		mcrypt_generic_deinit($iv_module);
		mcrypt_module_close($iv_module);
		
		// Finish the main encryption
		mcrypt_generic_init($module, $key, $iv);
		$ciphertext = mcrypt_generic($module, $plaintext);
		
		// Clean up the main encryption
		mcrypt_generic_deinit($module);
		mcrypt_module_close($module);
		
		// Here we are generating the HMAC for the encrypted data to ensure data integrity
		$hmac = hash_hmac('sha1', $encrypted_iv . $ciphertext, $secret_key);
		
		// All of the data is then encoded using base64 to prevent issues with character sets
		$encoded_iv         = base64_encode($encrypted_iv);
		$encoded_ciphertext = base64_encode($ciphertext);
		
		// Indicate in the resulting encrypted data what the encryption tool was
		return 'fCryptography::symmetric#' . $encoded_iv . '#' . $encoded_ciphertext . '#' . $hmac;
	}
	
	
	/**
	 * Makes sure the required PHP extensions and library versions are all correct
	 * 
	 * @return void
	 */
	static private function verifyPublicKeyEnvironment()
	{
		if (!extension_loaded('openssl')) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The PHP %s extension is required, however is does not appear to be loaded',
					'openssl'
				)
			);
		}
	}
	
	
	/**
	 * Makes sure the required PHP extensions and library versions are all correct
	 * 
	 * @return void
	 */
	static private function verifySymmetricKeyEnvironment()
	{
		if (!extension_loaded('mcrypt')) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The PHP %s extension is required, however is does not appear to be loaded',
					'mcrypt'
				)
			);
		}
		if (!extension_loaded('hash')) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The PHP %s extension is required, however is does not appear to be loaded',
					'hash'
				)
			);
		}
		if (!function_exists('mcrypt_module_open')) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The cipher used, %1$s (also known as %2$s), requires libmcrypt version 2.4.x or newer. The version installed does not appear to meet this requirement.',
					'AES-192',
					'rijndael-192'
				)
			);
		}
		if (!in_array('rijndael-192', mcrypt_list_algorithms())) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The cipher used, %1$s (also known as %2$s), does not appear to be supported by the installed version of libmcrypt',
					'AES-192',
					'rijndael-192'
				)
			);
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fSecurity
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */