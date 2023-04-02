<?php

require_once "MySpider.php";

$maxpages = 2;

$spider = new MySpider();

// Seed the queue with the starting URL
$spider->start = "http://localhost:8888/localsearchphp/test";
$spider->first_page($spider->start);
$spider->crawl($maxpages);
$spider->dump();
$results = $spider->search('first second', 0, 10);
echo(json_encode($results, JSON_PRETTY_PRINT));
echo("\n");



