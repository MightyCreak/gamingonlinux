<?php
return [
	"mail_list_unsubbed" => 
	[
		"text" => "You have unsubscribed from the daily article mailing list!"
	],
	"mail_list_subbed" => 
	[
		"text" => "You have subscribed to the daily article mailing list!"
	],
	"email_exists" =>
	[
		"text" => "That email is already in the mailing list!",
		"error" => 1
	],
	"mail_list_subbed_guest" =>
	[
		"text" => "Thank you, we require you to check your email for confirmation!"
	],
	"keys_missing" =>
	[
		"text" => "Either the ID or the Key were missing, so we couldn't adjust your subscription!",
		"error" => 1
	],
	"no_key_match" =>
	[
		"text" => "Sorry, but your ID and Key didn't match our records, we couldn't adjust your subscription!",
		"error" => 1
	],
	"email_wrong" =>
	[
		"text" => "That was not an email address, please try again!",
		"error" => 1
	],
	"account_used" =>
	[
		"text" => 'An account with that email exists, please <a href="/index.php?module=login">login first</a> or use a different email.',
		"error" => 1
	]
];
?>
