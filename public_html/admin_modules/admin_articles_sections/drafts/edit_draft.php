<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}

$grab_author = $dbl->run("SELECT `article_id`, `author_id`, `tagline_image`, `text` FROM `articles` WHERE `article_id` = ?", array($_POST['article_id']))->fetch();
if ($grab_author['author_id'] == $_SESSION['user_id'])
{
	$title = strip_tags($_POST['title']);
	$tagline = trim($_POST['tagline']);
	$text = trim($_POST['text']);
	$slug = core::nice_title($_POST['slug']);

	$article_class->gallery_tagline($grab_author);

	$dbl->run("UPDATE `articles` SET `title` = ?, `slug` = ?, `tagline` = ?, `text`= ?, `show_in_menu` = 0 WHERE `article_id` = ?", array($title, $slug, $tagline, $text, $_POST['article_id']));

	$article_class->process_categories($_POST['article_id']);
	$article_class->process_games($_POST['article_id']);

	if (isset($_SESSION['uploads_tagline']) && $_SESSION['uploads_tagline']['image_rand'] == $_SESSION['image_rand'])
	{
		$core->move_temp_image($_POST['article_id'], $_SESSION['uploads_tagline']['image_name'], $text);
	}

	if ($grab_author['text'] != $text) // only update history if text is actually different
	{
		$dbl->run("INSERT INTO `article_history` SET `article_id` = ?, `user_id` = ?, `date` = ?, `text` = ?", array($_POST['article_id'], $_SESSION['user_id'], core::$date, $_SESSION['original_text']));
	}

	$article_class->reset_sessions();

	$_SESSION['message'] = 'edited';
	$_SESSION['message_extra'] = 'draft';
	header("Location: " . $core->config('website_url') . "admin.php?module=articles&view=drafts&aid={$_POST['article_id']}");
}
else
{
	header("Location: " . $core->config('website_url') . "admin.php?module=articles&view=drafts");
}
