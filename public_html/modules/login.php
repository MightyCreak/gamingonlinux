<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}
use Abraham\TwitterOAuth\TwitterOAuth;

$templating->set_previous('title', 'Login', 1);
$templating->set_previous('meta_description', 'Login page for GamingOnLinux', 1);

$templating->load('login');

if (!isset($_POST['action']))
{
	if (!isset($_GET['forgot']) && !isset($_GET['reset']) && !isset($_GET['twitter']) && !isset($_GET['steam']))
	{
		if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 0 || !isset($_SESSION['user_id']))
		{
			$templating->block('main', 'login');
			$templating->set('url', $core->config('website_url'));

			$username = '';
			if (isset($_SESSION['login_error_username']))
			{
				$username = $_SESSION['login_error_username'];
			}

			$templating->set('username', $username);

			$current_page = '';
			$templating->set('current_page', $current_page);
			
			$twitter_button = '';
			if ($core->config('twitter_login') == 1)
			{	
				$twitter_button = '<a href="'.$core->config('website_url').'index.php?module=login&twitter" class="btn-auth btn-twitter"><span class="btn-icon"><img alt="" src="'.$core->config('website_url'). 'templates/' . $core->config('template') .'/images/network-icons/white/twitter.png" /> </span>Sign in with <b>Twitter</b></a>';
			}
			$templating->set('twitter_button', $twitter_button);
			
			$steam_button = '';
			if ($core->config('steam_login') == 1)
			{
				$steam_button = '<a href="'.$core->config('website_url').'index.php?module=login&steam" class="btn-auth btn-steam"><span class="btn-icon"><img alt="" src="'.$core->config('website_url'). 'templates/' . $core->config('template') .'/images/network-icons/white/steam.png" /> </span>Sign in with <b>Steam</b></a>';
			}
			$templating->set('steam_button', $steam_button);
			
			$google_button = '';
			if ($core->config('google_login') == 1)
			{
				$client_id = $core->config('google_login_public'); 
				$client_secret = $core->config('google_login_secret');
				$redirect_uri = $core->config('website_url') . 'includes/google/login.php';
				require_once ($core->config('path') . 'includes/google/libraries/Google/autoload.php');
				$client = new Google_Client();
				$client->setClientId($client_id);
				$client->setClientSecret($client_secret);
				$client->setRedirectUri($redirect_uri);
				$client->addScope("email");
				$client->addScope("profile");
				$service = new Google_Service_Oauth2($client);
				$authUrl = $client->createAuthUrl();
				
				$google_button = '<a href="'.$authUrl.'" class="btn-auth btn-google"><span class="btn-icon"><img alt="" src="'.$core->config('website_url'). 'templates/' . $core->config('template') .'/images/network-icons/google.svg" /> </span>Sign in with <b>Google</b></a>';
			}
			$templating->set('google_button', $google_button);
		}

		else
		{
			$core->message("You are already logged in!", 1);
		}
	}

	else if (isset($_GET['forgot']))
	{
		if (isset($_GET['bademail']))
		{
			$core->message("That is not a correct email address!", 1);
		}
		$templating->block('forgot', 'login');
	}

	else if (isset($_GET['reset']))
	{
		$email = $_GET['email'];
		$code = $_GET['code'];

		// check its a valid time
		$get_time = $dbl->run("SELECT `user_email`, `expires` FROM `password_reset` WHERE `user_email` = ? AND `secret_code` = ?", array($email, $code))->fetch();

		// check code and email is valid
		if (!$get_time)
		{
			$core->message("That is not a correct password reset request, you will need to <a href=\"/index.php?module=login&forgot\">request a new code!</a>");
		}

		else if (time() > $get_time['expires'])
		{
			// drop any previous requested
			$dbl->run("DELETE FROM `password_reset` WHERE `user_email` = ?", array($email));

			$core->message("That reset request has expired, you will need to <a href=\"/index.php?module=login&forgot\">request a new code!</a>");
		}

		else
		{
			$url_email = rawurlencode($_GET['email']);
			$templating->block('reset');
			$templating->set('code', $code);
			$templating->set('email', $url_email);
		}
	}
	
	else if (isset($_GET['steam']))
	{		
		require("includes/steam/steam_login.php");
	
		$steam_user = new steam_user($dbl, $user, $core);
		$steam_user->apikey = $core->config('steam_openid_key'); // put your API key here
		$steam_user->domain = $core->config('website_url'); // put your domain
		$steam_user->return_url = $core->config('website_url');
		$steam_user->signIn();
	}
	
	else if (isset($_GET['twitter']))
	{		
		require 'includes/twitter/twitteroauth/autoload.php';
		
		define('CONSUMER_KEY', $core->config('tw_consumer_key'));
		define('CONSUMER_SECRET', $core->config('tw_consumer_skey'));
		define('OAUTH_CALLBACK', getenv('OAUTH_CALLBACK'));

		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);

		$request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => OAUTH_CALLBACK));
		if ($connection->getLastHttpCode() == 200 && $request_token['oauth_callback_confirmed'] == 'true')
		{
			$_SESSION['oauth_token'] = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

			$url = $connection->url('oauth/authorize', array('oauth_token' => $request_token['oauth_token']));

			header('Location: ' . $url);
			die();
		}
		else
		{
			$core->message('We were unable to autheticate you, Twitter might be having issues. If this persists please contact the admins.');
		}
	}
}

else if (isset($_POST['action']))
{
	if ($_POST['action'] == 'Login')
	{
		$stay = 0;
		if (isset($_POST['stay']))
		{
			$stay = 1;
		}

		if ($user->login($_POST['username'], $_POST['password'], $stay) == true)
		{
			unset($_SESSION['login_error']);
			unset($_SESSION['login_error_username']);

			// if the login form had a current page set, we need to check to see if we can redirect
			if(!empty($_POST['current_page'])) 
			{
				$parse_url = parse_url($_POST['current_page']);

				if (!empty($parse_url['scheme']) && $parse_url['scheme'].'://'.$parse_url['host'].'/' == $core->config('website_url'))
				{
					$extra = '';
					if (isset($parse_url['query']) && !empty($parse_url['query']))
					{
						$extra .= '?'.$parse_url['query'];
					}

					$path = substr($parse_url['path'], 1); // remove the slash at the start so we don't double up
					header("Location: ".$core->config('website_url').$path.$extra);
					die();
				}
				else
				{
					header("Location: ".$core->config('website_url'));
					die();
				}
			}
			else
			{
				header("Location: ".$core->config('website_url'));
				die();
			}
		}

		else
		{
			$_SESSION['login_error_username'] = htmlspecialchars($_POST['username']);
			header("Location: ".$core->config('website_url')."index.php?module=login");
			die();
		}
	}

	else if ($_POST['action'] == 'Send')
	{
		// check if user exists
		$check_res = $dbl->run("SELECT `email` FROM `users` WHERE `email` = ?", array($_POST['email']))->fetch();
		if (!$check_res)
		{
			header("Location: ".$core->config('website_url')."index.php?module=login&forgot&bademail");
			die();
		}

		else
		{
			$random_string = core::random_id();

			// drop any previous requested
			$dbl->run("DELETE FROM `password_reset` WHERE `user_email` = ?", array($_POST['email']));

			// make expiry 7 days from now
			$next_week = time() + (7 * 24 * 60 * 60);

			// insert number to database with email
			$dbl->run("INSERT INTO `password_reset` SET `user_email` = ?, `secret_code` = ?, `expires` = ?", array($_POST['email'], $random_string, $next_week));

			$url_email = rawurlencode($_POST['email']);

			// send mail with link including the key
			$html_message = '<p>Someone, hopefully you, has requested to reset your password on ' . $core->config('website_url') . '!</p>
			<p>If you didn\'t request this, don\'t worry! Unless someone has access to your email address it isn\'t an issue!</p>
			<p>Please click <a href="' . $core->config('website_url') . 'index.php?module=login&reset&code=' . $random_string . '&email=' . $url_email . '">this link</a> to reset your password</p>';

			$plain_message = 'Someone, hopefully you, has requested to reset your password on ' . $core->config('website_url') . '! Please go here: "' . $core->config('website_url') . 'index.php?module=login&reset&code=' . $random_string . '&email=' . $url_email . '" to change your password. If you didn\'t request this, you can ignore it as it\'s not a problem unless anyone has access to your email!';

			// Mail it
			if ($core->config('send_emails') == 1)
			{
				$mail = new mailer($core);
				$mail->sendMail($_POST['email'], 'GamingOnLinux password reset request', $html_message, $plain_message);

				$core->message("An email has been sent to {$_POST['email']} with instructions on how to change your password.");
			}
		}
	}

	// actually change the password as their code was correct and password + confirmation matched
	else if ($_POST['action'] == 'Reset')
	{
		$email = $_GET['email'];
		$code = $_GET['code'];

		// check its a valid time
		$get_time = $dbl->run("SELECT `user_email`, `expires` FROM `password_reset` WHERE `user_email` = ? AND `secret_code` = ?", array($email, $code))->fetch();
		
		// check code and email is valid
		if (!$get_time)
		{
			$core->message("That is not a correct password reset request, you will need to <a href=\"/index.php?module=login&forgot\">request a new code!</a>");
		}
		
		else if (time() > $get_time['expires'])
		{
			// drop any previous requested
			$dbl->run("DELETE FROM `password_reset` WHERE `user_email` = ?", array($email));
		
			$core->message("That reset request has expired, you will need to <a href=\"/index.php?module=login&forgot\">request a new code!</a>");
		}

		else
		{
			// check the passwords match
			if ($_POST['password'] != $_POST['password_again'])
			{
				$core->message("The new passwords didn't match! <a href=\"".$core->config('website_url')."index.php?module=login\">Go back.</a>");
			}

			// change the password
			else
			{
				$new_password = password_hash($_POST['password'], PASSWORD_BCRYPT);

				// new password
				$dbl->run("UPDATE `users` SET `password` = ? WHERE `email` = ?", array($new_password, $email));

				// drop any previous requested
				$dbl->run("DELETE FROM `password_reset` WHERE `user_email` = ?", array($email));

				$core->message("Your password has been updated! <a href=\"".$core->config('website_url')."index.php?module=login\">Click here to now login.</a>");
			}
		}
	}
}
?>
