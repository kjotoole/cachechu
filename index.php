<?php
	// Copyright (c) kevogod, 2008.
	//
	// This program is free software: you can redistribute it and/or modify
	// it under the terms of the GNU General Public License as published by
	// the Free Software Foundation, either version 3 of the License, or
	// (at your option) any later version.
	//
	// This program is distributed in the hope that it will be useful,
	// but WITHOUT ANY WARRANTY; without even the implied warranty of
	// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	// GNU General Public License for more details.
	//
	// You should have received a copy of the GNU General Public License
	// along with this program.  If not, see <http://www.gnu.org/licenses/>.

	define('HOST_PATH', 'data/hosts.dat');
	define('URL_PATH', 'data/urls.dat');
	define('BAN_PATH', 'data/bans.dat');
	define('HOST_LIMIT', 50); // Store 50 hosts,
	define('HOST_OUTPUT', 30); // Output at most 30 hosts
	define('URL_OUTPUT', 15); // Output at most 15 URLs
	define('MAX_HOST_AGE', 10800); // 3 hours
	define('MAX_URL_AGE', 86400); // 24 hours
	define('BAN_TIME', 3600); // 1 hour
	define('ADVERTISE', FALSE);

	$remote_ip = $_SERVER['REMOTE_ADDR'];
	$now       = time();
	$client    = isset($_GET['client']) ? $_GET['client'] : '';
	$get       = isset($_GET['get']) ? $_GET['get'] : '';
	$host      = isset($_GET['ip']) ? $_GET['ip'] : '';
	$net       = isset($_GET['net']) ? $_GET['net'] : '';
	$ping      = isset($_GET['ping']) ? $_GET['ping'] : '';
	$update    = isset($_GET['update']) ? $_GET['update'] : '';
	$url       = isset($_GET['url']) ? trim($_GET['url']) : '';

	if(!empty($_GET)) {
		header('Content-Type: text/plain');
		if(strtolower($net) != 'gnutella2') {
			header('HTTP/1.0 404 Not Found');
			die("ERROR: Network Not Supported\n");
		}
	} else if(file_exists('index.html')) {
		require('index.html');
	} else {
		header('Content-Type: text/plain');
	}

	// Basic spam protection (1 update per hour [default])
	if($update) {
		$bans = file_exists(BAN_PATH) ? @unserialize(file_get_contents(BAN_PATH)) : array();
		if($bans == FALSE) { $bans = array(); } // File could not be unserialized
		if(isset($bans[$remote_ip]) && $now - $bans[$remote_ip] <= BAN_TIME) {
			die("ERROR: Client returned too early\n");
		} else {
			foreach($bans as $ip => $time) {
				if($now - $time > BAN_TIME) {
					unset($bans[$ip]); // Remove old banned hosts
				}
			}
			$bans[$remote_ip] = $now; // Add current IP to banned list
			@file_put_contents(BAN_PATH, serialize($bans));
			echo "I|update|period|", BAN_TIME, "\n";
		}
	}

	// Pong!
	if($ping) { echo "I|pong|Cachechu R10|gnutella2\n";	}

	// Add host to cache
	if($update && $host) {
		define('IP_REGEX', '/\\A((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)):(\\d+)\\z/');
		$error = TRUE;
		if(strpos($host, $remote_ip) !== FALSE && preg_match(IP_REGEX, $host)) {
			list($ip, $port) = explode(':', $host);
			$socket = @fsockopen($ip, $port,  $errno, $errstr, 5);
			if($socket) {
				if(@fwrite($socket, "GNUTELLA CONNECT/0.6\r\n\r\n") !== FALSE) {
					stream_set_timeout($socket, 5);
					if(stream_get_contents($socket, 12) === 'GNUTELLA/0.6') {
						$error = FALSE;
					}
				}
				fclose($socket);
			}
		}
		if(!$error) {
			// Add new host to hosts file and do a little cleanup of the file
			$new_lines = array();
			$lines = file_exists(HOST_PATH) ? file(HOST_PATH) : array();
			$client = ereg_replace('[\\r\\n\\|]', '', $_SERVER['HTTP_USER_AGENT']); //  Don't want no problems in host file
			$lines[] = "$host|" . $now . "|$client|\n";
			foreach($lines as $line) {
				list($ip, $time, $client) = explode('|', $line);
				$age = $now - $time;
				if($age < MAX_HOST_AGE && preg_match(IP_REGEX, $ip)) {
					list($ip, $port) = explode(':', $ip);
					// Remove duplicates and old hosts
					$new_lines[$ip] = "$ip:$port|$time|$client|";
				}
			}

			// Limit number of hosts in file, keeps hosts at the end of the list
			$host_count = count($new_lines);
			if($host_count > HOST_LIMIT) {
				$new_lines = array_slice($new_lines, $host_count - HOST_LIMIT, HOST_LIMIT);
			}

			// Save hosts file and ignore concurrency issues
			@file_put_contents(HOST_PATH, implode("\r\n", $new_lines), LOCK_EX);
			echo "I|update|OK\n";
		} else {
			echo "I|update|WARNING|Rejected IP\n";
		}
	}

	if($update && $url) {
		define('OUTPUT_REGEX', '%\\A(?:(?:H\\|(?:[0-9]{1,3}\\.){3}[0-9]{1,3}.*)|(?:U\\|http://.+)|(?:[A-GI-TV-Z]\\|.*))\\z%i');
		define('URL_REGEX', '/\\Ahttp:\/\/(?P<domain>[-A-Z0-9.]+)(?::(?P<port>[0-9]+))?(?P<file>\/[-A-Z0-9+&@#\/%=~_|!:,.;]*)?\\z/i');
		// Makes it easier to detect duplicates if index page is removed
		$url = preg_replace('/(?:default|index)\\.(?:aspx?|cfm|cgi|htm|html|jsp|php)$/iD', '', $url);
		$url = rtrim($url, '/'); // Trims slashes to avoid duplicates
		$url = urldecode($url); // URLs must be stored in file unescaped
		$test_urls = array();
		$urls = array();
		$lines = file_exists(URL_PATH) ? file(URL_PATH) : array();
		shuffle($lines); // Used to randomize URL testing
		foreach($lines as $line) {
			list($xurl, $time, $status, $ip) = explode('|', trim($line));
			if($xurl) {
				$urls[$xurl] = array('time' => $time, 'status' => $status, 'ip' => $ip);
				if($time === 0 || $now - $time >= MAX_URL_AGE) {
					$test_urls[$xurl] = $status; // Test old URLs
				}
			}
		}

		// Prioritize known good caches (Sorts to OK --> NEW --> BAD)
		arsort($test_urls);

		// Add submitted GWebCache, do not allow Coral Cache URLs and only add new URL
		if(strpos($url, 'nyud.net') === FALSE && preg_match(URL_REGEX, $url) && !isset($urls[$url])) {
			$urls[$url] = array('time' => 0, 'status' => 'NEW', 'ip' => '');
			$test_urls[$url] = 'NEW';
		}

		// Test "random" GWebCache, submitted cache goes to the end of the list
		$test_url = key($test_urls); // First URL
		$test_ip = '';
		$test_status = '';
		if(preg_match(URL_REGEX, $test_url, $matches)) {
			$error = NULL;
			$domain = $matches['domain'];
			$ip = gethostbyname($domain);
			$socket = NULL;
			if($ip != $domain) { // If gethostbyname fails, it will return the tested domain
				$error = TRUE;
				$port = isset($matches['port']) && $matches['port'] ? $matches['port'] : 80;
				ini_set('user_agent', 'Cachechu');
				$socket = @fsockopen($domain, $port, $errno, $errstr, 5);
			}
			if($socket) {			
				$file = isset($matches['file']) ? $matches['file'] : '/'; // No need to URL encode
				$query = "$file?get=1&net=gnutella2&client=TEST&version=Cachechu";
				if(ADVERTISE) {
					$current_url = 'http://' . $_SERVER['SERVER_NAME'];
					if($_SERVER['SERVER_PORT'] != 80) { $current_url .= ':' . $_SERVER['SERVER_PORT']; }
					$current_url .= rtrim(str_replace('index.php', '', $_SERVER['PHP_SELF']), '/');
					$query .= '&update=1&url=' . urlencode($current_url);
				}
				$out = "GET $query HTTP/1.0\r\n";
				$out .= "Host: $domain\r\n";
				$out .= "Connection: Close\r\n\r\n";
				$response = '';
				if(@fwrite($socket, $out) !== FALSE) {
					stream_set_timeout($socket, 5);
					$response = stream_get_contents($socket, 4096);
				}
				fclose($socket);
				$pos = strpos($response, "\r\n\r\n");
				if($pos !== FALSE) {
					$contents = trim(substr($response, $pos + 4));
					if($contents) {
						$error = FALSE;
					}
					$lines = explode("\n", $contents);
					foreach($lines as $line) {
						if(!preg_match(OUTPUT_REGEX, $line)) {
							$error = TRUE; // Contains invalid output
							break;
						}
					}
				}
			}
			if(is_null($error) || ($error && !$urls[$test_url]['status'])) {
				unset($urls[$test_url]); // Remove from cache after testing BAD a 2nd time, or no IP
			} else {
				$urls[$test_url]['time'] = $now;
				$test_status = $error ? 'BAD' : 'OK';
				$urls[$test_url]['status'] = $test_status;
				$test_ip = $ip;
				$urls[$test_url]['ip'] = $test_ip;
			}
		} else if($test_url) {
			unset($urls[$test_url]); // For whatever reason the URL is invalid
		}

		// Generate URL output
		$output = '';
		foreach($urls as $xurl => $values) {
			if($values) {
				$time = $values['time'];
				$status = $values['status'];
				$ip = $values['ip'];
				if($test_ip === $ip && $test_url !== $xurl && $test_status === $status && $status === 'OK') {
					$status = 'DUPE'; // Duplicate IP that is OK but will not be output on GET
					$urls[$test_url]['status'] = $status;
				}
				$output .= "$xurl|$time|$status|$ip|\r\n";
			}
		}
		
		// Save URL files and ignore concurrency issues
		@file_put_contents(URL_PATH, $output, LOCK_EX);
		// Output update notice (returns OK on untested URLs)
		echo isset($urls[$url]) && $urls[$url]['status'] !== 'BAD' ? "I|update|OK\n" : "I|update|WARNING|Rejected URL\n";
	}

	if($get) {
		// Output Hosts
		$count = 0;
		$lines = file_exists(HOST_PATH) ? file(HOST_PATH) : array();
		shuffle($lines);
		foreach($lines as $line) {
			list($ip, $time) = explode('|', $line);
			$age = $now - $time;
			if($age < MAX_HOST_AGE) {
				echo "H|$ip|$age\n";
				++$count;
				if($count >= HOST_OUTPUT) { break; }
			}
		}
		if(!$count) { echo "I|NO-HOSTS\n"; }

		// Output URLs
		$count = 0;
		$lines = file_exists(URL_PATH) ? file(URL_PATH) : array();
		shuffle($lines);
		foreach($lines as $line) {
			list($url, $time, $status) = explode('|', $line);
			$age = $time > 0 ? $now - $time : 3600;
			if($age < MAX_URL_AGE && $status === 'OK') {
				echo "U|$url|$age\n";
				++$count;
				if($count >= URL_OUTPUT) { break; }
			}
		}
		if(!$count) { echo "I|NO-URLS\n"; }
	}

	// Must output something if no requests cause output
	if(!ob_get_contents()) { echo "I|something\n"; }
?>