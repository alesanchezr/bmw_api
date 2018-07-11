<?php
	require_once('../vendor/autoload.php');
	require_once('../globals.php');
	require_once('../JsonPDO.php');
	require_once('../SlimAPI.php');
	require('routes.php');
	
	$api = new SlimAPI([
		'debug' => true,
		'name' => 'Contacts API'
	]);
	//$api->addReadme('/','./README.md');
	$pdo = new \PDO( 'mysql:host='.DB_HOST.';dbname='.DB_NAME,DB_USERNAME,DB_PASS );
	
	$db = new \LessQL\Database( $pdo );
	$db->setPrimary( 'contact', 'id' );
	$api->addDB('mysql', $db);
	$api->addDB('failed', new JsonPDO('failed/','[]',false));
	
	$api = addAPIRoutes($api);
	$api->run(); 