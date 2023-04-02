Local Search PHP
----------------

A simple PHP search engine that crawls a site it is embedded in and supports simple searches of the site.

This only looks at the web pages on your site through a crawl process like any other web search engine.
The crawl process is embedded into the pages of the site with AJAX.   The crawl process and the search
process return JSON so you can use it any way you like and format it in a way that fits with the design
of your site.

Quick test
----------

First check this repo into your hosting environment in a top level folder.  Then go into the folder and:

    cp config-dist.php config.php

Then edit the `config.php` file to point to the URL where your site can be crawled and then run the test.
Instructions are contained in the `config-dist.php` file.

    php test.php

You can run the `test.php` over and over as it crawls your web site.  You can even set
`$spider_crawl_max_pages` to a larger number like 100 to fill up your database
more quickly once things seem to be working.

Restarting the Crawl
--------------------

To restart the crawl, go into the `localsearchphp` folder and rewmove the `crawler.db`
file and start things back up using `test.php`. While testing, you might have to restart
a few times to make suer your configuration is working.

Test the REST Endpoints
-----------------------

Once `test.php` seems to work, OK you yould be able to hit the REST endpoints as follows:

https://online.dr-chuck.com/localsearchphp/crawl.php

https://online.dr-chuck.com/localsearchphp/search.php?query=tsugi+hopes

Integrating Search into Your Web Site
-------------------------------------

To cause the crawl to happen, usually you put some JavaScript on the main page that waits a few seconds
and then makes an AJAX request to the crawler endpoint.  Something like this in your top page should
do the trick.

    <script>
    function doLocalSearchPHPCrawl() {
        fetch('localsearchphp/crawl.php')
            .then(response => response.json())
            .then(result => {
            setTimeout(() => { doCrawl(); }, 20000);
            })
        .catch(err => console.log(err))
    }

    setTimeout(() => { doLocalSearchPHPCrawl(); }, 5000);
    </script>

This waits five seconds after page load and does a background crawl.  After that every 20
seconds it does another crawl.

You could also make a shell script that you scheduled in `cron` to `curl` or `wget` the
`crawl.php` URL from time to time.   Make suer that script runs as the correct user so the
permissions on the `crawler.db` file is correct to the web server process can write to the
file.

You can build a UI that takes search terms, calls one of your pages that
calls the search endpoint and formats the results you get from JSON.

https://online.dr-chuck.com/localsearchphp/search.php?query=tsugi+hopes

Performance
-----------

This uses an SQLite database stored on the local drive of your web server.  As long as the
dis is not a networked drive (i.e. like an NFS mount) this should be pretty quick as long as
the size of your sites is in the few thousands of pages. Larger sites might need to use
MySQL - which is an easily added feature.

In general - the database does not store all the content.  It stores a snippet
of the page and the unique words in each page after running through a stopword
list.  It scans the words column with a LIKE clause.

With the speed of disk drives and the disk caching in most Linux servers, both
the crawling and searching should be a pretty light load on your server.

Future enhancements can include using MySQL and putting something in `crawl.php`
to limit the nuber of crawls per minute overall using APC cache, to handle
cases like hundreds of folks are sitting on your main page for hours and hours.

Both the `crawl.php` and `search.php` report an ellapsed time in seconds
that allows you to see if your search activities are taking longer than expected.

Using ChatGPT
-------------

The initial version of this code was built with ChatGPT.  I asked the following questions:

* I want to write a simple PHP search engine that crawls a site it is embedded in and supports simple searches of the site.
* Chuck: Started a file - crawl.php
* Could I use DOMDocument and loadHTML instead?
* How would I remove multiple spaces and blank lines from the body and title text
* How do I exclude the contents of the nav tag from the body content
* How do I made sure not to add the same body content twice?
* How about if I just store the hash of the body content in the body array?
* What are good places to add error checking
* How do I get the error code like 404 from `file_get_contents`
* How to check if `$http_response_header` is a 2xx or 3xx
* Can I store the pages in an SQLITE database so this crawler is restartable?
* Chuck: Started a second file - crawl2.php
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
