<?php
	require_once('../vendor/autoload.php');
	require_once('../globals.php');
	require_once('../SlimAPI.php');
	require('routes.php');
	
	$api = new SlimAPI([
		'debug' => true,
		'name' => 'Contacts API'
	]);
	//$api->addReadme('/','./README.md');
	$pdo = new \PDO( 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';dbpassword='.DB_PASS,DB_USERNAME,'' );
	
	$db = new \LessQL\Database( $pdo );
	$db->setPrimary( 'contact', 'id' );
	$api->addDB('mysql', $db);
	
	$api = addAPIRoutes($api);
	$api->run(); 