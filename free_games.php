<?php
define("APP_ROOT", dirname(__FILE__));

include(APP_ROOT . '/includes/header.php');

$game_sales = new game_sales($dbl, $templating, $user, $core);

$templating->set_previous('title', 'Free Linux games', 1);
$templating->set_previous('meta_description', 'Free Linux games', 1);

// TWITCH ONLINE INDICATOR
if (!isset($_COOKIE['gol_announce_gol_twitch'])) // if they haven't dissmissed it
{
	$templating->load('twitch_bar');
	$templating->block('main', 'twitch_bar');
}

$get_announcements = $dbl->run("SELECT `id`, `text`, `user_groups`, `type`, `modules`, `can_dismiss` FROM `announcements` ORDER BY `id` DESC")->fetch_all();
if ($get_announcements)
{
	$templating->load('announcements');
	$templating->block('announcement_top', 'announcements');
	foreach ($get_announcements as $announcement)
	{
		if (!isset($_COOKIE['gol_announce_'.$announcement['id']]))
		{
			$show = 0;
			
			// one to show to everyone (generic announcement)
			if ((empty($announcement['user_groups']) || $announcement['user_groups'] == NULL) && (empty($announcement['modules']) || $announcement['modules'] == NULL))
			{
				$show = 1;
			}
			// otherwise, we need to do some checks
			else
			{
				$module_show = 0;
				$group_show = 0;
				
				// check if the currently loaded module is allow to show it
				if (!empty($announcement['modules'] && $announcement['modules'] != NULL))
				{
					$modules_array = unserialize($announcement['modules']);
					
					if (in_array(core::$current_module['module_id'], $modules_array))
					{
						$module_show = 1;
					}
				}
				else
				{
					$module_show = 1;
				}
				
				// check their user group against the setting
				if (!empty($announcement['user_groups'] && $announcement['user_groups'] != NULL))
				{
					$group_ids_array = unserialize($announcement['user_groups']);
					
					// if this is to only be shown to specific groups, is the user in that group?
					if ($announcement['type'] == 'in_groups' && $user->check_group($group_ids_array) == true)
					{
						$group_show = 1;				
					}
					
					// if it's to only be shown if they aren't in those groups
					if ($announcement['type'] == 'not_in_groups' && $user->check_group($group_ids_array) == false)
					{
						$group_show = 1;			
					}
				}
				else
				{
					$group_show = 1;	
				}
			}
			
			if ($show == 1 || ($module_show == 1 && $group_show == 1))
			{
				$templating->block('announcement', 'announcements');
				$templating->set('text', $bbcode->parse_bbcode($announcement['text']));
				
				$dismiss = '';
				if ($announcement['can_dismiss'] == 1)
				{
					$dismiss = '<span class="fright"><a href="#" class="remove_announce" title="Hide Announcement" data-announce-id="'.$announcement['id'].'">&#10799;</a></span>';
				}
				$templating->set('dismiss', $dismiss);
			}
		}
	}

	$templating->block('announcement_bottom', 'announcements');
}

// let them know they aren't activated yet
if (isset($_GET['user_id']))
{
	if (!isset($_SESSION['activated']) && $_SESSION['user_id'] != 0)
	{
		$get_active = $dbl->run("SELECT `activated` FROM `".$dbl->table_prefix."users` WHERE `user_id` = ?", array($_SESSION['user_id']))->fetch();
		$_SESSION['activated'] = $get_active['activated'];
	}
}

if (isset($_SESSION['activated']) && $_SESSION['activated'] == 0)
{
	if ( (isset($_SESSION['message']) && $_SESSION['message'] != 'new_account') || !isset($_SESSION['message']))
	{
		$templating->block('activation', 'mainpage');
		$templating->set('url', $core->config('website_url'));
	}
}

if (isset($_SESSION['message']))
{
	$extra = NULL;
	if (isset($_SESSION['message_extra']))
	{
		$extra = $_SESSION['message_extra'];
	}
	$message_map->display_message('sales_page', $_SESSION['message'], $extra);
}

$templating->load('free_games');

$total_free = $dbl->run("SELECT COUNT(*) FROM `calendar` WHERE `free_game` = 1 AND `approved` = 1 AND `is_application` = 0 AND `also_known_as` IS NULL")->fetchOne();

$templating->block('top', 'free_games');
$templating->set('total', $total_free);

$game_sales->display_free();

$templating->block('filters', 'free_games');

// stores
$genres_res = $dbl->run("select count(*) as `total`, cat.category_name, cat.category_id from `calendar` c INNER JOIN `game_genres_reference` ref ON ref.game_id = c.id INNER JOIN `articles_categorys` cat ON cat.category_id = ref.genre_id where c.`free_game` = 1 group by cat.category_name, cat.category_id")->fetch_all();
$genres_output = '';
foreach ($genres_res as $genre)
{
	$checked = '';
	if (isset($filters_sort['genres']) && in_array($genre['category_id'], $filters_sort['genres']))
	{
		$checked = 'checked';
	}
	$total = '';
	if ($genre['total'] > 0)
	{
		$total = ' <small>('.$genre['total'].')</small>';
	}
	$genres_output .= '<label><input type="checkbox" name="genres[]" value="'.$genre['category_id'].'" '.$checked.'> '.$genre['category_name'].$total.'</label>';
}
$templating->set('genres_output', $genres_output);

$licenses = ['BSD', 'GPL', 'MIT', 'Closed Source'];
$licenses_output = '';
foreach ($licenses as $license)
{
	$checked = '';
	if (isset($filters_sort['license']) && in_array($license['id'], $filters_sort['license']))
	{
		$checked = 'checked';
	}
	$licenses_output .= '<label><input type="checkbox" name="licenses[]" value="'.$license.'" '.$checked.'> '.$license.'</label>';	
}
$templating->set('licenses_output', $licenses_output);

include(APP_ROOT . '/includes/footer.php');