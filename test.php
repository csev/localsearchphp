<?php

// Change the start based on where this is checked out locally
$spider_start = "http://localhost:8888/localsearchphp/test";
$spider_crawl_max_pages = 2;

if( ! defined('STDIN') ) die('Command line only');

require_once "MySpider.php";

$spider = new MySpider($spider_start);

$results = $spider->crawl($spider_crawl_max_pages);
echo(json_encode($results, JSON_PRETTY_PRINT));
echo("\nContinue with search?\n");
fgets(STDIN);

$results = $spider->search('first second', 0, 10);
echo(json_encode($results, JSON_PRETTY_PRINT));
echo("\nContinue with dump?\n");
fgets(STDIN);

$spider->dump(); echo("\n");



