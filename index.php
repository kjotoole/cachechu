<?php
	// Copyright (c) kevogod, 2008-2009.
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

	ob_start(); // Enable output buffering
	define('VERSION', 'R43');
	define('AGENT', 'Cachechu ' . VERSION);
	define('IP_REGEX', '/\\A((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)):([1-9][0-9]{0,4})\\z/');
	define('INDEX_REGEX', '/(?:default|index)\\.(?:aspx?|cfm|cgi|htm|html|jsp|php)$/iD');
	define('OUTPUT_REGEX', '%\\A(?:(?:(?:H\\|(?:[0-9]{1,3}\\.){3}[0-9]{1,3}.*):\\d+\\|(\\d+).*|(?:U\\|http://.+)|(?:[A-GI-TV-Z]\\|.*)))\\z%i');
	define('URL_REGEX', '/\\Ahttp:\/\/(?P<domain>[-A-Z0-9.]+)(?::(?P<port>[0-9]+))?(?P<file>\/[-A-Z0-9+&@#\/%=~_!:,.;]*)?\\z/i');
	define('SLASH_REGEX', '%^[^.]+[^/]$%');
	define('MAX_HOST_AGE', 259200); // If any hosts are older than 3 days, the cache is marked as BAD
	define('CONFIG_PATH', 'config/config.ini');
	
	// Request data from a host asynchronously
	function download_data($address, $port, $input, $web) {
		ini_set('user_agent', AGENT);
		$socket = stream_socket_client("tcp://$address:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT);
		stream_set_blocking($socket, 0); // Non-blocking IO
		$read = null;
		$write = array($socket);
		$except = null;
		$timeout = time() + 10; // Script should time out after 10 seconds
		while($input && time() < $timeout) {
			$write[0] = $socket;
			if(stream_select($read, $write, $except, 0, 100000)) { // Return after 0.1 seconds
				$written = fwrite($socket, $input, strlen($input));
				$input = substr($input, $written);
			}
		}
		$output = '';
		$read = array($socket);
		$write = null;
		$except = null;
		while (!feof($socket) && time() < $timeout) {
			$read[0] = $socket;
			if(stream_select($read, $write, $except, 0, 100000)) {
				$output .= fread($socket, 8192);
			}
		}
		fclose($socket);
		if($web) {
			$pos = strpos($output, "\r\n\r\n");
			$output = $pos !== FALSE ? trim(substr($output, $pos + 4)) : '';
		}
		return $output;
	}
	
	// Format Web request
	function get_input($query, $domain) {
		return "GET $query HTTP/1.0\r\nHost: $domain\r\nConnection: Close\r\n\r\n";
	}
	
	// Get valid URL or return false on error
	function get_url($url) {
		$url = urldecode(preg_replace(INDEX_REGEX, '', $url));
		$new_url = rtrim($url, '/');
		if($new_url != $url) { $url = $new_url . '/'; }
		if(!preg_match('/nyuc?d\\.net/s', $url) && preg_match(URL_REGEX, $url, $match)) {
			if($match['port']) {
				$port = ltrim($match['port'], '0');
				if($port >= 1 && $port <= 65535) {
					$replace = $port == 80 ? '' : ':' . $port;
					$url = str_replace(':' . $match['port'], $replace, $url);
				} else {
					$url = FALSE;
				}
			}
			$url = str_replace($match['domain'], strtolower($match['domain']), $url);
			return $url;
		} else {
			return FALSE;
		}
	}
	
	$config = file_exists(CONFIG_PATH) ? @parse_ini_file(CONFIG_PATH, TRUE) : array();
	$config['Host']['Age'] = isset($config['Host']['Age']) ? $config['Host']['Age'] : 28800;
	$config['Host']['Output'] = isset($config['Host']['Output']) ? $config['Host']['Output'] : 30;
	$config['Host']['Testing'] = isset($config['Host']['Testing']) ? $config['Host']['Testing'] : 1;
	$config['URL']['Age'] = isset($config['URL']['Age']) ? $config['URL']['Age'] : 604800;
	$config['URL']['Output'] = isset($config['URL']['Output']) ? $config['URL']['Output'] : 30;
	$config['URL']['TestAge'] = isset($config['URL']['TestAge']) ? $config['URL']['TestAge'] : 86400;
	$config['Cache']['Advertise'] = isset($config['Cache']['Advertise']) ? $config['Cache']['Advertise'] : 1;
	$config['Cache']['BanTime'] = isset($config['Cache']['BanTime']) ? $config['Cache']['BanTime'] : 3600;
	$config['Path']['Ban'] = isset($config['Path']['Ban']) ? $config['Path']['Ban'] : 'data/bans.dat';
	$config['Path']['Host'] = isset($config['Path']['Host']) ? $config['Path']['Host'] : 'data/hosts.dat';
	$config['Path']['URL'] = isset($config['Path']['URL']) ? $config['Path']['URL'] : 'data/urls.dat';
	$config['Path']['Stats'] = isset($config['Path']['Stats']) ? $config['Path']['Stats'] : 'data/stats.dat';
	$config['Path']['Start'] = isset($config['Path']['Start']) ? $config['Path']['Start'] : 'data/start.dat';
	$config['Interface']['Show'] = isset($config['Interface']['Show']) ? $config['Interface']['Show'] : 1;
	$config['Interface']['Info'] = isset($config['Interface']['Info']) ? $config['Interface']['Info'] : 1;
	$config['Interface']['StatsLimit'] = isset($config['Interface']['StatsLimit']) ? $config['Interface']['StatsLimit'] : 10;
	$config['Stats']['Enable'] = isset($config['Stats']['Enable']) ? $config['Stats']['Enable'] : TRUE;
	
	$remote_ip = $_SERVER['REMOTE_ADDR'];
	$now       = time();
	$vendor    = isset($_GET['client']) ? ucwords(strtolower($_GET['client'])) : '';
	$version   = isset($_GET['version']) ? $_GET['version'] : '';
	$client    = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$client    = trim(preg_replace('[|\\r\\n]', '', substr($client, 0, 50))); // Sanitize
	$client    = $client == 'Mozilla/4.0' && $vendor == 'Foxy' ? "$vendor $version" : $client;
	$get       = isset($_GET['get']) ? $_GET['get'] : '';
	$host      = isset($_GET['ip']) ? $_GET['ip'] : '';
	$net       = isset($_GET['net']) ? $_GET['net'] : '';
	$ping      = isset($_GET['ping']) ? $_GET['ping'] : '';
	$update    = isset($_GET['update']) ? $_GET['update'] : '';
	$url       = isset($_GET['url']) ? trim($_GET['url']) : '';
	// GWC1 requests (Used only by gnutella)
	$hostfile  = isset($_GET['hostfile']) ? $_GET['hostfile'] : '';
	$urlfile   = isset($_GET['urlfile']) ? $_GET['urlfile'] : '';
	$statfile  = isset($_GET['statfile']) ? $_GET['statfile'] : '';
	$is_new    = TRUE; // Use GWC2 style if true

	if(!empty($_GET)) {
		header('Content-Type: text/plain');
		if(strtolower($net) != 'gnutella2') {
			header('HTTP/1.0 404 Not Found');
			die("ERROR: Network Not Supported\n");
		}
		if(file_exists($config['Path']['Stats'])) { // Log stats
			if(!file_exists($config['Path']['Start'])) {
				// Add the start time for statistics
				@file_put_contents($config['Path']['Start'], time());
			}
			if(file_exists($config['Path']['Start'])) {
				$timestamp = @file_get_contents($config['Path']['Start']);
				if(!is_numeric($timestamp)) { @file_put_contents($config['Path']['Start'], time()); }
				$file = @fopen($config['Path']['Start'], 'a');
				// Lock the file to avoid concurrency issues with stats
				if($file && @flock($file, LOCK_EX)) {
					$clients = array();
					$lines = file($config['Path']['Stats']);
					foreach($lines as $line) {
						@list($version, $gets, $updates, $pings, $requests) = explode('|', $line);
						$version = trim($version);
						if($version != '') {
							$clients[$version] = array("gets" => $gets, "updates" => $updates, "pings" => $pings, "requests" => $requests);
						}
					}
					if(!array_key_exists($client, $clients)) {
						$clients[$client] = array("gets" => 0, "updates" => 0, "pings" => 0, "requests" => 0);
					}
					$clients[$client]["gets"] += $get == '' ? 0 : 1;
					$clients[$client]["updates"] += $update == '' ? 0 : 1;
					$clients[$client]["pings"] += $ping == '' ? 0 : 1;
					$clients[$client]["requests"] += 1;
					$output = '';
					foreach($clients as $version => $stats) {
						$output .= $version;
						foreach($stats as $stat) { $output .= "|$stat"; }
						$output .= "|\r\n";
					}
					@file_put_contents($config['Path']['Stats'], $output, LOCK_EX);
					if($file) { @flock($file, LOCK_UN); }
				}
				if($file) { @fclose($file); }
			}
		}
	} else if(file_exists('main.php') && $config['Interface']['Show']) {
		require('main.php');
	} else {
		header('Content-Type: text/plain');
	}

	// Basic spam protection (1 update per hour [default])
	if($update) {
		$bans = file_exists($config['Path']['Ban']) ? @unserialize(file_get_contents($config['Path']['Ban'])) : array();
		if($bans == FALSE) { $bans = array(); } // File could not be unserialized
		if(isset($bans[$remote_ip]) && $now - $bans[$remote_ip] <= $config['Cache']['BanTime']) {
			die("ERROR: Client returned too early\n");
		}
		foreach($bans as $ip => $time) {
			if($now - $time > $config['Cache']['BanTime']) {
				unset($bans[$ip]); // Remove old banned hosts
			}
		}
		$bans[$remote_ip] = $now; // Add current IP to banned list
		@file_put_contents($config['Path']['Ban'], serialize($bans));
		echo "I|update|period|", $config['Cache']['BanTime'], "\n";
	}

	// Pong!
	if($ping) { echo 'I|pong|', AGENT, "|gnutella2\n"; }

	// Add host to cache
	if($update && $host) {
		$error = TRUE;
		if(strpos($host, $remote_ip) !== FALSE && preg_match(IP_REGEX, $host)) {
			if($config['Host']['Testing']) {
				list($ip, $port) = explode(':', $host);
				$output = trim(download_data($ip, $port, "GNUTELLA CONNECT/0.6\r\n\r\n", FALSE));
				if($output != '') { $error = FALSE; }
			} else {
				$error = FALSE; // Assume host is good if testing is off
			}
		}
		if(!$error) {
			// Add new host to hosts file and do a little cleanup of the file
			$new_lines = array();
			$lines = file_exists($config['Path']['Host']) ? file($config['Path']['Host']) : array();
			$lines[] = "$host|$now|$client|\n";
			foreach($lines as $line) {
				list($ip, $time, $client) = explode('|', $line);
				$age = $now - $time;
				if($age < $config['Host']['Age'] && preg_match(IP_REGEX, $ip)) {
					list($ip, $port) = explode(':', $ip);
					// Remove duplicates and old hosts
					$new_lines[$ip] = "$ip:$port|$time|$client|";
				}
			}

			// Limit number of hosts in file, keeps hosts at the end of the list
			$host_count = count($new_lines);
			if($host_count > $config['Host']['Output']) {
				$new_lines = array_slice($new_lines, $host_count - $config['Host']['Output'], $config['Host']['Output']);
			}

			// Save hosts file and ignore concurrency issues
			@file_put_contents($config['Path']['Host'], implode("\r\n", $new_lines), LOCK_EX);
			echo "I|update|OK\n";
		} else {
			echo "I|update|WARNING|Rejected IP\n";
		}
	}

	if($update && $url) {
		$test_urls = array();
		$urls = array();
		$lines = file_exists($config['Path']['URL']) ? file($config['Path']['URL']) : array();
		shuffle($lines); // Used to randomize URL testing
		foreach($lines as $line) {
			list($xurl, $time, $status, $ip) = explode('|', trim($line));
			$xurl = get_url($xurl);
			if($xurl !== FALSE) {
				$urls[$xurl] = array('time' => $time, 'status' => $status, 'ip' => $ip);
				if($time === 0 || $now - $time >= $config['URL']['TestAge']) {
					$test_urls[$xurl] = $status; // Test old URLs
				}
			}
		}

		// Prioritize known good caches (Sorts to OK --> NEW --> BAD)
		arsort($test_urls);

		// Add submitted GWebCache, do not allow Coral Cache URLs and only add new URL
		$url = get_url($url);
		if($url !== FALSE && !isset($urls[$url])) {
			$urls[$url] = array('time' => 0, 'status' => 'NEW', 'ip' => '');
			$test_urls[$url] = 'NEW';
		}

		// Test "random" GWebCache, submitted cache goes to the end of the list
		$test_url = key($test_urls); // First URL
		$test_ip = '';
		$test_status = '';
		if(preg_match(URL_REGEX, $test_url, $match)) {
			$error = NULL;
			$contents = '';
			$test_client = '';
			$port = isset($match['port']) && $match['port'] ? intval($match['port']) : 80;
			$file = $match['file'];
			$domain = $match['domain'];
			$ip = gethostbyname($domain);
			if($ip != $domain) { // If gethostbyname fails, it will return the tested domain
				$error = TRUE;
				$query = "$file?ping=1&net=$net&client=TEST&version=" . urlencode(AGENT);
				$contents .= download_data($domain, $port, get_input($query, $domain), TRUE) . "\n";
				if($contents) {
					$query = "$file?get=1&net=$net&client=TEST&version=" . urlencode(AGENT);
					$contents .= download_data($domain, $port, get_input($query, $domain), TRUE) . "\n";
				}
				if($contents && $config['Cache']['Advertise']) {
					$current_url = 'http://' . $_SERVER['SERVER_NAME'];
					if($_SERVER['SERVER_PORT'] != 80) { $current_url .= ':' . $_SERVER['SERVER_PORT']; }
					$current_url = urlencode(get_url($current_url . $_SERVER['PHP_SELF']));
					$query = "$file?update=1&url=$current_url&net=$net&client=TEST&version=" . urlencode(AGENT);
					$contents .= download_data($domain, $port, get_input($query, $domain), TRUE);
				}
			}
			$contents = trim($contents);
			if($contents) { $error = FALSE; }
			// Validate the GWebCache output
			$lines = explode("\n", $contents);
			foreach($lines as $line){
				@list($field1, $field2, $field3) = explode('|', trim($line));
				if(strtoupper($field1) == 'I') {
					if(strtolower($field2) == 'pong') {
						$test_client = substr(trim($field3), 0, 50);
					}
				} else if(strtoupper($field1) == 'H' && preg_match(IP_REGEX, $field2) && ctype_digit($field3) && $field3 <= MAX_HOST_AGE) {
				} else if(strtoupper($field1) == 'U' && preg_match(URL_REGEX, $field2) && ctype_digit($field3)) {
				} else if(strlen($field1) > 5 && substr_compare($field1, 'PONG ', 0, 5, TRUE) == 0) {
					$test_client = substr(trim(substr($field1, 5)), 0, 50);
				} else {
					$error = TRUE;
					break;
				}
			}
			if(is_null($error) || ($error && $urls[$test_url]['status'] === 'BAD')) {
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
		@file_put_contents($config['Path']['URL'], $output, LOCK_EX);
		// Output update notice (returns OK on untested URLs)
		echo isset($urls[$url]) && $urls[$url]['status'] !== 'BAD' ? "I|update|OK\n" : "I|update|WARNING|Rejected URL\n";
	}

	if($get) {
		// Output Hosts
		$count = 0;
		$lines = file_exists($config['Path']['Host']) ? file($config['Path']['Host']) : array();
		shuffle($lines);
		foreach($lines as $line) {
			list($ip, $time) = explode('|', $line);
			$age = $now - $time;
			if($age < $config['Host']['Age']) {
				echo "H|$ip|$age\n";
				++$count;
				if($count >= $config['Host']['Output']) { break; }
			}
		}
		if(!$count) { echo "I|NO-HOSTS\n"; }

		// Output URLs
		$count = 0;
		$lines = file_exists($config['Path']['URL']) ? file($config['Path']['URL']) : array();
		shuffle($lines);
		foreach($lines as $line) {
			list($xurl, $time, $status) = explode('|', $line);
			$xurl = get_url($xurl);
			$age = $time > 0 ? $now - $time : 3600;
			if($xurl && $age < $config['URL']['Age'] && $age >= 0 && $status === 'OK') {
				echo "U|$xurl|$age\n";
				++$count;
				if($count >= $config['URL']['Output']) { break; }
			}
		}
		if(!$count) { echo "I|NO-URLS\n"; }
	}

	if(!empty($_GET)) { echo "I|access|period|1800\n"; }
