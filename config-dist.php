<?php


// This default might work for online servers, but not for test.php
// So the smart thing is to set spider_start to the right url below
// after copying config-dist.php to config.php
//
$our_server_port = ($_SERVER['SERVER_PORT'] ?? 443 );
$spider_start = ( $_SERVER['REQUEST_SCHEME'] ?? 'https' ) . "://" .
	($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP:HOST'] ?? 'localhost') .
	( ($our_server_port == 80 || $our_server_port == 443) ? '' : ':'.$our_server_port );

// If you know your server start, enter it here
// $spider_start = "https://online.dr-chuck.com/";
// $spider_start = "http://localhost/localsearchphp";
// $spider_start = "http://localhost:8888/localsearchphp";

$spider_crawl_max_pages = 5;

