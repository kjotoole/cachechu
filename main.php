<?php
	if(!isset($config)): die(); endif;
	
	define('UPDATE_DOMAIN', 'cachechu.googlecode.com');
	define('UPDATE_PATH', '/svn/VERSION');	
	define('GEOIP_PATH', 'geoip/geoip.php');
	define('GEOIP_DATA_PATH', 'geoip/GeoIP.dat');
	
	$config['Path']['Update'] = isset($config['Path']['Update']) ? $config['Path']['Update'] : 'data/update.dat';
	$config['Interface']['Info'] = isset($config['Interface']['Info']) ? $config['Interface']['Info'] : 1;
	$config['Interface']['StatsLimit'] = isset($config['Interface']['StatsLimit']) ? $config['Interface']['StatsLimit'] : 10;
	$config['Interface']['Update'] = isset($config['Interface']['Update']) ? $config['Interface']['Update'] : 1;
	
	function get_flag($ip) {
		global $geoip;
		$html = '';
		if($geoip) {
			$code = geoip_country_code_by_addr($geoip, $ip);
			$path = 'flags/' . strtolower($code) . '.png';
			if($code && file_exists($path)) {
				$country = geoip_country_name_by_addr($geoip, $ip);
				$html = '<img src="' . $path . '" width="16" height="11" alt="' . $code . '" title="' . $country . '">';
			}
		}
		return $html;
	}
	
	$geoip = FALSE;
	if(file_exists(GEOIP_PATH) && file_exists(GEOIP_DATA_PATH) && file_exists('flags')) {
		require(GEOIP_PATH);
		if(function_exists('geoip_open') && function_exists('geoip_country_code_by_addr') &&
				function_exists('geoip_country_name_by_addr')) {
			$geoip = geoip_open(GEOIP_DATA_PATH, GEOIP_STANDARD);
		}
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="language" content="en">
	<meta name="description" content="Cachechu is a GWebCache licensed under the GPL.">
	<meta name="keywords" content="Gnutella2">
	<title>Cachechu GWebCache</title>
	<link href="main.css" rel="stylesheet" type="text/css">
</head>

<body>
	<h1>
		<a href="http://code.google.com/p/cachechu/"
			title="Cachechu Projects Page">Cachechu GWebCache</a>
	</h1>
	
  <?php
		// Do not show update notification for test versions
		if(substr(VERSION, 0, 1) != 'R' && $config['Interface']['Update']):
			$config['Path']['Update'] = isset($config['Path']['Update']) ? $config['Path']['Update'] : 'data/update.dat';
			if(file_exists($config['Path']['Update'])) {
				$age = $now - @filemtime($config['Path']['Update']);
			} else {
				$dir = dirname($config['Path']['Update']);
				if(!file_exists($dir)) {
					@mkdir($dir, DIR_FLAGS, TRUE); // Create directory if it does not exist
				}
				$age = $config['URL']['Age'];
			}
			if($age >= $config['URL']['Age']) {
				$domain = UPDATE_DOMAIN;
				$contents = trim(download_data(UPDATE_DOMAIN, 80, get_input(UPDATE_PATH, UPDATE_DOMAIN), TRUE));
				if($contents != '') { @file_put_contents($config['Path']['Update'], $contents); }
			}
			if(file_exists($config['Path']['Update'])):
				$latest = substr(trim(@file_get_contents($config['Path']['Update'])), 0, 5);
				if($latest != '' && $latest != VERSION): ?>
					<div class="update">
						<a href="http://code.google.com/p/cachechu/downloads/list">
							GWebCache is out of date. Update to Cachechu <?php echo $latest; ?>.
						</a>
					</div>
				<?php
				endif;
			endif;
		endif;
  ?>
	
	<?php if($config['Interface']['Info']): ?>
	<?php date_default_timezone_set('UTC'); ?>
	<ul id="navigation">
		<?php if($config['Stats']['Enable']): ?>
		<li><?php if($page == 'stats'): ?>Stats<?php else: ?><a href="?page=stats">Stats</a><?php endif; ?></li>
		<?php endif; ?>
		<li><?php if($page == 'hosts'): ?>Hosts<?php else: ?><a href="?page=hosts">Hosts</a><?php endif; ?></li>
		<li><?php if($page == 'services'): ?>Services<?php else: ?><a href="?page=services">Services</a><?php endif; ?></li>
	</ul>

	<?php if($page == 'stats' && $config['Stats']['Enable']): ?>
	<div id="main">
	<?php foreach($config['Network']['Support'] as $network): ?>
	<?php
		$times = array();
		$path = str_replace(NET_REPLACE, $network, $config['Path']['Start']); 
		$lines = file_exists($path) ? @file($path) : array();
		foreach($lines as $line) {
			@list($timestamp, $xnet) = explode('|', $line);
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			if(is_numeric($timestamp)) { $times[$xnet] = $timestamp; }
		}
		$path = str_replace(NET_REPLACE, $network, $config['Path']['Stats']);	
		$lines = file_exists($path) ? @file($path) : array();
		$hour = 1;
		if(isset($times[$network])) {
			$timestamp = $times[$network];
			$hour = ($now - $timestamp) / 60 / 60;
		} else {
			$timestamp = $now;
		}
		if($hour == 0) { $hour = 1; }
		$total_gets = 0;
		$total_updates = 0;
		$total_pings = 0;
		$total_requests = 0;
		$stats = array();
		foreach($lines as $line) {
			@list($version, $gets, $updates, $pings, $requests, $xnet) = explode('|', $line);
			$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
			$version = trim($version);
			if($xnet == $network && $version != '') {
				$stats["Gets"][$version] = $gets;
				$stats["Updates"][$version] = $updates;
				$stats["Pings"][$version] = $pings;
				$stats["Requests"][$version] = $requests;
				$total_gets += $gets;
				$total_updates += $updates;
				$total_pings += $pings;
				$total_requests += $requests;
			}
		}
	?>
	<h2><?php echo ucwords($network); ?> Stats</h2>
	<table summary="Cache stats for <?php echo ucwords($network); ?>" class="stats">
		<colgroup>
			<col>
			<col class="stats">
			<col class="stats">
			<col class="stats">
			<col class="stats">
		</colgroup>
		<thead>
			<tr>
				<td></td>
				<th scope="col">Requests</th>
				<th scope="col">Gets</th>
				<th scope="col">Updates</th>
				<th scope="col">Pings</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="5">
					Start Date:
					<?php echo date('Y-m-d H:i', $timestamp); ?>Z
				</td>
			</tr>
		</tfoot>
		<tbody>
			<tr class="odd">
				<th scope="row">Total:</th>
				<td><?php echo number_format($total_requests); ?>
				<td><?php echo number_format($total_gets); ?></td>
				<td><?php echo number_format($total_updates); ?></td>
				<td><?php echo number_format($total_pings); ?></td>
			</tr>
			<tr class="even">
				<th scope="row">Hourly:</th>
				<td><?php echo number_format($total_requests / $hour, 1); ?>
				<td><?php echo number_format($total_gets / $hour, 1); ?></td>
				<td><?php echo number_format($total_updates / $hour, 1); ?></td>
				<td><?php echo number_format($total_pings / $hour, 1); ?></td>
			</tr>
			<tr class="caption">
				<th colspan="5">Top <?php echo $config['Interface']['StatsLimit']; ?></th>
			</tr>
			<?php
				$count = 0;
				$requests = isset($stats['Requests']) ? $stats['Requests'] : array();
				arsort($requests, SORT_NUMERIC);
				$requests = array_slice($requests, 0, $config['Interface']['StatsLimit'], TRUE);
				foreach($requests as $vendor => $reqs) {
					$reqs = number_format($reqs);
					$gets = isset($stats['Gets'][$vendor]) ? number_format($stats['Gets'][$vendor]) : 0;
					$updates = isset($stats['Updates'][$vendor]) ? number_format($stats['Updates'][$vendor]) : 0;
					$pings = isset($stats['Pings'][$vendor]) ? number_format($stats['Pings'][$vendor]) : 0;
					echo '<tr class="';
					echo $count % 2 == 0 ? 'odd' : 'even';
					echo '"><th scope="row">', substr(htmlentities($vendor), 0, 50), '</th>';
					echo "<td>$reqs</td>";
					echo "<td>$gets</td>";
					echo "<td>$updates</td>";
					echo "<td>$pings</td>";
					echo "</tr>";
					++$count;
				}
			?>
		</tbody>
	</table>
	<?php endforeach; ?>
	</div>
	<?php elseif($page == 'hosts'): ?>
	<div id="main">
	<?php foreach($config['Network']['Support'] as $network): ?>
	<h2><?php echo ucwords($network); ?> Hosts</h2>
	<table summary="Current <?php echo ucwords($network); ?> hosts in cache">
		<?php if($geoip): ?><col class="flags"><?php endif; ?>
		<col class="ips">
		<col class="ports">
		<col class="clients">
		<col class="timestamps">
		<col class="ages">
		<thead>
			<tr>
				<?php if($geoip): ?><th scope="col"></th><?php endif; ?>
				<th scope="col">IP</th>
				<th scope="col">Port</th>
				<th scope="col">Client</th>
				<th scope="col">Timestamp</th>
				<th scope="col">Age (<abbr title="seconds">s</abbr>)</th>
			</tr>
		</thead>
		<tbody>
			<?php
				$count = 0;
				$path = str_replace(NET_REPLACE, $network, $config['Path']['Host']);
				$lines = file_exists($path) ? @file($path) : array();
				foreach($lines as $line) {
						@list($host, $time, $client, $xnet) = explode('|', $line);
						$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
						$age = $now - $time;
						@list($ip, $port) = explode(':', $host);
						if($xnet == $network && $age < $config['Host']['Age'] && $age >= 0) {
	 						echo '<tr class="';
							echo $count % 2 == 0 ? 'odd' : 'even';
							echo '">';
							echo $geoip ? '<td>' . get_flag($ip) . '</td>' : '';
							echo '<td>', htmlentities($ip), '</td>';
							echo '<td>', htmlentities($port), '</td>';
							echo '<td>', htmlentities($client), '</td>';
							echo '<td>', date('Y-m-d H:i', $time), 'Z</td>';
							echo '<td>', $age, '</td>';
							echo '</tr>';
							++$count;
						}
				}
				if(!$count) {
					echo '<tr class="empty"><td colspan="', $geoip ? 6 : 5, '">There are no ', ucwords($network), ' hosts.</td></tr>';
				}
			?>
		</tbody>
	</table>
	<?php endforeach; ?>
	</div>
	<?php elseif($page == 'services'): ?>
	<div id="main">
	<?php foreach($config['Network']['Support'] as $network): ?>
	<h2><?php echo ucwords($network); ?> Services</h2>
	<table summary="Current <?php echo ucwords($network); ?> services in cache">
		<?php if($geoip): ?><col class="flags"><?php endif; ?>
		<col class="urls">
		<col class="ips">
		<col class="clients">
		<col class="timestamps">
		<thead>
			<tr>
				<?php if($geoip): ?><th scope="col"></th><?php endif; ?>
				<th scope="col">URL</th>
				<th scope="col">IP</th>
				<th scope="col">Client</th>
				<th scope="col">Timestamp</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="<?php echo $geoip ? 5 : 4 ?>">
					<form method="get" action=".">
						<div>
							<?php if(!($network == OLD_NET)): ?>
							<input type="hidden" name="net" value="<?php echo $network; ?>">
							<input type="hidden" name="update" value="1">
							<?php endif; ?>
							<input type="hidden" name="client" value="CACH">
							<input type="hidden" name="client" value="ECHU">
							<label for="<?php echo $network; ?>url"><?php echo ucwords($network); ?> URL:</label>
							<input type="text" name="url" id="<?php echo $network; ?>url" size="50">
							<input type="submit" value="Add">
						</div>
					</form>
				</td>
			</tr>
		</tfoot>
		<tbody>
			<?php
				$count = 0;
				$path = str_replace(NET_REPLACE, $network, $config['Path']['URL']);
				$lines = file_exists($path) ? @file($path) : array();
				sort($lines);
				foreach($lines as $line) {
					@list($url, $time, $status, $ip, $xnet, $client) = explode('|', $line);
					$xnet = trim($xnet) == '' ? DEFAULT_NET : $xnet;
					$url = get_url($url);
					$url = preg_match(URL_REGEX, $url, $match) ? $url : '';
					if($time == 0) { $time = $now; }
					$age = $now - $time;
					if($url && $xnet == $network && $age < $config['URL']['Age'] && $age >= 0 && $status === 'OK') {
						echo '<tr class="';
						echo $count % 2 == 0 ? 'odd' : 'even';
						echo '">';
						echo $geoip ? '<td>' . get_flag($ip) . '</td>' : '';
						echo '<td><a href="', htmlentities($url), '" rel="nofollow">', htmlentities($match['domain']), '</a></td>';
						echo '<td>', htmlentities(trim($ip) == '' ? 'Unknown' : $ip), '</td>';
						echo '<td>', htmlentities(trim($client) == '' ? 'Unknown' : $client), '</td>';
						echo '<td>', date('Y-m-d H:i', $time > 0 ? $time : $now), 'Z</td>';
						echo '</tr>';
						++$count;
					}
				}
				if(!$count) {
					echo '<tr class="empty"><td colspan="', $geoip ? 5 : 4,'">There are no ', ucwords($network), ' services.</td></tr>';
				}
			?>
		</tbody>
	</table>
	<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php endif; ?>
	
	<div id="footer">
		Cachechu <?php echo VERSION; ?> under <a href="http://www.gnu.org/licenses/gpl-3.0.html">GPLv3</a>
	</div>
</body>
</html>