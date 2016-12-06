<?php
$templating->set_previous('title', 'Notification Preferences' . $templating->get('title', 1)  , 1);
$templating->merge('usercp_modules/notification_preferences');

if (!isset($_GET['go']))
{
	if (isset($_GET['message']))
	{
		if ($_GET['message'] == 'updated')
		{
			$core->message('Notifications preferences updated!');
		}
	}

	$templating->block('main', 'usercp_modules/notification_preferences');

	$comments_check = '';
	if ($_SESSION['display_comment_alerts'] == 1)
	{
		$comments_check = 'checked';
	}
	$templating->set('comments_check', $comments_check);

	$db->sqlquery("SELECT `auto_subscribe`, `auto_subscribe_email`, `email_on_pm`, `auto_subscribe_new_article`, `email_options`, `login_emails` FROM `users` WHERE `user_id` = ?", array($_SESSION['user_id']));
	$usercpcp = $db->fetch();

	// auto subscribe to replies
	$subscribe_check = '';
	if ($usercpcp['auto_subscribe'] == 1)
	{
		$subscribe_check = 'checked';
	}
	$templating->set('subscribe_check', $subscribe_check);

	// auto subscribe when creating an article
	$subscribe_article_check = '';
	if ($usercpcp['auto_subscribe_new_article'] == 1)
	{
		$subscribe_article_check = 'checked';
	}
	$templating->set('subscribe_article_check', $subscribe_article_check);

	// get emails about replies when subscribed
	$subscribe_email_check = '';
	if ($usercpcp['auto_subscribe_email'] == 1)
	{
		$subscribe_email_check = 'checked';
	}
	$templating->set('subscribe_email_check', $subscribe_email_check);

	// get an email when you get a PM
	$email_pm = '';
	if ($usercpcp['email_on_pm'] == 1)
	{
		$email_pm = 'checked';
	}
	$templating->set('email_on_pm', $email_pm);

	// the type of emails they will get for replies, either one until the check again, or all
	$all_check = '';
	if ($usercpcp['email_options'] == 1)
	{
		$all_check = 'selected';
	}

	$one_check = '';
	if ($usercpcp['email_options'] == 2)
	{
		$one_check = 'selected';
	}

	$email_options = '<option value="1" '. $all_check .'>All - Get all replies to your email</option><option value="2" ' . $one_check . '>New reply only - Get the first new reply, then none until you visit the article/forum post or reply again.</option>';
	$templating->set('email_options', $email_options);

	// security emails
	$email_login = '';
	if ($usercpcp['login_emails'] == 1)
	{
		$email_login = 'checked';
	}

	$templating->set('email_on_login', $email_login);
}

else if (isset($_GET['go']))
{
	if ($_GET['go'] == 'update')
	{
		// activate the notification area for comments
		$comment_alerts = 0;
		if (isset($_POST['comments']))
		{
			$comment_alerts = 1;
		}

		$auto_subscribe = 0;
		$subscribe_article = 0;
		$subscribe_emails = 0;
		$email_on_pm = 0;
		$email_on_login = 0;

		// if they auto-subscribe for replies
		if (isset($_POST['auto_subscribe']))
		{
			$auto_subscribe = 1;
		}

		// if they auto subscribe for created articles
		if (isset($_POST['subscribe_article']))
		{
			$subscribe_article = 1;
		}

		if (isset($_POST['emails']))
		{
			$subscribe_emails = 1;
		}

		if (isset($_POST['emailpm']))
		{
			$email_on_pm = 1;
		}

		if (isset($_POST['emaillogin']))
		{
			$email_on_login = 1;
		}

		$db->sqlquery("UPDATE `users` SET
			`display_comment_alerts` = ?,
			`auto_subscribe` = ?,
			`auto_subscribe_new_article` = ?,
			`auto_subscribe_email` = ?,
			`email_options` = ?,
			`email_on_pm` = ?,
			`login_emails` = ?
			WHERE
			`user_id` = ?",
			array($comment_alerts,
			$auto_subscribe,
			$subscribe_article,
			$subscribe_emails,
			$_POST['email_options'],
			$email_on_pm,
			$email_on_login,
			$_SESSION['user_id']));

		$_SESSION['display_comment_alerts'] = $comment_alerts;

		header("Location: /usercp.php?module=notification_preferences&message=updated");
	}
}
?>
