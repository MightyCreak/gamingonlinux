<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

define("APP_ROOT", dirname(__FILE__));

// we dont need the whole bootstrap
require dirname(__FILE__) . "/includes/loader.php";
include dirname(__FILE__) . '/includes/config.php';
$dbl = new db_mysql();
$core = new core($dbl);

if (isset($_GET['id']) && is_numeric($_GET['id']))
{
	$public = $dbl->run("SELECT `pc_info_public` FROM `users` WHERE `user_id` = ?", [$_GET['id']])->fetchOne();

	if ($public == 1)
	{
		$user_info = $dbl->run("SELECT u.`distro`, u.`username`, i.`desktop_environment`, i.`gpu_vendor`, i.`cpu_vendor`, i.`cpu_model`, g.`id` AS `gpu_id`, g.`name` AS `gpu_model` FROM `users` u LEFT JOIN `user_profile_info` i ON u.user_id = i.user_id LEFT JOIN `gpu_models` g ON g.id = i.gpu_model WHERE u.`user_id` = ?", [$_GET['id']])->fetch();

		$username = $user_info['username'];

		$desktop_text = '';
		if (!empty($user_info['distro']))
		{
			$desktop_text = $user_info['distro'];
			if (!empty($user_info['desktop_environment']))
			{
				$desktop_text .= ' : ' . $user_info['desktop_environment'];
			}
		}

		$gpu = '';
		if (!empty($user_info['gpu_vendor']))
		{
			$gpu = $user_info['gpu_vendor'];
			if (is_numeric($user_info['gpu_id']))
			{
				$gpu .= ' : ' . $user_info['gpu_model'];
			}
		}

		$cpu = '';
		if (!empty($user_info['cpu_vendor']))
		{
			$cpu = $user_info['cpu_vendor'];
			if (!empty($user_info['cpu_model']))
			{
				$cpu .= ' : ' . $user_info['cpu_model'];
			}
		}

		$base_image = imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/signature.png');

		$distro_image_picker = 'linux_icon';
		if (!empty($user_info['distro']))
		{
			$distro_image_picker = $user_info['distro'];
		}

		$distro = @imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/distros/' . $distro_image_picker . '.png');

		if (!$distro)
		{
			// if it didn't work, they might have somehow picked a distro image we don't have, so force the standard Linux "tux" image
			$distro = @imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/distros/linux_icon.png');
		}

		// only do this if it actually worked
		if ($distro)
		{
			imagecopy($base_image, $distro, 5, (imagesy($base_image)/2)-(imagesy($distro)/2)+5, 0, 0, imagesx($distro), imagesy($distro));
		}

		$text_colour = imagecolorallocate($base_image, 0, 0, 0);
		$width = imagesx($base_image);
		$height = imagesy($base_image);
		putenv('GDFONTPATH=' . realpath('.'));
		$font = 'Ubuntu-L.ttf';

		imagettftext($base_image, 11, 0, 257, 14, $text_colour, $font, 'GamingOnLinux');

		imagettftext($base_image, 11, 0, 3, 14, $text_colour, $font, $username);
		if (!empty($desktop_text))
		{
			imagettftext($base_image, 11, 0, 45, 30, $text_colour, $font, $desktop_text);
		}
		if (!empty($gpu))
		{
			imagettftext($base_image, 11, 0, 45, 45, $text_colour, $font, $gpu);
		}
		if (!empty($cpu))
		{
			imagettftext($base_image, 11, 0, 45, 60, $text_colour, $font, $cpu);
		}

		header('Content-Type: image/png');
		imagepng($base_image);
	}
	else
	{
		$base_image = imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/signature.png');
		$distro = @imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/distros/linux_icon.png');
		imagecopy($base_image, $distro, (imagesx($base_image)/2)-(imagesx($distro)/2), (imagesy($base_image)/2)-(imagesy($distro)/2), 0, 0, imagesx($distro), imagesy($distro));
		$text_colour = imagecolorallocate($base_image, 0, 0, 0);
		$height = imagesy($base_image);
		$font = 4;
		imagestring($base_image, $font, 1, $height-70, "ERROR: That users PC info is not public!", $text_colour);
		imagestring($base_image, $font, 260, $height-20, 'GamingOnLinux', $text_colour);
		header('Content-Type: image/png');
		imagepng($base_image);
	}
}
else
{
	$base_image = imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/signature.png');
	$distro = @imagecreatefrompng(APP_ROOT . '/templates/'.$core->config('template').'/images/distros/linux_icon.png');
	imagecopy($base_image, $distro, (imagesx($base_image)/2)-(imagesx($distro)/2), (imagesy($base_image)/2)-(imagesy($distro)/2), 0, 0, imagesx($distro), imagesy($distro));
	$text_colour = imagecolorallocate($base_image, 0, 0, 0);
	$height = imagesy($base_image);
	$font = 4;
	imagestring($base_image, $font, 1, $height-70, "ERROR: No User ID set", $text_colour);
	imagestring($base_image, $font, 260, $height-20, 'GamingOnLinux', $text_colour);
	header('Content-Type: image/png');
	imagepng($base_image);
}
?>
