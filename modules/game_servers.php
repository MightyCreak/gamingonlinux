<?php
$templating->set_previous('title', 'Linux game servers', 1);
$templating->set_previous('meta_description', 'Linux game servers', 1);

$templating->load('game_servers');

$templating->block('head', 'game_servers');

$get_servers = $dbl->run("SELECT s.`id`, g.`name`, s.`connection_info`, s.`official` FROM `game_servers` s INNER JOIN `calendar` g ON g.id = s.game_id ORDER BY s.`id`, s.`official`")->fetch_all();

if ($get_servers)
{
	foreach ($get_servers as $servers)
	{
		$templating->block('server', 'game_servers');
		$templating->set('name', $servers['name']);
		
		$badge = '';
		if ($servers['official'] == 0)
		{
		$badge = '<span class="badge blue">Community Server</span>';
		}
		else if ($servers['official'] == 1)
		{
		$badge = '<span class="badge editor">Official GOL Server</span>';
		}
		$templating->set('badge', $badge);
		
		$templating->set('connection_info', $bbcode->parse_bbcode($servers['connection_info']));
	}
}
else
{
	$core->message("We aren't listing any servers at the moment, come back soon!");
}
