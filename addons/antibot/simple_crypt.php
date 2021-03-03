<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: simple_crypt.php
 * Simple encryption & decryption
 */

require_once __DIR__."/config.php";

/**
 * Simple encryption and decryption of strings
 */
class simple_crypt
{
	/**
	 * Encrypt a string
	 * @param string $string string to be encrypted
	 * @returns string
	 */
	public static function encrypt($string)
	{
		return self::run($string, true);
	}

	/**
	 * Decrypt a string
	 * @param string $string string to be decrypted
	 * @returns string
	 */
	public static function decrypt($string)
	{
		return self::run($string, false);
	}

	/**
	 * Encrypt & decrypt
	 *
	 * @param string $string string to be encrypted or decrypted
	 * @param bool $encrypt do you need encryption or decryption?
	 * @return string
	 */
	private static function run($string, $encrypt)
	{
	    $secret_key = CHALLENGE_SECRET_KEY;

	    $method = "AES-256-CBC";
	    $key = hash_hmac("sha256", $secret_key, ".".__FILE__);
	    $ivlen = openssl_cipher_iv_length($method);
	 
	    if($encrypt) {
		$iv = openssl_random_pseudo_bytes($ivlen);
		$enc = openssl_encrypt($string, $method, $key, 0, $iv);
		return base64_encode($iv.$enc);
	    }
	    else {
		$string = base64_decode($string);
		$iv = substr($string, 0, $ivlen);
		$enc = substr($string, $ivlen);
		return openssl_decrypt($enc, $method, $key, 0, $iv);
	    }
	}
}
