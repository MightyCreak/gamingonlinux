<?php
$templating->set_previous('meta_description', 'There are ways you can help GamingOnLinux', 1);
$templating->set_previous('title', 'Support GamingOnLinux!', 1);

$templating->merge('support_us');
$templating->block('main');

$templating->block('list_top');

// get supporter list Sorted by last login
$res = $db->sqlquery("SELECT `username`, `user_id`, `avatar`, `gravatar_email`, `avatar_uploaded`,`avatar_gravatar`, `avatar_gallery`, `supporter_link` FROM `users` WHERE `secondary_user_group` IN (6,7) AND `user_group` != 1 AND `user_group` != 2 ORDER BY RAND() DESC LIMIT 9");

//Chop the results up in arrays of 3 users per row
$chucks = array_chunk($res->fetch_all_rows(PDO::FETCH_ASSOC), 3);

foreach ($chucks as $row)
{
	$templating->block('list_row_start');
	foreach ($row as $bb => $rowuser)
	{
		$templating->block('person');

		$templating->set('user_id', $rowuser['user_id']);
		$templating->set('username', $rowuser['username']);

		$avatar = user::sort_avatar($rowuser);
		$templating->set('avatarurl', $avatar);

		$supporter_link = '';
		if (!empty($rowuser['supporter_link']))
		{
			$supporter_link = "<a href=\"{$rowuser['supporter_link']}\">{$rowuser['supporter_link']}</a>";
		}
		$templating->set('supporter_link', $supporter_link);
	}
	$templating->block('list_row_end');
}
