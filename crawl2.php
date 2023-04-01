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
 * (Switched to crawl2.php)
 * Can I store the pages in an SQLITE database so this crawler is restartable?
 * Can you also store the queue of unretrieved urls in the database
 * Can you store the queue in the pages table and add a retrieved date so we can do the crawl over and over and re-crawl older pages?
 * Add some code to the end to read and dump all the pages in the table
 * How do I insert the initial url with on on duplicate key ignore
 * Is "INSERT IGNORE" valid SQLITE syntax?
 * If I insert the initial page with $now it never is retrieved - how would you make it so the first url is retrieved and the actual loop is properly primed?
 * Your answer was wrong.
 * ChatGPT:  Could you please clarify which part of my answer was wrong so I can provide a more accurate response?
 * You still are selecting retrieved as null in the first select
 * Your answer is still wrong.
 * Chuck: I decided to work on the code and ask smaller questions
 * Can SQLite do on dpulicate key update?
 */

$bodyHashes = array();

// Connect to SQLite database
$pdo = new PDO('sqlite:crawler.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if they don't exist
$pdo->exec('CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY, url TEXT UNIQUE, title TEXT, body TEXT, hash TEXT, retrieved_date INTEGER)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_retrieved_date ON pages (retrieved_date)');

// Function to insert a page into the database
function insert_page($pdo, $url, $title, $body, $hash, $retrieved_date) {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO pages (url, title, body, hash, retrieved_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$url, $title, $body, $hash, $retrieved_date]);
}

// Function to check whether a page already exists in the database
function page_exists($pdo, $url) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pages WHERE url = ?');
    $stmt->execute([$url]);
    return $stmt->fetchColumn() > 0;
}

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

$start = "http://localhost:8888/localsearchphp/test";

// Seed the queue with the starting URL
$now = time();
// insert_page($pdo, $start, null, null, null, $now);
insert_page($pdo, $start, null, null, null, null);

// Crawl the website
$crawled = array();

while (true) {
    // Get oldest unretrieved page from database
    $stmt = $pdo->query('SELECT * FROM pages WHERE retrieved_date IS NULL ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo("----- no unretrieved pages...\n");
        // No more unretrieved pages
        break;
    }

    $url = $row['url'];
    echo("----- URL $url ------\n");
    $html = file_get_contents($url);

    // Check HTTP response code
    $response_code = substr($http_response_header[0], 9, 3);
    if (strpos('23', $response_code[0]) === false) {
        // Handle error (e.g. non-2xx/3xx response code)
        continue;
    }

    // Parse HTML
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
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
    $hash = md5($body);

    // Check whether page already exists
    if (page_exists($pdo, $url)) {
        continue;
    }

    // Insert page into database
    insert_page($pdo, $url, $title, $body, $hash, $now);

    // Add links to queue
    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
       if(strpos($href, $start) === 0) {
           $abs_url = $href;
        } else {
            if(strpos($href, '/') === 0) {
                $abs_url = $start . $href;
            } else {
                $abs_url = $start . '/' . $href;
            }
        }

        $abs_url = get_absolute_url($url, $href);
        if (filter_link($abs_url) && !in_array($abs_url, $crawled)) {
            insert_page($pdo, $abs_url, null, null, null, null);
            $crawled[] = $abs_url;
        }
    }
}


// Dump all pages in the table
$stmt = $pdo->query('SELECT * FROM pages');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "URL: " . $row['url'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "Body: " . $row['body'] . "\n";
    echo "Retrieved Date: " . date('Y-m-d H:i:s', $row['retrieved_date']) . "\n";
    echo "\n";
}

