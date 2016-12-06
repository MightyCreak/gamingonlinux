<?php
$templating->set_previous('title', 'Notifications manager' . $templating->get('title', 1)  , 1);
$templating->merge('usercp_modules/notifications');

if (!isset($_GET['go']))
{
	// paging for pagination
	if (!isset($_GET['page']))
	{
		$page = 1;
	}

	else if (is_numeric($_GET['page']))
	{
		$page = $_GET['page'];
	}

	if (isset($_GET['message']))
	{
		if ($_GET['message'] == 'all_clear')
		{
			$core->message('All notifications cleared!');
		}
	}

	$templating->block('top', 'usercp_modules/notifications');

	$clear_all_link = '';
	$pagination = '';

	if ($_SESSION['display_comment_alerts'] == 1)
	{
		// count how many there is in total
		$db->sqlquery("SELECT `id` FROM `user_notifications` WHERE `owner_id` = ?", array($_SESSION['user_id']));
		$total_notifications = $db->num_rows();

		if ($total_notifications > 0)
		{
			// show the notifications here
			$db->sqlquery("SELECT n.`id`, n.`date`, n.`article_id`, n.`comment_id`, n.`seen`, n.is_like, n.total, u.user_id, u.username, u.avatar_gravatar, u.gravatar_email, u.avatar_gallery, u.avatar, u.avatar_uploaded, a.title FROM `user_notifications` n LEFT JOIN `users` u ON u.user_id = n.notifier_id LEFT JOIN `articles` a ON n.article_id = a.article_id WHERE n.`owner_id` = ? ORDER BY n.seen, n.date DESC", array($_SESSION['user_id']));

			while ($note_list = $db->fetch())
			{
				if ($note_list['is_like'] == 0)
				{
					$templating->block('row', 'usercp_modules/notifications');
				}
				else
				{
					$templating->block('liked_row', 'usercp_modules/notifications');
					if ($note_list['total'] > 1)
					{
						$total = $note_list['total'] - 1;
						$additional_likes = ' and ' . $total . ' others';
					}
					else if ($note_list['total'] == 1)
					{
						$additional_likes = '';
					}
					$templating->set('additional_likes', $additional_likes);
				}

				if ($note_list['seen'] == 0)
				{
					$icon = 'envelope';
				}
				else if ($note_list['seen'] == 1)
				{
					$icon = 'envelope-open';
				}

				if (core::config('pretty_urls') == 1)
				{
					$profile_link = '/profiles/' . $grab_notes['user_id'];
				}
				else
				{
					$profile_link = '/index.php?module=profile&user_id=' . $note_list['user_id'];
				}

				if (!empty($note_list['username']))
				{
					$username = $note_list['username'];
				}
				else
				{
					$username = 'Guest';
				}

				$avatar = user::sort_avatar($note_list);

				$link = '/index.php?module=articles_full&amp;aid=' . $note_list['article_id'] . '&amp;comment_id=' . $note_list['comment_id'] . '&amp;clear_note=' . $note_list['id'];

				$templating->set('icon', $icon);
				$templating->set('title', $note_list['title']);
				$templating->set('link', $link);
				$templating->set('avatar', $avatar);
				$templating->set('username', $username);
				$templating->set('profile_link', $profile_link);
			}
		}
		else
		{
			$templating->block('none', 'usercp_modules/notifications');
		}

		$templating->block('bottom', 'usercp_modules/notifications');

		// sort out the pagination link
		$pagination = $core->pagination_link(9, $total_notifications, "usercp.php?module=notifications&", $page);
		$templating->set('pagination', $pagination);
	}
}

else if (isset($_GET['go']))
{
	if ($_GET['go'] == 'clear_all')
	{
		if (!isset($_POST['yes']) && !isset($_POST['no']))
		{
			$templating->set_previous('title', 'Clear all notifications', 1);
			$core->yes_no('Are you sure you want to clear all notifications?', url."usercp.php?module=notifications&go=clear_all");
		}

		else if (isset($_POST['no']))
		{
			header("Location: /usercp.php?module=notifications");
		}

		else if (isset($_POST['yes']))
		{
			$db->sqlquery("UPDATE `user_notifications` SET `seen` = 1, `seen_date` = ? WHERE `owner_id` = ?", array(core::$date, $_SESSION['user_id']));
			header("Location: /usercp.php?module=notifications&message=all_clear");
		}
	}
}
?>
