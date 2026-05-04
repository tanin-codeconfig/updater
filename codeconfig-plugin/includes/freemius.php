<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function codeconfig_is_pro() {
	return defined( 'CODECONFIG_PRO_ACTIVE' ) && CODECONFIG_PRO_ACTIVE;
}

if ( file_exists( CODECONFIG_PATH . 'includes/freemius.php' ) ) {
	require_once CODECONFIG_PATH . 'includes/freemius.php';
}
