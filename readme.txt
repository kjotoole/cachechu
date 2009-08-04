****************************************************************************
Cachechu 1.2
kevogod
http://code.google.com/p/cachechu/
****************************************************************************

I. Description

    Cachechu is a GWebCache written in PHP.

II. File List

    o   index.php                      - Cachechu PHP code
    o   main.php                       - Web interface
    o   main.css                       - main stylesheet
    o   data/gnutella2/urls.dat.sample - default list of URLs for Gnutella2 (remove .sample to use)
    o   data/gnutella/urls.dat.sample  - default list of URLs for Gnutella (remove .sample to use)
    o   data/foxy/urls.dat.sample      - default list of URLs for Foxy (remove .sample to use)
    o   config/config.ini.sample       - default config file (remove .sample to use)
    o   readme.txt                     - this file

III. Requirements

    1.  First time users should remove .sample from config.ini.sample.
        Remove .sample from urls.dat.sample files, if you want to start off with default caches.

    2.  The following paths require write (and read) access:

      data/<network>/bans.dat
      data/<network>/hosts.dat
      data/<network>/urls.dat
      data/update.dat (if update notification enabled)

        Replace <network> by the networks you support.

    3.  The following paths MAY require write (and read) access,
        depending on configuration:

      data/bans.dat
      data/hosts.dat
      data/urls.dat

    4.  The following file should be set as the directory index:

      index.php

    5.  If your host cannot test clients on a port other than 80,
        the following configuration should be present:

      [Host]
      Testing = 0

IV. Web interface

    1.  To disable the Web interface, the following configuration should be present:

      [Interface]
      Show = 0

    2.  To disable info pages on the Web interface, the following configuration should be present:

      [Interface]
      Info = 0

    3.  Hosts shows the country (if GeoIP present), IP, port, client, timestamp,
        and age of hosts for each network.

    4.  Services shows the country (if GeoIP present), URL, IP, client, timestamp of
        caches for each network.

V. Compatibility

    Cachechu has been tested under PHP 5.3.0.

VI. Notes

    Stats support was removed from Cachechu 1.2.
