<?php
class MySpider {

    public string $start_url = "http://localhost:8888/py4e";

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

    public array $stoptags = array('nav');

    function walkDocument(&$words, &$snippet, &$links, $doc) {
        $this->walkDocumentInternal($words, $snippet, $links, $doc, 0);
        $snippet = trim(preg_replace('/\s+/', ' ',$snippet));
        $snippet = str_replace(array(' .', ' ,'), array('.', ',') ,$snippet);
        $snippet = str_replace(' ,', '.',$snippet);
        sort($words);
        sort($links);
    }

    function walkDocumentInternal(&$words, &$snippet, &$links, $doc, $depth) {
        foreach ($doc->childNodes as $item) {
            if ( in_array($item->nodeName, $this->stoptags) ) {
                continue;
            }
            if ( $item->nodeName == "a" ) {
                $link = $item->getAttribute('href');
                if ( ! in_array($link, $links) ) array_push($links, $link);
            }
            if ( $item->nodeName == "#text" ) {
                $snippet = $snippet . " " . trim($item->textContent);
                $string = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $item->textContent));
                $pieces = explode(' ', $string);
                foreach($pieces as $piece) {
                    if ( strlen($piece) < 3 ) continue;
                    if ( in_array($piece, $this->stopwords) ) continue;
                    if ( in_array($piece, $words) ) continue;
                    array_push($words, $piece);
                }
            }
            $this->walkDocumentInternal($words, $snippet, $links, $item, $depth+1);
        }

        $snippet = trim(preg_replace('/\s+/', ' ',$snippet));
        $snippet = str_replace(array(' .', ' ,'), array('.', ',') ,$snippet);
        $snippet = str_replace(' ,', '.',$snippet);
        sort($words);
        sort($links);
    }
};

$spider = new MySpider();

$db = new PDO("sqlite:".__DIR__."/database.db");

$start = "http://localhost:8888/py4e";
$contents = file_get_contents($start);
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($contents);
// var_dump($doc);

$words = array();
$links = array();
$snippet = "";
$spider->walkDocument($words, $snippet, $links, $doc);
    
echo($snippet."\n");
print_r($words);

print_r($links);

$db->exec("CREATE TABLE IF NOT EXISTS Pages
    (id INTEGER PRIMARY KEY, url TEXT UNIQUE, words TEXT, snippet TEXT,
     error INTEGER, retrieved_at DATE)");

$stmt = $db->prepare("INSERT INTO PAGES (url) VALUES (?)");
$stmt->execute([$start]);

$stmt = $db->prepare('SELECT id,url FROM Pages WHERE snippet is NULL and error is NULL ORDER BY RANDOM() LIMIT 1');
var_dump($stmt);
$rows = $stmt->execute();

var_dump($rows);




