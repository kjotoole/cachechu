<?php
	// Copyright (c) kevogod, 2008-2011.
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
	
	$client = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if($client == "Shareaza") { // To reduce hammering, attempt to ignore Shareaza clients with no specific version information
		header('HTTP/1.0 403 Forbidden');
		die("");
	}
	$now = time();
	ob_start(); // Enable output buffering
	define('VERSION', '1.4');
	define('AGENT', 'Cachechu ' . VERSION);
	define('DEFAULT_NET', 'gnutella2');
	define('MUTE', 'mute');
	define('MUTE_REGEX', '/mute(?!lla)/i');
	define('SANITIZE_REGEX', '[|\\r\\n]');
	define('GNUTELLA', 'gnutella');
	define('NET_REPLACE', '<network>');
	define('BLOCK_REGEX', '%^Mozilla/4\\.0$|^CoralWebPrx.*$|^(?:FT|M)WebCache.*$%');
	define('IP_REGEX', '/\\A((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)):([1-9][0-9]{0,4})\\z/');
	define('TRAIL_REGEX', '%(?<=\\.(?:asp|cfm|cgi|htm|jsp|php))/.*$%i');
	define('INDEX_REGEX', '/(?:default|index)\\.(?:aspx?|cfm|cgi|htm|html|jsp|php)$/iD');
	define('URL_REGEX', '/\\Ahttp:\/\/(?P<domain>[A-Z0-9](?:\\.|(?:(?:[A-Z0-9]|(?:[A-Z0-9][-A-Z0-9]*[A-Z0-9]))\\.)*)[A-Z]{2,})(?::(?P<port>[0-9]+))?(?P<file>\/[A-Z0-9\/.~_-]*)?\\z/i');
	define('SLASH_REGEX', '%^[^.]+[^/]$%');
	define('MAX_HOST_AGE', 259200); // If any hosts are older than 3 days, the cache is marked as BAD
	define('CONFIG_PATH', 'config/config.ini');
	define('DIR_FLAGS', 0750); // Flags to use when creating directories
	
	// Request data from a host asynchronously
	function download_data($address, $port, $input, $web) {
		$socket = stream_socket_client("tcp://$address:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT);
		stream_set_blocking($socket, 0); // Non-blocking IO
		$read = null;
		$write = array($socket);
		$except = null;
		$timeout = time() + 10; // Script should time out after 10 seconds
		while($input && time() < $timeout) {
			$write[0] = $socket;
			if(stream_select($read, $write, $except, 0, 100000)) { // Return after 0.1 seconds
				$written = @fwrite($socket, $input, strlen($input));
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
		return "GET $query HTTP/1.0\r\nHost: $domain\r\nConnection: Close\r\nUser-Agent:" . AGENT . "\r\n\r\n";
	}
	
	// Sanitizes input and makes usable for file
	function sanitize($text) {
		return trim(preg_replace(SANITIZE_REGEX, '', substr($text, 0, 50)));
	}
	
	// Get valid URL or return false on error
	function get_url($url) {
		$url = preg_replace(INDEX_REGEX, '', urldecode($url)); // Removes index.php from URL
		$url = preg_replace(TRAIL_REGEX, '', $url); // Removes trailing garbage
		$new_url = rtrim($url, '/'); // Removes all trailing slashes
		if($new_url != $url) { $url = $new_url . '/'; } // Adds trailing slash
		if(!preg_match('/nyuc?d\\.net/s', $url) && preg_match(URL_REGEX, $url, $match)) {
			if(isset($match['port']) && $match['port'] != '') {
				$port = ltrim($match['port'], '0');
				if($port >= 1 && $port <= 65535) { // Must have valid port
					$replace = $port == 80 ? '' : ':' . $port; // Remove port 80 from URL
					$url = str_replace(':' . $match['port'], $replace, $url);
				} else {
					$url = FALSE; // Port out of range
				}
			}
			$url = str_replace($match['domain'], strtolower($match['domain']), $url); // Domain should be lowercase
			return $url;
		} else {
			return FALSE; // URL is invalid or a Coral Cache
		}
	}
	
	$remote_ip = $_SERVER['REMOTE_ADDR'];
	$data      = isset($_GET['data']) ? strtolower(trim($_GET['data'])) : '';
	$vendor    = isset($_GET['client']) ? ucwords(strtolower($_GET['client'])) : '';
	$version   = isset($_GET['version']) ? $_GET['version'] : '';
	$client    = sanitize($client);
	$client    = $client == 'Mozilla/4.0' && $vendor == 'Foxy' ? sanitize("$vendor $version") : $client;
	$get       = isset($_GET['get']) ? $_GET['get'] : '';
	$host      = isset($_GET['ip']) ? $_GET['ip'] : '';
	$net       = isset($_GET['net']) ? strtolower($_GET['net']) : '';
	$ping      = isset($_GET['ping']) ? $_GET['ping'] : '';
	$update    = isset($_GET['update']) ? $_GET['update'] : '';
	$url       = isset($_GET['url']) ? trim($_GET['url']) : '';
	$gwcs      = isset($_GET['gwcs']) ? $_GET['gwcs'] : '';
	$hostfile  = isset($_GET['hostfile']) ? $_GET['hostfile'] : '';
	$urlfile   = isset($_GET['urlfile']) ? $_GET['urlfile'] : '';
	$is_gwc2   = ($get || $update) && !$gwcs; // Use GWC2 style if true
	if(!$is_gwc2) {
		$update = $host || $url ? 1 : 0;
		$get    = 0;
	}
	
	$config = file_exists(CONFIG_PATH) ? @parse_ini_file(CONFIG_PATH, TRUE) : array();
	if(isset($config['Network']['Support'])) {
		$config['Network']['Support'] = array_unique(explode(',', strtolower(trim(str_replace(' ', '', $config['Network']['Support'])))));
	} else {
		$config['Network']['Support'] = array(DEFAULT_NET);
	}
	if($net == '') {
		if(strtolower($vendor) == MUTE && preg_match(MUTE_REGEX, $client)) {
			$client = sanitize("$vendor $version");
			$old_net = MUTE;
		} else {
			$old_net = GNUTELLA;
		}
		if(in_array($old_net, $config['Network']['Support'])) {
			$net = $old_net; // Set net to gnutella or mute if no net parameter
		}
	}
	$config['Host']['Age']        = isset($config['Host']['Age']) ? $config['Host']['Age'] : 86400;
	$config['Host']['Output']     = isset($config['Host']['Output']) ? $config['Host']['Output'] : 30;
	$config['Host']['Verify']     = isset($config['Host']['Verify']) ? $config['Host']['Verify'] : 0;
	$config['URL']['Age']         = isset($config['URL']['Age']) ? $config['URL']['Age'] : 604800;
	$config['URL']['Output']      = isset($config['URL']['Output']) ? $config['URL']['Output'] : 30;
	$config['URL']['TestAge']     = isset($config['URL']['TestAge']) ? $config['URL']['TestAge'] : 86400;
	$config['Cache']['Advertise'] = isset($config['Cache']['Advertise']) ? $config['Cache']['Advertise'] : 1;
	$config['Cache']['BanTime']   = isset($config['Cache']['BanTime']) ? $config['Cache']['BanTime'] : 3600;
	$config['Path']['Ban']        = isset($config['Path']['Ban']) ? $config['Path']['Ban'] : 'data/bans.dat';
	$config['Path']['Host']       = isset($config['Path']['Host']) ? $config['Path']['Host'] : 'data/hosts.dat';
	$config['Path']['URL']        = isset($config['Path']['URL']) ? $config['Path']['URL'] : 'data/urls.dat';
	$config['Interface']['Show']  = isset($config['Interface']['Show']) ? $config['Interface']['Show'] : 1;
	
	if(!empty($_GET) && $data == '') {
		header('Content-Type: text/plain');
		if(!$vendor || preg_match(BLOCK_REGEX, $client) || !in_array($net, $config['Network']['Support'])) {
			header('HTTP/1.0 404 Not Found');
			die("ERROR: Network Not Supported\n");
		}
		// Replace <network> for $net in paths
		$config['Path']['Ban']   = str_replace(NET_REPLACE, $net, $config['Path']['Ban']);
		$config['Path']['Host']  = str_replace(NET_REPLACE, $net, $config['Path']['Host']);
		$config['Path']['URL']   = str_replace(NET_REPLACE, $net, $config['Path']['URL']);
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
				@mkdir($dir, DIR_FLAGS, TRUE); // Create directory if it does not exist
			}
		}
		$bans = $exists ? @unserialize(@file_get_contents($config['Path']['Ban'])) : array();
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
		// Serialize bans list and limit to 250 entries
		@file_put_contents($config['Path']['Ban'], serialize(array_slice($bans, 0, 250, TRUE)));
		if($is_gwc2) {
			echo "I|update|period|", $config['Cache']['BanTime'], "\n"; // Tell client how long it is banned for
		}
	}
	
	// Pong!
	if($ping) {
		if($is_gwc2) {
			$nets = implode('|', $config['Network']['Support']);
			echo 'I|pong|', AGENT, "|$nets\n";
			echo "I|networks|$nets\n";
		} else {
			echo 'PONG ', AGENT, "\n";
		}
	}
	
	// Add host to cache
	if($update && $host) {
		$error = TRUE;
		if(strpos($host, $remote_ip) !== FALSE && preg_match(IP_REGEX, $host)) {
			if($config['Host']['Verify'] && $net != 'foxy') {
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
					@mkdir($dir, DIR_FLAGS, TRUE); // Create directory if it does not exist
				}
				@touch($config['Path']['Host']); // Create new host file
			}
			$file = @fopen($config['Path']['Host'], 'r+'); // Open file for reading and writing
			if($file && @flock($file, LOCK_EX)) { // Lock file before making changes
				while(!feof($file)) {
					@list($ip, $time, $client, $xnet) = explode('|', trim(fgets($file)));
					$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet; // Assume gnutella2 if no network
					$age = $now - $time;
					if($age < $config['Host']['Age'] && preg_match(IP_REGEX, $ip) && isset($counts[$xnet]) && $counts[$xnet] < $config['Host']['Output'] - 1) {
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
			echo $is_gwc2 ? "I|update|OK\n" : "OK\n";
		} else {
			echo $is_gwc2 ? "I|update|WARNING|Rejected IP\n" : "WARNING: Rejected IP\n";
		}
	}
	
	if($update && $url) {
		if($net == MUTE) { die($is_gwc2 ? "I|update|WARNING|URL Adding Disabled\n" : "WARNING: URL Adding Disabled\n"); }
		$test_urls = array();
		$urls = array();
		$lines = file_exists($config['Path']['URL']) ? file($config['Path']['URL']) : array();
		shuffle($lines); // Used to randomize URL testing
		$bad_count = 0;
		foreach($lines as $line) {
			@list($xurl, $time, $status, $ip, $xnet, $xclient) = explode('|', trim($line));
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			$xurl = get_url($xurl);
			if(($bad_count < 1000 || $status != 'BAD') && $xurl !== FALSE && !preg_match(BLOCK_REGEX, $xclient)) {
				$urls[$xurl][$xnet] = array('Time' => $time, 'Status' => $status, 'IP' => $ip, 'Client' => $xclient);
				if($xnet == $net && $time === 0 || $now - $time >= $config['URL']['TestAge']) {
					$test_urls[$xurl] = $status; // Test old URLs
				}
				if($status == 'BAD') { ++$bad_count; } // Limit to 1000 bad URLs
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
				if($is_gwc2) {
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
					$query = "$file?ping=1&net=$net&client=TEST&version=" . urlencode(AGENT);
					$contents = download_data($domain, $port, get_input($query, $domain), TRUE) . "\n";
					if($contents) {
						$query = "$file?hostfile=1&net=$net&client=TEST&version=" . urlencode(AGENT);
						$contents .= download_data($domain, $port, get_input($query, $domain), TRUE) . "\n";
					}
					if($contents) {
						$query = "$file?urlfile=1&net=$net&client=TEST&version=" . urlencode(AGENT);
						$contents .= download_data($domain, $port, get_input($query, $domain), TRUE);
					}
				}
				$contents = trim($contents);
				if($contents) { $error = FALSE; }
				// Validate the GWebCache output
				$lines = preg_split("/\r\n|\r|\n/", $contents, NULL, PREG_SPLIT_NO_EMPTY);
				foreach($lines as $line){
					$line = trim($line);
					@list($field1, $field2, $field3) = explode('|', $line);
					if(strtoupper($field1) == 'I') {
						if(strtolower($field2) == 'pong') {
							$test_client = substr(trim($field3), 0, 50);
						}
					} else if(strtoupper($field1) == 'H' && preg_match(IP_REGEX, $field2) && ctype_digit($field3) && $field3 <= MAX_HOST_AGE) {
					} else if(strtoupper($field1) == 'U' && preg_match(URL_REGEX, $field2) && ctype_digit($field3)) {
					} else if(strlen($field1) > 5 && substr_compare($field1, 'PONG ', 0, 5, TRUE) == 0) {
						$test_client = substr(trim(substr($field1, 5)), 0, 50);
					} else if(!$is_gwc2 && preg_match(IP_REGEX, $line)) {
					} else if(!$is_gwc2 && preg_match(URL_REGEX, $line)) {
					} else {
						$error = TRUE;
						break;
					}
				}
				if($test_client == '' || preg_match(BLOCK_REGEX, $test_client)) {
					$error = TRUE;
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
				@mkdir($dir, DIR_FLAGS, TRUE); // Create directory if it does not exist
			}
		}
		
		// Save URL files and ignore concurrency issues
		@file_put_contents($config['Path']['URL'], $output, LOCK_EX);
		
		// Output update notice (returns OK on untested URLs)
		if(isset($urls[$url][$net]) && $urls[$url][$net]['Status'] !== 'BAD') {
			echo $is_gwc2 ? "I|update|OK\n" : "OK\n";
		} else {
			echo $is_gwc2 ? "I|update|WARNING|Rejected URL\n" : "WARNING: Rejected URL\n";
		}
	}
	
	if($get || $hostfile || $urlfile || $gwcs) {
		// Output Hosts
		$count = 0;
		if($get || $hostfile) {
			$lines = file_exists($config['Path']['Host']) ? file($config['Path']['Host']) : array();
			foreach($lines as $line) {
				list($ip, $time, $client, $xnet) = explode('|', $line);
				$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
				$age = $now - $time;
				if($xnet == $net && $age < $config['Host']['Age'] && $age >= 0) {
					echo $is_gwc2 ? "H|$ip|$age\n" : "$ip\n";
					++$count;
					if($count >= $config['Host']['Output']) { break; }
				}
			}
			if(!$count && $is_gwc2) { echo "I|NO-HOSTS\n"; }
		}
		
		// Output URLs
		$count = 0;
		if($get || $urlfile || $gwcs) {
			if(file_exists($config['Path']['URL']) && $net != MUTE) {
				$lines = @file($config['Path']['URL']);
				shuffle($lines);
			} else {
				$lines = array();
			}
			foreach($lines as $line) {
				list($url, $time, $status, $ip, $xnet) = explode('|', $line);
				$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
				$url = get_url($url);
				$age = $time > 0 ? $now - $time : 0;
				if($url && $xnet == $net && $age < $config['URL']['Age'] && $age >= 0 && $status === 'OK') {
					echo $is_gwc2 ? "U|$url|$age\n" : "$url\n";
					++$count;
					if($count > $config['URL']['Output']) { break; }
				}
			}
			if(!$count && $is_gwc2) { echo "I|NO-URLS\n"; }
		}
	}
	
	// Tell client not to come back for another 30 minutes
	if(!empty($_GET) && $data == '' && $is_gwc2) {
		echo "I|access|period|1800\n";
	}