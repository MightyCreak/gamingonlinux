<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}
include_once(APP_ROOT . '/includes/image_class/SimpleImage.php');
use claviska\SimpleImage;
$img = new SimpleImage();

$games_database = new game_sales($dbl, $templating, $user, $core);

$templating->set_previous('title', 'Linux Games & Software List', 1);
$templating->set_previous('meta_description', 'Linux Games & Software List', 1);

$templating->load('items_database');
$templating->block('quick_links');

if (!isset($_GET['view']) && !isset($_POST['act']))
{
	$core->message("This is not the page you're looking for!");
}

if (isset($_GET['view']))
{
	if ($_GET['view'] == 'developer')
	{
		if (!isset($_GET['id']) || !core::is_number($_GET['id']))
		{
			$_SESSION['message'] = 'no_id';
			$_SESSION['message_extra'] = 'item id';
			header("Location: /index.php");
			die();
		}
		
		// make sure they exist
		$check_dev = $dbl->run("SELECT `name`,`website` FROM `developers` WHERE `id` = ?", array($_GET['id']))->fetch();
		if (!$check_dev)
		{
			$_SESSION['message'] = 'none_found';
			$_SESSION['message_extra'] = 'developers matching that ID';
			header("Location: /index.php");
			die();			
		}

		$templating->set_previous('meta_description', 'GamingOnLinux Games & Software database: '.$check_dev['name'], 1);
		$templating->set_previous('title', $check_dev['name'], 1);	

		$templating->block('developer_list_top');
		$templating->set('dev_name', $check_dev['name']);

		if (!empty($check_dev['website']))
		{
			$templating->block('developer_website');
			$templating->set('link', '<a href="'.$check_dev['website'].'">'.$check_dev['website'].'</a>');
		}

		// look for some games
		$get_item = $dbl->run("SELECT c.`name`,c.`id` FROM `calendar` c JOIN `game_developer_reference` d WHERE d.game_id = c.id AND d.developer_id = ? ORDER BY `name` ASC", array($_GET['id']))->fetch_all();
		if ($get_item)
		{
			foreach ($get_item as $game)
			{
				$templating->block('dev_game_row');
				$templating->set('name', '<a href="/index.php?module=items_database&view=item&id='.$game['id'].'">'.$game['name'] . '</a>');
			}
		}	
		else
		{
			$core->message('No games were found in our database from that developer.');
		}
	}
	if ($_GET['view'] == 'item')
	{
		if (!isset($_GET['id']) || !core::is_number($_GET['id']))
		{
			$_SESSION['message'] = 'no_id';
			$_SESSION['message_extra'] = 'item id';
			header("Location: /index.php");
			die();
		}
		
		$templating->block('full_db_search');

		$json_extra = array();

		$links_sql = array();
		foreach ($games_database->main_links as $field => $name)
		{
			$links_sql[] = 'c.`'.$field.'`';
		}

		// make sure it exists
		$get_item = $dbl->run("SELECT ".implode(', ', $links_sql).", c.`id`, c.`steam_id`, c.`name`, c.`trailer`, c.`trailer_thumb`, c.`date`, c.`description`, c.`best_guess`, c.`is_dlc`, c.`free_game`, c.`bundle`, c.`license`, c.`supports_linux`, c.`is_hidden_steam`, c.`is_crowdfunded`, b.`name` as base_game_name, b.`id` as base_game_id, ge.engine_id, ge.engine_name FROM `calendar` c LEFT JOIN `calendar` b ON c.`base_game_id` = b.`id` LEFT JOIN `game_engines` ge ON ge.engine_id = c.game_engine_id WHERE c.`id` = ? AND c.`approved` = 1", array($_GET['id']))->fetch();
		if ($get_item)
		{
			// sort out the external links we have for it
			$external_links = '';
			$links_array = [];
			$link_types = $games_database->main_links;
			foreach ($link_types as $key => $text)
			{
				if (!empty($get_item[$key]) && $key != 'crowdfund_link')
				{
					$links_array[$key] = '<a href="'.$get_item[$key].'">'.$text.'</a>';
					$json_extra['links'][$key] = $get_item[$key];
				}
			}
			if ($get_item['steam_id'] != NULL && $get_item['steam_id'] != 0)
			{
				$links_array[] = '<a href="https://steamdb.info/app/'.$get_item['steam_id'].'/">SteamDB</a>';
				$links_array[] = '<a href="https://pcgamingwiki.com/api/appid.php?appid='.$get_item['steam_id'].'">PCGamingWiki</a>';

				$json_extra['links']['steamdb'] = 'https://steamdb.info/app/'.$get_item['steam_id'].'/';
				$json_extra['links']['PCGamingWiki'] = 'https://pcgamingwiki.com/api/appid.php?appid='.$get_item['steam_id'];
			}

			// sort out license
			$license_name = 'Not Listed';
			if (!empty($get_item['license']) || $get_item['license'] != NULL)
			{
				$license_name = $get_item['license'];
			}
			$license_output = '<li><strong>License</strong></li><li>' . $license_name . '</li>';

			$get_item['name'] = trim($get_item['name']);
			$articles_res = $dbl->run("SELECT a.`author_id`, a.`article_id`, a.`title`, a.`slug`, a.`date`, a.`guest_username`, u.`username` FROM `article_item_assoc` g LEFT JOIN `calendar` c ON c.id = g.game_id LEFT JOIN `articles` a ON a.article_id = g.article_id LEFT JOIN `users` u ON u.user_id = a.author_id WHERE c.name = ? AND a.active = 1 ORDER BY a.article_id DESC LIMIT 5", array($get_item['name']))->fetch_all();
			if ($articles_res)
			{
				$article_list = '';
				$article_json = array();
				
				foreach ($articles_res as $articles)
				{
					$article_link = $article_class->article_link(array('date' => $articles['date'], 'slug' => $articles['slug']));

					if ($articles['author_id'] == 0)
					{
						$username = $articles['guest_username'];
					}

					else
					{
						$username = "<a href=\"/profiles/{$articles['author_id']}\">" . $articles['username'] . '</a>';
					}

					$article_list .= '<li><a href="' . $article_link . '">'.$articles['title'].'</a> by '.$username.'<br />
					<small>'.$core->human_date($articles['date']).'</small></li>';
					$article_json[] = url . 'articles/' . $articles['article_id'];

				}
				if (count($articles_res) == 5)
				{
					$article_list .= '<li><a href="/index.php?module=search&appid='.$get_item['id'].'">View all tagged articles</a>.</li>';
				}
			}

			$game_engine_name = 'Not Listed';
			if (isset($get_item['engine_id']) && is_numeric($get_item['engine_id']))
			{
				$game_engine_name = $get_item['engine_name'];
			}
			$game_engine_output = '<li><strong>Made With</strong></li><li>'.$game_engine_name.'</li>';

			if (isset($_GET['json']))
			{
				header('Content-Type: application/json; charset=utf-8');

				$data = array('title' => $get_item['name'], 'GOL_page' => url . 'itemdb/'.$get_item['id'], 'supports_linux' => $get_item['supports_linux'], 'free_game' => $get_item['free_game'], 'is_dlc' => $get_item['is_dlc'], 'is_bundle' => $get_item['bundle'], 'license' => $license_name, 'game_engine' => $game_engine_name, 'was_crowdfunded' => $get_item['is_crowdfunded']);

				$data = array_merge($data, $json_extra);

				if (isset($article_json) && !empty($article_json))
				{
					$data['recent_articles'] = $article_json;
				}

				echo json_encode($data);
				die();
			}
			$templating->set_previous('meta_description', 'GamingOnLinux Games & Software database: '.$get_item['name'], 1);
			$templating->set_previous('title', $get_item['name'], 1);

			if ($get_item['supports_linux'] == 0 && !empty($get_item['steam_link']))
			{
				$core->message("Note: This item does not currently support Linux. You can try <a href=\"https://www.gamingonlinux.com/steamplay/\">Steam Play Proton</a> or Wine.", 2);
			}

			if ($get_item['is_hidden_steam'] == 1)
			{
				$core->message("This item is not advertised on Steam as supporting Linux, but it does have a Linux version.", 2);
			}

			$templating->block('item_view_top', 'items_database');
			$templating->set('name', htmlentities($get_item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

			// sort out price
			$top_badges = [];
			if ($get_item['free_game'] == 1)
			{
				$top_badges[] = '<span class="badge blue">FREE</span>';
			}
			if ($get_item['is_dlc'] == 1)
			{
				$top_badges[] = '<span class="badge yellow">DLC</span>';
			}
			if ($get_item['bundle'] == 1)
			{
				$top_badges[] = '<span class="badge green">Bundle</span>';
			}
			$templating->set('top_badges', implode(' ', $top_badges));

			$edit_link = '';
			if ($user->check_group([1,2,5]))
			{
				$edit_link = '<a class="fright" href="/admin.php?module=games&amp;view=edit&amp;id=' . $get_item['id'] . '&return=view_item">Edit</a>';
			}
			$templating->set('edit-link', $edit_link);

			if ($get_item['base_game_id'] != NULL && $get_item['base_game_id'] != 0)
			{
				$templating->block('base_game', 'items_database');
				$templating->set('base_game_id', $get_item['base_game_id']);
				$templating->set('base_game_name', $get_item['base_game_name']);
			}

			$templating->block('main-info', 'items_database');

			// get uploaded media
			$display_media = '';

			$trailer_id = NULL;
			if (!empty($get_item['trailer']))
			{
				preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $get_item['trailer'], $trailer_id);
			}

			$uploaded_media = $dbl->run("SELECT `filename` FROM `itemdb_images` WHERE `item_id` = ? AND `featured` = 0", array($get_item['id']))->fetch_all();
			if ($uploaded_media || $trailer_id)
			{
				$display_media .= '<div class="itemdb-media-container">';
				$max_images = 2;

				if ($trailer_id)
				{
					$trailer_thumbnail = '<img class src="/templates/default/images/youtube_cache_default.png" />';
					if (isset($get_item['trailer_thumb']) && !empty($get_item['trailer_thumb']))
					{
						$trailer_thumbnail = '<img src="'.$core->config('website_url').'uploads/gamesdb/big/thumbs/'.$get_item['id'].'/trailer_thumb.jpg" />';
					}
					else if ($uploaded_media)
					{
						if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/uploads/gamesdb/big/thumbs/'.$get_item['id'].'/trailer_thumb.jpg'))
						{
							$img->fromFile($_SERVER['DOCUMENT_ROOT'].'/uploads/gamesdb/big/thumbs/'.$get_item['id'].'/'.$uploaded_media[0]['filename'])->overlay($_SERVER['DOCUMENT_ROOT'].'/templates/default/images/playbutton.png')->toFile($_SERVER['DOCUMENT_ROOT'].'/uploads/gamesdb/big/thumbs/'.$get_item['id'].'/trailer_thumb.jpg', 'image/jpeg');
						}
						
						$trailer_thumbnail = '<img src="'.$core->config('website_url').'uploads/gamesdb/big/thumbs/'.$get_item['id'].'/trailer_thumb.jpg" />';
					}
					$display_media .= '<a data-caption="'.$get_item['name'].'" data-fancybox="images" href="https://www.youtube-nocookie.com/embed/'.$trailer_id[1].'">'.$trailer_thumbnail.'</a>';
					$max_images = 1;
				}

				$total_counter = 0;

				$flipped = array_reverse($uploaded_media); // so we don't see the same image used for trailer right next to it
				
				foreach ($flipped as $media)
				{
					$total_counter++;

					if ($total_counter <= $max_images)
					{
						$display_media .= '<a data-caption="'.$get_item['name'].'" data-fancybox="images" href="/uploads/gamesdb/big/'.$get_item['id'].'/'.$media['filename'].'"><img src="/uploads/gamesdb/big/thumbs/'.$get_item['id'].'/'.$media['filename'].'" /></a>';
					}

					if ($total_counter > $max_images)
					{
						$display_media .= '<a data-caption="'.$get_item['name'].'" data-fancybox="images" href="/uploads/gamesdb/big/'.$get_item['id'].'/'.$media['filename'].'"></a>';
					}
				}

				$display_media .= '</div>';
			}

			$templating->set('uploaded_media',$display_media);

			// parse the release date, with any info tags about it
			$date = '';
			if (!empty($get_item['date']))
			{
				$unreleased = '';
				if ($get_item['date'] > date('Y-m-d'))
				{
					$unreleased = '<br /><span class="badge blue">Unreleased!</span>';
				}
				$best_guess = '';
				if ($get_item['best_guess'] == 1)
				{
					$best_guess = '<span class="badge blue">Best Guess</span>';
				}
				$date = '<li>' . $get_item['date'] . ' ' . $best_guess . $unreleased . '</li>';
			}
			else
			{
				$date = '<li>Not currently known.</li>';
			}
			$templating->set('release-date', $date);

			$templating->set('license', $license_output);

			if (!empty($links_array))
			{
				$external_links = implode(', ', $links_array);
			}
			$templating->set('external_links', $external_links);
			$templating->set('game_engine', $game_engine_output);

			// sort out genres
			$genres_output = '';
			$genres_array = [];
			$genres_res = $dbl->run("SELECT g.`category_name`, g.`category_id` FROM `articles_categorys` g INNER JOIN `game_genres_reference` r ON r.genre_id = g.category_id WHERE r.`game_id` = ?", array($get_item['id']))->fetch_all();
			if ($genres_res)
			{
				$genres_output = '<li><strong>Genres</strong></li><li>';
				foreach ($genres_res as $genre)
				{
					$genres_array[] = $genre['category_name'];
				}
				$genres_output .= implode(', ', $genres_array);
				
				if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0)
				{
					$genres_output .= '<br /><small><a href="/index.php?module=items_database&view=suggest_tags&id='.$get_item['id'].'" target="_blank">Suggest Tags</a></small></li>';
				}
			}
			$templating->set('genres', $genres_output);		
			
			$developers_list = $dbl->run("SELECT r.developer_id,d.name FROM `game_developer_reference` r LEFT JOIN `developers` d ON r.developer_id = d.id WHERE r.game_id = ?", array($get_item['id']))->fetch_all();
			$dev_names = '';
			if (isset($developers_list) && !empty($developers_list))
			{
				$dev_names = array();
				foreach ($developers_list as $developer)
				{
					$dev_names[] = '<a href="/itemdb/developer/'.$developer['developer_id'].'">'.$developer['name'] . '</a>';
				}
		
				$dev_names = '<li><strong>Who made this?</strong></li><li>' . implode(', ', $dev_names) . '</li>';
			}
			$templating->set('devs_list', $dev_names);

			$description = '';
			if (!empty($get_item['description']) && $get_item['description'] != NULL)
			{
				$description = '<strong>About this game</strong>:<br />' . $get_item['description'];
			}
			$templating->set('description', $description);

			// crowdfunded?
			if ($get_item['is_crowdfunded'] == 1)
			{
				$templating->block('crowdfunded', 'items_database');
			}

			// find any associations
			$get_associations = $dbl->run("SELECT `name` FROM `calendar` WHERE `also_known_as` = ?", array($get_item['id']))->fetch_all();
			$same_games = array();
			if ($get_associations)
			{
				$templating->block('associations');
				foreach ($get_associations as $associations)
				{
					$same_games[] = $associations['name'];
				}
				$templating->set('games', implode(', ', $same_games));
			}

			// see if it's on sale
			$sales_res = $dbl->run("SELECT s.`link`, s.`sale_dollars`, s.`original_dollars`, s.`sale_pounds`, s.`original_pounds`, s.`sale_euro`, s.`original_euro`, st.`name` as `store_name` FROM `sales` s INNER JOIN `game_stores` st ON s.store_id = st.id WHERE s.accepted = 1 AND s.game_id = ?", [$get_item['id']])->fetch_all();
			if ($sales_res)
			{
				$templating->block('sales', 'items_database');
				$sales_list = '';
				$currencies = ['dollars', 'pounds', 'euro'];
				foreach ($sales_res as $sale)
				{
					$currency_list = [];
					foreach ($currencies as $currency)
					{
						$savings = '';
						if ($sale['sale_'.$currency] != NULL)
						{
							if ($sale['original_'.$currency] != 0)
							{
								$savings = 1 - ($sale['sale_'.$currency] / $sale['original_'.$currency]);
								$savings = round($savings * 100) . '% off';
							}
							$front_sign = NULL;
							$back_sign = NULL;
							if ($currency == 'dollars')
							{
								$front_sign = '&dollar;';
							}
							else if ($currency == 'euro')
							{
								$back_sign = '&euro;';
							}
							else if ($currency == 'pounds')
							{
								$front_sign = '&pound;';
							}
							$currency_list[] = '<span class="badge">'. $front_sign . $sale['sale_'.$currency] . $back_sign . ' ' . $savings . '</span>';
						}
					}
					$sales_list .= '<li><a href="' . $sale['link'] . '">'.$sale['store_name'].'</a> - '.implode(' ', $currency_list).'</li>';
				}
				$templating->set('sales_list', $sales_list);
			}

			if ($articles_res)
			{
				$templating->block('articles', 'items_database');
				$templating->set('articles', $article_list);
				$templating->set('item_id', $get_item['id']);
			}

			$templating->block('help_info', 'items_database');

			if ($user->check_group([1,2,5]))
			{
				$templating->block('editor_bottom', 'items_database');
				$templating->set('edit-link', $edit_link);
			}
			else
			{
				$templating->block('user_bottom', 'items_database');
			}
		}
		else
		{
			$templating->set_previous('meta_description', 'Game does not exist - GamingOnLinux Linux games database,', 1);
			$templating->set_previous('title', 'Game does not exist - GamingOnLinux Linux games database', 1);
			$core->message("That game id does not exist!", NULL, 1);
		}
	}
	if ($_GET['view'] == 'submit')
	{
		$templating->block('submit_picker');
	}
	if ($_GET['view'] == 'submit_dev')
	{
		$templating->block('submit_developer');
	}
	if ($_GET['view'] == 'suggest_tags')
	{
		if (isset($_GET['id']))
		{
			// check exists and grab info
			$get_item_res = $dbl->run("SELECT `id`, `name` FROM `calendar` WHERE `id` = ?", [$_GET['id']])->fetch();
			if ($get_item_res)
			{
				$templating->block('suggest_tags');
				$templating->set('name', $get_item_res['name']);
				$templating->set('id', $_GET['id']);

				$current_genres = 'None!';
				$get_genres = $core->display_game_genres($_GET['id'], false);
				if (is_array($get_genres))
				{
					$current_genres = implode(', ', $get_genres);
				}
				$templating->set('current_genres', $current_genres);
			}
		}
		else
		{
			$core->message("This is not the page you're looking for!");
		}
	}
	if ($_GET['view'] == 'submit_item')
	{
		$templating->block('submit_item');

		// there was an error on submission, populate fields with previous data
		if (isset($message_map::$error) && $message_map::$error >= 1 && isset($_SESSION['item_post_data']))
		{
			//print_r($_SESSION['item_post_data']);
			foreach ($_SESSION['item_post_data'] as $key => $previous_data)
			{
				if ($previous_data == 'on')
				{
					$previous_data = 'checked';
				}
				$templating->set($key, $previous_data);
			}
		}
		// clear any old info left in session from previous error when submitting, make fields blank
		else
		{
			unset($_SESSION['item_post_data']); 
			$templating->set_many(array('name' => '', 'link' => '', 'steam_link' => '', 'gog_link' => '', 'itch_link' => '', 'supports_linux' => 'checked', 'hidden_steam' => ''));
		}

		$types_options = '';
		$item_types = array('is_game' => 'Game', 'is_application' => 'Misc Software or Application', 'is_emulator' => 'Emulator');
		foreach ($item_types as $key => $value)
		{
			$selected = '';
			if (isset($_SESSION['item_post_data']['type']) && $_SESSION['item_post_data']['type'] == $key)
			{
				$selected = 'selected';
			}
			$types_options .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
		}
		$templating->set('types_options', $types_options);

		$licenses = $dbl->run("SELECT `license_name` FROM `item_licenses` ORDER BY `license_name` ASC")->fetch_all();
		$license_options = '';
		foreach ($licenses as $license)
		{
			$selected = '';
			if (isset($_SESSION['item_post_data']['license']) && $_SESSION['item_post_data']['license'] == $license['license_name'])
			{
				$selected = 'selected';
			}
			$license_options .= '<option value="'.$license['license_name'].'" '.$selected.'>'.$license['license_name'].'</option>';
		}
		$templating->set('license_options', $license_options);

		// game name if DLC
		if (isset($_SESSION['item_post_data']['game']))
		{
			$check_exists = $dbl->run("SELECT `name` FROM `calendar` WHERE `id` = ?", array($_SESSION['item_post_data']['game']))->fetch();
			if ($check_exists)
			{
				$game = '<option value="'.$_SESSION['item_post_data']['game'].'">'.$check_exists['name'].'</option>';
			}
		}
		else
		{
			$game = '';
		}
		$templating->set('game', $game);

		// game tags
		$genre_ids = '';
		if (isset($_SESSION['item_post_data']['genre_ids']))
		{
			$in  = str_repeat('?,', count($_SESSION['item_post_data']['genre_ids']) - 1) . '?';
			$grab_genres = $dbl->run("SELECT `category_name`, `category_id` FROM `articles_categorys` WHERE `category_id` IN ($in)", $_SESSION['item_post_data']['genre_ids'])->fetch_all();
			foreach($grab_genres as $genre)
			{
				$genre_ids .= '<option value="'.$genre['category_id'].'" selected>'.$genre['category_name'].'</option>';
			}
		}
		$templating->set('genre_ids', $genre_ids);
	}
}

if (isset($_POST['act']))
{
	if ($_POST['act'] == 'suggest_tags')
	{
		if (isset($_POST['id']))
		{
			// check exists and grab info
			$get_item_res = $dbl->run("SELECT `name` FROM `calendar` WHERE `id` = ?", [$_POST['id']])->fetch();
			if ($get_item_res)
			{
				// get fresh list of suggestions, and insert any that don't exist
				$current_suggestions = $dbl->run("SELECT `genre_id` FROM `game_genres_suggestions` WHERE `game_id` = ?", array($_POST['id']))->fetch_all(PDO::FETCH_COLUMN, 0);

				// get fresh list of current tags, insert any that don't exist
				$current_genres = $dbl->run("SELECT `genre_id` FROM `game_genres_reference` WHERE `game_id` = ?", array($_POST['id']))->fetch_all(PDO::FETCH_COLUMN, 0);				

				if (isset($_POST['genre_ids']) && !empty($_POST['genre_ids']) && core::is_number($_POST['genre_ids']))
				{
					$total_added = 0;
					foreach($_POST['genre_ids'] as $genre_id)
					{
						if (!in_array($genre_id, $current_suggestions) && !in_array($genre_id, $current_genres))
						{
							$total_added++;
							$dbl->run("INSERT INTO `game_genres_suggestions` SET `game_id` = ?, `genre_id` = ?, `suggested_time` = ?, `suggested_by_id` = ?", array($_POST['id'], $genre_id, core::$date, $_SESSION['user_id']));
						}
					}
				}

				if ($total_added > 0)
				{
					$core->new_admin_note(['complete' => 0, 'type' => 'submitted_game_genre_suggestion', 'content' => 'submitted a genre suggestion for an item in the database.', 'data' => $_POST['id']]);
				}

				$core->message('Your tag suggestions for ' . $get_item_res['name'] . ' have been submitted! Thank you!');
			}
			else
			{
				$core->message("This is not the page you're looking for!");
			}
		}
	}
	if ($_POST['act'] == 'submit_item')
	{
		// quick helper func for below
		function filter($value) 
		{
			$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		}

		// in case of form errors, set it in the session to use it again
		foreach ($_POST as $key => $data)
		{
			if (!is_array($data))
			{
				$_SESSION['item_post_data'][$key] = htmlspecialchars($data);
			}
			else
			{
				array_walk_recursive($data, "filter");
				$_SESSION['item_post_data'][$key] = $data;
			}
		}
		$name = strip_tags(trim($_POST['name']));

		// deal with the links, make sure not empty and validate
		$links = array('link', 'steam_link', 'gog_link', 'itch_link');
		$empty_check = 0;
		foreach ($links as $link)
		{
			$_POST[$link] = trim($_POST[$link]);

			// make doubly sure it's an actual URL, if not make it blank
			if (!filter_var($_POST[$link], FILTER_VALIDATE_URL)) 
			{
				$_POST[$link] = '';
			}

			if (!empty($_POST[$link]))
			{
				$empty_check = 1;
			}
		}
		if ($empty_check == 0)
		{
			$_SESSION['message'] = 'one_link_needed';
			header("Location: /index.php?module=items_database&view=submit_item");
			die();
		}
		
		// make sure its not empty
		$empty_check = core::mempty(compact('name'));
		if ($empty_check !== true)
		{
			$_SESSION['message'] = 'empty';
			$_SESSION['message_extra'] = $empty_check;
			header("Location: /index.php?module=items_database&view=submit_item");
			die();
		}

		// make sure it doesn't exist already
		$add_res = $dbl->run("SELECT `id`, `name` FROM `calendar` WHERE `name` = ?", array($_POST['name']))->fetch();
		if ($add_res)
		{
			$_SESSION['message'] = 'item_submit_exists';
			header("Location: /index.php?module=items_database&view=submit_item");
			die();
		}

		// this needs finishing
		$check_similar = $dbl->run("SELECT `id`, `name` FROM `calendar` WHERE `name` LIKE ?", array('%'.$_POST['name'].'%'))->fetch_all();
		if ($check_similar)
		{
			
		}

		$types = ['is_game', 'is_application', 'is_emulator'];
		$sql_type = '';
		if (!in_array($_POST['type'], $types))
		{
			$_SESSION['message'] = 'no_item_type';
			header("Location: /index.php?module=items_database&view=submit_item");
			die();
		}
		else
		{
			$sql_type = '`'.$_POST['type'].'` = 1, ';
		}

		$supports_linux = 0;
		if (isset($_POST['supports_linux']))
		{
			$supports_linux = 1;
		}

		$hidden_steam = 0;
		if (isset($_POST['hidden_steam']))
		{
			$hidden_steam = 1;
		}

		$free = 0;
		if (isset($_POST['free']))
		{
			$free = 1;
		}

		$dlc = 0;
		if (isset($_POST['dlc']))
		{
			$dlc = 1;
		}

		$base_game = NULL;
		if (isset($_POST['game']) && is_numeric($_POST['game']))
		{
			$base_game = $_POST['game'];
		}

		$license = NULL;
		if (!empty($_POST['license']))
		{
			$license = $_POST['license'];
		}

		$dbl->run("INSERT INTO `calendar` SET `name` = ?, `link` = ?, `steam_link` = ?, `gog_link` = ?, `itch_link` = ?, `approved` = 0, `is_dlc` = ?, `base_game_id` = ?, `free_game` = ?, $sql_type `license` = ?, `supports_linux` = ?, `is_hidden_steam` = ?", array($name, $_POST['link'], $_POST['steam_link'], $_POST['gog_link'], $_POST['itch_link'], $dlc, $base_game, $free, $license, $supports_linux, $hidden_steam));
		$new_id = $dbl->new_id();

		$core->process_game_genres($new_id);

		$core->new_admin_note(['complete' => 0, 'type' => 'item_database_addition', 'content' => 'submitted a new item for the games database.', 'data' => $new_id]);

		unset($_SESSION['item_post_data']);

		$_SESSION['message'] = 'item_submitted';
		$_SESSION['message_extra'] = $name;
		header("Location: /index.php?module=items_database&view=submit_item");		
	}

	if ($_POST['act'] == 'submit_dev')
	{
		// make sure its not empty
		$name = trim(strip_tags($_POST['name']));
		if (empty($name))
		{
			$_SESSION['message'] = 'empty';
			$_SESSION['message_extra'] = 'developer/publisher name';
			header("Location: /index.php?module=items_database&view=submit_dev");
			die();
		}
		
		$link = trim($_POST['link']);

		$add_res = $dbl->run("SELECT `name` FROM `developers` WHERE `name` = ?", array($name))->fetch();
		if ($add_res)
		{
			$_SESSION['message'] = 'dev_submit_exists';
			header("Location: /index.php?module=items_database&view=submit_dev");
			die();
		}

		$dbl->run("INSERT INTO `developers` SET `name` = ?, `website` = ?, `approved` = 0", [$name, $link]);

		$new_id = $dbl->new_id();

		$core->new_admin_note(['complete' => 0, 'type' => 'dev_database_addition', 'content' => 'submitted a new developer for the games database.', 'data' => $new_id]);

		$_SESSION['message'] = 'dev_submitted';
		$_SESSION['message_extra'] = $name;
		header("Location: /index.php?module=items_database&view=submit_dev");			
	}
}