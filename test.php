<?php

require_once "MySpider.php";

$maxpages = 2;

$spider = new MySpider();

// Seed the queue with the starting URL
$spider->start = "https://online.dr-chuck.com/";
$spider->first_page($spider->start);
$results = $spider->crawl($maxpages);
echo(json_encode($results, JSON_PRETTY_PRINT));
echo("\n");
$results = $spider->search('coursera', 0, 10);
echo(json_encode($results, JSON_PRETTY_PRINT));
echo("\n");
// $spider->dump(); echo("\n");



