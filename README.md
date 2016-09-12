# Cachechu

## Description
Cachechu is a GWebCache written in PHP.

## Requirements

- PHP 5.3.0+ or newer
- HTTP server with PHP support (eg: Apache, Nginx, Caddy)
- [Composer](https://getcomposer.org)

## Setup
*  Run Composer
```
composer install --no-dev
```
*  First time users should remove .sample from config.ini.sample. Remove .sample from urls.dat.sample files, if you want to start off with default caches.

*  The following paths require write (and read) access:
```
data/<network>/bans.dat
data/<network>/hosts.dat
data/<network>/urls.dat
data/update.dat (if update notification enabled)
```
    Replace <network> by the networks you support.

*  The following paths MAY require write (and read) access, depending on configuration:
```
data/bans.dat
data/hosts.dat
data/urls.dat
```
*  The following file should be set as the directory index:
index.php

*  If your host cannot test clients on a port other than 80, the following configuration should be present:
```
[Host]
Testing = 0
```
### Web interface

* To disable the Web interface, the following configuration should be present:
```
[Interface]
Show = 0
```
* To disable info pages on the Web interface, the following configuration should be present:
```
[Interface]
Info = 0
```
* Hosts shows the country (if GeoIP present), IP, port, client, timestamp,
        and age of hosts for each network.

* Services shows the country (if GeoIP present), URL, IP, client, timestamp of
        caches for each network.

## Compatibility

Cachechu has been tested under PHP 5.3.0 & 7.0.0.

## Notes

Stats support was removed from Cachechu 1.2.
