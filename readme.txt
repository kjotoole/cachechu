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
    o   data/mute/urls.dat.sample      - default list of URLs for Mute (remove .sample to use)
    o   config/config.ini.sample       - default config file (remove .sample to use)
    o   readme.txt                     - this file

III. Requirements

    1.  The following paths require write (and read) access:

      data/<network>/bans.dat
      data/<network>/hosts.dat
      data/<network>/urls.dat
      data/<network>/stats.dat (if stat logging enabled)
      data/<network>/start.dat (if stat logging enabled)

        Replace <network> by the networks you support.

    2.  The following paths MAY require write (and read) access,
        depending on configuration:

      data/bans.dat
      data/hosts.dat
      data/urls.dat
      data/stats.dat (if stat logging enabled)
      data/start.dat (if stat logging enabled)

    3.  The following file should be set as the directory index:

      index.php

    4.  To disable stat logging, the following configuration should be present:

      [Stats]
      Enable = 0;

    5.  If your host cannot test clients on a port other than 80,
        set [Host] Testing in config.ini to 0.

IV. Web interface

    1.  To disable the Web interface, the following configuration should be present:

      [Interface]
      Show = 0

    2.  To disable info pages on the Web interface, the following configuration should be present:

      [Interface]
      Info = 0

    3.  Stats shows the statistics for each supported network, if enabled.

    4.  Hosts shows the IP, port, client, timestamp, and age of hosts for each network.

    5.  Services shows the country (if geoip present), URL, IP, client, timestamp of
        caches for each network.

V. Compatibility

    Cachechu has been tested under PHP 5.2.9.