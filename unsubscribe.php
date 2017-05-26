<?php
define("APP_ROOT", dirname(__FILE__));

include(APP_ROOT . '/includes/header.php');

$user_id = '';
if (isset($_GET['user_id']))
{
	$user_id = $_GET['user_id'];
}
$email = '';
if (isset($_GET['email']))
{
	$email = $_GET['email'];
}
$article_id = '';
if (isset($_GET['article_id']))
{
	$article_id = $_GET['article_id'];
}

$empty_check = core::mempty(compact('user_id', 'email', 'article_id'));
if($empty_check !== true)
{
	$_SESSION['message'] = 'cannotunsubscribe';
	header("Location: home/");
	die();
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id']) && isset($_GET['email']) && isset($_GET['secret_key']))
{
	$db->sqlquery("SELECT `email` FROM `users` WHERE `user_id` = ? AND `email` = ?", array($_GET['user_id'], $_GET['email']));
	if ($db->num_rows() == 1)
	{
		if (isset($_GET['article_id']) && is_numeric($_GET['article_id']))
		{
			// check secret key
			$db->sqlquery("SELECT `secret_key` FROM `articles_subscriptions` WHERE `user_id` = ? AND `article_id` = ? AND `secret_key` = ?", array($_GET['user_id'], $_GET['article_id'], $_GET['secret_key']));
			$check_exists = $db->num_rows();
			
			if ($check_exists == 1)
			{
				$db->sqlquery("DELETE FROM `articles_subscriptions` WHERE `user_id` = ? AND `article_id` = ?", array($_GET['user_id'], $_GET['article_id']));
				$_SESSION['message'] = 'unsubscribed';
				header("Location: home/");
				die();
			}
			else
			{
				$_SESSION['message'] = 'cannotunsubscribe';
				header("Location: home/");
				die();
			}
		}

		else if (isset($_GET['topic_id']) && is_numeric($_GET['topic_id']))
		{
			// check secret key
			$db->sqlquery("SELECT `secret_key` FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ? AND `secret_key` = ?", array($_GET['user_id'], $_GET['topic_id'], $_GET['secret_key']));
			$check_exists = $db->num_rows();
			
			if ($check_exists == 1)
			{
				$db->sqlquery("DELETE FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_GET['user_id'], $_GET['topic_id']));
				$_SESSION['message'] = 'unsubscribed';
				header("Location: home/");
				die();
			}
			else
			{
				$_SESSION['message'] = 'cannotunsubscribe';
				header("Location: home/");
				die();
			}
		}
	}

	else
	{
		$_SESSION['message'] = 'cannotunsubscribe';
		header("Location: home/");
		die();
	}
}
else
{
	$_SESSION['message'] = 'cannotunsubscribe';
	header("Location: home/");
	die();
}

?>
