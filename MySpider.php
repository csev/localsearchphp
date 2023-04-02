<?php
class MySpider {

    public ?object $pdo = null;

    public string $start = "http://localhost:8888/localsearchphp/test";

    // public string $alternate = "https://www.localsearchphp.com/";
    public ?string $alternate = null;

    // Tags where we don't want text
    public array $stoptags = array('nav', 'footer');

    public array $stopwords = array(
        'a', 'about', 'actually', 'almost', 'also', 'although', 'always', 'am', 'an', 'and',
        'any', 'are', 'as', 'at', 'be', 'became', 'become', 'but', 'by', 'can', 'could', 'did',
        'do', 'does', 'each', 'either', 'else', 'for', 'from', 'had', 'has', 'have', 'hence',
        'how', 'i', 'if', 'in', 'is', 'it', 'its', 'just', 'may', 'maybe', 'me', 'might', 'mine',
        'must', 'my', 'mine', 'must', 'my', 'neither', 'nor', 'not', 'of', 'oh', 'ok', 'when',
        'where', 'whereas', 'wherever', 'whenever', 'whether', 'which', 'while', 'who', 'whom',
        'whoever', 'whose', 'why', 'will', 'with', 'within', 'without', 'would', 'yes', 'yet',
        'you', 'your'
    );

    function __construct() {
        // Connect to SQLite database
        $this->pdo = new PDO('sqlite:crawler.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables if they don't exist
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY, url TEXT UNIQUE, title TEXT, body TEXT, words TEXT, hash TEXT UNIQUE, code INTEGER, retrieved_date INTEGER)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_retrieved_date ON pages (retrieved_date)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_url ON pages (url)');
    }

    public function first_page($url) {
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO pages (url) VALUES (?)');
        $stmt->execute([$url]);
    }

    // Function to insert a page into the database
    public function insert_page($url, $title, $body, $hash, $error, $retrieved_date) {
        $words = null;
        if ( is_string($body) && strlen($body) > 0 ) {
            $string = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $body));
            $words = array();
            $pieces = explode(' ', $string);
            foreach($pieces as $piece) {
                if ( strlen($piece) < 3 ) continue;
                if ( in_array($piece, $this->stopwords) ) continue;
                if ( in_array($piece, $words) ) continue;
                array_push($words, $piece);
            }
            if ( count($words) > 1 ) {
                sort($words);
                $words = ' ' . implode(' ', $words) . ' ';
            } else {
                $words = null;
            }
            if ( strlen($body) > 200 ) $body = substr($body, 0, 200) . " ...";
        }

        $sql = 'INSERT OR REPLACE INTO pages (url, title, body, words, hash, code, retrieved_date) 
                         VALUES (:url,  :title, :body, :words, :hash, :code, :date)
                ON CONFLICT (url) DO UPDATE SET 
                    title=excluded.title, body=excluded.body, words=excluded.words,
                    hash=excluded.hash, code=excluded.code, retrieved_date=excluded.retrieved_date';

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([':url' => $url, ':title' => $title, ':body' => $body, 
                ':words' => $words, ':hash' => $hash, ':code' => $error, ':date' => $retrieved_date]);
        // If we have a hash conflict, we have duplicat content at multiple URLs
        } catch(Exception $e) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':url' => $url, ':title' => $title, ':body' => $body, 
                ':words' => $words, ':hash' => null, ':code' => $error, ':date' => $retrieved_date]);
        }

    }

    // Function to check whether a page already exists in the database
    public function page_exists($url) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pages WHERE url = ?');
        $stmt->execute([$url]);
        return $stmt->fetchColumn() > 0;
    }

    public function crawl($maxpages) {
        while ($maxpages-- > 0 ) {
            // Get an unretrieved page from database
            $stmt = $this->pdo->query('SELECT * FROM pages WHERE retrieved_date IS NULL ORDER BY id ASC LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $this->pdo->query('SELECT * FROM pages ORDER BY retrieved_date ASC LIMIT 1');
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
                if ( $diff < 30 ) {
                    echo("Too soon\n");
                    break;
                }
            }

            $url = $row['url'];
            // echo("----- URL $url ------\n");
            $html = @file_get_contents($url);

            // Check HTTP response code
            $response_code = substr($http_response_header[0], 9, 3);
            if (strpos('23', $response_code[0]) === false) {
                // Handle error (e.g. non-2xx/3xx response code)
                $now = time();
                $this->insert_page($url, null, null, null, $response_code, $now);
                continue;
            }

            // Parse HTML
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $title = $doc->getElementsByTagName('title')->item(0)->textContent;

            // Remove the nav and footer tags from the document
            foreach ($this->stoptags as $stoptag ) {
                $stopelem = $doc->getElementsByTagName($stoptag)->item(0);
                if($stopelem) {
                    $stopelem->parentNode->removeChild($stopelem);
                }
            }

            $body = $doc->getElementsByTagName('body')->item(0)->textContent;

            // Remove multiple spaces and blank lines from the title and body
            $title = preg_replace('/\s+/', ' ', $title);
            $body = preg_replace('/\s+/', ' ', $body);
            $body = preg_replace('/\n(\s*\n)+/', "\n", $body);
            $hash = md5($body);

            // echo("--- Retrieved $url $body\n");

            // Insert or update page in database
            $now = time();
            $this->insert_page($url, $title, $body, $hash, null, $now);

            // Reload the document.
            @$doc->loadHTML($html);
            // Add links to queue
            $links = $doc->getElementsByTagName('a');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if(strpos($href, $this->start) === 0) {
                    $abs_url = $href;
                } else if(is_string($this->alternate) && strpos($href, $this->alternate) === 0) {
                    $abs_url = str_replace($this->alternate, $this->start, $href);
                } else if ( strpos($href, 'http://') === 0 ) {
                    continue;
                } else if ( strpos($href, 'https://') === 0 ) {
                    continue;
                } else {
                    if(strpos($href, '/') === 0) {
                        $abs_url = $this->start . $href;
                    } else {
                        $abs_url = $this->start . '/' . $href;
                    }
                }

                if ( ! $this->page_exists($abs_url) ) {
                    $this->insert_page($abs_url, null, null, null, null, null);
                }
            }
        }
    }

    public function search($search, $start, $count) {
        $search = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $search));
        $words = explode(' ',$search);
        $where = null;
        if ( count($words) > 0 ) {
            $where = '';
            foreach($words as $word) {
                if ( strlen($where) > 0 ) $where .= ' OR ';
                $where .= "words LIKE '% " . $word . " %'";
            }
            $where = ' AND  (' . $where . ') ';
        }
        $sql = "SELECT * FROM pages WHERE code IS NULL AND hash IS NOT NULL AND 
            retrieved_date IS NOT NULL ".$where." ORDER BY id LIMIT $count OFFSET $start";

        echo("--- sql $sql\n");
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Dump all pages in the table
    public function dump() {
        echo("\n");
        $stmt = $this->pdo->query('SELECT * FROM pages ORDER BY retrieved_date DESC');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: " . $row['id'] . "\n";
            echo "URL: " . $row['url'] . "\n";
            echo "Title: " . $row['title'] . "\n";
            echo "Body: " . $row['body'] . "\n";
            echo "Words: " . $row['words'] . "\n";
            echo "Code: " . $row['code'] . "\n";
            echo "Hash: " . $row['hash'] . "\n";
            echo "Retrieved Date: " . date('Y-m-d H:i:s', $row['retrieved_date']) . "\n";
            echo "\n";
        }
    }

};
