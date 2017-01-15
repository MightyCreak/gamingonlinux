<?php
if (!is_numeric($_GET['topic_id']))
{
	$core->message('That is not a valid forum topic!');
}

else
{
	$templating->merge('viewtopic');

	if (isset($_GET['view']) && $_GET['view'] == 'deletepost')
	{
		if ($user->check_group(1,2) == true)
		{
			if (!isset($_POST['yes']))
			{
				$core->yes_no("Are you sure you wish to delete that post?", "index.php?module=viewtopic&view=deletepost&post_id={$_GET['post_id']}&topic_id={$_GET['topic_id']}");
			}

			else
			{
				// Get the info from the post
				$db->sqlquery("SELECT r.author_id, r.post_number, r.reported, t.forum_id FROM `forum_replies` r INNER JOIN `forum_topics` t ON r.topic_id = t.topic_id WHERE r.`post_id` = ?", array($_GET['post_id']));
				$post_info = $db->fetch();

				$db->sqlquery("UPDATE `forum_replies` SET `post_number` = (post_number - 1) WHERE `post_number` > ?", array($post_info['post_number']));

				// remove the post
				$db->sqlquery("DELETE FROM `forum_replies` WHERE `post_id` = ?", array($_GET['post_id']));

				// update admin notifications
				if ($post_info['reported'] == 1)
				{
					$db->sqlquery("DELETE FROM `admin_notifications` WHERE `reply_id` = ?", array($_GET['post_id']));
				}

				$db->sqlquery("INSERT INTO `admin_notifications` SET `completed` = 1, `action` = ?, `created` = ?, `completed_date` = ?, `reply_id` = ?", array("{$_SESSION['username']} deleted a forum reply.", core::$date, core::$date, $_GET['post_id']));

				// update the authors post count
				if ($post_info['author_id'] != 0)
				{
					$db->sqlquery("UPDATE `users` SET `forum_posts` = (forum_posts - 1) WHERE `user_id` = ?", array($post_info['author_id']));
				}

				// now update the forums post count
				$db->sqlquery("UPDATE `forums` SET `posts` = (posts - 1) WHERE `forum_id` = ?", array($post_info['forum_id']));

				// update the topics info, get the newest last post and update the topics last info with that ones
				$db->sqlquery("SELECT `creation_date`, `author_id`, `guest_username` FROM `forum_replies` WHERE `topic_id` = ? ORDER BY `post_id` DESC LIMIT 1", array($_GET['topic_id']));
				$topic_info = $db->fetch();

				$db->sqlquery("UPDATE `forum_topics` SET `replys` = (replys - 1), `last_post_date` = ?, `last_post_id` = ? WHERE `topic_id` = ?", array($topic_info['creation_date'], $topic_info['author_id'], $_GET['topic_id']));

				// finally check if this is the latest topic we are deleting to update the latest topic info for the forum
				$db->sqlquery("SELECT `last_post_topic_id` FROM `forums` WHERE `forum_id` = ?", array($post_info['forum_id']));
				$last_post = $db->fetch();

				// if it is then we need to get the *now* newest topic and update the forums info
				if ($last_post['last_post_topic_id'] == $_GET['topic_id'])
				{
					$db->sqlquery("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `forum_id` = ? ORDER BY `last_post_date` DESC LIMIT 1", array($post_info['forum_id']));
					$new_info = $db->fetch();

					$db->sqlquery("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $post_info['forum_id']));
				}

				$core->message("That post has now been deleted! <a href=\"/forum/topic/{$_GET['topic_id']}\">Click here to return to the forum</a>.");
			}
		}
	}

	else if (!isset($_POST['act']) && !isset($_GET['go']) && !isset($_GET['view']))
	{
		include('includes/profile_fields.php');

		$db_grab_fields = '';
		foreach ($profile_fields as $field)
		{
			$db_grab_fields .= "u.{$field['db_field']},";
		}

		// get topic info/make sure it exists
		$db->sqlquery("SELECT t.*, u.user_id, u.distro, u.pc_info_public, u.pc_info_filled, u.user_group, u.secondary_user_group, u.username, u.avatar, u.avatar_uploaded, u.avatar_gravatar, u.register_date, u.gravatar_email, u.avatar_gallery, u.forum_posts, u.game_developer, $db_grab_fields f.name as forum_name FROM `forum_topics` t LEFT JOIN `users` u ON t.author_id = u.user_id INNER JOIN `forums` f ON t.forum_id = f.forum_id WHERE t.topic_id = ? AND t.approved = 1", array($_GET['topic_id']));
		if ($db->num_rows() != 1)
		{
			$core->message('That is not a valid forum topic!');
		}

		else
		{
			$topic = $db->fetch();

			$remove_bbcode = remove_bbcode($topic['topic_text']);
			$rest = substr($remove_bbcode, 0, 70);

			$templating->set_previous('title', "Viewing topic {$topic['topic_title']}", 1);
			$templating->set_previous('meta_description', $rest . ' - Forum post on GamingOnLinux.com', 1);

			$core->forum_permissions($topic['forum_id']);

			// are we even allow to view this forum?
			if($parray['view'] == 0)
			{
				$core->message('You do not have permission to view this forum!');
			}

			else
			{
				if (isset($_GET['message']) && $_GET['message'] == 'reported')
				{
					$core->message('Thank you for reporting the post!');
				}

				// update topic views
				$db->sqlquery("UPDATE `forum_topics` SET `views` = (views +1) WHERE `topic_id` = ?", array($_GET['topic_id']));

				// count how many replies this topic has
				$db->sqlquery("SELECT `post_id` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));
				$total_replies = $db->num_rows();

				//lastpage = total pages / items per page, rounded up.
				if ($total_replies < $_SESSION['per-page'])
				{
					$lastpage = 1;
				}
				else
				{
					$lastpage = ceil($total_replies/$_SESSION['per-page']);
				}

				// paging for pagination
				if (isset($_GET['page']))
				{
					if ($_GET['page'] <= 0 || !is_numeric($_GET['page']))
					{
						$page = 1;
					}
					else if ($_GET['page'] <= $lastpage)
					{
						$page = $_GET['page'];
					}
					else
					{
						$page = $lastpage;
					}
				}
				else if (!isset($_GET['page']))
				{
					$page = 1;
				}

				// sort out edit link if its allowed
				$edit_link = '';
				if ($_SESSION['user_id'] == $topic['author_id'] || $user->check_group(1,2) == true)
				{
					$edit_link = "<li><a class=\"tooltip-top\" title=\"Edit\" href=\"/index.php?module=editpost&amp;topic_id={$topic['topic_id']}&page=$page\"><span class=\"icon edit\"></span></a></li>";
				}

				// update their subscriptions if they are reading the last page
				if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != 0)
				{
					$db->sqlquery("SELECT `topic_id`, `emails`, `send_email` FROM `forum_topics_subscriptions` WHERE `topic_id` = ? AND `user_id` = ?", array($_GET['topic_id'], $_SESSION['user_id']));
					$sub_counter = $db->num_rows();
					if ($sub_counter == 1)
					{
						$check_sub = $db->fetch();

						if ($_SESSION['email_options'] == 2 && $check_sub['send_email'] == 0)
						{
							// they have read all new comments (or we think they have since they are on the last page)
							if ($page == $lastpage)
							{
								// send them an email on a new comment again
								$db->sqlquery("UPDATE `forum_topics_subscriptions` SET `send_email` = 1 WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $_GET['topic_id']));
							}
						}
					}
				}

				// sort out the pagination link
				$pagination = $core->pagination_link($_SESSION['per-page'], $total_replies, "/forum/topic/{$_GET['topic_id']}/", $page);

				// find out if this user has subscribed to the comments
				if ($_SESSION['user_id'] != 0)
				{
					if ($sub_counter == 1)
					{
						$subscribe_link = "<a href=\"/index.php?module=viewtopic&amp;go=unsubscribe&amp;topic_id={$_GET['topic_id']}\"> <i class=\"icon-trash\"></i>Unsubscribe</a><br />";
					}

					else
					{
						$subscribe_link = "<a href=\"/index.php?module=viewtopic&amp;go=subscribe&amp;topic_id={$_GET['topic_id']}\"> <i class=\"icon-star\"></i>Subscribe</a><br />";
					}
				}

				// if they are a guest don't show them a link
				else
				{
					$subscribe_link = '';
				}

				// get the template, sort out the breadcrumb
				$templating->block('top', 'viewtopic');
				$templating->set('pagination', $pagination);
				$templating->set('forum_id', $topic['forum_id']);
				$templating->set('forum_name', $topic['forum_name']);

				if (core::config('pretty_urls') == 1)
				{
					$forum_index = '/forum/';
				}
				else
				{
					$forum_index = '/index.php?module=forum';
				}
				$templating->set('forum_index', $forum_index);

				// check notices
				$notices = array();
				if ($topic['is_locked'] == 1)
				{
					$notices[] = ' <strong>Locked</strong> ';
				}

				if ($topic['is_sticky'] == 1)
				{
					$notices[] = ' <strong>Sticky</strong> ';
				}

				$notice_html = '';
				if (!empty($notices))
				{
					foreach($notices as $notice)
					{
						$notice_html .= $notice;
					}

				}

				$templating->set('notice', $notice_html);

				$templating->set('topic_title', $topic['topic_title']);

				// find if there's a poll
				$show_results = 1;
				if (isset($_SESSION['user_id']))
				{
					$db->sqlquery("SELECT `poll_id`, `author_id`, `poll_question`, `topic_id`, `poll_open` FROM `polls` WHERE `topic_id` = ?", array($_GET['topic_id']));
					if ($poll_count = $db->num_rows() == 1)
					{
						if ($_SESSION['user_id'] != 0)
						{
							$grab_poll = $db->fetch();
							if ($grab_poll['poll_open'] == 1)
							{
								// find if they have voted or not
								$db->sqlquery("SELECT `user_id` FROM `poll_votes` WHERE `poll_id` = ? AND `user_id` = ?", array($grab_poll['poll_id'], $_SESSION['user_id']));

								// if they haven't voted
								if ($db->num_rows() == 0)
								{
									// don't show the results, let them vote!
									$show_results = 0;

									$templating->block('poll_vote');
									$templating->set('poll_question', $grab_poll['poll_question']);
									$options = '';
									$grab_options = $db->sqlquery("SELECT `option_id`, `poll_id`, `option_title` FROM `poll_options` WHERE `poll_id` = ?", array($grab_poll['poll_id']));
									foreach ($grab_options as $option)
									{
										$options .= '<li><button name="pollvote" class="poll_button_vote poll_button" data-poll-id="'.$option['poll_id'].'" data-option-id="'.$option['option_id'].'">'.$option['option_title'].'</button></li>';
									}
									$options .= '<li><button name="pollresults" class="poll_button results_button" data-poll-id="'.$option['poll_id'].'">View Results</button></li>';

									if ($_SESSION['user_id'] == $topic['author_id'])
									{
										$options .= '<li><button name="closepoll" class="poll_button close_poll" data-poll-id="'.$option['poll_id'].'">Close Poll</button></li>';
									}
									$templating->set('options', $options);
								}
							}
						}
					}
				}

				// show results as it's either closed, they are a guest, or they have voted already
				if ($show_results == 1 && $poll_count == 1)
				{
					$db->sqlquery("SELECT `poll_id`, `author_id`, `poll_question`, `topic_id`, `poll_open` FROM `polls` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$grab_poll = $db->fetch();

					$templating->block('poll_results');

					$db->sqlquery("SELECT `option_id`, `option_title`, `votes` FROM `poll_options` WHERE `poll_id` = ? ORDER BY `votes` DESC", array($grab_poll['poll_id']));
					$options = $db->fetch_all_rows();

					// see if they voted to make their option have a star * by the name
					if (isset($_SESSION['user_id']))
					{
						if ($_SESSION['user_id'] != 0)
						{
							$db->sqlquery("SELECT `user_id`, `option_id` FROM `poll_votes` WHERE `user_id` = ? AND `poll_id` = ?", array($_SESSION['user_id'], $grab_poll['poll_id']));
							$get_user = $db->fetch();
						}
					}

					$total_votes = 0;
					foreach ($options as $votes)
					{
						$total_votes = $total_votes + $votes['votes'];
					}

					$results = '';
					$star = '';
					foreach ($options as $option)
					{
						if (isset($_SESSION['user_id']))
						{
							if ($_SESSION['user_id'] != 0)
							{
								if ($option['option_id'] == $get_user['option_id'])
								{
									$star = '*';
								}
							}
						}
						$total_perc = round($option['votes'] / $total_votes * 100);
						$results .= '<div class="group"><div class="col-4">' . $star . $option['option_title'] . $star . '</div> <div class="col-4"><div style="background:#CCCCCC; border:1px solid #666666;"><div style="background: #28B8C0; width:'.$total_perc.'%;">&nbsp;</div></div></div> <div class="col-2">'.$option['votes'].' vote(s)</div> <div class="col-2">'.$total_perc.'%</div></div>';
						$star = '';
					}

					if ($grab_poll['poll_open'] == 1)
					{
						if ($_SESSION['user_id'] == $topic['author_id'])
						{
							$results .= '<ul style="list-style: none; padding:5px; margin: 0;"><li><button name="closepoll" class="close_poll" data-poll-id="'.$grab_poll['poll_id'].'">Close Poll</button></li></ul>';
						}
					}

					if ($grab_poll['poll_open'] == 0)
					{
						if ($_SESSION['user_id'] == $topic['author_id'])
						{
							$results .= '<ul style="list-style: none; padding:5px; margin: 0;"><li><button name="openpoll" class="open_poll" data-poll-id="'.$grab_poll['poll_id'].'">Open Poll</button></li></ul>';
						}
					}

					$templating->set('results', $results);
					$templating->set('poll_question', $grab_poll['poll_question']);
				}

				$pc_info = '';
				if ($topic['pc_info_public'] == 1)
				{
					if ($topic['pc_info_filled'] == 1)
					{
						$pc_info = '<a class="computer_deets fancybox.ajax" data-fancybox-type="ajax" href="/includes/ajax/call_profile.php?user_id='.$topic['author_id'].'">View PC info</a>';
					}
				}

				// if we are on the first page then show the initial topic post
				if ($page == 1)
				{
					$templating->block('topic', 'viewtopic');
					$templating->set('pc_info_link', $pc_info);
					$templating->set('topic_title', $topic['topic_title']);

					$topic_date = $core->format_date($topic['creation_date']);
					$templating->set('topic_date', $topic_date);
					$templating->set('tzdate', date('c',$topic['creation_date']) ); // timeago
					$templating->set('edit_link', $edit_link);
					$templating->set('subscribe_link', $subscribe_link);

					if ($topic['author_id'] != 0)
					{
						$username = "<a href=\"/profiles/{$topic['author_id']}\">{$topic['username']}</a>";
					}

					$into_username = '';
					if (!empty($topic['distro']) && $topic['distro'] != 'Not Listed')
					{
						$into_username .= "<img title=\"{$topic['distro']}\" class=\"distro tooltip-top\" alt=\"\" src=\"/templates/default/images/distros/{$topic['distro']}.svg\" />";

					}

					$templating->set('username', $into_username . $username);

					$cake_bit = $user->cake_day($topic['register_date'], $topic['username']);
					$templating->set('cake_icon', $cake_bit);

					$new_user = $user->new_user_badge($topic['register_date']);
					$templating->set('new_user_badge', $new_user);

					$new_user = $user->new_user_badge($topic['register_date']);
					$templating->set('new_user_badge', $new_user);

					// sort out the avatar
					$avatar = user::sort_avatar($topic);
					$templating->set('avatar', $avatar);

					$editor_bit = '';
					$donator_badge = '';
					$dev_badge = '';

					// check if editor or admin
					if ($topic['user_group'] == 1 || $topic['user_group'] == 2)
					{
						$editor_bit = "<li><span class=\"badge editor\">Editor</span></li>";
					}

					// check if accepted submitter
					if ($topic['user_group'] == 5)
					{
						$editor_bit = "<li><span class=\"badge editor\">Contributing Editor</span></li>";
					}

					if (($topic['secondary_user_group'] == 6 || $topic['secondary_user_group'] == 7) && $topic['user_group'] != 1 && $topic['user_group'] != 2)
					{
						$donator_badge = ' <li><span class="badge supporter">GOL Supporter</span></li>';
					}

					$developer_badge = '';

					if ($topic['game_developer'] == 1)
					{
						$developer_badge = ' <li><span class="badge yellow">Game Dev</span></li>';
					}

					$profile_fields_output = '';

					foreach ($profile_fields as $field)
					{
						if (!empty($topic[$field['db_field']]))
						{
								if ($field['db_field'] == 'website')
								{
									if (substr($topic[$field['db_field']], 0, 7) != 'http://')
									{
										$topic[$field['db_field']] = 'http://' . $topic[$field['db_field']];
									}
								}

								$url = '';
								if ($field['base_link_required'] == 1 && strpos($topic[$field['db_field']], $field['base_link']) === false ) //base_link_required and not already in the database
								{
									$url = $field['base_link'];
								}

								$image = '';
								if ($field['image'] != NULL)
								{
									$image = "<img src=\"{$field['image']}\" alt=\"{$field['name']}\" />";
								}

								$span = '';
								if (isset($field['span']))
								{
									$span = $field['span'];
								}
								$into_output = '';
								if ($field['name'] != 'Distro')
								{
									$into_output .= "<li><a href=\"$url{$topic[$field['db_field']]}\">$image$span</a></li>";
								}

								$profile_fields_output .= $into_output;
						}
					}

					$templating->set('profile_fields', $profile_fields_output);

					$templating->set('editor', $editor_bit);
					$templating->set('donator_badge', $donator_badge);
					$templating->set('game_developer', $developer_badge);

					$templating->set('post_id', $topic['topic_id']);
					$templating->set('topic_id', $topic['topic_id']);
					$templating->set('post_text', bbcode($topic['topic_text'], 0));

					$user_options = '';
					if ($_SESSION['user_id'] != 0)
					{
						$user_options = "<li><a class=\"tooltip-top\" title=\"Report\" href=\"" . core::config('website_url') . "index.php?module=report_post&view=reporttopic&topic_id={$topic['topic_id']}\"><span class=\"icon flag\">Flag</span></a></li><li><a class=\"tooltip-top quote_function\" title=\"Quote\" data-quote=\"".$topic['username']."\" data-comment=\"".htmlspecialchars($topic['topic_text'], ENT_QUOTES)."\"><span class=\"icon quote\">Quote</span></a></li>";
					}
					$templating->set('user_options', $user_options);
				}

				$reply_count = 0;

				/*
				REPLIES SECTION
				*/

				// FIND THE CORRECT PAGE IF THEY HAVE A LINKED COMMENT
				if (isset($_GET['post_id']) && is_numeric($_GET['post_id']))
				{
					// see if we are above their set limit per-page
					$db->sqlquery("SELECT `replys` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$count = $db->fetch();

					if ($count['replys'] > $_SESSION['per-page'])
					{
						$db->sqlquery("SELECT count(`post_id`) as counter FROM `forum_replies` WHERE `topic_id` = ? AND `post_id` <= ?", array($_GET['topic_id'], $_GET['post_id']));
						$number = $db->fetch();

						$last_page = ceil($number['counter']/$_SESSION['per-page']);

						if (core::config('pretty_urls') == 1)
						{
							header("Location: /forum/topic/{$_GET['topic_id']}/page=$last_page#{$_GET['post_id']}");
						}
						else
						{

							header("Location: /index.php?module=viewtopic&topic_id={$_GET['topic_id']}&page=$last_page#{$_GET['post_id']}");
						}
					}
					else
					{
						if (core::config('pretty_urls') == 1)
						{
							header("Location: /forum/topic/{$_GET['topic_id']}#{$_GET['post_id']}");
						}
						else
						{

							header("Location: /index.php?module=viewtopic&topic_id={$_GET['topic_id']}#{$_GET['post_id']}");
						}
					}
				}

				if ($topic['replys'] > 0)
				{

					$db_grab_fields = '';
					foreach ($profile_fields as $field)
					{
						$db_grab_fields .= "u.{$field['db_field']},";
					}

					$get_replies = $db->sqlquery("SELECT p.`post_id`, p.`author_id`, p.`reply_text`, p.`creation_date`, u.user_id, u.pc_info_public, u.register_date, u.pc_info_filled, u.distro, u.user_group, u.secondary_user_group, u.username, u.avatar, u.avatar_uploaded, u.avatar_gravatar, u.gravatar_email, u.avatar_gallery, $db_grab_fields u.forum_posts, u.game_developer FROM `forum_replies` p LEFT JOIN `users` u ON p.author_id = u.user_id WHERE p.`topic_id` = ? ORDER BY p.`creation_date` ASC LIMIT ?,{$_SESSION['per-page']}", array($_GET['topic_id'], $core->start));
					while ($post = $get_replies->fetch())
					{
						if ($page > 1 && $reply_count == 0)
						{
							$templating->block('reply_notopic', 'viewtopic');
							$templating->set('topic_title', $topic['topic_title']);
							$templating->set('subscribe_link', $subscribe_link);
						}

						else
						{
							$templating->block('reply', 'viewtopic');
						}

						$reply_date = $core->format_date($post['creation_date']);
						$templating->set('tzdate', date('c',$post['creation_date']) ); // timeago
						$templating->set('reply_date', $reply_date);

						$templating->set('page', $page);

						// sort out edit link if its allowed
						$edit_link = '';
						if ($_SESSION['user_id'] == $post['author_id'] || $user->check_group(1,2) == true)
						{
							$edit_link = '<li><a class="tooltip-top" title="Edit" href="' . core::config('website_url') . 'index.php?module=editpost&amp;post_id=' . $post['post_id'] . '&page=' . $page . '"><span class="icon edit"></span></a></li>';
						}
						$templating->set('edit_link', $edit_link);

						// sort out delete link if it's allowed
						$delete_link = '';
						if ($user->check_group(1,2) == true)
						{
							$delete_link = '<li><a class="tooltip-top" title="Delete" href="' . core::config('website_url') . 'index.php?module=viewtopic&amp;view=deletepost&amp;post_id=' . $post['post_id'] . '&amp;topic_id=' . $topic['topic_id'] . '"><span class="icon delete"></span></a>';
						}
						$templating->set('delete_link', $delete_link);

						if ($post['author_id'] != 0)
						{
							$username = "<a href=\"/profiles/{$post['author_id']}\">{$post['username']}</a>";
						}

						$into_username = '';
						if (!empty($post['distro']) && $post['distro'] != 'Not Listed')
						{
							$into_username .= "<img title=\"{$post['distro']}\" class=\"distro tooltip-top\" alt=\"\" src=\"/templates/default/images/distros/{$post['distro']}.svg\" />";
						}

						$cake_bit = $user->cake_day($post['register_date'], $post['username']);
						$templating->set('cake_icon', $cake_bit);

						$new_user = $user->new_user_badge($post['register_date']);
						$templating->set('new_user_badge', $new_user);

						$pc_info = '';
						if ($post['pc_info_public'] == 1)
						{
							if ($post['pc_info_filled'] == 1)
							{
								$pc_info = '<a class="computer_deets fancybox.ajax" data-fancybox-type="ajax" href="/includes/ajax/call_profile.php?user_id='.$post['author_id'].'">View PC info</a>';
							}
						}
						$templating->set('pc_info_link', $pc_info);

						$templating->set('username', $into_username . $username);

						$avatar = user::sort_avatar($post);
						$templating->set('avatar', $avatar);

						$editor_bit = '';
						$donator_badge = '';

						// check if editor or admin
						if ($post['user_group'] == 1 || $post['user_group'] == 2)
						{
							$editor_bit = "<li><span class=\"badge editor\">Editor</span></li>";
						}

						// check if accepted submitter
						if ($post['user_group'] == 5)
						{
							$editor_bit = "<li><span class=\"badge editor\">Contributing Editor</span></li>";
						}

						if (($post['secondary_user_group'] == 6 || $post['secondary_user_group'] == 7) && $post['user_group'] != 1 && $post['user_group'] != 2)
						{
							$donator_badge = '<li><span class="badge supporter">GOL Supporter</span></li>';
						}

						$developer_badge = '';

						if ($post['game_developer'] == 1)
						{
							$developer_badge = ' <li><span class="badge yellow">Game Dev</span></li>';
						}

						$profile_fields_output = '';

						foreach ($profile_fields as $field)
						{
							if (!empty($post[$field['db_field']]))
							{
								if ($field['db_field'] == 'website')
								{
									if (substr($post[$field['db_field']], 0, 7) != 'http://')
									{
										$post[$field['db_field']] = 'http://' . $post[$field['db_field']];
									}
								}

								$url = '';
								if ($field['base_link_required'] == 1)
								{
									$url = $field['base_link'];
								}

								$image = '';
								if ($field['image'] != NULL)
								{
									$image = "<img src=\"{$field['image']}\" alt=\"{$field['name']}\" />";
								}

								$span = '';
								if (isset($field['span']))
								{
									$span = $field['span'];
								}

								$into_output = '';
								if ($field['name'] != 'Distro')
								{
									$into_output .= "<li><a href=\"$url{$post[$field['db_field']]}\">$image$span</a></li>";
								}

								$profile_fields_output .= $into_output;
							}
						}

						$templating->set('profile_fields', $profile_fields_output);

						$templating->set('editor', $editor_bit);
						$templating->set('donator_badge', $donator_badge);
						$templating->set('game_developer', $developer_badge);

						$templating->set('post_text', bbcode($post['reply_text'], 0));
						$templating->set('post_id', $post['post_id']);
						$templating->set('topic_id', $_GET['topic_id']);

						$user_options = '';
						if ($_SESSION['user_id'] != 0)
						{
							$user_options = "<li><a class=\"tooltip-top\" title=\"Report\" href=\"" . core::config('website_url') . "index.php?module=report_post&view=reportreply&post_id={$post['post_id']}&topic_id={$_GET['topic_id']}\"><span class=\"icon flag\">Flag</span></a></li><li><a class=\"tooltip-top quote_function\" title=\"Quote\" data-quote=\"".$post['username']."\" data-comment=\"".htmlspecialchars($post['reply_text'], ENT_QUOTES)."\"><span class=\"icon quote\">Quote</span></a></li>";
						}
						$templating->set('user_options', $user_options);

						if (core::config('pretty_urls') == 1)
						{
							$post_link = '/forum/topic/' . $_GET['topic_id'] . '/post_id=' . $post['post_id'];
						}
						else
						{
							$post_link = '/index.php?module=viewtopic&topic_id=' . $_GET['topic_id'] . '&post_id=' . $post['post_id'];;

						}
						$templating->set('post_link', $post_link);

						$reply_count++;
					}
				}

				$templating->block('bottom', 'viewtopic');
				$templating->set('pagination', $pagination);

				// Sort out moderator options
				$options_count = 0;
				$options = '';
				$options_form = '';
				if ($parray['sticky'] == 1)
				{
					if ($topic['is_sticky'] == 1)
					{
						$options .= '<option value="unsticky">Unsticky Topic</option>';
					}

					else
					{
						$options .= '<option value="sticky">Sticky Topic</option>';

					}
					$options_count++;
				}

				if ($parray['lock'] == 1)
				{
					if ($topic['is_locked'] == 1)
					{
						$options .= '<option value="unlock">Unlock Topic</option>';
					}

					else
					{
						$options .= '<option value="lock">Lock Topic</option>';

					}
					$options_count++;
				}

				if ($parray['sticky'] == 1 && $parray['lock'] == 1)
				{
					if ($topic['is_locked'] == 1 && $topic['is_sticky'] == 0)
					{
						$options .= '<option value="bothunlock">Unlock & Sticky Topic</option>';
					}

					if ($topic['is_sticky'] == 1 && $topic['is_locked'] == 0)
					{
						$options .= '<option value="bothunsticky">Lock & Unsticky Topic</option>';
					}

					if ($topic['is_sticky'] == 1 && $topic['is_locked'] == 1)
					{
						$options .= '<option value="bothundo">Unlock & Unsticky Topic</option>';
					}

					if ($topic['is_sticky'] == 0 && $topic['is_locked'] == 0)
					{
						$options .= '<option value="both">Lock & Sticky Topic</option>';
					}

					$options_count++;
				}

				if ($parray['delete'] == 1)
				{
					$options .= '<option value="Delete">Delete</option>';
					$options_count++;
				}

				if ($parray['can_move'] == 1)
				{
					$options .= '<option value="Move">Move</option>';
					$options_count++;
				}

				if ($options_count > 0)
				{
					$options_form .= "<form method=\"post\" action=\"/index.php?module=viewtopic&amp;topic_id={$_GET['topic_id']}&forum_id={$topic['forum_id']}&author_id={$topic['author_id']}\"><strong>Standalone Moderator Options</strong><br />
					<select name=\"moderator_options\"><option value=\"\"></option>" . $options . "</select><br /><input type=\"submit\" name=\"act\" value=\"Go\" class=\"button\" /></form>";
					$templating->block('options', 'viewtopic');
					$templating->set('standalone_moderator_options', $options_form);
				}

				$reply_access = 0;

				// sort out the reply area (if it's allowed)
				if ($parray['reply'] == 1 && $topic['is_locked'] == 0)
				{
					$reply_access = 1;
				}

				else if ($parray['reply'] == 1 && $topic['is_locked'] == 1)
				{
					if ($user->check_group(1,2) == false)
					{
						$reply_access = 0;
					}

					else
					{
						$reply_access = 1;
					}
				}

				if (core::config('forum_posting_open') == 1)
				{
					if ((isset($_SESSION['user_group']) && $_SESSION['user_group'] == 4) || !isset($_SESSION['user_group']))
					{
						$templating->merge('login');
						$templating->block('small');
					}
					else
					{
						if ($reply_access == 1)
						{
							$subscribe_check = '';
							if ($_SESSION['auto_subscribe'] == 1 || $sub_counter == 1)
							{
								$subscribe_check = 'checked';
							}

							$subscribe_email_check = '';
							if ($_SESSION['auto_subscribe_email'] == 1 || (isset($sub_counter) && $sub_counter['emails'] == 1))
							{
								$subscribe_email_check = 'selected';
							}

							if (!isset($_SESSION['activated']))
							{
								$db->sqlquery("SELECT `activated` FROM `users` WHERE `user_id` = ?", array($_SESSION['user_id']));
								$get_active = $db->fetch();
								$_SESSION['activated'] = $get_active['activated'];
							}

							$templating->block('reply_top', 'viewtopic');

							if (isset($_SESSION['activated']) && $_SESSION['activated'] == 1)
							{
								$core->editor('text', '', $article_editor = 0, $disabled = 0, $anchor_name = 'commentbox', $ays_ignore = 1);

								$templating->block('reply_buttons', 'viewtopic');
								$templating->set('subscribe_check', $subscribe_check);
								$templating->set('subscribe_email_check', $subscribe_email_check);
								$templating->set('url', url);
								$templating->set('topic_id', $_GET['topic_id']);
								$templating->set('forum_id', $topic['forum_id']);

								$reply_options = 'Moderator options after posting: <select name="moderator_options"><option value=""></option>';
								$options_count = 0;

								if ($parray['sticky'] == 1)
								{
									if ($topic['is_sticky'] == 1)
									{
										$reply_options .= '<option value="unsticky">Unsticky Topic</option>';
									}

									else
									{
										$reply_options .= '<option value="sticky">Sticky Topic</option>';
									}
									$options_count++;
								}

								if ($parray['lock'] == 1)
								{
									if ($topic['is_locked'] == 1)
									{
										$reply_options .= '<option value="unlock">Unlock Topic</option>';
									}

									else
									{
										$reply_options .= '<option value="lock">Lock Topic</option>';
									}
									$options_count++;
								}

								if ($parray['sticky'] == 1 && $parray['lock'] == 1)
								{
									if ($topic['is_locked'] == 1 && $topic['is_sticky'] == 0)
									{
										$reply_options .= '<option value="bothunlock">Unlock & Sticky Topic</option>';
									}

									if ($topic['is_sticky'] == 1 && $topic['is_locked'] == 0)
									{
										$reply_options .= '<option value="bothunsticky">Lock & Unsticky Topic</option>';
									}

									if ($topic['is_sticky'] == 1 && $topic['is_locked'] == 1)
									{
										$reply_options .= '<option value="bothundo">Unlock & Unsticky Topic</option>';
									}

									if ($topic['is_sticky'] == 0 && $topic['is_locked'] == 0)
									{
										$reply_options .= '<option value="both">Lock & Sticky Topic</option>';
									}

									$options_count++;
								}

								if ($options_count > 0)
								{
									$reply_options .= '</select><br />';
								}

								// if they have no moderator abilitys then remove the select box altogether
								else
								{
									$reply_options = '';
								}

								$templating->set('moderator_options', $reply_options);
							}
							else
							{
								$core->message('To reply you need to activate your account! You were sent an email with instructions on how to activate. <a href="/index.php?module=activate_user&redo=1">Click here to re-send a new activation key</a>');
							}
						}
					}
				}
				else if (core::config('forum_posting_open') == 0)
				{
					$core->message('Posting is currently down for maintenance.');
				}
			}
		}
	}

	else if (isset($_GET['go']))
	{
		if ($_GET['go'] == 'subscribe')
		{
			// make sure we don't make lots of doubles
			$db->sqlquery("DELETE FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $_GET['topic_id']));

			// now subscribe
			$db->sqlquery("INSERT INTO `forum_topics_subscriptions` SET `user_id` = ?, `topic_id` = ?", array($_SESSION['user_id'], $_GET['topic_id']));

			header("Location: /forum/topic/{$_GET['topic_id']}");
		}

		if ($_GET['go'] == 'unsubscribe')
		{
			$db->sqlquery("DELETE FROM `forum_topics_subscriptions` WHERE `user_id` = ? AND `topic_id` = ?", array($_SESSION['user_id'], $_GET['topic_id']));

			header("Location: /forum/topic/{$_GET['topic_id']}");
		}
	}

	else if (isset($_POST['act']) && $_POST['act'] == 'Go')
	{
		$mod_sql = '';
		if (!empty($_POST['moderator_options']) && $user->check_group(1,2) == true)
		{
			if ($_POST['moderator_options'] == 'Move')
			{
				if (!isset($_POST['new_forum']))
				{
					$templating->block('move');

					$options = '';
					$db->sqlquery("SELECT `forum_id`, `name` FROM `forums` WHERE `forum_id` <> ? AND `is_category` = 0", array($_GET['forum_id']));
					while ($forums = $db->fetch())
					{
						$options .= "<option value=\"{$forums['forum_id']}\">{$forums['name']}</option>";
					}

					$templating->set('options', $options);
					$templating->set('topic_id', $_GET['topic_id']);
					$templating->set('old_forum_id', $_GET['forum_id']);
					$templating->set('author_id', $_GET['author_id']);
				}

				else
				{
					// count all the posts
					$db->sqlquery("SELECT `post_id` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$total_count = $db->num_rows() + 1;

					// remove count from current forum
					$db->sqlquery("UPDATE `forums` SET `posts` = (posts - ?) WHERE `forum_id` = ?", array($total_count, $_POST['old_forum_id']));

					// add to new forum
					$db->sqlquery("UPDATE `forums` SET `posts` = (posts + ?) WHERE `forum_id` = ?", array($total_count, $_POST['new_forum']));

					// update the topic
					$db->sqlquery("UPDATE `forum_topics` SET `forum_id` = ? WHERE `topic_id` = ?", array($_POST['new_forum'], $_GET['topic_id']));

					// finally check if this is the latest topic we are moving to update the latest topic info for the previous forum
					$db->sqlquery("SELECT `last_post_topic_id` FROM `forums` WHERE `forum_id` = ?", array($_POST['old_forum_id']));
					$last_post = $db->fetch();

					// if it is then we need to get the *now* newest topic and update the forums info
					if ($last_post['last_post_topic_id'] == $_GET['topic_id'])
					{
						$db->sqlquery("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `forum_id` = ?", array($_POST['old_forum_id']));
						$new_info = $db->fetch();

						$db->sqlquery("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $_POST['old_forum_id']));
					}

					// now we need to check if the topic being moved is newer than the new forums last post and update if needed
					$db->sqlquery("SELECT `last_post_time` FROM `forums` WHERE `forum_id` = ?", array($_POST['new_forum']));
					$last_post_new = $db->fetch();

					$db->sqlquery("SELECT `last_post_date` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$last_post_topic = $db->fetch();

					//
					if ($last_post_topic['last_post_date'] > $last_post_new['last_post_time'])
					{
						$db->sqlquery("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));
						$new_info = $db->fetch();

						$db->sqlquery("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $_POST['new_forum']));
					}

					// add to editor tracking
					$db->sqlquery("INSERT INTO `editor_tracking` SET `action` = ?, `time` = ?", array("{$_SESSION['username']} moved a forum topic.", core::$date));

					$core->message("The topic has been moved! Options: <a href=\"index.php?module=viewforum&amp;forum_id={$_POST['new_forum']}\">View Forum</a> or <a href=\"index.php?module=viewtopic&amp;topic_id={$_GET['topic_id']}\">View Topic</a>");
				}
			}

			else if ($_POST['moderator_options'] == 'Delete')
			{
				if (!isset($_POST['yes']) && !isset($_POST['no']))
				{
					$templating->set_previous('title', 'Deleting a forum topic', 1);
					$core->yes_no('Are you sure you want to delete that topic?', "index.php?module=viewtopic&topic_id={$_GET['topic_id']}&forum_id={$_GET['forum_id']}&author_id={$_GET['author_id']}", 'Go', 'Delete', 'moderator_options');
				}

				else if (isset($_POST['no']))
				{
					header("Location: /forum/topic/{$_GET['topic_id']}");
				}

				else if (isset($_POST['yes']))
				{
					// check if its been reported first so we can remove the report
					$db->sqlquery("SELECT `reported`, `replys` FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$check = $db->fetch();

					if ($check['reported'] == 1)
					{
						$db->sqlquery("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'forum_topic_report' AND `data` = ?", array(core::$date, $_GET['topic_id']));
					}

					// delete any replies that may have been reported from the admin notifications
					if ($check['replys'] > 0)
					{
						$db->sqlquery("SELECT `post_id`, `reported` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));
						$get_replies = $db->fetch_all_rows();

						foreach ($get_replies as $delete_replies)
						{
							if ($delete_replies['reported'] == 1)
							{
								$db->sqlquery("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'forum_reply_report', `data` = ?", array(core::$date, $delete_replies['post_id']));
							}
						}
					}

					$db->sqlquery("INSERT INTO `admin_notifications` SET `user_id` = ?, `completed` = 1, `type` = 'delete_forum_topic', `created_date` = ?, `completed_date` = ?, `data` = ?", array($_SESSION['user_id'], core::$date, core::$date, $_GET['topic_id']));

					// count all posts including the topic
					$db->sqlquery("SELECT `post_id` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$total_count = $db->num_rows() + 1;

					// Here we get each person who has posted along with their post count for the topic ready to remove it from their post count sql
					$db->sqlquery("SELECT `author_id` FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));
					$posts = $db->fetch_all_rows();

					$users_posts = array();
					foreach ($posts as $post)
					{
						$db->sqlquery("SELECT `post_id` FROM `forum_replies` WHERE `author_id` = ? AND `topic_id` = ?", array($post['author_id'], $_GET['topic_id']));
						$user_post_count = $db->num_rows();

						$users_posts[$post['author_id']]['author_id'] = $post['author_id'];
						$users_posts[$post['author_id']]['posts'] = $user_post_count;
					}

					// now we can remove the topic
					$db->sqlquery("DELETE FROM `forum_topics` WHERE `topic_id` = ?", array($_GET['topic_id']));

					// now we can remove all replys
					$db->sqlquery("DELETE FROM `forum_replies` WHERE `topic_id` = ?", array($_GET['topic_id']));

					// now update each users post count
					foreach($users_posts as $post)
					{
						$db->sqlquery("UPDATE `users` SET `forum_posts` = (forum_posts - ?) WHERE `user_id` = ?", array($post['posts'], $post['author_id']));
					}

					// remove a post from the topic author for the topic post itself
					$db->sqlquery("UPDATE `users` SET `forum_posts` = (forum_posts - 1) WHERE `user_id` = ?", array($_GET['author_id']));

					// now update the forums post count
					$db->sqlquery("UPDATE `forums` SET `posts` = (posts - ?) WHERE `forum_id` = ?", array($total_count, $_GET['forum_id']));

					// finally check if this is the latest topic we are deleting to update the latest topic info for the forum
					$db->sqlquery("SELECT `last_post_topic_id` FROM `forums` WHERE `forum_id` = ?", array($_GET['forum_id']));
					$last_post = $db->fetch();

					// if it is then we need to get the *now* newest topic and update the forums info
					if ($last_post['last_post_topic_id'] == $_GET['topic_id'])
					{
						$db->sqlquery("SELECT `topic_id`, `last_post_date`, `last_post_id` FROM `forum_topics` WHERE `forum_id` = ? ORDER BY `last_post_date` DESC LIMIT 1", array($_GET['forum_id']));
						$new_info = $db->fetch();

						$db->sqlquery("UPDATE `forums` SET `last_post_time` = ?, `last_post_user_id` = ?, `last_post_topic_id` = ? WHERE `forum_id` = ?", array($new_info['last_post_date'], $new_info['last_post_id'], $new_info['topic_id'], $_GET['forum_id']));
					}

					$core->message("That topic has now been deleted! <a href=\"/forum/{$_GET['forum_id']}/\">Click here to return to the forum</a>.");
				}
			}

			else
			{
				if ($_POST['moderator_options'] == 'sticky')
				{
					$mod_sql = '`is_sticky` = 1';
					$action = 'Stuck';
				}

				if ($_POST['moderator_options'] == 'unsticky')
				{
					$mod_sql = '`is_sticky` = 0';
					$action = 'Unstuck';
				}

				if ($_POST['moderator_options'] == 'lock')
				{
					$mod_sql = '`is_locked` = 1';
					$action = 'Locked';
				}

				if ($_POST['moderator_options'] == 'unlock')
				{
					$mod_sql = '`is_locked` = 0';
					$action = 'Unlocked';
				}

				if ($_POST['moderator_options'] == 'bothunlock')
				{
					$mod_sql = '`is_locked` = 0,`is_sticky` = 1';
					$action = 'Unlocked and Stuck';
				}

				if ($_POST['moderator_options'] == 'bothunsticky')
				{
					$mod_sql = '`is_locked` = 1,`is_sticky` = 0';
					$action = 'Locked and Unstuck';
				}

				if ($_POST['moderator_options'] == 'bothundo')
				{
					$mod_sql = '`is_locked` = 0,`is_sticky` = 0';
					$action = 'Unlocked and Unstuck';
				}

				if ($_POST['moderator_options'] == 'both')
				{
					$mod_sql = '`is_locked` = 1,`is_sticky` = 1';
					$action = 'Locked and Stuck';
				}

				// do the lock/stick action
				$db->sqlquery("UPDATE `forum_topics` SET $mod_sql WHERE `topic_id` = ?", array($_GET['topic_id']));

				// add to editor tracking
				$db->sqlquery("INSERT INTO `admin_notifications` SET `action` = ?, `created` = ?, `completed` = 1, `completed_date` = ?, `topic_id` = ?", array("{$_SESSION['username']} \"{$action}\" a forum topic.", core::$date, core::$date, $_GET['topic_id']));

				$core->message("You have {$action} the topic! <a href=\"/forum/topic/{$_GET['topic_id']}\">Click here to return.</a>");
			}
		}

		else if (!empty($_POST['moderator_options']))
		{
			$core->message('You must select an action to perform if you wish to do one!');
		}
	}
}
?>
