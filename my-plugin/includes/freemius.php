<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function my_plugin_is_pro() {
	return defined( 'MY_PLUGIN_PRO_ACTIVE' ) && MY_PLUGIN_PRO_ACTIVE;
}

if ( file_exists( MY_PLUGIN_PATH . 'freemius.php' ) ) {
	require_once MY_PLUGIN_PATH . 'freemius.php';
}
