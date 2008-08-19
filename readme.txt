****************************************************************************
Cachechu
kevogod
http://code.google.com/p/cachechu/
****************************************************************************

I. Description

    Cachechu is a simple G2 GWebCache written in PHP.

II. File List

    o   index.php         - Cachechu PHP code
    o   readme.txt        - this file
    o   main.html         - Web interface
    o   data/urls.dat     - default list of URLs
    o   config/config.ini - default config file

III. Requirements

    1.  The following paths require write access:
    
      data/bans.dat
      data/hosts.dat
      data/urls.dat

    2.  The following file should be set as the directory index:

      index.php

IV. Web interface

    1.  'Ping and Get' outputs a 'get' and 'ping' GWebCache request.

    2.  'Update' performs a URL 'update' GWebCache request with the URL entered.

V. Compatibility

    Cachechu has been tested under PHP 5.2.6.