<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: icon_db.php
 * Icon utilities
 */

require_once __DIR__."/config.php";
require_once __DIR__."/antibot_exception.php";

/**
 * Icon utilities
 *
 * Icons are uniquely associated to indices 0, 1, ..., n-1,
 * where n is the number of icons that are available. You
 * may use those indices to get the name of the file of
 * a particular icon.
 *
 * Working with indices is handy when it comes to
 * serialization.
 */
class icon_db
{
	/** @var array convert icon name to icon index */
	private static $name2index = array();

	/** @var string[] convert icon index to icon name */
	private static $index2name = array();

	/** @var string[] convert icon index to icon extension */
	private static $index2ext = array();

	/** @var bool already initialized? */
	private static $initialized = false;

	/**
	 * Get the index of an icon
	 * @param string $icon_name name of the icon
	 * @return int index of the icon, or -1 if not found
	 */
	public static function get_index($icon_name)
	{
		if(isset(self::$name2index[$icon_name]))
			return self::$name2index[$icon_name];
		else
			return -1;
	}

	/**
	 * Get the name of an icon
	 * @param int $icon_index index of the icon
	 * @return string name of the icon, or "" if not found
	 */
	public static function get_name($icon_index)
	{
		if(isset(self::$index2name[$icon_index]))
			return self::$index2name[$icon_index];
		else
			return "";
	}

	/**
	 * Get the full filepath of an icon (in the host filesystem)
	 * @param int $icon_index index of the icon
	 * @return string absolute path, or "" if the icon doesn't exist
	 */
	public static function get_filepath($icon_index)
	{
		$icon_name = self::get_name($icon_index);
		if($icon_name !== "") {
			$icon_ext = self::$index2ext[$icon_index];
			return __DIR__.DIRECTORY_SEPARATOR.CHALLENGE_ICON_FOLDER.DIRECTORY_SEPARATOR.$icon_name.$icon_ext;
		}
		else
			return "";
	}

	/**
	 * The number of available icons
	 * @return int
	 */
	public static function count()
	{
		return count(self::$index2name);
	}

	/**
	 * Initialize this class
	 * @return void
	 */
	public static function init()
	{
		if(self::$initialized)
			return;

		$icons = glob(__DIR__.DIRECTORY_SEPARATOR.CHALLENGE_ICON_FOLDER.DIRECTORY_SEPARATOR."*.[Pp][Nn][Gg]");
		sort($icons, SORT_STRING);
		for($i = count($icons) - 1; $i >= 0; $i--) {
			$file = basename($icons[$i]);
			self::$index2ext[$i] = substr($file, strlen($file) - 4);
			self::$index2name[$i] = substr($file, 0, strlen($file) - 4);
			self::$name2index[self::$index2name[$i]] = $i;
		}

		if(self::count() == 0)
			throw new antibot_exception("no icons have been found. Have you uploaded any to CHALLENGE_ICON_FOLDER?");

		self::$initialized = true;
	}
}
