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
	define('VERSION', 'R45');
	define('AGENT', 'Cachechu ' . VERSION);
	define('DEFAULT_NET', 'gnutella2');
	define('OLD_NET', 'gnutella');
	define('NET_REPLACE', '<network>');
	define('BLOCK_REGEX', '%^Mozilla/4\\.0$|^CoralWebPrx.*$%');
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
	
	$remote_ip = $_SERVER['REMOTE_ADDR'];
	$now       = time();
	$page      = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : '';
	$vendor    = isset($_GET['client']) ? ucwords(strtolower($_GET['client'])) : '';
	$version   = isset($_GET['version']) ? $_GET['version'] : '';
	$client    = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$client    = trim(preg_replace('[|\\r\\n]', '', substr($client, 0, 50))); // Sanitize
	$client    = $client == 'Mozilla/4.0' && $vendor == 'Foxy' ? "$vendor $version" : $client;
	$get       = isset($_GET['get']) ? $_GET['get'] : '';
	$host      = isset($_GET['ip']) ? $_GET['ip'] : '';
	$net       = isset($_GET['net']) ? strtolower($_GET['net']) : '';
	$ping      = isset($_GET['ping']) ? $_GET['ping'] : '';
	$update    = isset($_GET['update']) ? $_GET['update'] : '';
	$url       = isset($_GET['url']) ? trim($_GET['url']) : '';
	// GWC1 requests (Used only by gnutella)
	$hostfile  = isset($_GET['hostfile']) ? $_GET['hostfile'] : '';
	$urlfile   = isset($_GET['urlfile']) ? $_GET['urlfile'] : '';
	$statfile  = isset($_GET['statfile']) ? $_GET['statfile'] : '';
	$is_new    = TRUE; // Use GWC2 style if true
	
	$config = file_exists(CONFIG_PATH) ? @parse_ini_file(CONFIG_PATH, TRUE) : array();
	if(isset($config['Network']['Support'])) {
		$config['Network']['Support'] = array_unique(explode(',', strtolower(trim(str_replace(' ', '', $config['Network']['Support'])))));
	} else {
		$config['Network']['Support'] = array(DEFAULT_NET);
	}
	if($net == '') {
		if(!$get && !$update) { // GWC1 style request
			$update = $host || $url ? 1 : 0;
			$is_new = FALSE;
		}
		if(in_array(OLD_NET, $config['Network']['Support'])) {
			$net = OLD_NET; // Set net to gnutella if no net parameter
		}
	}
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
	
	if(!empty($_GET) && $page == '') {
		header('Content-Type: text/plain');
		if(!$vendor || preg_match(BLOCK_REGEX, $client) || !in_array($net, $config['Network']['Support'])) {
			header('HTTP/1.0 404 Not Found');
			die("ERROR: Network Not Supported\n");
		}
		// Replace <network> for $net in paths
		$config['Path']['Ban'] = str_replace(NET_REPLACE, $net, $config['Path']['Ban']);
		$config['Path']['Host'] = str_replace(NET_REPLACE, $net, $config['Path']['Host']);
		$config['Path']['URL'] = str_replace(NET_REPLACE, $net, $config['Path']['URL']);
		$config['Path']['Stats'] = str_replace(NET_REPLACE, $net, $config['Path']['Stats']);
		$config['Path']['Start'] = str_replace(NET_REPLACE, $net, $config['Path']['Start']);
		if($config['Stats']['Enable']) { // Log stats
			$start_exists = file_exists($config['Path']['Start']);
			$stats_exists = file_exists($config['Path']['Stats']);
			if(!$start_exists) {
				$dir = dirname($config['Path']['Start']);
				if(!file_exists($dir)) {
					@mkdir($dir, 0750, TRUE); // Create directory if it does not exist
				}
				// Add the start time for statistics
				@file_put_contents($config['Path']['Start'], "$now|$net|\r\n");
				$start_exists = file_exists($config['Path']['Start']);
			}
			if(!$stats_exists) {
				$dir = dirname($config['Path']['Stats']);
				if(!file_exists($dir)) {
					@mkdir($dir, 0750, TRUE); // Create directory if it does not exist
				}
				@touch($config['Path']['Stats']);
				$stats_exists = file_exists($config['Path']['Stats']);
			}
			if($stats_exists && $start_exists) {
				$times = array();
				$lines = @file($config['Path']['Start']);
				foreach($lines as $line) {
					@list($timestamp, $xnet) = explode('|', $line);
					$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
					if(is_numeric($timestamp)) { $times[$xnet] = $timestamp; }
				}
				$output_time = FALSE;
				$output = '';
				foreach($config['Network']['Support'] as $xnet) {
					if(isset($times[$xnet])) {
						$output .= $times[$xnet] . "|$xnet|\r\n";
					} else if($xnet == $net) {
						$output .= "$now|$xnet|\r\n";
						$output_time = TRUE;
					}
				}
				if($output_time) { @file_put_contents($config['Path']['Start'], $output); }
				$file = @fopen($config['Path']['Stats'], 'r+');
				// Lock the file to avoid concurrency issues with stats
				if($file && @flock($file, LOCK_EX)) {
					$clients = array();
					$size = filesize($config['Path']['Stats']);
					$contents = $size > 0 ? fread($file, $size) : '';
					$lines = explode("\r\n", $contents);
					$client_exists = FALSE;
					foreach($lines as $line) {
						@list($version, $gets, $updates, $pings, $requests, $xnet) = explode('|', $line);
						$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
						$version = trim($version);
						if($version != '') {
							$clients[$version][$xnet] = array('Gets' => $gets, 'Updates' => $updates, 'Pings' => $pings, 'Requests' => $requests);
						}
						if($xnet == $net && $version == $client) { $client_exists = TRUE; }
					}
					if(!$client_exists) {
						$clients[$client][$net] = array('Gets' => 0, 'Updates' => 0, 'Pings' => 0, 'Requests' => 0);
					}
					$clients[$client][$net]['Gets'] += ($is_new && $get) || (!$is_new && $hostfile || $urlfile) ? 1 : 0;
					$clients[$client][$net]['Updates'] += $update ? 1 : 0;
					$clients[$client][$net]['Pings'] += $ping ? 1 : 0;
					$clients[$client][$net]['Requests'] += 1;
					$output = '';
					foreach($clients as $version => $nets) {
						foreach($nets as $xnet => $stats) {
							$output .= $version;
							foreach($stats as $stat) { $output .= "|$stat"; }
							$output .= "|$xnet|\r\n";
						}
					}
					ftruncate($file, 0); // Clear out stats
					rewind($file); // Go back to start of file
					fwrite($file, $output); // Recreate stats
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
		$exists = file_exists($config['Path']['Ban']);
		if(!$exists) {
			$dir = dirname($config['Path']['Ban']);
			if(!file_exists($dir)) {
				@mkdir($dir, 0750, TRUE); // Create directory if it does not exist
			}
		}
		$bans = $exists ? @unserialize(file_get_contents($config['Path']['Ban'])) : array();
		if($bans == FALSE) { $bans = array(); } // File could not be unserialized
		if(isset($bans[$remote_ip]) && $now - $bans[$remote_ip] <= $config['Cache']['BanTime']) {
			die("ERROR: Client returned too early\n");
		}
		$bans[$remote_ip] = $now; // Add current IP to banned list
		foreach($bans as $ip => $time) {
			if($now - $time > $config['Cache']['BanTime']) {
				unset($bans[$ip]); // Remove old banned hosts
			}
		}
		// Serialize bans list and limit to 100 entries
		@file_put_contents($config['Path']['Ban'], serialize(array_slice($bans, 0, 100, TRUE)));
		if($is_new) {
			echo "I|update|period|", $config['Cache']['BanTime'], "\n"; // Tell client how long it is banned for
		}
	}
	
	// Pong!
	if($ping) {
		if($is_new) {
			echo 'I|pong|', AGENT, '|', implode('-', $config['Network']['Support']), "\n";
		} else if(!$hostfile && !$urlfile) {
			die('PONG '. AGENT . "\n"); // Output only PONG GWC1 request
		}
	}
	
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
			foreach($config['Network']['Support'] as $xnet) {
				$counts[$xnet] = 0; // Initialize host counts for each network
			}
			@list($ip, $port) = explode(':', $host);
			$ips = array($ip); // Keep track of duplicate IPs
			$output = "$host|$now|$client|$net|\r\n"; // Add new host on first line
			if(!file_exists($config['Path']['Host'])) {
				$dir = dirname($config['Path']['Host']);
				if(!file_exists($dir)) {
					@mkdir($dir, 0750, TRUE); // Create directory if it does not exist
				}
				@touch($config['Path']['Host']); // Create new host file
			}
			$file = @fopen($config['Path']['Host'], 'r+'); // Open file for reading and writing
			if($file && @flock($file, LOCK_EX)) { // Lock file before making changes
				while(!feof($file)) {
					@list($ip, $time, $client, $xnet) = explode('|', trim(fgets($file)));
					$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet; // Assume gnutella2 if no network
					$age = $now - $time;
					if($age < $config['Host']['Age'] && preg_match(IP_REGEX, $ip) && isset($counts[$xnet]) && $counts[$xnet] < $config['Host']['Output']) {
						list($ip, $port) = explode(':', $ip);
						if(!in_array($ip, $ips)) { // Do not allow duplicate IPs
							$ips[] = $ip;
							$output .= "$ip:$port|$time|$client|$xnet|\r\n"; // Add line from file
							$counts[$xnet] += 1; // Limit number of hosts in file, keeps hosts at the end of the list
						}
					}
				}
				@ftruncate($file, 0); // Clear out hosts
				@rewind($file); // Go back to start of file
				@fwrite($file, rtrim($output)); // Recreate hosts
				@fclose($file); // Unlocks file
			}
			echo $is_new ? "I|update|OK\n" : "OK\n";
		} else {
			echo $is_new ? "I|update|WARNING|Rejected IP\n" : "OK\nWARNING: Rejected IP\n";
		}
	}
	
	if($update && $url) {
		$test_urls = array();
		$urls = array();
		$lines = file_exists($config['Path']['URL']) ? file($config['Path']['URL']) : array();
		shuffle($lines); // Used to randomize URL testing
		foreach($lines as $line) {
			@list($xurl, $time, $status, $ip, $xnet, $xclient) = explode('|', trim($line));
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			$xurl = get_url($xurl);
			if($xurl !== FALSE) {
				$urls[$xurl][$xnet] = array('Time' => $time, 'Status' => $status, 'IP' => $ip, 'Client' => $xclient);
				if($xnet == $net && $time === 0 || $now - $time >= $config['URL']['TestAge']) {
					$test_urls[$xurl] = $status; // Test old URLs
				}
			}
		}
		
		// Prioritize known good caches (Sorts to OK --> NEW --> BAD)
		arsort($test_urls);
		
		// Add submitted GWebCache, do not allow Coral Cache URLs and only add new URL
		$url = get_url($url);
		if($url !== FALSE && !isset($urls[$url][$net])) {
			$urls[$url][$net] = array('Time' => 0, 'Status' => 'NEW', 'IP' => '', 'Client' => '');
			$test_urls[$url] = 'NEW';
		}
		
		// Test "random" GWebCache, submitted cache goes to the end of the list
		$test_url = key($test_urls); // First URL
		$test_ip = '';
		$test_status = '';
		if(preg_match(URL_REGEX, $test_url, $match)) {
			$error = NULL;
			$contents = '';
			$test_client = ''; // GWebCache version
			$port = isset($match['port']) && $match['port'] ? $match['port'] : 80;
			$file = $match['file'];
			$domain = $match['domain'];
			$ip = gethostbyname($domain);
			if($ip != $domain) { // If gethostbyname fails, it will return the tested domain
				$error = TRUE;
				if($is_new) {
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
				} else {
					$query = "$file?ping=1&client=TEST&version=" . urlencode(AGENT);
					$contents = download_data($domain, $port, get_input($query, $domain), TRUE);
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
			// Add or remove URL from cache
			if(is_null($error) || ($error && isset($urls[$test_url][$net]) && $urls[$test_url][$net]['Status'] === 'BAD')) {
				unset($urls[$test_url]); // Remove from cache after testing BAD a 2nd time, or no IP
			} else {
				$urls[$test_url][$net]['Time'] = $now;
				$test_status = $error ? 'BAD' : 'OK';
				$urls[$test_url][$net]['Status'] = $test_status;
				$test_ip = $ip; // Used to check for duplicate caches
				$urls[$test_url][$net]['IP'] = $test_ip;
				$urls[$test_url][$net]['Client'] = $test_client;
			}
		} else if($test_url) {
			unset($urls[$test_url]); // For whatever reason the URL is invalid
		}
		
		// Generate URL output
		$output = '';		
		foreach($urls as $xurl => $nets) {
			foreach($nets as $xnet => $values) {
				$status = $values['Status'];
				$ip = $values['IP'];
				$time = $values['Time'];
				$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
				$xclient = $values['Client'];
				if($test_ip === $ip && $test_url !== $xurl && $test_status === $status && $status === 'OK') {
					$status = 'DUPE'; // Duplicate IP that is OK but will not be output on GET
				}
				$output .= "$xurl|$time|$status|$ip|$xnet|$xclient|\r\n";
			}
		}
		
		if(!file_exists($config['Path']['URL'])) {
			$dir = dirname($config['Path']['URL']);
			if(!file_exists($dir)) {
				@mkdir($dir, 0750, TRUE); // Create directory if it does not exist
			}
		}
		
		// Save URL files and ignore concurrency issues
		@file_put_contents($config['Path']['URL'], $output, LOCK_EX);
		
		// Output update notice (returns OK on untested URLs)
		if(isset($urls[$url][$net]) && $urls[$url][$net]['Status'] !== 'BAD') {
			echo $is_new ? "I|update|OK\n" : ($host ? '' : "OK\n");
		} else {
			echo $is_new ? "I|update|WARNING|Rejected URL\n" : ($host ? "WARNING: Rejected URL\n" : "OK\nWARNING: Rejected URL\n");
		}
	}
	
	if($get || ($hostfile || $urlfile && !$is_new && !$update)) {
		// Output Hosts
		$count = 0;
		if(($get && $is_new) || ($hostfile && !$is_new)) {
			$lines = file_exists($config['Path']['Host']) ? file($config['Path']['Host']) : array();
			foreach($lines as $line) {
				list($ip, $time, $client, $xnet) = explode('|', $line);
				$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
				$age = $now - $time;
				if($xnet == $net && $age < $config['Host']['Age'] && $age >= 0) {
					echo $is_new ? "H|$ip|$age\n" : "$ip\n";
					++$count;
					if($count >= $config['Host']['Output']) { break; }
				}
			}
			if(!$count && $is_new) { echo "I|NO-HOSTS\n"; }
			if($hostfile && !$is_new) { die(); } // Output only hosts for old style request
		}
		
		// Output URLs
		$count = 0;
		if(($get && $is_new) || ($urlfile && !$is_new)) {
			$lines = file_exists($config['Path']['URL']) ? file($config['Path']['URL']) : array();
			shuffle($lines);
			foreach($lines as $line) {
				list($url, $time, $status, $ip, $xnet) = explode('|', $line);
				$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
				$url = get_url($url);
				$age = $time > 0 ? $now - $time : 0;
				if($url && $xnet == $net && $age < $config['URL']['Age'] && $age >= 0 && $status === 'OK') {
					echo $is_new ? "U|$url|$age\n" : "$url\n";
					++$count;
					if($count > $config['URL']['Output']) { break; }
				}
			}
			if(!$count && $is_new) { echo "I|NO-URLS\n"; }
			if($urlfile && !$is_new) { die(); } // Output only URLs for old style request
		}
	}
	
	// Return stats information for old style request
	if($statfile && !$is_new && file_exists($config['Path']['Stats'])) {
		$time = $now;
		$lines = file_exists($config['Path']['Start']) ? @file($config['Path']['Start']) : array();
		foreach($lines as $line) {
			@list($timestamp, $xnet) = explode('|', $line);
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			if(is_numeric($timestamp) && $xnet == OLD_NET) { $time = $timestamp; }
		}
		$lines = file($config['Path']['Stats']);
		$hour = ($now - $time) / 60 / 60;
		if($hour == 0) { $hour = 1; }
		$total_updates = 0;
		$total_requests = 0;
		foreach($lines as $line) {
			@list($version, $gets, $updates, $pings, $requests, $xnet) = explode('|', $line);
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			if($xnet == OLD_NET && $version != '') {
				$total_updates += $updates;
				$total_requests += $requests;
			}
		}
		$hourly_updates = intval($total_updates / $hour);
		$hourly_requests = intval($total_requests / $hour);
		die("$total_requests\n$hourly_updates\n$hourly_requests\n");
	}
	
	// Tell client not to come back for another 30 minutes
	if(!empty($_GET) && $page == '' && $is_new) {
		echo "I|access|period|1800\n";
	}