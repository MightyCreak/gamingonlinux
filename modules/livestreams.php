<?php
$templating->set_previous('title', 'Livestreaming schedule', 1);
$templating->set_previous('meta_description', 'GamingOnLinux livestreaming schedule', 1);

if (!isset($_POST['act']))
{
	$templating->load('livestreams');

	$templating->block('top', 'livestreams');
	$edit_link = '';
	if ($user->check_group([1,2]))
	{
		$edit_link = '<span class="fright"><a href="admin.php?module=livestreams&amp;view=manage">Edit Livestreams</a></span>';
	}
	$templating->set('edit_link', $edit_link);

	$user_timezone = $user->user_details['timezone'];

	if (isset($_SESSION['user_id']) && $_SESSION['user_id'])
	{
		$templating->block('submit', 'livestreams');
		$timezones = core::timezone_list($user_timezone);
		$templating->set('timezones_list', $timezones);
	}

	$grab_streams = $dbl->run("SELECT `row_id`, `title`, `date`, `end_date`, `community_stream`, `streamer_community_name`, `stream_url` FROM `livestreams` WHERE NOW() < `end_date` AND `accepted` = 1 ORDER BY `date` ASC")->fetch_all();
	if ($grab_streams)
	{
		foreach ($grab_streams as $streams)
		{
			$templating->block('item', 'livestreams');

			$badge = '';
			if ($streams['community_stream'] == 1)
			{
				$badge = '<span class="badge blue">Community Stream</span>';
			}
			else if ($streams['community_stream'] == 0)
			{
				$badge = '<span class="badge editor">Official GOL Stream</span>';
			}
			$templating->set('badge', $badge);

			$stream_url = 'https://www.twitch.tv/gamingonlinux';
			if ($streams['community_stream'] == 1)
			{
				$stream_url = $streams['stream_url'];
			}
			$templating->set('stream_url', $stream_url);

			$templating->set('title', $streams['title']);
			
			$templating->set('local_time', core::adjust_time($streams['date'], 'UTC', $user_timezone));
			$templating->set('local_time_end', core::adjust_time($streams['end_date'], 'UTC', $user_timezone));

			$countdown = '<span id="timer'.$streams['row_id'].'"></span><script type="text/javascript">var timer' . $streams['row_id'] . ' = moment.tz("'.$streams['date'].'", "UTC"); $("#timer'.$streams['row_id'].'").countdown(timer'.$streams['row_id'].'.toDate(),function(event) {$(this).text(event.strftime(\'%D days %H:%M:%S\'));});</script>';
			$templating->set('countdown', $countdown);

			$streamer_list = [];
			$grab_streamers = $dbl->run("SELECT s.`user_id`, u.`username` FROM `livestream_presenters` s INNER JOIN `users` u ON u.`user_id` = s.`user_id` WHERE `livestream_id` = ?", array($streams['row_id']))->fetch_all();
			foreach ($grab_streamers as $streamer)
			{
				if ($core->config('pretty_urls') == 1)
				{
					$streamer_list[] = '<a href="/profiles/' . $streamer['user_id'] . '">'.$streamer['username'].'</a>';
				}
				else
				{
					$streamer_list[] = '<a href="/index.php?module=profile&user_id=' . $streamer['user_id'] . '">'.$streamer['username'].'</a>';
				}
			}

			if (!empty($streamer_list))
			{
				$streamer_list = implode(', ', $streamer_list);
				if (!empty($streams['streamer_community_name']))
				{
					$streamer_list .= ', ' . $streams['streamer_community_name'];
				}
			}
			else
			{
				$streamer_list = $streams['streamer_community_name'];
			}
			
			$templating->set('profile_links', $streamer_list);
	}
	}
	else
	{
		$core->message('There are no livestreams currently planned, or we forgot to update this page. Please <a href="https://www.gamingonlinux.com/forum/2">bug us to update it</a>!');
	}
}

if (isset($_POST['act']))
{
	if ($_POST['act'] == 'submit')
	{		
		if (!isset($_POST['yes']) && !isset($_POST['no']))
		{
			// wipe any old details
			unset($_SESSION['live_info']);
		
			$start_time = core::adjust_time($_POST['date'], $_POST['timezone'], 'UTC', 0);
			$end_time = core::adjust_time($_POST['end_date'], $_POST['timezone'], 'UTC', 0);
			$title = trim($_POST['title']);
			$title = strip_tags($title);
			$community_name = trim($_POST['community_name']);
			$community_name = strip_tags($community_name);
			$stream_url = trim($_POST['stream_url']);
			$stream_url = strip_tags($stream_url);
			
			$user_ids = [];
			if (isset($_POST['user_ids']) && !empty($_POST['user_ids']))
			{
				$user_ids = $_POST['user_ids'];
			}

			$empty_check = core::mempty(compact('title', 'start_time', 'end_time', 'stream_url'));
			
			if ($empty_check !== true)
			{
				$_SESSION['message'] = 'empty';
				$_SESSION['message_extra'] = $empty_check;
				header("Location: /index.php?module=livestreams");
				die();
			}
			
			// ask them to check their time before continuing
			$date1 = new DateTime($start_time);
			$date2 = new DateTime($end_time);
			$diff = $date2->diff($date1);
		
			$_SESSION['live_info'] = ['start' => $start_time, 'end' => $end_time, 'title' => $title, 'community_name' => $community_name, 'stream_url' => $stream_url, 'user_ids' => $user_ids];
		
			$confirmation_text = 'The stream will last ' . $diff->format('%a Day and %h Hours') . '<br />Start time: ' . $_POST['date'] . ' ('.$_POST['timezone'].')<br />End time: ' . $_POST['end_date'] . ' ('.$_POST['timezone'].')<br />Title: ' . $title . '<br />Stream url: ' . $stream_url;
			
			$core->confirmation(['title' => 'Please confirm these details are correct!', 'text' => $confirmation_text, 'act' => 'submit', 'action_url' => '/index.php?module=livestreams']);
		}

		else if (isset($_POST['no']))
		{
			header("Location: /index.php?module=livestreams");
		}

		else if (isset($_POST['yes']))
		{
			$date_created = core::$sql_date_now;

			$dbl->run("INSERT INTO `livestreams` SET `author_id` = ?, `accepted` = 0, `title` = ?, `date_created` = ?, `date` = ?, `end_date` = ?, `community_stream` = 1, `streamer_community_name` = ?, `stream_url` = ?", array($_SESSION['user_id'], $_SESSION['live_info']['title'], $date_created, $_SESSION['live_info']['start'], $_SESSION['live_info']['end'], $_SESSION['live_info']['community_name'], $_SESSION['live_info']['stream_url']));
			$new_id = $dbl->new_id();

			$core->process_livestream_users($new_id, $_SESSION['live_info']['user_ids']);
			
			unset($_SESSION['live_info']);

			$dbl->run("INSERT INTO `admin_notifications` SET `user_id` = ?, `type` = ?, `completed` = 0, `created_date` = ?, `data` = ?", array($_SESSION['user_id'], 'new_livestream_submission', core::$date, $new_id));

			$_SESSION['message'] = 'livestream_submitted';
			header("Location: /index.php?module=livestreams");
		}
	}
}
