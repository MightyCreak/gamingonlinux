<?php
class forum
{
	protected $dbl;
	private $core;
	private $user;
	
	function __construct($core, $user = NULL)
	{
		$this->dbl = db_mysql::instance();
		$this->core = $core;
		$this->user = $user;
	}
	
	// this will subscribe them to an forum topic and generate any possible missing secret key for emails
	function subscribe($topic_id, $emails = NULL)
	{
		if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && $_SESSION['user_id'] != 0)
		{
			$res = $this->dbl->run("SELECT `user_id`, `topic_id`, `secret_key`, `emails` FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $topic_id))->fetch();

			if (!$res)
			{
				// have we been given an email option, if so use it
				if ($emails == NULL)
				{
					// find how they like to normally subscribe
					$get_email_type = $this->dbl->run("SELECT `auto_subscribe_email` FROM `users` WHERE `user_id` = ?", array($_SESSION['user_id']))->fetchOne();
					
					$sql_emails = $get_email_type;
				}
				else
				{
					$sql_emails = (int) $emails;
				}
        
				// for unsubscribe link in emails
				$secret_key = core::random_id(15);

				$this->dbl->run("INSERT INTO `forum_topics_subscriptions` SET `user_id` = ?, `topic_id` = ?, `emails` = ?, `send_email` = ?, `secret_key` = ?", array($_SESSION['user_id'], $topic_id, $sql_emails, $sql_emails, $secret_key));
			}
			else
			{
				// for unsubscribe link in emails
				if (empty($res['secret_key']))
				{
					$secret_key = core::random_id(15);
				}
				else
				{
					$secret_key = $res['secret_key'];
				}
				
				// check over their email options on this new subscription
				if ($emails == NULL)
				{
					// find how they like to normally subscribe
					$get_email_type = $this->dbl->run("SELECT `auto_subscribe_email` FROM `users` WHERE `user_id` = ?", array($_SESSION['user_id']))->fetchOne();
					
					$sql_emails = $get_email_type;
				}
				else
				{
					$sql_emails = (int) $emails;
				}

				$this->dbl->run("UPDATE `forum_topics_subscriptions` SET `secret_key` = ?, `emails` = ?, `send_email` = ? WHERE `user_id` = ? AND `topic_id` = ?", array($secret_key, $sql_emails, $sql_emails, $_SESSION['user_id'], $topic_id));
			}
		}
	}
	
	public function delete_topic($return_page_done = NULL, $return_page_no = NULL, $post_page = NULL)
	{
		global $core, $parray, $templating;
		
		if (!isset($_GET['forum_id']) || !isset($_GET['author_id']) || !isset($_GET['topic_id']))
		{
			header('Location: ' . $return_page_no);
			die();
		}
		
		if (!core::is_number($_GET['forum_id']) || !core::is_number($_GET['author_id']) || !core::is_number($_GET['topic_id']))
		{
			header('Location: ' . $return_page_no);
			die();
		}

		$this->forum_permissions($_GET['forum_id']);
		if ($parray['can_delete'] == 0 || !isset($parray['can_delete']))
		{
			header('Location: ' . $return_page_no);
			die();
		}
		
		if (!isset($_POST['yes']) && !isset($_POST['no']))
		{
			
			$templating->set_previous('title', 'Deleting a forum topic', 1);
			$core->yes_no('Are you sure you want to delete that topic?', $post_page, 'delete_topic');
		}

		else if (isset($_POST['no']))
		{
			header("Location: " . $return_page_no);
		}

		else if (isset($_POST['yes']))
		{
			// check if its been reported first so we can remove the report
			$check = $this->dbl->run("SELECT `reported`, `replys` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']))->fetch();

			if ($check['reported'] == 1)
			{
				$this->dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'forum_topic_report' AND `data` = ?", array(core::$date, $_GET['topic_id']));
			}

			// delete any replies that may have been reported from the admin notifications
			if ($check['replys'] > 0)
			{
				$get_replies = $this->dbl->run("SELECT `post_id`, `reported` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']))->fetch_all();

				foreach ($get_replies as $delete_replies)
				{
					if ($delete_replies['reported'] == 1)
					{
						$this->dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'forum_reply_report' AND `data` = ?", array(core::$date, $delete_replies['post_id']));
					}
				}
			}

			$this->dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `completed` = 1, `type` = 'delete_forum_topic', `created_date` = ?, `completed_date` = ?, `data` = ?", array($_SESSION['user_id'], core::$date, core::$date, $_GET['topic_id']));

			// count all posts including the topic
			$total_count = 0;
			$current_count = $this->dbl->run("SELECT COUNT(`post_id`) FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']))->fetchOne();
			$total_count = $current_count + 1;

			// Here we get each person who has posted along with their post count for the topic ready to remove it from their post count sql
			$posts = $this->dbl->run("SELECT `author_id` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']))->fetch_all();

			$users_posts = array();
			foreach ($posts as $post)
			{
				$user_post_count = $this->dbl->run("SELECT COUNT(`post_id`) FROM `forum_replies` WHERE `author_id` = ? AND `topic_id` = ?", array($post['author_id'], $_GET['topic_id']))->fetchOne();

				$users_posts[$post['author_id']]['author_id'] = $post['author_id'];
				$users_posts[$post['author_id']]['posts'] = $user_post_count;
			}

			// now we can remove the topic
			$this->dbl->run("DELETE FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));

			// now we can remove all replys
			$this->dbl->run("DELETE FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));

			// now update each users post count
			foreach($users_posts as $post)
			{
				$this->dbl->run("UPDATE `users` SET `forum_posts` = (forum_posts - ?) WHERE `user_id` = ?", array($post['posts'], $post['author_id']));
			}

			// remove a post from the topic author for the topic post itself
			$this->dbl->run("UPDATE `users` SET `forum_posts` = (forum_posts - 1) WHERE `user_id` = ?", array($_GET['author_id']));

			// now update the forums post count
			$this->dbl->run("UPDATE `forums` SET `posts` = (posts - ?) WHERE `forum_id` = ?", array($total_count, $_GET['forum_id']));

			// finally check if this is the latest topic we are deleting to update the latest topic info for the forum
			$last_post = $this->dbl->run("SELECT `last_post_topic_id` FROM `forums` WHERE `forum_id` = ?", array($_GET['forum_id']))->fetchOne();

			// if it is then we need to get the *now* newest topic and update the forums info
			if ($last_post == $_GET['topic_id'])
			{
				$new_info = $this->dbl->run("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `forum_id` = ? ORDER BY `last_post_date` DESC LIMIT 1", array($_GET['forum_id']))->fetch();

				$this->dbl->run("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $_GET['forum_id']));
			}

			$_SESSION['message'] = 'deleted';
			$_SESSION['message_extra'] = 'post';
			header("Location: " . $return_page_done);
		}
	}
	
	public function delete_reply($return_page_done = NULL, $return_page_no = NULL, $post_page = NULL)
	{
		global $core, $parray, $templating;
		
		if (!isset($_GET['forum_id']) || !isset($_GET['post_id']) || !isset($_GET['topic_id']))
		{
			header('Location: ' . $return_page_no);
			die();
		}
		
		if (!core::is_number($_GET['forum_id']) || !core::is_number($_GET['post_id']) || !core::is_number($_GET['topic_id']))
		{
			header('Location: ' . $return_page_no);
			die();
		}

		$this->forum_permissions($_GET['forum_id']);
		if ($parray['can_delete'] == 0 || !isset($parray['can_delete']))
		{
			header('Location: ' . $return_page_no);
			die();
		}
		
		if (!isset($_POST['yes']) && !isset($_POST['no']))
		{
			
			$templating->set_previous('title', 'Deleting a forum post', 1);
			$core->yes_no('Are you sure you want to delete that forum post?', $post_page, 'delete_topic');
		}

		else if (isset($_POST['no']))
		{
			header("Location: " . $return_page_no);
		}
		
		else if (isset($_POST['yes']))
		{
			// Get the info from the post
			$post_info = $this->dbl->run("SELECT r.author_id, r.reported, t.forum_id FROM `forum_replies` r INNER JOIN `forum_topics` t ON r.topic_id = t.topic_id WHERE r.`post_id` = ?", array($_GET['post_id']))->fetch();

			// remove the post
			$this->dbl->run("DELETE FROM `forum_replies` WHERE `post_id` = ?", array($_GET['post_id']));

			// update admin notifications
			if ($post_info['reported'] == 1)
			{
				$this->dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'forum_reply_report' AND `data` = ?", array(core::$date, $_GET['post_id']))->fetch();
			}

			$this->dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `completed` = 1, `type` = 'delete_forum_reply', `created_date` = ?, `completed_date` = ?, `data` = ?", array($_SESSION['user_id'], core::$date, core::$date, $_GET['post_id']));

			// update the authors post count
			if ($post_info['author_id'] != 0)
			{
				$this->dbl->run("UPDATE `users` SET `forum_posts` = (forum_posts - 1) WHERE `user_id` = ?", array($post_info['author_id']));
			}

			// now update the forums post count
			$this->dbl->run("UPDATE `forums` SET `posts` = (posts - 1) WHERE `forum_id` = ?", array($post_info['forum_id']));

			// update the topics info, get the newest last post and update the topics last info with that ones
			$topic_info = $this->dbl->run("SELECT `creation_date`, `author_id`, `guest_username` FROM `forum_replies` WHERE `topic_id` = ? ORDER BY `post_id` DESC LIMIT 1", array($_GET['topic_id']))->fetch();

			$this->dbl->run("UPDATE `forum_topics` SET `replys` = (replys - 1), `last_post_date` = ?, `last_post_id` = ? WHERE `topic_id` = ?", array($topic_info['creation_date'], $topic_info['author_id'], $_GET['topic_id']));

			// finally check if this is the latest topic we are deleting to update the latest topic info for the forum
			$last_post = $this->dbl->run("SELECT `last_post_topic_id` FROM `forums` WHERE `forum_id` = ?", array($post_info['forum_id']))->fetchOne();

			// if it is then we need to get the *now* newest topic and update the forums info
			if ($last_post == $_GET['topic_id'])
			{
				$new_info = $this->dbl->run("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `forum_id` = ? ORDER BY `last_post_date` DESC LIMIT 1", array($post_info['forum_id']))->fetch();

				$this->dbl->run("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $post_info['forum_id']));
			}
			
			$_SESSION['message'] = 'deleted';
			$_SESSION['message_extra'] = 'post';
			header("Location: " . $return_page_done);
		}
	}
	
	public function get_link($id, $additional = NULL)
	{
		$link = '';
		
		if ($this->core->config('pretty_urls') == 1)
		{
			$link = 'forum/topic/'.$id;
			
			if ($additional != NULL)
			{
				$link = $link . '/' . $additional;
			}
		}
		else
		{
			$link = 'index.php?module=viewtopic&topic_id='.$id;
			
			if ($additional != NULL)
			{
				$link = $link . '&' . $additional;
			}
		}
		return $this->core->config('website_url') . $link;
	}
	// check user forum permissions
	function forum_permissions($forum_id)
	{
		global $parray;

		$group_ids = $this->user->get_user_groups();
		
		// placeholder for forum id, then for user groups
		$end_replace = [$forum_id];
		foreach ($group_ids as $group)
		{
			$end_replace[] = $group;
		}
		
		$in = str_repeat('?,', count($group_ids) - 1) . '?';
		
		$sql_permissions = "
		SELECT
			`can_view`,
			`can_topic`,
			`can_reply`,
			`can_lock`,
			`can_sticky`,
			`can_delete`,
			`can_delete_own`,
			`can_avoid_floods`,
			`can_move`
		FROM
			`forum_permissions`
		WHERE
			`forum_id` = ? AND `group_id` IN ($in)
		";

		$permissions = $this->dbl->run($sql_permissions, $end_replace)->fetch_all();
		
		// first set them all to 0 (not allowed), and if any of their groups allow them, change it
		$parray = [
		'can_view' => 0,
		'can_topic' => 0,
		'can_reply' => 0,
		'can_lock' => 0,
		'can_sticky' => 0,
		'can_delete' => 0,
		'can_delete_own' => 0,
		'can_avoid_floods' => 0,
		'can_move' => 0
		];
		foreach ($permissions as $group_level)
		{
			foreach ($group_level as $permission => $value)
			{
				if ($value == 1)
				{
					$parray[$permission] = 1;
				}
			}
		}
	}
}
?>
