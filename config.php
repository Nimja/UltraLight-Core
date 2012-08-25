<?php

/**
 * Enable DEBUG for error showing.
 */
if (!defined('DEBUG')) {
	define('DEBUG', FALSE);
}

/**
 * The HASH Type for making passwords. (SHA256 is fairly secure.)
 */
if (!defined('HASH_TYPE')) {
	define('HASH_TYPE', 'sha256');
}
/**
 * The HASH Key for making password. CHANGE THIS! 
 */
if (!defined('HASH_KEY')) {
	define('HASH_KEY', 'UltraLight');
}

/**
 * Basic PHP settings.
 */
session_start();
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

/**
 * Main config object
 * Available through $GLOBALS['config']
 */
$GLOBALS['config'] = array(
# ----------- Main config ----------
	#Automatically gotten. -> Needs to have trailing slash!
	'base_url' => '',
	'contact_email' => 'mail@server.ext',
# ----------- Database config ----------
	#Table prefix for model tables.
	'table_prefix' => 'ul_',
	#Default database
	'database' => 'dev',
	#Selection of databases
	'databases' => array(
		'dev' => array(
			'server' => 'localhost',
			'database' => 'database',
			'username' => 'username',
			'password' => 'password',
		),
	),
# ----------- Route config ----------
	'routes' => array(
		#Catch-all route, for pages that cannot be found.
		'*' => 'index',
	),
);
