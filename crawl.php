<?php

/**
 * I used ChatGPT to give me a basic outline of this file - here are my questions
 *
 * I want to write a simple PHP search engine that crawls a site it is embedded in and supports simple searches of the site.
 * Could I use DOMDocument and loadHTML instead?
 * How would I remove multiple spaces and blank lines from the body and title text
 * How do I exclude the contents of the nav tag from the body content
 * How do I made sure not to add the same body content twice?
 * How about if I just store the hash of the body content in the body array?
 * What are good places to add error checking
 * How do I get the error code like 404 from file_get_contents
 * How to check if $http_response_header is a 2xx or 3xx
 * (Switched to crawl2 to use the database)
 * Can I store the pages in an SQLITE database so this crawler is restartable?
 *  ...
 */

$bodyHashes = array();

// Function to extract the contents of a webpage using DOMDocument
function extractContent($contents) {
    global $bodyHashes;
    $doc = new DOMDocument();
    @$doc->loadHTML($contents);
    $title = $doc->getElementsByTagName('title')->item(0)->textContent;

    // Remove the nav tag and its contents from the document
    $nav = $doc->getElementsByTagName('nav')->item(0);
    if($nav) {
        $nav->parentNode->removeChild($nav);
    }

    $body = $doc->getElementsByTagName('body')->item(0)->textContent;

    // Remove multiple spaces and blank lines from the title and body
    $title = preg_replace('/\s+/', ' ', $title);
    $body = preg_replace('/\s+/', ' ', $body);
    $body = preg_replace('/\n(\s*\n)+/', "\n", $body);

    // Generate a hash of the body content and check if it already exists
    $bodyHash = md5($body);
    if(!in_array($bodyHash, $bodyHashes)) {
        $bodyHashes[] = $bodyHash;
        return array('title' => $title, 'body' => $body);
    } else {
        return false;
    }
}

// Array to store all pages and their contents
$pages = array();

// Function to crawl the website and store the contents of each page
function crawl($url) {
    global $pages, $start;
    echo("---------url $url ------------\n");
    $contents = @file_get_contents($url);

    // Check HTTP response code
    $response_code = substr($http_response_header[0], 9, 3);
    if (strpos('23', $response_code[0]) === false) {
        echo("---------error $url $http_response_header[0] ------------\n");
        echo($contents);
        return;
    }
    $page = extractContent($contents);
    if ( is_array($page) ) $pages[$url] = $page;
    $doc = new DOMDocument();
    @$doc->loadHTML($contents);
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
