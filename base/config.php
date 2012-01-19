<?php

# Set this to true for debugging info on screen.
if (!defined('DEBUG'))
	define('DEBUG', FALSE);

# This file is ALWAYS loaded.
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

# Main config object
# Available through $GLOBALS['config']

$GLOBALS['config'] = array(
# ----------- Main config ----------
	#Automatically gotten. -> Needs to have trailing slash!
	'base_url' => '',
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
