<?php
// check it hasn't been accepted already
$db->sqlquery("SELECT a.tagline_image, a.`active`, a.`date_submitted`, a.`guest_username`, a.`guest_email`, u.`username`, u.`email` FROM `articles` a LEFT JOIN `".$dbl->table_prefix."users` u ON a.author_id = u.user_id WHERE `article_id` = ?", array($_POST['article_id']));
$check_article = $db->fetch();
if ($check_article['active'] == 1)
{
	header("Location: /admin.php?module=articles&view=Submitted&error=alreadyapproved");
}

else
{
	$return_page = "admin.php?module=articles&view=Submitted&aid={$_POST['article_id']}";
	if ($checked = $article_class->check_article_inputs($return_page))
	{
		// show in the editors pick block section
		$block = 0;
		if (isset($_POST['show_block']))
		{
			$block = 1;
		}

		// if the editor is submitting it as themselves, thank the submitter automatically
		$author_id = $_POST['author_id'];
		$submission_date = $check_article['date_submitted'];

		if (isset($_POST['submit_as_self']))
		{
			$author_id = $_SESSION['user_id'];
			$submission_date = '';

			if (!empty($check_article['username']))
			{
				$submitted_by_user = $check_article['username'];
			}

			else if (!empty($check_article['guest_username']))
			{
				$submitted_by_user = $check_article['guest_username'];
			}

			else
			{
				$submitted_by_user = "a guest submitter";
			}

			$checked['text'] = $checked['text'] . "\r\n\r\n[i]Thanks to " . $submitted_by_user . ' for letting us know![/i]';
		}
		$db->sqlquery("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `data` = ? AND `type` = ?", array(core::$date, $_POST['article_id'], 'submitted_article'));

		$db->sqlquery("INSERT INTO `admin_notifications` SET `user_id` = ?, `completed` = 1, `type` = ?, `created_date` = ?, `completed_date` = ?, `data` = ?", array($_SESSION['user_id'], 'approve_submitted_article', core::$date, core::$date, $_POST['article_id']));

		// remove all the comments made by admins
		$db->sqlquery("DELETE FROM `articles_comments` WHERE `article_id` = ?", array($_POST['article_id']));

		$article_class->gallery_tagline($checked);

		$db->sqlquery("UPDATE `articles` SET `author_id` = ?, `title` = ?, `slug` = ?, `tagline` = ?, `text`= ?, `show_in_menu` = ?, `active` = 1, `date` = ?, `date_submitted` = ?, `submitted_unapproved` = 0, `locked` = 0 WHERE `article_id` = ?", array($author_id, $checked['title'], $checked['slug'], $checked['tagline'], $checked['text'], $block, core::$date, $submission_date, $_POST['article_id']));

		if (isset($_SESSION['uploads']))
		{
			foreach($_SESSION['uploads'] as $key)
			{
				$db->sqlquery("UPDATE `article_images` SET `article_id` = ? WHERE `filename` = ?", array($_POST['article_id'], $key['image_name']));
			}
		}

		// since they are approving and not neccisarily editing, check if the text matches, if it doesnt they have edited it
		if ($_SESSION['original_text'] != $checked['text'])
		{
			$db->sqlquery("INSERT INTO `article_history` SET `article_id` = ?, `user_id` = ?, `date` = ?, `text` = ?", array($_POST['article_id'], $_SESSION['user_id'], core::$date, $_SESSION['original_text']));
		}

		article_class::process_categories($_POST['article_id']);

		plugins::do_hooks('article_database_entry', $_POST['article_id']);

		if (isset($_SESSION['uploads_tagline']) && $_SESSION['uploads_tagline']['image_rand'] == $_SESSION['image_rand'])
		{
			$core->move_temp_image($_POST['article_id'], $_SESSION['uploads_tagline']['image_name']);
		}

		unset($_SESSION['atitle']);
		unset($_SESSION['atagline']);
		unset($_SESSION['atext']);
		unset($_SESSION['aslug']);
		unset($_SESSION['acategories']);
		unset($_SESSION['aactive']);
		unset($_SESSION['uploads']);
		unset($_SESSION['uploads_tagline']);
		unset($_SESSION['image_rand']);
		unset($_SESSION['original_text']);
		unset($_SESSION['gallery_tagline_id']);
		unset($_SESSION['gallery_tagline_rand']);
		unset($_SESSION['gallery_tagline_filename']);

		// pick the email to use
		$email = '';
		if (!empty($check_article['guest_email']))
		{
			$email = $check_article['guest_email'];
		}

		else if (!empty($check_article['email']))
		{
			$email = $check_article['email'];
		}
		
		$article_link = article_class::get_link($_POST['article_id'], $checked['slug']);

		// subject
		$subject = 'Your article was approved and published on ' . core::config('site_title');
		
		$html_message = '<p>We have accepted your article titled "<a href="'.$article_link.'">'.$checked['title'].'</a>" on <a href="'.core::config('website_url').'" target="_blank">'.core::config('site_title').'</a>. Thank you for taking the time to send us news we really appreciate the help, you are awesome!</p>';

		// message
		$plain_message = 'We have accepted your article titled "'.$checked['title'].'" on '.core::config('site_title').', you can see it here: '.$article_link;
		
		if (core::config('send_emails') == 1)
		{
			$mail = new mail($email, $subject, $html_message, $plain_message);
			$mail->send();
		}

		include(core::config('path') . 'includes/telegram_poster.php');

		telegram($checked['title'] . ' ' . $article_link);

		if (!isset($_POST['show_block']))
		{
			header("Location: " . core::config('website_url') . "admin.php?module=articles&view=Submitted&accepted");
		}
		else
		{
			header("Location: ". core::config('website_url') . "admin.php?module=featured&view=add&article_id={$_POST['article_id']}");
		}
	}
}
