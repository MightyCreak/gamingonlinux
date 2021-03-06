<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}

$text = trim($_POST['text']);

$title = strip_tags($_POST['title']);

// check its set, if not hard-set it based on the article title
if (isset($_POST['slug']) && !empty($_POST['slug']))
{
	$slug = core::nice_title($_POST['slug']);
}
else
{
	$slug = core::nice_title($_POST['title']);
}

$gallery_tagline_sql = $article_class->gallery_tagline();

$dbl->run("INSERT INTO `articles` SET `author_id` = ?, `title` = ?, `slug` = ?, `tagline` = ?, `text`= ?, `show_in_menu` = 0, `active` = 0, `draft` = 1, `date` = ?, `preview_code` = ? $gallery_tagline_sql", array($_SESSION['user_id'], $title, $slug, $_POST['tagline'], $text, core::$date, core::random_id()));

$article_id = $dbl->new_id();

$article_class->process_categories($article_id);
$article_class->process_games($article_id);

// force subscribe, so they don't lose editors comments
$secret_key = core::random_id(15);
$dbl->run("INSERT INTO `articles_subscriptions` SET `user_id` = ?, `article_id` = ?, `emails` = 1, `send_email` = 1, `secret_key` = ?", array($_SESSION['user_id'], $article_id, $secret_key));

// attach uploaded media to this article id
if (isset($_POST['uploads']))
{
	foreach($_POST['uploads'] as $key)
	{
		$dbl->run("UPDATE `article_images` SET `article_id` = ? WHERE `id` = ?", array($article_id, $key));
	}
}

if (isset($_SESSION['uploads_tagline']) && $_SESSION['uploads_tagline']['image_rand'] == $_SESSION['image_rand'])
{
	$core->move_temp_image($article_id, $_SESSION['uploads_tagline']['image_name'], $text);
}

// article has been posted, remove any saved info from errors (so the fields don't get populated if you post again)
unset($_SESSION['atitle']);
unset($_SESSION['atagline']);
unset($_SESSION['atext']);
unset($_SESSION['acategories']);
unset($_SESSION['agames']);
unset($_SESSION['uploads_tagline']);
unset($_SESSION['image_rand']);
unset($_SESSION['uploads']);
unset($_SESSION['original_text']);
unset($_SESSION['gallery_tagline_id']);
unset($_SESSION['gallery_tagline_rand']);
unset($_SESSION['gallery_tagline_filename']);

header("Location: admin.php?module=articles&view=drafts&message=saved&extra=draft");
