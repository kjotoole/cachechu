<?php if(!isset($config)): die(); endif; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Cachechu Gnutella2 GWebCache</title>
	<style type="text/css">
	body,table,h1,form{margin:0;padding:0;font-family:Tahoma,Arial,sans-serif;}
	body{background:white;color:#555753;text-align:center;}
	a{color:#3465a4;}
	a:hover{color:#c00;}
	h1{font-size:1.5em;padding:0.75em 0;}
	form{padding-bottom:1em;}
	table{border-collapse:collapse;margin:0 auto;border:1px solid #2e3436;}
	thead, tr.caption{background:#2e3436;color:white;}
	tr.caption th{text-align:left;}
	tbody th{text-align:right;}
	th, td{text-align:left;padding:0.25em 0.75em;}
	#footer {background:#2e3436;padding:0.3em 0.5em;margin-top:1em;text-align:center;}
	caption{display:none;}
	#footer,#footer a{color: white;}
	.even{background:#eeeeec;}
	</style>
</head>

<body>
	<h1>
		<a href="http://code.google.com/p/cachechu/"
			title="Cachechu Projects Page">Cachechu Gnutella2 GWebCache</a>
	</h1>

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
		$stats = @parse_ini_file($config['Path']['Stats'], TRUE);
		$gets = isset($stats['Get']) ? array_sum($stats['Get']) : 0;
		$updates = isset($stats['Update']) ? array_sum($stats['Update']) : 0;
		$pings = isset($stats['Ping']) ? array_sum($stats['Ping']) : 0;
		$hour = isset($stats['Time']['Start']) ? ($now - $stats['Time']['Start']) / 60 / 60 : 1;
		if($hour == 0) { $hour = 1; }
	?>
	<table>
		<caption>Stats</caption>
		<thead>
			<tr>
				<th></th>
				<th>Get</th>
				<th>Update</th>
				<th>Ping</th>
			</tr>
		</thead>
		<tbody>
			<tr class="odd">
				<th>Total:</th>
				<td><?php echo $gets; ?></td>
				<td><?php echo $updates; ?></td>
				<td><?php echo $pings; ?></td>
			</tr>
			<tr class="even">
				<th>Hourly:</th>
				<td><?php echo round($gets / $hour, 1); ?></td>
				<td><?php echo round($updates / $hour, 1); ?></td>
				<td><?php echo round($pings / $hour, 1); ?></td>
			</tr>
			<tr class="caption">
				<th colspan="4">Top 10</th>
			</tr>
			<?php
				$count = 0;
				$gets = is_array($stats['Get']) ? $stats['Get'] : array();
				natsort($gets);
				$gets = array_reverse($gets, TRUE);
				$gets = array_slice($gets, 0, 10, TRUE);
				foreach($gets as $vendor => $get_count) {
					$update_count = isset($stats['Update'][$vendor]) ? $stats['Update'][$vendor] : 0;
					$ping_count = isset($stats['Ping'][$vendor]) ? $stats['Ping'][$vendor] : 0;
					echo '<tr class="';
					echo $count % 2 == 0 ? 'odd' : 'even';
					echo '"><th>', htmlentities($vendor), '</th>';
					echo "<td>$get_count</td>";
					echo "<td>$update_count</td>";
					echo "<td>$ping_count</td>";
					echo "</tr>";
					++$count;
				}
			?>
		</tbody>
	</table>
	<?php endif; ?>

	<div id="footer">
		Cachechu <?php echo VERSION; ?> under <a href="http://www.gnu.org/licenses/gpl-3.0.html">GPLv3</a> 
	</div>
</body>
</html>