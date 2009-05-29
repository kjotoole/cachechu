<?php if(!isset($config)): die(); endif; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Cachechu Gnutella2 GWebCache</title>
	<link href="main.css" rel="stylesheet" type="text/css">
</head>

<body>
	<h1>
		<a href="http://code.google.com/p/cachechu/"
			title="Cachechu Projects Page">Cachechu Gnutella2 GWebCache</a>
	</h1>
	
	<?php if($config['Interface']['Info']): ?>
	<form method="get" action="<?php echo $_SERVER['PHP_SELF'] ?>">
		<div>
			<input type="hidden" name="net" value="gnutella2">
			<input type="hidden" name="ping" value="1">
			<input type="hidden" name="get" value="1">
			<input type="submit" value="Test Cache">
		</div>
	</form>

	<form method="get" action=".">
		<div>
			<input type="hidden" name="net" value="gnutella2">
			<input type="hidden" name="update" value="1">
			<label for="url">URL</label>
			<input type="text" name="url" id="url" size="50">
			<input type="submit" value="Update">
		</div>
	</form>
	
	<?php if(file_exists($config['Path']['Stats'])):
		$total_gets = 0;
		$total_updates = 0;
		$total_pings = 0;
		$total_requests = 0;
		$hour = 1;
		if(file_exists($config['Path']['Start'])) {
			$timestamp = @file_get_contents($config['Path']['Start']);
			$hour = ($now - $timestamp) / 60 / 60;
		}
		if($hour == 0) { $hour = 1; }
		$stats = array();
		$lines = file($config['Path']['Stats']);
		foreach($lines as $line) {
			@list($version, $gets, $updates, $pings, $requests) = explode('|', $line);
			$version = trim($version);
			if($version != '') {
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
	<table>
		<caption>Stats</caption>
		<thead>
			<tr>
				<th></th>
				<th>Requests</th>
				<th>Gets</th>
				<th>Updates</th>
				<th>Pings</th>
			</tr>
		</thead>
		<tbody>
			<tr class="odd">
				<th>Total:</th>
				<td><?php echo $total_requests; ?>
				<td><?php echo $total_gets; ?></td>
				<td><?php echo $total_updates; ?></td>
				<td><?php echo $total_pings; ?></td>
			</tr>
			<tr class="even">
				<th>Hourly:</th>
				<td><?php echo round($total_requests / $hour, 1); ?>
				<td><?php echo round($total_gets / $hour, 1); ?></td>
				<td><?php echo round($total_updates / $hour, 1); ?></td>
				<td><?php echo round($total_pings / $hour, 1); ?></td>
			</tr>
			<tr class="caption">
				<th colspan="5">Top <?php echo $config['Interface']['StatsLimit']; ?></th>
			</tr>
			<?php
				$count = 0;
				$requests = is_array($stats['Requests']) ? $stats['Requests'] : array();
				natsort($requests);
				$requests = array_reverse($requests, TRUE);
				$requests = array_slice($requests, 0, $config['Interface']['StatsLimit'], TRUE);
				foreach($requests as $vendor => $reqs) {
					$gets = isset($stats['Gets'][$vendor]) ? $stats['Gets'][$vendor] : 0;
					$updates = isset($stats['Updates'][$vendor]) ? $stats['Updates'][$vendor] : 0;
					$pings = isset($stats['Pings'][$vendor]) ? $stats['Pings'][$vendor] : 0;
					echo '<tr class="';
					echo $count % 2 == 0 ? 'odd' : 'even';
					echo '"><th>', substr(htmlentities($vendor), 0, 50), '</th>';
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
	<?php endif; ?>
	<?php endif; ?>

	<div id="footer">
		Cachechu <?php echo VERSION; ?> under <a href="http://www.gnu.org/licenses/gpl-3.0.html">GPLv3</a>
	</div>
</body>
</html>