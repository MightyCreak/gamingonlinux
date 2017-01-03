<?php
echo "Itch importer started on " .date('d-m-Y H:m:s'). "\n";

define('path', '/home/gamingonlinux/public_html/includes/');
//define('path', '/mnt/storage/public_html/includes/');

include(path . 'config.php');

include(path . 'class_mysql.php');
$db = new mysql($database_host, $database_username, $database_password, $database_db);

include(path . 'class_core.php');
$core = new core();

include(path . 'class_mail.php');

$date = strtotime(gmdate("d-n-Y H:i:s"));
$url = 'https://itch.io/feed/new.xml';
if ($core->file_get_contents_curl($url) == true)
{
	// magic
}
else
{
	$to = 'liamdawe@gmail.com';
	$subject = 'GOL ERROR - Cannot reach Itch.io new games importer';
	// To send HTML mail, the Content-type header must be set
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	$headers .= "From: GOL Contact Us <noreply@gamingonlinux.com>\r\n";
	mail($to, $subject, "Could not reach the new itch games importer!", $headers);
	die('Itch XML not available!');
}

$get_url = $core->file_get_contents_curl($url);
$get_url = preg_replace("^&(?!#38;)^", "&amp;", $get_url);
$xml = simplexml_load_string($get_url);

$games_added = '';
$email = 0;
foreach ($xml->channel->item as $game)
{

	// for seeing what we have available
	/*
	echo '<pre>';
	print_r($game);
	echo '</pre>';*/

	if ($game->{'platforms'}->linux == 'yes')
	{
		$game->plainTitle = html_entity_decode($game->plainTitle, ENT_QUOTES);

		$name = preg_replace("/(™|®|©|&trade;|&reg;|&copy;|&#8482;|&#174;|&#169;)/", "", $game->plainTitle);

		$parsed_release_date = strtotime($game->pubDate);
		$released_date = date('Y-m-d', $parsed_release_date);

		echo $name . ' ' . $game->link . ' ' . $released_date . '<br />';

		$get_info = $db->sqlquery("SELECT `name`, `itch_link` FROM `calendar` WHERE `name` = ?", array($name));
		$grab_info = $get_info->fetch();

		$count_rows = $db->num_rows();

		// if it does exist, make sure it's not from itch already
		if ($count_rows == 0)
		{
			$db->sqlquery("INSERT INTO `calendar` SET `name` = ?, `itch_link` = ?, `date` = ?, `approved` = 1", array($name, $game->link, $released_date));

			$calendar_id = $db->grab_id();

			echo "\tAdded this game to the calendar DB with id: " . $calendar_id . ".\n";

			$games_added .= $name . '<br />';
		}

		// if we already have it, just update it
		else if ($count_rows == 1 && $grab_info['itch_link'] == NULL)
		{
			$db->sqlquery("UPDATE `calendar` SET `itch_link` = ? WHERE `name` = ?", array($game->link, $name));

			echo "Updated {$name} with the latest information<br />";
		}
	}

}

if (!empty($games_added))
{
  if (core::config('send_emails') == 1)
  {
    $mail = new mail('liamdawe@gmail.com', 'The itch new games importer has added new games', 'New games added to the <a href="https://www.gamingonlinux.com/index.php?module=calendar">calendar</a> from itch.io!<br />' . $games_added, '');
    $mail->send();
  }
}
echo "End of Itch.io import @ " . date('d-m-Y H:m:s');
