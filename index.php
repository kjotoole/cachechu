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

	// Tests GWebCache for valid output
	function test_url($url) {
		$error = TRUE;
		if(preg_match(URL_REGEX, $url, $matches)) {
			$domain = $matches['domain'];
			$ip = gethostbyname($domain);
			$ip = $ip != $domain ? $ip : ''; // Make sure IP is blank if domain is returned by function
			if($ip) {
				$port = isset($matches['port']) && $matches['port'] ? $matches['port'] : 80;
				ini_set('user_agent', 'Cachechu');
				$socket = @fsockopen($domain, $port, $errno, $errstr, 3);
				if($socket) {
					$file = isset($matches['file']) ? $matches['file'] : '/'; // No need to URL encode
					$out = "GET $file?get=1&net=gnutella2&client=TEST&version=Cachechu HTTP/1.0\r\n";
					$out .= "Host: $domain\r\n";
					$out .= "Connection: Close\r\n\r\n";
					$response = '';
					if(@fwrite($socket, $out) !== FALSE) {
						stream_set_timeout($socket, 2);
						$response = stream_get_contents($socket);
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
			}
		}
		return !$error; // Return TRUE if it works
	}

	define('HOST_PATH', 'store/hosts');
	define('URL_PATH', 'store/urls');
	define('HOST_LIMIT', 50); // Only store 50 hosts
	define('MAX_HOST_AGE', 9000); // 2.5 hours
	define('MAX_URL_AGE', 86400); // 24 hours
	define('IP_REGEX', '/\\A(?:(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?):\\d+)\\z/');
	define('URL_REGEX', '/\\Ahttp:\/\/(?P<domain>[-A-Z0-9.]+)(?::(?P<port>[0-9]+))?(?P<file>\/[-A-Z0-9+&@#\/%=~_|!:,.;]*)?\\z/i');
	define('OUTPUT_REGEX', '%\\A(?:(?:H\\|(?:[0-9]{1,3}\\.){3}[0-9]{1,3}.*)|(?:U\\|http://.+)|(?:[A-GI-TV-Z]\\|.*))\\z%i');

	$client = isset($_GET['client']) ? $_GET['client'] : '';
	$get    = isset($_GET['get']) ? $_GET['get'] : '';
	$host   = isset($_GET['ip']) ? $_GET['ip'] : '';
	$net    = isset($_GET['net']) ? $_GET['net'] : '';
	$ping   = isset($_GET['ping']) ? $_GET['ping'] : '';
	$update = isset($_GET['update']) ? $_GET['update'] : '';
	$url    = isset($_GET['url']) ? trim($_GET['url']) : '';

	if(count($_GET) > 0) {
		header('Content-Type: text/plain');
		if(strtolower($net) != 'gnutella2') {
			if(!$net) { // Kill off Gnutella clients, such as BearShare
				header('HTTP/1.0 404 Not Found');
			}
			die("ERROR: Network Not Supported\n");
		}
	}
	else {
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>Cachechu!</title></head><body><p>I Choose You! Cachechu!</p></body></html>';
	}

	if($ping) {
		echo "I|pong|Cachechu|gnutella2\n";
	}

	// Add host to cache
	if($update && $host) {
		$error = TRUE;
		if(strpos($host, $_SERVER['REMOTE_ADDR']) !== FALSE && preg_match(IP_REGEX, $host)) {
			list($ip, $port) = explode(':', $host);
			$socket = @fsockopen($ip, $port, $error_num, $error, 1);
			if($socket) {
				fclose($socket);
				$error = FALSE;
			}
		}
		if(!$error) {
			// Add new host to hosts file and do a little cleanup of the file
			$new_lines = array();
			$lines = file_exists(HOST_PATH) ? file(HOST_PATH) : array();
			$client = ereg_replace('[\\r\\n\\|]', '', $_SERVER['HTTP_USER_AGENT']); //  Don't want no problems in host file
			$lines[] = "$host|" . time() . "|$client|\n"; 
			foreach($lines as $line) {
				list($ip, $time, $client) = explode('|', $line);
				$age = time() - $time;
				if($age < MAX_HOST_AGE && preg_match(IP_REGEX, $ip)) {
					list($ip, $port) = explode(':', $ip);
					// Remove duplicates and old hosts
					$new_lines[$ip] = "$ip:$port|$time|$client|";
				}
			}

			// Limit number of hosts in file
			$host_count = count($new_lines);
			if($host_count > HOST_LIMIT) {
				$new_lines = array_slice($new_lines, $host_count - HOST_LIMIT, HOST_LIMIT);
			}
			@file_put_contents(HOST_PATH, $new_lines, LOCK_EX);
			echo "I|update|OK\n";
		} else {
			echo "I|update|WARNING|Rejected IP\n";
		}
	}

	if($update && $url) {
		// Makes it easier to detect duplicates if index page is removed
		$url = preg_replace('/(?:default|index)\\.(?:aspx?|cfm|cgi|htm|html|jsp|php)$/iD', '', $url);
		$url = rtrim($url, '/'); // Trims slashes to avoid duplicates
		$url = urldecode($url); // URLs must be stored in file unescaped
		$test_urls = array();
		$urls = array();
		$lines = file_exists(URL_PATH) ? file(URL_PATH) : array();
		foreach($lines as $line) {
			list($xurl, $time, $status) = explode('|', trim($line));
			if($xurl) {
				$urls[$xurl] = array('time' => $time, 'status' => $status);
				if($time == 0 || time() - $time >= MAX_URL_AGE) {
					$test_urls[$xurl] = TRUE; // Test old URLs
				}
			}
		}

		// Add submitted GWebCache, do not allow Coral Cache URLs and only add new URL
		if(strpos($url, 'nyud.net') === FALSE && preg_match(URL_REGEX, $url) && !isset($urls[$url])) {
			$urls[$url] = array('time' => 0, 'status' => 'UNKNOWN');
			$test_urls[$url] = TRUE;
		}

		// Test random GWebCache, possibility that the submitted cache will be tested
		$rand_url = array_rand($test_urls);
		if($rand_url) {
			$status = test_url($rand_url);
			if(!$status && !$urls[$rand_url]['status']) {
				$urls[$rand_url] = NULL; // Remove from cache after testing FAILED a 2nd time
			} else {
				$urls[$rand_url]['time'] = time();
				$urls[$rand_url]['status'] = $status ? 'OK' : 'FAILED';
			}
		}

		// Generate URL output
		$output = '';
		foreach($urls as $xurl => $values) {
			$output .= "$xurl|" . $values['time'] . '|' . $values['status'] . "|\r\n";
		}
		@file_put_contents(URL_PATH, $output, LOCK_EX);
		
		if(isset($urls[$url]) && $urls[$url]['status'] != 'FAILED') {
			echo "I|update|OK\n";
		} else {
			echo "I|update|WARNING|Rejected URL\n";
		}
	}

	if($get) {
		// Output Hosts
		$count = 0;
		$lines = file_exists(HOST_PATH) ? file(HOST_PATH) : array();
		foreach($lines as $line) {
			list($ip, $time) = explode('|', $line);
			$age = time() - $time;
			if($age < MAX_HOST_AGE) {
				echo "H|$ip|$age\n";
				++$count;
			}
		}
		if(!$count) {
			echo "I|NO-HOSTS\n";
		}

		// Output URLs
		$count = 0;
		$lines = file_exists(URL_PATH) ? file(URL_PATH) : array();
		foreach($lines as $line) {
			list($url, $time, $status) = explode('|', $line);
			$age = $time > 0 ? time() - $time : 3600;
			if($age < MAX_URL_AGE && $status == 'OK') {
				echo "U|$url|$age\n";
				++$count;
			}
		}
		if(!$count) {
			echo "I|NO-URLS\n";
		}
	}

	// Must output something
	if(!ob_get_contents()) {
		echo "I|\n";
	}
?>