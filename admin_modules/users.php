<?php
if (!$user->check_group([1,2]))
{
	$core->message("You need to be an editor or an admin to access this section!", 1);
}
else
{
	$templating->load('admin_modules/users');

	if (isset($_GET['view']) && !isset($_POST['act']))
	{
		if ($_GET['view'] == 'search')
		{
			$templating->block('search','admin_modules/users');

			$username = '';
			$email = '';
			if (isset($_POST['search']))
			{
				if (isset($_POST['username']))
				{
					$username = trim($_POST['username']);
				}

				if (isset($_POST['email']))
				{
					$email = trim($_POST['email']);
				}

				if (!empty($username) || !empty($email))
				{
					if (isset($username) && !empty($username))
					{
						$search_res = $dbl->run("SELECT `user_id`, `username`, `banned`,`email` FROM `users` WHERE `username` LIKE ?", array('%'.$username.'%'))->fetch_all();
					}
					if (isset($email) && !empty($email))
					{
						$search_res = $dbl->run("SELECT `user_id`, `username`, `banned`,`email` FROM `users` WHERE `email` LIKE ?", array('%'.$email.'%'))->fetch_all();
					}

					$templating->block('search_row_top', 'admin_modules/users');
					if ($search_res)
					{
						foreach ($search_res as $found_user)
						{
							$templating->block('search_row','admin_modules/users');
							$templating->set('username', $found_user['username']);
							$templating->set('user_id', $found_user['user_id']);
							$templating->set('email', $found_user['email']);
						}
					}
					else
					{
						$core->message('No matching users found, sorry!');
					}
				}
			}
		}

		if ($_GET['view'] == 'premium')
		{
			$search_res = $dbl->run("SELECT u.`username`, u.`user_id`, u.`supporter_link`, u.`avatar`, u.`avatar_gravatar`, u.`gravatar_email`, u.`avatar_uploaded`, u.`avatar_gallery` FROM `users` u INNER JOIN `user_group_membership` g ON u.user_id = g.user_id WHERE g.group_id = 6 ORDER BY u.`username` ASC")->fetch_all();

			$total = count($search_res);
			$templating->block('premium_row_top');
			$templating->set('total', $total);

			foreach ($search_res as $search)
			{
				$templating->block('premium_row','admin_modules/users');
				$templating->set('username', $search['username']);
				$templating->set('user_id', $search['user_id']);
			}
		}

		if ($_GET['view'] == 'edituser')
		{
			if (!isset($_GET['user_id']) || isset($_GET['user_id']) && empty($_GET['user_id']))
			{
				$_SESSION['message'] = 'no_id';
				$_SESSION['message_extra'] = 'user id';
				header("Location: admin.php?module=users&view=search");
			}
			else
			{
				$templating->block('search','admin_modules/users');

				$user_info = $dbl->run("SELECT * FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

				$templating->block('edituser', 'admin_modules/users');

				if ($core->config('pretty_urls') == 1)
				{
					$profile_link = '/profiles/' . $user_info['user_id'];
				}
				else
				{
					$profile_link = '/index.php?module=profile&user_id='. $user_info['user_id'];
				}
				$templating->set('profile_link', $profile_link);

				$templating->set('user_id', $user_info['user_id']);
				$templating->set('username', $user_info['username']);
				$templating->set('email', $user_info['email']);
				$templating->set('website', $user_info['website']);
				$templating->set('bio', $user_info['article_bio']);

				$groups_list = '';
				
				$users_groups = $dbl->run("SELECT m.`group_id`, g.`group_name` FROM `user_group_membership` m INNER JOIN `user_groups` g ON m.group_id = g.group_id WHERE m.`user_id` = ?", [$user_info['user_id']])->fetch_all();

				foreach($users_groups as $group)
				{
					$groups_list .= "<option value=\"{$group['group_id']}\" selected>{$group['group_name']}</option>";
				}
				$templating->set('groups_list', $groups_list);
				
				$developer_check = '';
				if ($user_info['game_developer'] == 1)
				{
					$developer_check = 'checked';
				}
				$templating->set('developer_check', $developer_check);

				// sort out the avatar
				$comment_avatar = $user->sort_avatar($user_info);
				$templating->set('avatar', $comment_avatar);

				if ($user_info['banned'] == 1)
				{
					$ban_button = '<button type="submit" name="act" value="unban">UnBan User</button>';
					$templating->set('delete_content_button', "<button type=\"submit\" name=\"act\" value=\"delete_user_content\">Delete user content</button>");
				}

				else
				{
					$ban_button = '<button type="submit" name="act" value="ban">Ban User</button>';
				}

				$templating->set('ban_button', $ban_button);

				$grab_notes = $dbl->run("SELECT `notes` FROM `admin_user_notes` WHERE `user_id` = ?", array($_GET['user_id']))->fetchOne();

				$templating->set('admin_notes', $grab_notes);
			}
		}

		if ($_GET['view'] == 'ipbanmanage')
		{
			$templating->block('ipban');
			
			$ip_res = $dbl->run("SELECT `id`, `ip`, `ban_date` FROM `ipbans` ORDER BY `id` DESC")->fetch_all();
			if ($ip_res)
			{
				foreach ($ip_res as $ip)
				{
					$templating->block('iprow');
					$templating->set('ip', $ip['ip']);
					
					$banned_until = new DateTime($ip['ban_date']);
					$banned_until->add(new DateInterval('P'.$core->config('ip_ban_length').'D'));
					
					$templating->set('removal_date', $banned_until->format('Y-m-d H:i:s'));
					
					$templating->set('id', $ip['id']);
				}
			}
			else
			{
				$core->message('No IP bans are currently in place!');
			}
		}
		
		if ($_GET['view'] == 'groups')
		{
			$templating->load('admin_modules/user_groups');
			$templating->block('start_top');
			
			$groups = $dbl->run("SELECT `group_id`, `group_name` FROM `user_groups` ORDER BY `group_name` ASC")->fetch_all();
			foreach ($groups as $group)
			{
				$templating->block('group_row');
				$templating->set('name', $group['group_name']);
				$templating->set('id', $group['group_id']);
			}
		}
		
		if ($_GET['view'] == 'edit_group')
		{
			$group = $dbl->run("SELECT `group_id`, `group_name`, `show_badge`, `badge_text`, `badge_colour`, `remote_group` FROM `user_groups` WHERE `group_id` = ?", [$_GET['id']])->fetch();
			
			$permissions_list = $dbl->run("SELECT `id`, `name` FROM `user_group_permissions` ORDER BY `id` ASC")->fetch_all();
			
			$current_permissions = $dbl->run("SELECT `permission_id` FROM `user_group_permissions_membership` WHERE `group_id` = ?", [$_GET['id']])->fetch_all(PDO::FETCH_COLUMN);
			
			$templating->load('admin_modules/user_groups');
			$templating->block('group_edit');
			$templating->set('name', $group['group_name']);
			$show_badge = '';
			if ($group['show_badge'] == 1)
			{
				$show_badge = 'checked';
			}
			$templating->set('badge_check', $show_badge);
			$templating->set('colour', $group['badge_colour']);
			$templating->set('badge_text', $group['badge_text']);
			$remote_group = '';
			if ($group['remote_group'] == 1)
			{
				$remote_group = 'checked';
			}
			$templating->set('remote_check', $remote_group);
			
			$permission_rows = '';
			foreach ($permissions_list as $perm)
			{
				$checked = '';
				if (is_array($current_permissions) && in_array($perm['id'], $current_permissions))
				{
					$checked = 'checked';
				}
				
				$this_permission = $templating->block_store('permission_row');
				$permission_rows .= $templating->store_replace($this_permission, ['checked' => $checked, 'permission_name' => $perm['name'], 'permission_id' => $perm['id']]);
			}
			$templating->set('permissions_list', $permission_rows);
			$templating->block('group_bottom');
			$templating->set('group_id', $group['group_id']);
		}
	}

	else if (isset($_POST['act']))
	{
		if ($_POST['act'] == 'edituser')
		{
			$username = trim($_POST['username']);
			$email = trim($_POST['email']);

			$empty_check = core::mempty(compact('username', 'email'));
			if ($empty_check !== true)
			{
				$_SESSION['message'] = 'empty';
				$_SESSION['message_extra'] = $empty_check;
				header("Location: admin.php?module=users&view=edituser&user_id=" . $_GET['user_id']);
			}

			else
			{
				// check username isn't taken IF they are changing it
				$current_username = $dbl->run("SELECT `username` FROM `users` WHERE `user_id` = ?", [$_GET['user_id']])->fetchOne();
				if ($username != $current_username)
				{
					$check_username = $dbl->run("SELECT `username` FROM `users` WHERE `username` = ?", [$username])->fetchOne();
					if ($check_username)
					{
						$_SESSION['message'] = 'username_exists';
						header("Location: admin.php?module=users&view=edituser&user_id=" . $_GET['user_id']);
						die();
					}
				}

				$expires = 0;
				if (isset($_POST['expires']))
				{
					$expires = strtotime(gmdate($_POST['expires']));
				}

				$dev_check = 0;
				if (isset($_POST['game_developer']))
				{
					$dev_check = 1;
				}

				$dbl->run("UPDATE `users` SET `username` = ?, `email` = ?, `article_bio` = ?, `website` = ?, `game_developer` = ? WHERE `user_id` = ?", array($_POST['username'], $_POST['email'], $_POST['article_bio'], $_POST['website'], $dev_check, $_GET['user_id']));
				
				// user group updating
				$current_groups = $dbl->run("SELECT `group_id` FROM `user_group_membership` WHERE `user_id` = ?", [$_GET['user_id']])->fetch_all(PDO::FETCH_COLUMN);

				// remove any groups no longer wanted
				foreach ($current_groups as $key => $group)
				{
					if (!in_array($group, $_POST['user_groups']))
					{
						$dbl->run("DELETE FROM `user_group_membership` WHERE `user_id` = ? AND `group_id` = ?", [$_GET['user_id'], $group]);
					}
				}
				
				// add in any missing groups
				foreach ($_POST['user_groups'] as $key => $group)
				{
					if (!in_array($group, $current_groups))
					{
						$dbl->run("INSERT INTO `user_group_membership` SET `user_id` = ?, `group_id` = ?", [$_GET['user_id'], $group]);
					}
				}
					
				// make sure they have a row for notes, if not add a new row otherwise edit
				$note_res = $dbl->run("SELECT 1 FROM `admin_user_notes` WHERE `user_id` = ?", array($_GET['user_id']))->fetchOne();

				$notes = trim($_POST['notes']);

				if ($note_res)
				{
					$dbl->run("UPDATE `admin_user_notes` SET `notes` = ?, `last_edited` = ?, `last_edit_by` = ? WHERE `user_id` = ?", array($notes, core::$date, $_SESSION['user_id'], $_GET['user_id']));
				}
				else
				{
					$dbl->run("INSERT INTO `admin_user_notes` SET `notes` = ?, `last_edited` = ?, `last_edit_by` = ?, `user_id` = ?", array($notes, core::$date, $_SESSION['user_id'], $_GET['user_id']));
				}

				$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'edited_user', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_GET['user_id'], core::$date, core::$date));

				$_SESSION['message'] = 'edited';
				$_SESSION['message_extra'] = 'user account';
				header("Location: admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
			}
		}

		if ($_POST['act'] == 'ban')
		{
			$ban_info = $dbl->run("SELECT `username` FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

			if (!isset($_POST['yes']))
			{
				$core->yes_no("Are you sure you wish to ban {$ban_info['username']}?", "admin.php?module=users&user_id={$_GET['user_id']}", 'ban');
			}

			else
			{
				if ($_GET['user_id'] == 1)
				{
					$core->message("You cannot ban the main editor!", 1);
				}

				else
				{
					$dbl->run("UPDATE `users` SET `banned` = 1 WHERE `user_id` = ?", array($_GET['user_id']));

					$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'banned_user', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_GET['user_id'], core::$date, core::$date));

					$_SESSION['message'] = 'user_banned';
					header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
				}
			}
		}

		if ($_POST['act'] == 'unban')
		{
			$dbl->run("UPDATE `users` SET `banned` = 0 WHERE `user_id` = ?", array($_GET['user_id']));

			$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'unbanned_user', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_GET['user_id'], core::$date, core::$date));

			$_SESSION['message'] = 'user_unbanned';
			header('Location: /admin.php?module=users&view=edituser&user_id='.$_GET['user_id']);
		}

		if ($_POST['act'] == 'ipban')
		{
			if (empty($_POST['ip']))
			{
				$core->message('You need to enter an IP you wish to ban!');
			}

			else
			{
				$dbl->run("INSERT INTO `ipbans` SET `ip` = ?, `ban_date` = ?", array($_POST['ip'], core::$sql_date_now));

				$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'ip_banned', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_POST['ip'], core::$date, core::$date));

				$_SESSION['message'] = 'ip_ban';
				header("Location: /admin.php?module=users&view=ipbanmanage");
			}
		}

		if ($_POST['act'] == 'totalban')
		{
			$ban_info = $dbl->run("SELECT `username` FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

			if (!isset($_POST['yes']) && !isset($_POST['no']))
			{
				$_SESSION['ban_ip'] = $_POST['ip'];
				$core->yes_no("Are you sure you wish to ban {$ban_info['username']} and ban the IP associated with them?", "admin.php?module=users&user_id={$_GET['user_id']}", 'totalban');
			}

			else if (isset($_POST['no']))
			{
				header("Location: /profiles/{$_GET['user_id']}");
			}

			else
			{
				if ($_GET['user_id'] == 1)
				{
					$core->message("You cannot ban the main editor!", 1);
				}

				else
				{
					$dbl->run("UPDATE `users` SET `banned` = 1 WHERE `user_id` = ?", array($_GET['user_id']));
					$dbl->run("INSERT INTO `ipbans` SET `ip` = ?, `ban_date` = ?", array($_SESSION['ban_ip'], core::$sql_date_now));

					$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'total_ban', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_GET['user_id'], core::$date, core::$date));

					$_SESSION['message'] = 'user_banned';
					header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
					unset($_SESSION['ban_ip']);
				}
			}
		}

		if ($_POST['act'] == 'unipban')
		{
			$get_ip_ban = $dbl->run("SELECT `ip` FROM `ipbans` WHERE `id` = ?", array($_POST['id']))->fetch();

			$dbl->run("DELETE FROM `ipbans` WHERE `id` = ?", array($_POST['id']));

			$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'unban_ip', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $get_ip_ban['ip'], core::$date, core::$date));

			$_SESSION['message'] = 'ip_unban';
			header("Location: /admin.php?module=users&view=ipbanmanage");
		}

		if ($_POST['act'] == 'editipban')
		{
			if (!isset($_POST['act2']))
			{
				$templating->block('editip');

				$ip = $dbl->run("SELECT `id`, `ip`, `ban_date` FROM `ipbans` WHERE `id` = ?", array($_POST['id']))->fetch();

				$templating->set('ip', $ip['ip']);
				
				$banned_until = new DateTime($ip['ban_date']);
				$banned_until->add(new DateInterval('P'.$core->config('ip_ban_length').'D'));
					
				$templating->set('removal_date', $banned_until->format('Y-m-d H:i:s'));
				
				$templating->set('id', $ip['id']);
			}

			else if (isset($_POST['act2']) && $_POST['act2'] == 'go')
			{
				if (empty($_POST['ip']))
				{
					$core->message('You need to enter an IP you wish to ban!');
				}

				else
				{
					$dbl->run("UPDATE `ipbans` SET `ip` = ? WHERE `id` = ?", array($_POST['ip'], $_POST['id']));
					
					$_SESSION['message'] = 'edited';
					$_SESSION['message_extra'] = 'IP address ban';
					header("Location: /admin.php?module=users&view=ipbanmanage");
				}
			}
		}

		if ($_POST['act'] == 'deleteavatar')
		{
			// remove any old avatar if one was uploaded
			$avatar = $dbl->run("SELECT `avatar`, `avatar_uploaded`, `avatar_gravatar` FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

			if ($avatar['avatar_uploaded'] == 1)
			{
				unlink('uploads/avatars/' . $avatar['avatar']);
			}

			$dbl->run("UPDATE `users` SET `avatar` = '', `avatar_uploaded` = 0, `avatar_gravatar` = 0, `gravatar_email` = '' WHERE `user_id` = ?", array($_GET['user_id']));

			$_SESSION['message'] = 'deleted';
			$_SESSION['message_extra'] = 'avatar';
			header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}&message=deleted&extra=avatar");
		}

		if ($_POST['act'] == 'delete_user')
		{
			if (!isset($_POST['yes']) && !isset($_POST['no']))
			{
				$core->yes_no("Are you sure you wish to delete {$_POST['username']}? This CANNOT be undone, and this action is logged!", "admin.php?module=users&user_id={$_GET['user_id']}", 'delete_user');
			}

			else if (isset($_POST['no']))
			{
				header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
			}

			else
			{
				// remove any old avatar if one was uploaded
				$deleted_info = $dbl->run("SELECT `avatar`, `avatar_uploaded`, `avatar_gravatar`, `username` FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

				if ($deleted_info['avatar_uploaded'] == 1)
				{
					unlink('uploads/avatars/' . $deleted_info['avatar']);
				}

				$dbl->run("DELETE FROM `users` WHERE `user_id` = ?", array($_GET['user_id']));
				$dbl->run("DELETE FROM `user_profile_info` WHERE `user_id` = ?", [$_GET['user_id']]);
				$dbl->run("DELETE FROM `forum_topics_subscriptions` WHERE `user_id` = ?", array($_GET['user_id']));
				$dbl->run("DELETE FROM `articles_subscriptions` WHERE `user_id` = ?", array($_GET['user_id']));
				$dbl->run("DELETE FROM `user_conversations_info` WHERE `owner_id` = ?", array($_GET['user_id']));
				$dbl->run("DELETE FROM `user_conversations_participants` WHERE `participant_id` = ?", array($_GET['user_id']));
				$dbl->run("DELETE FROM `user_notifications` WHERE `owner_id` = ?", [$_GET['user_id']]);
				$dbl->run("UPDATE `articles_comments` SET `author_id` = 0 WHERE `author_id` = ?", [$_GET['user_id']]);
				$dbl->run("UPDATE `forum_topics` SET `author_id` = 0 WHERE `author_id` = ?", array($_GET['user_id']));
				$dbl->run("UPDATE `forum_replies` SET `author_id` = 0 WHERE `author_id` = ?", array($_GET['user_id']));
				$dbl->run("UPDATE `config` SET `data_value` = (data_value - 1) WHERE `data_key` = 'total_users'");
				$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = 'delete_user', `data` = ?, `completed` = 1, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $deleted_info['username'], core::$date, core::$date));

				$_SESSION['message'] = 'deleted';
				$_SESSION['message_extra'] = 'user account';
				header("Location: /admin.php?module=users&view=search");
			}
		}

		if ($_POST['act'] == 'delete_user_content')
		{
			if (!isset($_GET['user_id']))
			{
				$_SESSION['message'] = 'no_id';
				$_SESSION['message_extra'] = 'user id';
				header("Location: /admin.php?module=users&view=search&message=no_id&extra=user");
			}
			else
			{
				$check_ban = $dbl->run("SELECT `username`, `banned` FROM `users` WHERE `user_id` = ?", array($_GET['user_id']))->fetch();

				if ($check_ban['banned'] !== 1)
				{
					$core->message("This user is not banned. Deleting a users content is only possible if they are banned.");
				}
				else
				{
					if (!isset($_POST['yes']) && !isset($_POST['no']))
					{
						$core->yes_no("Are you sure you wish to delete all content from {$check_ban['username']}? This CANNOT be undone, and this action is logged!", "admin.php?module=users&user_id={$_GET['user_id']}", 'delete_user_content');
					}

					else if (isset($_POST['no']))
					{
						header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
					}
					else
					{
						// delete subscriptions and complete any reports on their forum topics
						$topics_subs_to_remove = $dbl->run("SELECT `topic_id`, `reported` FROM `forum_topics` WHERE `author_id` = ?", array($_GET['user_id']))->fetch_all();
						foreach ($topics_subs_to_remove as $key => $row)
						{
							$dbl->run("DELETE FROM `forum_topics_subscriptions` WHERE `topic_id` = ?", array( $row['topic_id'] ));
							if ($row['reported'] == 1)
							{
								$dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ?  WHERE `data` = ? AND `type` = 'forum_topic_report'", array(core::$date, $row['topic_id']));
							}
						}

						// complete any reports on their forum replies
						$topics_admin_notif_rep_to_remove = $dbl->run("SELECT `post_id`, `reported` FROM `forum_replies` WHERE `author_id` = ?", array($_GET['user_id']))->fetch_all();
						foreach ($topics_admin_notif_rep_to_remove as $key => $row)
						{
							if ($row['reported'] == 1)
							{
								$dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ?  WHERE `data` = ? AND `type` = 'forum_reply_report'", array(core::$date, $row['post_id']));
							}
						}

						// complete any comment reports for their comments
						$reported_comments = $dbl->run("SELECT `comment_id`, `spam` FROM `articles_comments` WHERE `author_id` = ?" , array($_GET['user_id']))->fetch_all();
						foreach ($reported_comments as $comment_loop)
						{
							if ($comment_loop['spam'] == 1)
							{
								$dbl->run("UPDATE `admin_notifications` SET `completed` = 1, `completed_date` = ? WHERE `type` = 'reported_comment' AND `data` = ?", array(core::$date, $comment_loop['comment_id']));
							}
						}

						// now do the actual deleting of the rest of their content
						$dbl->run("DELETE FROM `forum_replies` WHERE `author_id` = ?", array($_GET['user_id']));
						$dbl->run("DELETE FROM `forum_topics` WHERE `author_id` = ?", array($_GET['user_id']));
						$dbl->run("DELETE FROM `articles_comments` WHERE `author_id` = ?", array($_GET['user_id']));

						// alert admins this was done
						$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `completed` = 1, `type` = 'deleted_user_content', `data` = ?, `created_date` = ?, `completed_date` = ?", array($_SESSION['user_id'], $_GET['user_id'], core::$date, core::$date));

						$_SESSION['message'] = 'user_content_deleted';
						$_SESSION['message_extra'] = 'user content';
						header("Location: /admin.php?module=users&view=edituser&user_id={$_GET['user_id']}");
					}
				}
			}
		}
		
		if ($_POST['act'] == 'edit_group')
		{
			$show_badge = 0;
			if (isset($_POST['show_badge']))
			{
				$show_badge = 1;
			}
			$remote = 0;
			if (isset($_POST['remote_group']))
			{
				$remote = 1;
			}
			
			$dbl->run("UPDATE `user_groups` SET `group_name` = ?, `show_badge` = ?, `badge_text` = ?, `badge_colour` = ?, `remote_group` = ? WHERE `group_id` = ?", [$_POST['name'], $show_badge, $_POST['badge_text'], $_POST['badge_colour'], $remote, $_POST['group_id']]);
			
			// user group updating
			$current_permissions = $dbl->run("SELECT `permission_id` FROM `user_group_permissions_membership` WHERE `group_id` = ?", [$_POST['group_id']])->fetch_all(PDO::FETCH_COLUMN);

			// remove any permissions no longer wanted
			foreach ($current_permissions as $key => $permission)
			{
				if (!in_array($permission, $_POST['permissions']))
				{
					$dbl->run("DELETE FROM `user_group_permissions_membership` WHERE `permission_id` = ? AND `group_id` = ?", [$permission, $_POST['group_id']]);
				}
			}
			
			// add in any missing groups
			foreach ($_POST['permissions'] as $key => $permission)
			{
				if (!in_array($permission, $current_permissions))
				{
					$dbl->run("INSERT INTO `user_group_permissions_membership` SET `permission_id` = ?, `group_id` = ?", [$permission, $_POST['group_id']]);
				}
			}

			// update cache
			$groups_query = $dbl->run("SELECT `group_id`, `group_name`, `show_badge`, `badge_text`, `badge_colour` FROM `user_groups` ORDER BY `group_name` ASC")->fetch_all(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);		
			core::$redis->set('user_group_list', serialize($groups_query));	
			
			header('Location: admin.php?module=users&view=edit_group&id=' . $_POST['group_id']);
		}
	}
}
