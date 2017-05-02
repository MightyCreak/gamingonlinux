<?php
session_start();
header('Content-Type: application/json');

$file_dir = dirname( dirname( dirname(__FILE__) ) );

$db_conf = include $file_dir . '/includes/config.php';

include($file_dir. '/includes/class_db_mysql.php');
$dbl = new db_mysql("mysql:host=".$db_conf['host'].";dbname=".$db_conf['database'],$db_conf['username'],$db_conf['password'],$db_conf['table_prefix']);

include($file_dir . '/includes/class_core.php');
$core = new core($dbl, $file_dir);

include($file_dir . '/includes/class_user.php');
$user = new user($dbl, $core);

if($_POST)
{
	// make sure the poll is open
	$checker = $dbl->run("SELECT `poll_open` FROM `polls` WHERE `poll_id` = ? AND `author_id` = ?", array($_POST['poll_id'], $_SESSION['user_id']))->fetchOne();
	if ($checker == 1 || $user->check_group([1]))
	{
			$dbl->run("UPDATE `polls` SET `poll_open` = 1 WHERE `poll_id` = ?", array($_POST['poll_id']));

			// find if they can vote or not to show the correct page
			$voted = $dbl->run("SELECT `user_id`, `option_id` FROM `poll_votes` WHERE `user_id` = ? AND `poll_id` = ?", array($_SESSION['user_id'], $_POST['poll_id']))->fetchOne();
			if ($voted)
			{
				echo json_encode(array("result" => 1));
				return;
			}
			else
			{
				echo json_encode(array("result" => 2));
				return;
			}

	}
	else
	{
		echo json_encode(array("result" => 3));
		return;
	}
}
?>
