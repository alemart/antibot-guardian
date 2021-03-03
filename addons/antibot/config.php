<?php

//
// Please do change the fields below:
//

// Challenges are generated based on this secret key, so make sure it is unique!
define("CHALLENGE_SECRET_KEY", "Type a secret key here!");

// Name of the folder that stores the icons - it should be kept secret!
define("CHALLENGE_ICON_FOLDER", "rename_this_folder");




//
// Do not change the fields below, unless you understand what you are doing!
//

// The time it takes for a challenge to expire, in minutes
define("CHALLENGE_EXPIRE_TIME", 5);

// Number of columns displayed in a challenge
define("CHALLENGE_COLUMNS", 5);

// Minimum number of rows displayed in a challenge
define("CHALLENGE_MIN_ROWS", 5);

// Icon width, in pixels
define("CHALLENGE_ICON_WIDTH", 64);

// Icon height, in pixels
define("CHALLENGE_ICON_HEIGHT", 64);

// How many reloads we'll allow (keep it a small number)
define("CHALLENGE_RELOAD_CYCLE_LENGTH", 2);

// Where are the files of the challenge? (relative to PUN_ROOT)
define("CHALLENGE_BASEDIR", "addons/antibot");
