<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: antibot_challenge.php
 * Anti-Bot Challenge - generation, encoding & utilities
 */

require_once __DIR__."/config.php";
require_once __DIR__."/antibot_exception.php";
require_once __DIR__."/icon_db.php";
require_once __DIR__."/simple_crypt.php";

/**
 * Anti-Bot Challenge
 */
class antibot_challenge
{
	/** @var int the time this challenge was generated (seconds since the Unix Epoch) */
	private $spawn_time = 0;

	/** @var int index of the target icon */
	private $target_icon = 0;

	/** @var int[] indices of the icons to be displayed */
	private $icons = array();

	/**
	 * Constructor
	 * @param int $target_icon index of the target icon
	 * @param int[] $icons indices of the icons to be displayed
	 * @param int|null $spawn_time unix timestamp
	 */
	private function __construct($target_icon, $icons, $spawn_time = null)
	{
		$this->target_icon = $target_icon;
		$this->icons = $icons;
		$this->spawn_time = $spawn_time === null ? time() : $spawn_time;
	}

	/**
	 * Generate a random challenge
	 * @param int $num_distinct minimum number of distinct icons
	 * @param int $num_repeated minimum number of times each distinct icon will be repeated
	 * @return antibot_challenge
	 */
	public static function generate($num_distinct, $num_repeated)
	{
		mt_srand(time() + crc32(php_uname())); // note: we call mt_srand() in image.php and it seems to influence things here, so we need a new seed
		$num_distinct += mt_rand(0, 2);

		$n = icon_db::count();
		if($num_distinct > $n)
			throw new antibot_exception("you need at least $num_distinct installed icons. You have only $n. Go get some more!");

		$challenge_size = max($num_distinct * $num_repeated, 0);
		if($challenge_size == 0)
			throw new antibot_exception("can't generate a challenge of size zero");
		$challenge_size += mt_rand(0, $num_repeated - 1);

		$known_icons = self::shuffle(range(0, $n - 1));
		$challenge_icons = array();
		for($i = 0; $i < $challenge_size; $i++)
			$challenge_icons[$i] = $known_icons[$i % $num_distinct];
		$target_icon = $challenge_icons[0];

		return new antibot_challenge($target_icon, self::shuffle($challenge_icons));
	}

	/**
	 * Encode this challenge as a string
	 * @return string
	 */
	public function encode()
	{
		$header = array($this->integrity_key(), $this->spawn_time, $this->target_icon);
		$serialized = array_merge($header, $this->icons);
		$str = implode(",", $serialized);

		return simple_crypt::encrypt($str);
	}

	/**
	 * Decode a challenge from a string
	 * @param string $encoded_challenge
	 * @return antibot_challenge
	 */
	public static function decode($encoded_challenge)
	{
		$str = simple_crypt::decrypt($encoded_challenge);
		$serialized = explode(",", $str);
		if(count($serialized) < 4)
			throw new antibot_exception("input has been tampered with.");

		list($integrity_key, $spawn_time, $target_icon) = array_slice($serialized, 0, 3);
		$icons = array_slice($serialized, 3);

		$spawn_time = intval($spawn_time);
		$target_icon = intval($target_icon);
		$icons = array_map("intval", $icons);

		$challenge = new antibot_challenge($target_icon, $icons, $spawn_time);
		if($challenge->integrity_key() !== $integrity_key || $challenge->size() == 0 || !in_array($target_icon, $icons))
			throw new antibot_exception("input has been tampered with.");

		return $challenge;
	}

	/**
	 * Has this challenge expired?
	 * @return bool
	 */
	public function expired()
	{
		return time() >= $this->spawn_time + CHALLENGE_EXPIRE_TIME * 60;
	}

	/**
	 * The moment this challenge was generated (unix timestamp)
	 * @return int
	 */
	public function spawn_time()
	{
		return $this->spawn_time;
	}

	/**
	 * The target icon of this challenge, i.e., the desired icon
	 * @return int
	 */
	public function target()
	{
		return $this->target_icon;
	}

	/**
	 * The icons of this challenge
	 * @return int[]
	 */
	public function icons()
	{
		return array_slice($this->icons, 0);
	}

	/**
	 * The set of icons that appear in this challenge
	 * @return int[]
	 */
	public function distinct_icons()
	{
		return array_values(array_unique($this->icons));
	}

	/**
	 * The size of this challenge, i.e., how many icons are displayed
	 * @return int
	 */
	public function size()
	{
		return count($this->icons);
	}

	/**
	 * The number of times the target icon appears in the challenge
	 * @return int
	 */
	public function answer_size()
	{
		$k = 0;
		foreach($this->icons as $icon) {
			if($icon == $this->target_icon)
				$k++;
		}

		return $k;
	}

	/**
	 * Check if an answer is correct
	 * @param string $answer user-provided answer
	 * @return bool
	 */
	public function verify($answer)
	{
		if($this->expired())
			return false;

		$ans = explode(",", $answer);
		sort($ans, SORT_NUMERIC);
		$answer = implode(",", $ans);

		return $answer === $this->answer();
	}

	/**
	 * Fisher-Yates shuffle algorithm
	 * @param array $arr
	 * @return array shuffled $arr
	 */
	private static function shuffle(&$arr)
	{
		for($i = count($arr) - 1; $i > 0; $i--) {
			$j = PHP_VERSION_ID >= 70000 ? random_int(0, $i) : mt_rand(0, $i);
			list($arr[$i], $arr[$j]) = array($arr[$j], $arr[$i]);
		}

		return $arr;
	}

	/**
	 * Compute an answer to this challenge
	 * @return string
	 */
	private function answer()
	{
		$ans = array(); // monotonic increasing

		$n = count($this->icons);
		for($i = 0; $i < $n; $i++) {
			if($this->icons[$i] == $this->target_icon)
				$ans[] = $i + 1;
		}

		return implode(",", $ans);
	}

	/**
	 * A code used to check the integrity of this challenge
	 * @return string
	 */
	private function integrity_key()
	{
		$arr = array_merge(array($this->spawn_time, $this->target_icon), $this->icons);
		$str = implode(",", $arr);

		return hash("crc32b", $str.__FILE__);
	}
}
