<?php
/*
 * Anti-Bot Guardian
 * Copyright 2021  Alexandre Martins <alemartf(at)gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * File: image.php
 * Image generation / visual challenge
 */

require_once __DIR__."/config.php";
require_once __DIR__."/antibot_exception.php";
require_once __DIR__."/antibot_challenge.php";
require_once __DIR__."/icon_db.php";

$message = "Anti-Bot Guardian";
$bgcolor = 0xFFFFFF;
$fgcolor = ~$bgcolor & 0xFFFFFF;

header("Content-type: image/png");

try {

// initialize
icon_db::init();

// read the challenge code from the query string
if(isset($_GET["q"]))
	$challenge = antibot_challenge::decode($_GET["q"]);

// validate the challenge
if(!isset($challenge))
	throw new antibot_exception("challenge not set");
//if($challenge->expired())
//	throw new antibot_exception("challenge expired");

// reload image
$seed_offset = isset($_GET["t"]) ? intval($_GET["t"]) % CHALLENGE_RELOAD_CYCLE_LENGTH : 0;

// we want the same image output for different
// requests that refer to the same challenge
mt_srand($challenge->spawn_time() + crc32(php_uname()) + $seed_offset);

// create a permutation
$permutation = my_shuffle(range(0, $challenge->size() - 1));

// how many distinct icons will show up?
$distinct_icons = $challenge->distinct_icons();
$num_distinct_icons = count($distinct_icons);

// load distinct icons
list($icon_width, $icon_height) = array(CHALLENGE_ICON_WIDTH, CHALLENGE_ICON_HEIGHT);
$icons = array_combine($distinct_icons, $distinct_icons);
foreach($icons as &$ico) {
	$filepath = icon_db::get_filepath($ico);
	$ico = imagecreatetruecolor($icon_width, $icon_height);
	imagefill($ico, 0, 0, 0xFFFFFF00);

	// resize the icon to $icon_width x $icon_height
	$im = imagecreatefrompng($filepath);
	list($imw, $imh) = array(imagesx($im), imagesy($im));
	$aspect_ratio = $imw / $imh;
	list($icow, $icoh) = $aspect_ratio >= 1 ?
		array($icon_width, $icon_height / $aspect_ratio) :
		array($icon_width * $aspect_ratio, $icon_height);
	imagecopyresized($ico, $im, 0, 0, 0, 0, $icow, $icoh, $imw, $imh);
	imagedestroy($im);
}
unset($ico);

// create challenge image
$num_cols = CHALLENGE_COLUMNS;
$num_rows = ceil($challenge->size() / $num_cols);
$im = imagecreatetruecolor(($icon_width * 2.35) * $num_cols, ($icon_height * 1.9) * $num_rows);
$im_width = imagesx($im);
$im_height = imagesy($im);
imagefill($im, 0, 0, $bgcolor);

// draw icons
$num_items = $challenge->size();
$items = $challenge->icons();
$icon_margin = max($icon_width, $icon_height) * 0.9;

$num_repetitions = 3;
$tmp_icon = imagecreatetruecolor($icon_width, $icon_height);
for($y = 0; $y < $num_rows; $y++) {
	for($x = 0; $x < $num_cols; $x++) {
		$k = $y * $num_cols + $x;
		if($k >= $num_items)
			break;

		$icon_index = $items[$permutation[$k]];
		$icon = $icons[$icon_index];
		imagefilledrectangle($tmp_icon, 0, 0, $icon_width, $icon_height, $bgcolor);
		imagecopy($tmp_icon, $icon, 0, 0, 0, 0, $icon_width, $icon_height);

		$contrast = -mt_rand(1, 5) * 20;
		for($z = 0; $z < $num_repetitions; $z++) {
			list($dst_x, $dst_y, $mul) = dst_mul($x, $y);
			$dst_x += $icon_margin / 3;

			$resize_factor = mt_rand(900, 1100) * 0.001;
			$rotation_angle = mt_rand(0, 359);

			$rotated_icon = imagerotate($tmp_icon, $rotation_angle, $bgcolor);
			imagefilter($rotated_icon, IMG_FILTER_CONTRAST, $contrast);
			imagefilter($rotated_icon, IMG_FILTER_MEAN_REMOVAL);
			imagecopyresized($im, $rotated_icon, $dst_x, $dst_y, 0, 0, $icon_width * $resize_factor, $icon_height * $resize_factor, $icon_width, $icon_height);
			imagedestroy($rotated_icon);
		}
	}
}
imagedestroy($tmp_icon);

// draw lines
$num_lines_per_row = 3;
for($y = 0; $y < $num_rows; $y++) {
	for($x = 0; $x < $num_lines_per_row; $x++) {
		list($dst_x, $dst_y, $mul) = dst_mul($x, $y);

		$g = mt_rand(32, 224);
		$sign = -1 + 2 * mt_rand(0, 1);
		$mul2 = $sign * mt_rand(80, 100) * 0.01;

		imageline($im, 0, $dst_y + $icon_margin * $mul / 2, $im_width, $dst_y + $icon_margin * $mul2, ($g << 16) | ($g << 8) | $g);
	}
}

// write numbers
for($y = 0; $y < $num_rows; $y++) {
	for($x = 0; $x < $num_cols; $x++) {
		$k = $y * $num_cols + $x;
		if($k >= $num_items)
			break;

		list($dst_x, $dst_y, $mul) = dst_mul($x, $y);
		$dst_x -= $icon_margin * 0.35;
		$dst_y += $icon_margin * 0.35;

		$fnt = mt_rand(3, 5);
		$label = strval(1 + $permutation[$k]);
		imagestring($im, $fnt, $dst_x - 1, $dst_y - 1, $label, $bgcolor);
		imagestring($im, $fnt, $dst_x - 1, $dst_y + 1, $label, $bgcolor);
		imagestring($im, $fnt, $dst_x + 1, $dst_y - 1, $label, $bgcolor);
		imagestring($im, $fnt, $dst_x + 1, $dst_y + 1, $label, $bgcolor);
		imagestring($im, $fnt, $dst_x, $dst_y, $label, $fgcolor);
	}
}

// write message
if(isset($message))
    imagestring($im, 3, 4, 4, $message, $fgcolor);

// apply filters
imagefilter($im, IMG_FILTER_GRAYSCALE);
imagefilter($im, IMG_FILTER_MEAN_REMOVAL);
imagefilter($im, IMG_FILTER_CONTRAST, -mt_rand(20, 40));

// output
imagepng($im);

// release
imagedestroy($im);

}
catch(Exception $e) {

// Error message
//$im = imagecreatetruecolor(1, 1);
$im = imagecreatetruecolor(640, 16);
imagefill($im, 0, 0, $bgcolor);
imagestring($im, 3, 4, 1, $e, $fgcolor);
imagepng($im);
imagedestroy($im);

}

/**
 * Shuffle an array using a previously set PRNG seed
 * @param array $arr
 * @return array shuffled $arr
 */
function my_shuffle(&$arr)
{
	// Fisher-Yates
	for($i = count($arr) - 1; $i > 0; $i--) {
		$j = mt_rand(0, $i - 1);
		list($arr[$i], $arr[$j]) = array($arr[$j], $arr[$i]);
	}

	return $arr;
}

/**
 * Helper function
 * @param int $x
 * @param int $y
 * @return int[]
 */
function dst_mul($x, $y)
{
	global $icon_width, $icon_height, $icon_margin;

	$mul = mt_rand(80, 100) * 0.01;
	$dst_x = ($x + 0.5) * ($icon_width + $icon_margin * $mul * 1.4);
	$dst_y = ($y + 0.2) * ($icon_height + $icon_margin * $mul);

	return array($dst_x, $dst_y, $mul);
}
