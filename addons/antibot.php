<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: antibot.php
 * FluxBB addon
 */

require_once __DIR__."/antibot/icon_db.php";
require_once __DIR__."/antibot/antibot_challenge.php";

class addon_antibot extends flux_addon
{
	/** @var array language-specific strings */
	private $lang = array();

	/** maximum number of exceeding <select> tags */
	const MAX_EXCEEDING_SELECTS = 3;

	/**
	 * Register this addon
	 * @param flux_addon_manager $manager
	 * @return void
	 */
	public function register($manager)
	{
		global $pun_user;

		if(!$pun_user["is_guest"])
			return;

		$manager->bind("register_before_submit", array($this, "hook_before_submit"));
		$manager->bind("register_after_validation", array($this, "hook_after_validation"));
	}

	/**
	 * FluxBB before_submit hook
	 * @return void
	 */
	public function hook_before_submit()
	{
		global $pun_config;

		try {
			$this->init();

			list($a, $b) = array(CHALLENGE_COLUMNS, CHALLENGE_MIN_ROWS);
			$challenge = antibot_challenge::generate(min($a, $b), max($a, $b));

			$target_icon_name = icon_db::get_name($challenge->target());
			if(isset($this->lang["icon"][$target_icon_name]))
				$target_icon_name = $this->lang["icon"][$target_icon_name];

			$title = htmlspecialchars($this->lang["title"]);
			$reload = htmlspecialchars($this->lang["reload"]);
			$question = nl2br(htmlspecialchars(sprintf($this->lang["question"], $target_icon_name)));
			$info = nl2br(htmlspecialchars($this->lang["info"]));
			$footer = nl2br(htmlspecialchars($this->lang["footer"]));

			$num_selects = $challenge->answer_size() + mt_rand(0, self::MAX_EXCEEDING_SELECTS);
			$selects = $this->generate_selects($num_selects, $challenge->size());

			$challenge_code = $challenge->encode();
			$challenge_image = $pun_config["o_base_url"]."/".CHALLENGE_BASEDIR."/image.php?q=".urlencode($challenge_code);

			echo <<<EOT
<!-- Anti-Bot Guardian -->
<div class="inform">
	<fieldset>
		<legend>$title</legend>
		<div class="infldset">
			<label class="required">
				<span>$question</span>
				<span>&nbsp;</span>
				$selects
				<span>&nbsp;</span>
				<a href="javascript:reload_challenge()">$reload</a>
				<input type="hidden" name="challenge_code" value="$challenge_code">
			</label>
			<p>$info</p>
			<img src="$challenge_image" id="antibot_challenge" alt="">
			<p>$footer</p>
		</div>
	</fieldset>
</div>
<script>
window.reload_challenge = (function() {
	var t = 0;
	return function() {
		var img = document.getElementById("antibot_challenge");
		img.src = "";
		img.src = "$challenge_image&t=" + (++t);
	};
})();
</script>
<!-- Anti-Bot Guardian -->
EOT;
		}
		catch(Exception $e) {
			echo "Anti-Bot Error: ".htmlspecialchars($e->getMessage());
		}
	}

	/**
	 * FluxBB after_validation hook
	 * @return void
	 */
	public function hook_after_validation()
	{
		global $errors;

		try {
			$this->init();

			if(!isset($_POST["challenge_code"]))
				throw new Exception($this->lang["error"]);

			$challenge = antibot_challenge::decode($_POST["challenge_code"]);
			if($challenge->expired())
				throw new Exception($this->lang["expired"]);
			else if($challenge->answer_size() == 0)
				throw new Exception($this->lang["error"]);

			$user_answer = array();
			$num_selects = $challenge->answer_size() + $this->max_exceeding_selects;
			for($i = 0; $i < $num_selects; $i++) {
				if(0 < ($ci = isset($_POST["challenge_$i"]) ? intval($_POST["challenge_$i"]) : 0))
					$user_answer[] = $ci;
			}

			$answer = implode(",", array_unique($user_answer));
			if(!$challenge->verify($answer))
				throw new Exception($this->lang["error"]);
		}
		catch(Exception $e) {
			$errors[] = htmlspecialchars($e->getMessage());
		}
	}

	/**
	 * Load the language-specific strings of this addon
	 * @return array
	 */
	private function load_lang()
	{
		global $pun_user;

		$user_lang = file_exists(PUN_ROOT."lang/".$pun_user["language"]."/antibot.php") ? $pun_user["language"] : "English";
		require_once PUN_ROOT."lang/".$user_lang."/antibot.php";
		return $lang_antibot;
	}

	/**
	 * Initialization routine
	 * @return void
	 */
	private function init()
	{
		icon_db::init();

		if(count($this->lang) == 0)
			$this->lang = $this->load_lang();
	}

	/**
	 * Generate a set of <option> tags
	 * @param int $num_options at least 1
	 * @return string
	 */
	private function generate_options($num_options)
	{
		$str = "<option value=\"0\" selected>-</option>";
		for($i = 1; $i <= $num_options; $i++)
			$str .= "<option value=\"$i\">$i</option>";

		return $str;
	}

	/**
	 * Generate a set of <select> tags
	 * @param int $num_selects number of select tags you want
	 * @param int $num_options number of options that each select holds
	 * @return string
	 */
	private function generate_selects($num_selects, $num_options)
	{
		$options = $this->generate_options($num_options);

		$str = "";
		for($i = 0; $i < $num_selects; $i++)
			$str .= "<select name=\"challenge_$i\">$options</select>\n";

		return $str;
	}
}
