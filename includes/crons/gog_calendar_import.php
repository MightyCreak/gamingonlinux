<?php
error_reporting(-1);

echo "GOG calendar importer started on " .date('d-m-Y H:m:s'). "\n";

define('path', '/home/gamingonlinux/public_html/includes/');
//define('path', '/mnt/storage/public_html/includes/');

include(path . 'config.php');

include(path . 'class_mysql.php');
$db = new mysql($database_host, $database_username, $database_password, $database_db);

include(path . 'class_core.php');
$core = new core();

include(path . 'class_mail.php');

$date = strtotime(gmdate("d-n-Y H:i:s"));

$url = 'http://www.gog.com/games/feed?format=json&page=1';
if ($core->file_get_contents_curl($url) == true)
{
	// magic
}
else
{
	$to = 'liamdawe@gmail.com';
	$subject = 'GOL ERROR - Cannot reach GOG calendar importer';

	// To send HTML mail, the Content-type header must be set
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	$headers .= "From: GOL Contact Us <noreply@gamingonlinux.com>\r\n";

	mail($to, $subject, "Could not reach the calendar importer!", $headers);
	die('GOG XML not available!');
}

$games_added = '';
$email = 0;

$urlMask = 'http://www.gog.com/games/feed?format=json&page=%d';

$page = 0;
do {
	$url = sprintf($urlMask, ++$page);
	$array = json_decode($core->file_get_contents_curl($url), true);
	$count = count($array['games']);
	printf("Page #%d: %d product(s)\n", $page, $count);

	foreach ($array['games'] as $games)
	{
		if ($games['linux_compatible'] == 1)
		{
			$website = $games['short_link'];

			echo $games['title'] . "<br />\n";
			echo "* Original release date: ". $games['original_release_date'] ."<br />\n";

			$db->sqlquery("SELECT `name`, `gog_link` FROM `calendar` WHERE `name` = ?", array($games['title']));
			$grab_info = $db->fetch();

			$check_rows = $db->num_rows();

			// if it does exist, make sure it's not from GOG already
			if ($check_rows == 0)
			{
				$db->sqlquery("INSERT INTO `calendar` SET `name` = ?, `gog_link` = ?, `date` = ?, `approved` = 1", array($games['title'], $games['short_link'], $games['original_release_date']));

				$calendar_id = $db->grab_id();

				echo "\tAdded this game to the calendar DB with id: " . $calendar_id . ".\n";

				$games_added .= $games['title'] . '<br />';
			}

			// if we already have it, just update it
			else if ($check_rows == 1 && $grab_info['gog_link'] == NULL)
			{
				$db->sqlquery("UPDATE `calendar` SET `gog_link` = ? WHERE `name` = ?", array($games['short_link'], $games['title']));

				echo "Updated {$games['title']} with the latest information<br />";
			}
		}
	}
} while ($count > 0);


echo "\n\n";//More whitespace, just to make the output look a bit more pretty

if (!empty($games_added))
{
  if (core::config('send_emails') == 1)
  {
    $mail = new mail('liamdawe@gmail.com', 'The Steam calendar importer has added new games', 'New games added to the <a href="https://www.gamingonlinux.com/index.php?module=calendar">calendar!</a><br />' . $games_added, '');
    $mail->send();
  }
}

echo "End of GOG import @ " . date('d-m-Y H:m:s') . ".\nHave a nice day.\n";
