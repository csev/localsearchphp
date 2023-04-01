<?php

/**
 * I used ChahatGPT to give me a basic outline of this file - here are my questions
 *
 * I want to write a simple PHP search engine that crawls a site it is embedded in and supports simple searches of the site.
 * could I use DOMDocument and loadHTML instead?
 */

// Function to extract the contents of a webpage using DOMDocument
function extractContent($url) {
    $doc = new DOMDocument();
    $contents = file_get_contents($url);
    @$doc->loadHTML($contents);
    $title = $doc->getElementsByTagName('title')->item(0)->textContent;
    $body = $doc->getElementsByTagName('body')->item(0)->textContent;
    return array('title' => $title, 'body' => $body);
}

// Array to store all pages and their contents
$pages = array();

// Function to crawl the website and store the contents of each page
function crawl($url) {
    global $pages, $start;
    echo("---------url $url ------------\n");
    $content = extractContent($url);
    $pages[$url] = $content;
    $doc = new DOMDocument();
    @$doc->loadHTML(file_get_contents($url));
    foreach($doc->getElementsByTagName('a') as $link) {
        $href = $link->getAttribute('href');
        if(strpos($href, $start) === 0) {
            if ( ! array_key_exists($href, $pages) ) crawl($href);
        } else {
            if(strpos($href, '/') === 0) {
                $fullUrl = $start . $href;
            } else {
                $fullUrl = $start . '/' . $href;
            }
            if ( ! array_key_exists($fullUrl, $pages) ) crawl($fullUrl);
        }
    }
}

$start = "http://localhost:8888/localsearchphp/test";
crawl($start);

print_r($pages);
