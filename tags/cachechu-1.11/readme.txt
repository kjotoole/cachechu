****************************************************************************
Cachechu 1.11
kevogod
http://code.google.com/p/cachechu/
****************************************************************************

I. Description

    Cachechu is a simple G2 GWebCache written in PHP.

II. File List

    o   index.php                - Cachechu PHP code
    o   main.php                 - Web interface
    o   main.css                 - main stylesheet
    o   robots.txt               - blocks CoralWebPrx
    o   data/urls.dat.sample     - default list of URLs (remove .sample to use)
    o   data/stats.dat.sample    - empty stats file (remove .sample to use)
    o   data/start.dat.sample    - empty start file (remove .sample to use)
    o   config/config.ini.sample - default config file (remove .sample to use)
    o   readme.txt               - this file

III. Requirements

    1.  The following paths require write (and read) access:
    
      data/bans.dat
      data/hosts.dat
      data/urls.dat
      data/stats.dat
      data/start.dat

    2.  The following file should be set as the directory index:

      index.php

    3.  To enable stat logging, the following files should exist:

      data/stats.dat
      data/start.dat

    4.  If your host cannot test clients on a port other than 80,
        set [Host] Testing in config.ini to 0.

IV. Web interface

    1.  'Test Cache' outputs a 'get' and 'ping' GWebCache request.

    2.  'Update' performs a URL 'update' GWebCache request with the URL entered.

V. Compatibility

    Cachechu has been tested under PHP 5.2.9.