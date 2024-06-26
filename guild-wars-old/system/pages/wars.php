<?php
defined('MYAAC') or die('Direct access not allowed!');

require PLUGINS . 'guild-wars-old/init.php';

$title = 'Guild Wars';

// wars actions
if(!empty($action)) {
	if(!ctype_alnum(str_replace(array('-', '_'), '', $action))) {
		error('Error: Action contains illegal characters.');
	}
	else if(file_exists(PAGES . 'wars/' . $action . '.php')) {
		require PAGES . 'wars/' . $action . '.php';
	}
	else {
		error('This page does not exists.');
	}

	return;
}

$warFrags = array();
foreach($db->query('SELECT * FROM `guildwar_kills` ORDER BY `time` DESC')->fetchAll() as $frag) {
	$warFrags[$frag['warid']][] = $frag;
}

$warsDb = $db->query('SELECT `guild_wars`.`id`, `guild_wars`.`guild1`, `guild_wars`.`guild2`, ' . $extraQuery . '`guild_wars`.`status`, (SELECT COUNT(1) FROM `guildwar_kills` WHERE `guildwar_kills`.`warid` = `guild_wars`.`id` AND `guildwar_kills`.`killerguild` = `guild_wars`.`guild1`) guild1_kills, (SELECT COUNT(1) FROM `guildwar_kills` WHERE `guildwar_kills`.`warid` = `guild_wars`.`id` AND `guildwar_kills`.`killerguild` = `guild_wars`.`guild2`) guild2_kills FROM `guild_wars` ORDER BY `' . $orderBy . '` DESC')->fetchAll();

displayGuildWars($warsDb, $warFrags);
