<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0)
{
	$_SESSION['message'] = "no_forum_reply_permission";
	header("Location: /forum/");
}
else
{
	if ($core->config('forum_posting_open') == 1)
	{
		$mod_queue = $user->user_details['in_mod_queue'];
		$forced_mod_queue = $user->can('forced_mod_queue');
		
		$forum_id = (int) $_GET['forum_id'];
		$topic_id = (int) $_GET['topic_id'];

		$parray = $forum_class->forum_permissions($forum_id);

		$name = $dbl->run("SELECT t.`topic_title`, t.`replys`, t.`is_locked`, t.`is_sticky`, f.`name`, f.`forum_id` FROM `forum_topics` t JOIN `forums` f ON t.`forum_id` = f.`forum_id` WHERE topic_id = ?", array($topic_id))->fetch();

		// check topic exists
		if (!$name)
		{
			$core->message('That is not a valid forum topic!');
		}

		// permissions for forum
		else if($parray['can_reply'] == 0)
		{
			$core->message('You do not have permission to post in this forum!');
		}

		// if the user wants to make their reply
		else if (isset($_POST['act']))
		{
			if ($_POST['act'] == 'Add')
			{
				if ($name['is_locked'] == 1 && $user->check_group([1,2]) == false)
				{
					$_SESSION['message'] = 'locked';
					$_SESSION['message_extra'] = 'forum post';
					header("Location: /forum/topic/".$topic_id.'/');
					die();
				}

				// make safe
				$message = core::make_safe($_POST['text']);
				$message = trim($message);
				$author = $_SESSION['user_id'];

				// check empty
				if (empty($message))
				{
					$_SESSION['message'] = 'empty';
					$_SESSION['message_extra'] = 'text';
					header("Location: /forum/topic/".$topic_id);
					die();
				}

				else
				{
					$mod_sql = '';
					if (!empty($_POST['moderator_options']))
					{
						if ($_POST['moderator_options'] == 'sticky')
						{
							$mod_sql = '`is_sticky` = 1,';
						}

						if ($_POST['moderator_options'] == 'unsticky')
						{
							$mod_sql = '`is_sticky` = 0,';
						}

						if ($_POST['moderator_options'] == 'lock')
						{
							$mod_sql = '`is_locked` = 1,';
						}

						if ($_POST['moderator_options'] == 'unlock')
						{
							$mod_sql = '`is_locked` = 0,';
						}

						if ($_POST['moderator_options'] == 'bothunlock')
						{
							$mod_sql = '`is_locked` = 0,`is_sticky` = 1,';
						}

						if ($_POST['moderator_options'] == 'bothunsticky')
						{
							$mod_sql = '`is_locked` = 1,`is_sticky` = 0,';
						}

						if ($_POST['moderator_options'] == 'bothundo')
						{
							$mod_sql = '`is_locked` = 0,`is_sticky` = 0,';
						}

						if ($_POST['moderator_options'] == 'both')
						{
							$mod_sql = '`is_locked` = 1,`is_sticky` = 1,';
						}
					}

					$approved = 1;
					if ($mod_queue == 1 || $forced_mod_queue == true)
					{
						$approved = 0;
					}

					// add the reply
					$dbl->run("INSERT INTO `forum_replies` SET `topic_id` = ?, `author_id` = ?, `reply_text` = ?, `creation_date` = ?, `approved` = ?, `is_topic` = 0", array($topic_id, $author, $message, core::$date, $approved));
					$post_id = $dbl->new_id();

					// update user post counter
					if ($approved == 1)
					{
						$dbl->run("UPDATE `users` SET `forum_posts` = (forum_posts + 1) WHERE `user_id` = ?", array($author));

						// update forums post counter and last post info
						$dbl->run("UPDATE `forums` SET `posts` = (posts + 1), `last_post_user_id` = ?, `last_post_time` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($author, core::$date, $topic_id, $forum_id));

						// update topic reply count and last post info
						$dbl->run("UPDATE `forum_topics` SET `replys` = (replys + 1), `last_post_date` = ?, $mod_sql `last_post_user_id` = ?, `last_post_id` = ? WHERE `topic_id` = ?", array(core::$date, $author, $post_id, $topic_id));

						// get article name for the email and redirect
						$title = $dbl->run("SELECT `topic_title` FROM `forum_topics` WHERE `topic_id` = ?", array($topic_id))->fetch();
							
						// see if they are subscribed right now, if they are and they untick the subscribe box, remove their subscription as they are unsubscribing
						if (!isset($_POST['subscribe']))
						{
							$sub_res = $dbl->run("SELECT `topic_id`, `emails`, `send_email` FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $topic_id))->fetch();
							if ($sub_res)
							{
								$dbl->run("DELETE FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $topic_id));
							}
						}

						$new_notification_id = $notifications->quote_notification($message, $_SESSION['username'], $_SESSION['user_id'], array('type' => 'forum_reply', 'thread_id' => $topic_id, 'post_id' => $post_id));

						// are we subscribing?
						if (isset($_POST['subscribe']) && $_SESSION['user_id'] != 0)
						{
							$emails = 0;
							if ($_POST['subscribe-type'] == 'sub-emails')
							{
								$emails = 1;
							}

							$forum_class->subscribe($topic_id, $emails);
						}

						// email anyone subscribed which isn't you
						$users_array = array();
						$fetch_subs = $dbl->run("SELECT s.`user_id`, s.`emails`, s.`secret_key`, u.email, u.username FROM `forum_topics_subscriptions` s INNER JOIN `users` u ON s.user_id = u.user_id WHERE s.`topic_id` = ? AND s.send_email = 1 AND s.emails = 1", array($topic_id))->fetch_all();
						
						if ($fetch_subs)
						{
							foreach ($fetch_subs as $users_fetch)
							{
								if ($users_fetch['user_id'] != $_SESSION['user_id'] && $users_fetch['emails'] == 1)
								{
									// use existing key, or generate any missing keys
									if (empty($users_fetch['secret_key']))
									{
										$secret_key = core::random_id(15);
										$dbl->run("UPDATE `forum_topics_subscriptions` SET `secret_key` = ? WHERE `user_id` = ? AND `topic_id` = ?", array($secret_key, $users_fetch['user_id'], $topic_id));
									}
									else
									{
										$secret_key = $users_fetch['secret_key'];
									}
									$users_array[$users_fetch['user_id']]['user_id'] = $users_fetch['user_id'];
									$users_array[$users_fetch['user_id']]['email'] = $users_fetch['email'];
									$users_array[$users_fetch['user_id']]['username'] = $users_fetch['username'];
									$users_array[$users_fetch['user_id']]['secret_key'] = $secret_key;
								}
							}

							// send the emails
							foreach ($users_array as $email_user)
							{
								$email_message = $bbcode->email_bbcode($message);

								// subject
								$subject = "New reply to forum post {$title['topic_title']} on GamingOnLinux.com";

								// message
								$html_message = "<p>Hello <strong>{$email_user['username']}</strong>,</p>
								<p><strong>{$_SESSION['username']}</strong> has replied to a forum topic you follow on titled \"<strong><a href=\"" . $core->config('website_url') . "forum/topic/{$topic_id}/post_id={$post_id}\">{$title['topic_title']}</a></strong>\". There may be more replies after this one, and you may not get any more emails depending on your email settings in your UserCP.</p>
								<div>
								<hr>
								{$email_message}
								<hr>
								You can unsubscribe from this topic by <a href=\"" . $core->config('website_url') . "unsubscribe.php?user_id={$email_user['user_id']}&topic_id={$topic_id}&email={$email_user['email']}&secret_key={$email_user['secret_key']}\">clicking here</a>, you can manage your subscriptions anytime in your <a href=\"" . $core->config('website_url') . "usercp.php\">User Control Panel</a>.";
								
								$plain_message = "Hello {$email_user['username']}, {$_SESSION['username']} has replied to a forum topic you follow on titled {$title['topic_title']} find it here: " . $core->config('website_url') . 'forum/topic/' . $topic_id . '/post_id=' . $post_id;

								// Mail it
								if ($core->config('send_emails') == 1)
								{
									$mail = new mailer($core);
									$mail->sendMail($email_user['email'], $subject, $html_message, $plain_message);
								}

								// remove anyones send_emails subscription setting if they have it set to email once
								$update_sub = $dbl->run("SELECT `email_options` FROM `users` WHERE `user_id` = ?", array($email_user['user_id']))->fetch();

								if ($update_sub['email_options'] == 2)
								{
									$dbl->run("UPDATE `forum_topics_subscriptions` SET `send_email` = 0 WHERE `topic_id` = ? AND `user_id` = ?", array($topic_id, $email_user['user_id']));
								}
							}
						}
						// help stop double postings
						unset($message);

						header("Location: /forum/topic/{$topic_id}/post_id={$post_id}");
						die();
					}

					if ($approved == 0)
					{
						// help stop double postings
						unset($message);

						// get the title
						$topic_title = $dbl->run("SELECT t.`topic_title` FROM `forum_replies` p INNER JOIN `forum_topics` t ON t.`topic_id` = p.`topic_id` WHERE p.`post_id` = ?", array($post_id))->fetchOne();

						// add a new notification for the mod queue
						$core->new_admin_note(array('content' => ' has a new forum post in the mod queue for the topic titled: <a href="/admin.php?module=mod_queue&view=manage">'.$topic_title.'</a>.', 'type' => 'mod_queue_reply', 'data' => $post_id));
						
						$_SESSION['message'] = 'mod_queue';

						header("Location: " . $core->config('website_url') . "index.php?module=viewtopic&topic_id={$topic_id}");
					}
				}
			}
		}
	}
	else if ($core->config('forum_posting_open') == 0)
	{
		$core->message('Posting is currently down for maintenance.');
	}
}
?>