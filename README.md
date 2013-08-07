wikipedia.org-xmldump-mongodb
=============================

Overview
--------

Wikipedia.org XML Dump Importer is a script to import the standard Wikipedia XML dump into a simple MongoDB data structure, useful as a local cache for searching and manipulating Wikipedia articles. The datastore structure is designed for ease of use, and is not mediawiki-compatible.

Dataset Source
--------------

URL: http://dumps.wikimedia.org/

Updates: monthly

Environment
-----------

GNU/Linux
PHP 5.4 + (with mbstring, simplexml, mongodb extensions)
MongoDB 2.2 +

Notes
-----

* This script is designed to run on the command line - not a web browser
* enwiki download is approximately 9.5GB compressed and will require another (approx.) 45GB of storage for the database - a total of approximately 55GB
* Import process required approximately 4 hours on a well configured quad core with 4GB of memory. 

Howto
-----

* Download the proper pages-articles XML file - for example, enwiki-20130708-pages-articles.xml.bz2.
* Download the script.
* Edit the three configuration variables at the beginning of the script:

        $dsname = 'mongodb://localhost/wp20130708';
        $file = 'enwiki-20130708-pages-articles.xml.bz2';
        $log = './';

* Run the script -- this may take several hours.

License
-------

This project is BSD (2 clause) licensed.
