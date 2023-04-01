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
 * Can SQLite do on duplicate key update?
 */

$bodyHashes = array();

// Connect to SQLite database
$pdo = new PDO('sqlite:crawler.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if they don't exist
$pdo->exec('CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY, url TEXT UNIQUE, title TEXT, body TEXT, words TEXT, hash TEXT UNIQUE, code INTEGER, retrieved_date INTEGER)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_retrieved_date ON pages (retrieved_date)');

    $stopwords = array(
        'a', 'about', 'actually', 'almost', 'also', 'although', 'always', 'am', 'an', 'and',
        'any', 'are', 'as', 'at', 'be', 'became', 'become', 'but', 'by', 'can', 'could', 'did',
        'do', 'does', 'each', 'either', 'else', 'for', 'from', 'had', 'has', 'have', 'hence',
        'how', 'i', 'if', 'in', 'is', 'it', 'its', 'just', 'may', 'maybe', 'me', 'might', 'mine',
        'must', 'my', 'mine', 'must', 'my', 'neither', 'nor', 'not', 'of', 'oh', 'ok', 'when',
        'where', 'whereas', 'wherever', 'whenever', 'whether', 'which', 'while', 'who', 'whom',
        'whoever', 'whose', 'why', 'will', 'with', 'within', 'without', 'would', 'yes', 'yet',
        'you', 'your'
    );

function first_page($pdo, $url) {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO pages (url) VALUES (?)');
    $stmt->execute([$url]);
}

// Function to insert a page into the database
function insert_page($pdo, $url, $title, $body, $hash, $error, $retrieved_date) {
    global $stopwords;
    $words = null;
    if ( is_string($body) && strlen($body) > 0 ) {
        $string = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $body));
        $words = array();
        $pieces = explode(' ', $string);
        foreach($pieces as $piece) {
            if ( strlen($piece) < 3 ) continue;
            if ( in_array($piece, $stopwords) ) continue;
            if ( in_array($piece, $words) ) continue;
            array_push($words, $piece);
        }
        sort($words);
        $words = implode(' ', $words);
        if ( strlen($body) > 200 ) $body = substr($body, 0, 200) . " ...";
    }

    $stmt = $pdo->prepare('INSERT OR REPLACE INTO pages (url, title, body, words, hash, code, retrieved_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$url, $title, $body, $words, $hash, $error, $retrieved_date]);
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
first_page($pdo, $start);

// Crawl the website
$crawled = array();

$maxpages = 2;
while ($maxpages-- > 0 ) {
    // Get an unretrieved page from database
    $stmt = $pdo->query('SELECT * FROM pages WHERE retrieved_date IS NULL ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $stmt = $pdo->query('SELECT * FROM pages ORDER BY retrieved_date ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            break;
        }
    }

    $retrieved_date = $row['retrieved_date'];
    if ( $retrieved_date == null || ! is_string($retrieved_date) ) {
        // Should retrieve
    } else {
        $diff = time() - $retrieved_date;
        if ( $diff < 60 ) {
            echo("Too soon\n");
            break;
        }
    }

    $url = $row['url'];
    echo("----- URL $url ------\n");
    $html = @file_get_contents($url);

    // Check HTTP response code
    $response_code = substr($http_response_header[0], 9, 3);
    if (strpos('23', $response_code[0]) === false) {
        // Handle error (e.g. non-2xx/3xx response code)
        $now = time();
        insert_page($pdo, $url, null, null, null, $response_code, $now);
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

    echo("--- Retrieved $url $body\n");

    // Insert or update page in database
    $now = time();
    insert_page($pdo, $url, $title, $body, $hash, null, $now);

    // Reload the document.
    @$doc->loadHTML($html);
    // Add links to queue
    $links = $doc->getElementsByTagName('a');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if(strpos($href, $start) === 0) {
           $abs_url = $href;
        } else if ( strpos($href, 'http://') === 0 ) {
            continue;
        } else if ( strpos($href, 'https://') === 0 ) {
            continue;
        } else {
            if(strpos($href, '/') === 0) {
                $abs_url = $start . $href;
            } else {
                $abs_url = $start . '/' . $href;
            }
        }

        if ( ! page_exists($pdo, $abs_url) ) {
            insert_page($pdo, $abs_url, null, null, null, null, null);
        }
    }
}

// Dump all pages in the table
echo("\n");
$stmt = $pdo->query('SELECT * FROM pages ORDER BY retrieved_date DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "URL: " . $row['url'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "Body: " . $row['body'] . "\n";
    echo "Words: " . $row['words'] . "\n";
    echo "Code: " . $row['code'] . "\n";
    echo "Hash: " . $row['hash'] . "\n";
    echo "Retrieved Date: " . date('Y-m-d H:i:s', $row['retrieved_date']) . "\n";
    echo "\n";
}

