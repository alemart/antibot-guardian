<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: antibot_exception.php
 * Exception class
 */

/**
 * Anti-Bot Exception class
 */
class antibot_exception extends Exception
{
	/**
	 * String representation of the exception
	 * @return string
	 */
	public function __toString()
	{
		return "Anti-Bot error: ".$this->message;
	}
}
