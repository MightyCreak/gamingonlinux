<?php
$templating->merge('admin_modules/admin_module_forum');


if (isset($_GET['view']) && !isset($_POST['act']))
{
	// add article
	if ($_GET['view'] == 'category')
	{
		$templating->block('category_add');
	}

	if ($_GET['view'] == 'forum')
	{
		$templating->block('forum_add');
		$options = '';

		$db->sqlquery("SELECT `forum_id`, `name` FROM `forums` WHERE `is_category` = 1 ORDER BY `order`");

		while ($display = $db->fetch())
		{
			$options .= "<option value=\"{$display['forum_id']}\">{$display['name']}</option>";
		}

		$templating->set('options', $options);

		$db->sqlquery("SELECT `group_id`, `group_name` FROM `user_groups` ORDER BY `group_id` DESC");
		while ($groups = $db->fetch())
		{
			$templating->block('forum_groups');
			$templating->set('group_id', $groups['group_id']);
			$templating->set('group_name', $groups['group_name']);
		}

		$templating->block('forum_end');
	}

	if ($_GET['view'] == 'manage')
	{
		$sql = "
		SELECT
			category.forum_id as CategoryId,
			category.name as CategoryName,
			category.order as CategoryOrder,
			forum.forum_id as ForumId,
			forum.name as ForumName,
			forum.parent_id as ForumParent,
			forum.description as ForumDescription,
			forum.posts as ForumPosts,
			forum.order as ForumOrder
		FROM
			`forums` category
		LEFT JOIN
			`forums` forum ON forum.parent_id = category.forum_id
		WHERE
			category.is_category = 1
		ORDER BY
			category.order, forum.order";

		$query_forums = $db->sqlquery($sql);

		// start the ids at 0
		$current_category_id = 0;
		$current_forum_id = 0;
		$category_array = array();
		$forum_array = array();

		// set the forum array so we can use it later and so we don't have to loop it just yet :)
		while ( $row = $db->fetch($query_forums) )
		{
			// make an array of categorys
			if ($current_category_id != $row['CategoryId'])
			{
				$category_array[$row['CategoryId']]['id'] = $row['CategoryId'];
				$category_array[$row['CategoryId']]['name'] = $row['CategoryName'];
				$category_array[$row['CategoryId']]['order'] = $row['CategoryOrder'];
				$current_category_id = $row['CategoryId'];
			}

			// make an array of forums
			if ($current_forum_id != $row['ForumId'])
			{
				$forum_array[$row['ForumId']]['id'] = $row['ForumId'];
				$forum_array[$row['ForumId']]['parent'] = $row['ForumParent'];
				$forum_array[$row['ForumId']]['name'] = $row['ForumName'];
				$forum_array[$row['ForumId']]['description'] = $row['ForumDescription'];
				$forum_array[$row['ForumId']]['posts'] = $row['ForumPosts'];
				$forum_array[$row['ForumId']]['order'] = $row['ForumOrder'];
			}
		}

		foreach ($category_array as $category)
		{
			$templating->block('category_top');
			$templating->set('category_id', $category['id']);
			$templating->set('category_name', $category['name']);
			$templating->set('category_order', $category['order']);

			foreach ($forum_array as $forum)
			{
				// show this categorys forums
				if ($forum['parent'] == $category['id'])
				{
					$templating->block('forum_row');
					$templating->set('forum_id', $forum['id']);
					$templating->set('forum_name', $forum['name']);
					$templating->set('forum_description', $forum['description']);
					$templating->set('forum_posts', $forum['posts']);
					$templating->set('forum_order', $forum['order']);
				}
			}
			$templating->block('category_bottom');
		}
	}

	if ($_GET['view'] == 'permissions')
	{
		if (!isset($_GET['forum_id']) || !is_numeric($_GET['forum_id']))
		{
			$core->message('Not a valid forum id!');
		}

		else
		{
			$templating->block('permissions_top');

			$db->sqlquery("SELECT `name` FROM `forums` WHERE `forum_id` = ?", array($_GET['forum_id']));
			$name = $db->fetch();

			$templating->set('forum_name', $name['name']);

			$db->sqlquery("SELECT
				g.*,
				p.*
			FROM
				`user_groups` g INNER JOIN `forum_permissions` p ON g.group_id = p.group_id
			WHERE
				p.forum_id = ? AND g.group_id = p.group_id", array($_GET['forum_id']));
			while ($groups = $db->fetch())
			{
				// check if they can view the forum
				$view = '';
				if ($groups['can_view'] == 1)
				{
					$view = 'CHECKED';
				}

				// check if they can make new topics
				$post = '';
				if ($groups['can_topic'] == 1)
				{
					$post = 'CHECKED';
				}

				// check if they can make reply
				$reply = '';
				if ($groups['can_reply'] == 1)
				{
					$reply = 'CHECKED';
				}

				// check if they can lock
				$lock = '';
				if ($groups['can_lock'] == 1)
				{
					$lock = 'CHECKED';
				}

				// check if they can sticky
				$sticky = '';
				if ($groups['can_sticky'] == 1)
				{
					$sticky = 'CHECKED';
				}

				// check if they can delete topics
				$delete = '';
				if ($groups['can_delete'] == 1)
				{
					$delete = 'CHECKED';
				}

				// check if they can own delete topics
				$deleteo = '';
				if ($groups['can_delete_own'] == 1)
				{
					$deleteo = 'CHECKED';
				}

				// check if they can avoid the flood filter
				$floods = '';
				if ($groups['can_avoid_floods'] == 1)
				{
					$floods = 'CHECKED';
				}

				// check if they can move topics
				$move = '';
				if ($groups['can_move'] == 1)
				{
					$move = 'CHECKED';
				}

				$templating->block('forum_permission_group');
				$templating->set('group_name', $groups['group_name']);
				$templating->set('group_id', $groups['group_id']);
				$templating->set('view', $view);
				$templating->set('post', $post);
				$templating->set('reply', $reply);
				$templating->set('lock', $lock);
				$templating->set('sticky', $sticky);
				$templating->set('delete', $delete);
				$templating->set('deleteo', $deleteo);
				$templating->set('floods', $floods);
				$templating->set('move', $move);

			}
			$templating->block('permissions_bottom');
			$templating->set('forum_id', $_GET['forum_id']);
		}
	}

	if ($_GET['view'] == 'reportedtopics')
	{
		if (isset($_GET['message']) && $_GET['message'] == 'alreadydone')
		{
			$core->message("That one has already been dealt with!");
		}

		if (isset($_GET['message']) && $_GET['message'] == 'done')
		{
			$core->message("That report has been removed!");
		}

		$templating->block('topic_top', 'admin_modules/admin_module_forum');

		$db->sqlquery("SELECT t.*, u2.user_id AS reporter_id, u2.username AS reporter_user, u.user_id, u.user_group, u.secondary_user_group, u.username, u.avatar, u.avatar_uploaded, u.avatar_gravatar, u.avatar_gallery, u.gravatar_email FROM `forum_topics` t INNER JOIN `users` u ON t.author_id = u.user_id LEFT JOIN `users` u2 ON t.reported_by_id = u2.user_id WHERE t.reported = 1");
		while ($topic = $db->fetch())
		{
			$templating->block('topic', 'admin_modules/admin_module_forum');
			$templating->set('topic_title', $topic['topic_title']);

			$topic_link = '';
			if (core::config('pretty_urls') == 1)
			{
				$topic_link = '/forum/topic/' . $topic['topic_id'];
			}
			else
			{
				$topic_link = '/index.php?module=viewtopic&topic_id=' . $topic['topic_id'];
			}
			$templating->set('topic_link', $topic_link);

			$topic_date = $core->format_date($topic['creation_date']);
			$templating->set('topic_date', $topic_date);

			if ($topic['author_id'] != 0)
			{
				$username = "<a href=\"/profiles/{$topic['author_id']}\">{$topic['username']}</a>";
			}

			$templating->set('username', $username);

			// sort out the avatar
			$avatar = $user->sort_avatar($topic);

			$templating->set('avatar', $avatar);

			$templating->set('post_id', $topic['topic_id']);
			$templating->set('topic_id', $topic['topic_id']);
			$templating->set('post_text', bbcode($topic['topic_text'], 0));
			if ($topic['reported_by_id'] == 0)
			{
				$reported_by = "Guest";
			}
			else
			{
				$reported_by = "<a href=\"" . core::config('website_url') . "profiles/{$topic['reporter_id']}\">{$topic['reporter_user']}</a>";
			}
			$templating->set('reporter', $reported_by);
		}
	}

	if ($_GET['view'] == 'reportedreplies')
	{
		if (isset($_GET['message']) && $_GET['message'] == 'alreadydone')
		{
			$core->message("That one has already been dealt with!");
		}

		if (isset($_GET['message']) && $_GET['message'] == 'done')
		{
			$core->message("That report has been removed!");
		}

		$templating->block('reply_top', 'admin_modules/admin_module_forum');

		$db->sqlquery("SELECT p.`post_id`, p.`author_id`, p.`reply_text`, p.`creation_date`, p.`reported_by_id`, u2.user_id AS reporter_id, u2.username AS reporter_user, u.user_id, u.user_group, t.topic_title, t.topic_id, u.secondary_user_group, u.username, u.avatar, u.avatar_uploaded, u.avatar_gravatar, u.avatar_gallery, u.gravatar_email FROM `forum_replies` p INNER JOIN `forum_topics` t ON p.topic_id = t.topic_id INNER JOIN `users` u ON p.author_id = u.user_id LEFT JOIN `users` u2 ON p.reported_by_id = u2.user_id WHERE p.`reported` = 1");
		while ($topic = $db->fetch())
		{
			$templating->block('reply', 'admin_modules/admin_module_forum');
			$templating->set('topic_title', $topic['topic_title']);

			$topic_link = '';
			if (core::config('pretty_urls') == 1)
			{
				$topic_link = '/forum/topic/' . $topic['topic_id'];
			}
			else
			{
				$topic_link = '/index.php?module=viewtopic&topic_id=' . $topic['topic_id'];
			}
			$templating->set('topic_link', $topic_link);

			$topic_date = $core->format_date($topic['creation_date']);
			$templating->set('post_date', $topic_date);

			if ($topic['author_id'] != 0)
			{
				$username = "<a href=\"/profiles/{$topic['author_id']}\">{$topic['username']}</a>";
			}

			$templating->set('username', $username);

			// sort out the avatar
			$avatar = $user->sort_avatar($topic);

			$templating->set('avatar', $avatar);

			$templating->set('post_id', $topic['post_id']);
			$templating->set('topic_id', $topic['topic_id']);
			$templating->set('post_text', bbcode($topic['reply_text'], 0));
			if ($topic['reported_by_id'] == 0)
			{
				$reported_by = "Guest";
			}
			else
			{
				$reported_by = "<a href=\"" . core::config('website_url') . "profiles/{$topic['reporter_id']}\">{$topic['reporter_user']}</a>";
			}
			$templating->set('reporter', $reported_by);
		}
	}

	if ($_GET['view'] == 'removetopicreport')
	{
		// check its still reported first
		$db->sqlquery("SELECT `reported` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));
		$check = $db->fetch();

		if ($check['reported'] == 0)
		{
			header("Location: /admin.php?module=forum&view=reportedtopics&message=alreadydone");
		}

		else
		{
			$db->sqlquery("UPDATE `forum_topics` SET `reported` = 0 WHERE `topic_id` = ?", array($_GET['topic_id']));

			$db->sqlquery("UPDATE `admin_notifications` SET `completed` = 1, `action` = ?, `completed_date` = ? WHERE `topic_id` = ?", array("{$_SESSION['username']} deleted a topic report.", core::$date, $_GET['topic_id']));

			header("Location: /admin.php?module=forum&view=reportedtopics&message=done");
		}
	}

	if ($_GET['view'] == 'removereplyreport')
	{
		// check its still reported first
		$db->sqlquery("SELECT `reported` FROM `forum_replies` WHERE `post_id` = ?", array($_GET['post_id']));
		$check = $db->fetch();

		if ($check['reported'] == 0)
		{
			header("Location: /admin.php?module=forum&view=reportedreplies&message=alreadydone");
		}

		else
		{
			$db->sqlquery("UPDATE `forum_replies` SET `reported` = 0 WHERE `post_id` = ?", array($_GET['post_id']));

			$db->sqlquery("UPDATE `admin_notifications` SET `completed` = 1, `action` = ?, `completed_date` = ? WHERE `reply_id` = ?", array("{$_SESSION['username']} deleted a forum topic reply report.", core::$date, $_GET['post_id']));

			header("Location: /admin.php?module=forum&view=reportedreplies&message=done");
		}
	}
}

else if (isset($_POST['act']) && !isset($_GET['view']))
{
	if ($_POST['act'] == 'category')
	{
		if (empty($_POST['list']))
		{
			$core->message('You must enter at least one category name!');
		}

		else
		{
			// get the categories asked for
			$categorys = preg_split('/(\\n|\\r)/', $_POST['list'], -1, PREG_SPLIT_NO_EMPTY);

			foreach ($categorys as $name)
			{
				// find the last order
				$db->sqlquery("SELECT `order` FROM `forums` WHERE `is_category` = 1 ORDER BY `order` DESC LIMIT 1");
				$order = $db->fetch();

				$order_now = $order['order'] + 1;

				// make the category
				$query = $db->sqlquery("INSERT INTO `forums` SET `name` = ?, `is_category` = 1, `order` = ?", array($name, $order_now));

				$core->message("Category $name added!");
			}
		}
	}

	if ($_POST['act'] == 'forum')
	{
		// make these safe for queries
		$name = $_POST['forum'];
		$description = $_POST['description'];
		$category = $_POST['category'];

		// find the last order
		$db->sqlquery("SELECT `order` FROM `forums` WHERE `is_category` = 0 AND `parent_id` = ? ORDER BY `order` DESC LIMIT 1", array($category));
		$order = $db->fetch();

		// find this forums order, which will be 1 after the last one
		$order_now = $order['order'] + 1;

		// make the actual forum
		$db->sqlquery("INSERT INTO `forums` SET `name` = ?, `is_category` = '0', `description` = ?, `parent_id` = ?, `order` = ?", array($name, $description, $category, $order_now));

		$last_id = $db->grab_id();

		// Get all IDs and sort them in an array
		$db->sqlquery("SELECT `group_id` FROM `user_groups`");
		$index = 0;
		while ($group = $db->fetch())
		{
			$ids[$index] = $group['group_id'];
			$index++;
		}

		if (isset($_POST['cview']))
		{
			$cview = $_POST['cview'];
		}

		if (isset($_POST['ctopic']))
		{
			$ctopic = $_POST['ctopic'];
		}

		if (isset($_POST['creply']))
		{
			$creply = $_POST['creply'];
		}

		if (isset($_POST['clock']))
		{
			$clock = $_POST['clock'];
		}

		if (isset($_POST['csticky']))
		{
			$csticky = $_POST['csticky'];
		}

		if (isset($_POST['cdelete']))
		{
			$cdelete = $_POST['cdelete'];
		}

		if (isset($_POST['cdelete_own']))
		{
			$cdelete_own = $_POST['cdelete_own'];
		}

		if (isset($_POST['cfloods']))
		{
			$cfloods = $_POST['cfloods'];
		}

		if (isset($_POST['cmove']))
		{
			$cmove = $_POST['cmove'];
		}


		// Update permissions
		for ($ind=0; $ind<$index; $ind++)
		{
			// can the group even view the forum?
			$cv = '0';
			if (isset($ids[$ind]) && isset($cview[$ids[$ind]]) && $cview[$ids[$ind]])
			{
				$cv = '1';
			}

			// can the group make new topics?
			$ct = '0';
			if (isset($ids[$ind]) && isset($ctopic[$ids[$ind]]) && $ctopic[$ids[$ind]])
			{
				$ct = '1';
			}

			// can the group make replies?
			$cr = '0';
			if (isset($ids[$ind]) && isset($creply[$ids[$ind]]) && $creply[$ids[$ind]])
			{
				$cr = '1';
			}

			// can the group lock topics?
			$cl = '0';
			if (isset($ids[$ind]) && isset($clock[$ids[$ind]]) && $clock[$ids[$ind]])
			{
				$cl = '1';
			}

			// can the group sticky topics?
			$cs = '0';
			if (isset($ids[$ind]) && isset($csticky[$ids[$ind]]) && $csticky[$ids[$ind]])
			{
				$cs = '1';
			}

			// can the group delete topics?
			$cd = '0';
			if (isset($ids[$ind]) && isset($cdelete[$ids[$ind]]) && $cdelete[$ids[$ind]])
			{
				$cd = '1';
			}

			// can the group delete own topics?
			$cdo = '0';
			if (isset($ids[$ind]) && isset($cdelete_own[$ids[$ind]]) && $cdelete_own[$ids[$ind]])
			{
				$cdo = '1';
			}

			// can the group avoid the flood filter?
			$cf = '0';
			if (isset($ids[$ind]) && isset($cfloods[$ids[$ind]]) && $cfloods[$ids[$ind]])
			{
				$cf = '1';
			}

			// can the group move topics?
			$cm = '0';
			if (isset($ids[$ind]) && isset($cmove[$ids[$ind]]) && $cmove[$ids[$ind]])
			{
				$cm = '1';
			}

			// add permissions for this forum
			$db->sqlquery("INSERT INTO `forum_permissions` SET `forum_id` = ?, `group_id` = ?, `can_view` = ?, `can_topic` = ?, `can_reply` = ?, `can_lock` = ?, `can_sticky` = ?, `can_delete` = ?, `can_delete_own` = ?, `can_avoid_floods` = ?, `can_move` = ?", array($last_id, $ids[$ind], $cv, $ct, $cr, $cl, $cs, $cd, $cdo, $cf, $cm));
		}
		$core->message("Forum {$name} added!");
	}

	if ($_POST['act'] == 'categorymanage')
	{
		if ($_POST['submit'] == 'Edit')
		{
			if (empty($_POST['name']))
			{
				$core->message('The category must be named!');
			}

			else
			{
				$db->sqlquery("UPDATE `forums` SET `name` = ?, `order` = ? WHERE `forum_id` = ?", array($_POST['name'], $_POST['order'], $_POST['category_id']));

				$core->message("Category {$_POST['name']} has been updated. <a href=\"admin.php?module=forum&amp;view=manage\">Click here to return</a>.");
			}
		}

		if ($_POST['submit'] == 'Delete')
		{

			// check if it has forums

			$db->sqlquery("SELECT `forum_id` FROM `forums` WHERE `parent_id` = ?", array($_POST['category_id']));
			if ($db->num_rows() > 0)
			{
				$core->message('You cannot delete a category that is populated with forums! Delete the forums first, this is a security measure so you don\'t end up deleting lots of forums with posts. <a href="admin.php?module=forum&amp;view=manage">Click here to return</a>.');
			}

			// if it has none
			else
			{
				$db->sqlquery("DELETE FROM `forums` WHERE `forum_id` = ?", array($_POST['category_id']));

				$core->message('Category has been deleted! <a href="admin.php?module=forum&amp;view=manage">Click here to return</a>.');
			}
		}
	}

	if ($_POST['act'] == 'forummanage')
	{
		if ($_POST['submit'] == 'Edit')
		{
			if (empty($_POST['name']))
			{
				$core->message('The forum must be named!');
			}

			else
			{
				$db->sqlquery("UPDATE `forums` SET `name` = ?, `order` = ?, `description` = ? WHERE `forum_id` = ?", array($_POST['name'], $_POST['order'], $_POST['description'],  $_POST['forum_id']));

				$core->message("Category {$_POST['name']} has been updated. <a href=\"admin.php?module=forum&amp;view=manage\">Click here to return</a>.");
			}
		}

		if ($_POST['submit'] == 'Delete')
		{

			// check if it has posts
			$db->sqlquery("SELECT `topic_id` FROM `forum_topics` WHERE `forum_id` = ?", array($_POST['forum_id']));
			$forum_post_count = $db->num_rows();
			if ($forum_post_count > 0)
			{
				$core->message('You cannot delete a forum that is populated with topics! Delete or move the topics first, this is a security measure so you don\'t end up deleting forums with posts. <a href="admin.php?module=forum&amp;view=manage">Click here to return</a>.');
			}

			// if it has none
			else
			{
				// delete the forum
				$db->sqlquery("DELETE FROM `forums` WHERE `forum_id` = ?", array($_POST['forum_id']));

				// remove forum permission rows
				$db->sqlquery("DELETE FROM `forum_permissions` WHERE `forum_id` = ?", array($_POST['forum_id']));

				$core->message('Forum has been deleted! <a href="admin.php?module=forum&amp;view=manage">Click here to return</a>.');
			}
		}
	}

	if ($_POST['act'] == 'permissions')
	{
		if (!isset($_POST['forum_id']) || !is_numeric($_POST['forum_id']))
		{
			$core->message('There was no forum id!');
		}

		else
		{
			// Get all IDs and sort them in an array
			$db->sqlquery("SELECT `group_id` FROM `user_groups`");
			$index = 0;
			while ($group = $db->fetch())
			{
				$ids[$index] = $group['group_id'];
				$index++;
			}

			if (isset($_POST['cview']))
			{
				$cview = $_POST['cview'];
			}

			if (isset($_POST['ctopic']))
			{
				$ctopic = $_POST['ctopic'];
			}

			if (isset($_POST['creply']))
			{
				$creply = $_POST['creply'];
			}

			if (isset($_POST['clock']))
			{
				$clock = $_POST['clock'];
			}

			if (isset($_POST['csticky']))
			{
				$csticky = $_POST['csticky'];
			}

			if (isset($_POST['cdelete']))
			{
				$cdelete = $_POST['cdelete'];
			}

			if (isset($_POST['cdelete_own']))
			{
				$cdelete_own = $_POST['cdelete_own'];
			}

			if (isset($_POST['cfloods']))
			{
				$cfloods = $_POST['cfloods'];
			}

			if (isset($_POST['cmove']))
			{
				$cmove = $_POST['cmove'];
			}


			// Update permissions
			for ($ind=0; $ind<$index; $ind++)
			{
				// can the group even view the forum?
				$cv = '0';
				if (isset($ids[$ind]) && isset($cview[$ids[$ind]]) && $cview[$ids[$ind]])
				{
					$cv = '1';
				}

				// can the group make new topics?
				$ct = '0';
				if (isset($ids[$ind]) && isset($ctopic[$ids[$ind]]) && $ctopic[$ids[$ind]])
				{
					$ct = '1';
				}

				// can the group make replies?
				$cr = '0';
				if (isset($ids[$ind]) && isset($creply[$ids[$ind]]) && $creply[$ids[$ind]])
				{
					$cr = '1';
				}

				// can the group lock topics?
				$cl = '0';
				if (isset($ids[$ind]) && isset($clock[$ids[$ind]]) && $clock[$ids[$ind]])
				{
					$cl = '1';
				}

				// can the group sticky topics?
				$cs = '0';
				if (isset($ids[$ind]) && isset($csticky[$ids[$ind]]) && $csticky[$ids[$ind]])
				{
					$cs = '1';
				}

				// can the group delete topics?
				$cd = '0';
				if (isset($ids[$ind]) && isset($cdelete[$ids[$ind]]) && $cdelete[$ids[$ind]])
				{
					$cd = '1';
				}

				// can the group delete own topics?
				$cdo = '0';
				if (isset($ids[$ind]) && isset($cdelete_own[$ids[$ind]]) && $cdelete_own[$ids[$ind]])
				{
					$cdo = '1';
				}

				// can the group avoid the flood filter?
				$cf = '0';
				if (isset($ids[$ind]) && isset($cfloods[$ids[$ind]]) && $cfloods[$ids[$ind]])
				{
					$cf = '1';
				}

				// can the group move topics?
				$cm = '0';
				if (isset($ids[$ind]) && isset($cmove[$ids[$ind]]) && $cmove[$ids[$ind]])
				{
					$cm = '1';
				}

				// add permissions for this forum
				$db->sqlquery("UPDATE `forum_permissions` SET `can_view` = ?, `can_topic` = ?, `can_reply` = ?, `can_lock` = ?, `can_sticky` = ?, `can_delete` = ?, `can_delete_own` = ?, `can_avoid_floods` = ?, `can_move` = ? WHERE `forum_id` = ? AND `group_id` = ?", array($cv, $ct, $cr, $cl, $cs, $cd, $cdo, $cf, $cm, $_POST['forum_id'], $ids[$ind]));
			}

			$db->sqlquery("SELECT `name` FROM `forums` WHERE `forum_id` = ?", array($_POST['forum_id']));
			$name = $db->fetch();
			$core->message("Forum permissions for {$name['name']} updated! <a href=\"admin.php?module=forum&view=permissions&forum_id={$_POST['forum_id']}\">Click here to edit again</a>.");
		}
	}
}
?>
