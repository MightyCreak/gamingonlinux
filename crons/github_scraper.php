<?php
/*
This is design to scrape GitHub accounts, to find new repos.

TODO: Grab a list of Accounts to loop over, don't hardcode to one repo
*/
define("APP_ROOT", dirname( dirname(__FILE__) ) . '/public_html');

require APP_ROOT . "/includes/cron_bootstrap.php";

echo "GitHub scraper started on " .date('d-m-Y H:m:s'). "\n";

// get the current file, with a serialized array of repo names
$local_file = dirname(__FILE__) . '/githublist.txt';
$known_repos = unserialize(@file_get_contents($local_file));

// no file, make an empty array for comparison later
if ($known_repos === false) 
{
	$known_repos = [];
}

$found_repos = [];
$new_repos_email = [];

$page = 1;
$stop = 0;

$url = "https://github.com/ValveSoftware?page=";

do
{
	$html = core::file_get_contents_curl($url . $page);

	libxml_use_internal_errors(true);

	$main_list = new DOMDocument;
	$main_list->loadHTML($html);

	//var_dump($main_list);

	$xpath = new DOMXPath($main_list);

	$values = $xpath->query("/html/body/div[@class='application-main ']/div/div/div/div[@id='org-repositories']/div/div/li/div/h3/a");

	if ($values->length == 0)
	{
		$stop = 1;
	}
	else
	{
		foreach($values as $element)
		{
			$repo_name = trim($element->nodeValue);
			$repo_link = $element->getAttribute("href");

			$found_repos[$repo_name] = $repo_link;

			//echo '<a href="https://github.com/'.$repo_link.'">'.$repo_name.'</a>';
		}
	}
	
	libxml_clear_errors();
	$page++;
	echo 'Moving onto page: ' . $page . PHP_EOL;
} while ($stop == 0);

// now compare the found repos at the URL to the locally noted repos we know about, mark any new that don't exist locally
foreach ($found_repos as $name => $link)
{
	if (!in_array($name, $known_repos))
	{
		$new_repos_email[] = '<a href="https://github.com'.$link.'">'.$name.'</a>';
		$known_repos[] = $name;
	}
}

file_put_contents($local_file, serialize($known_repos));

$total_added = count($new_repos_email);

if ($total_added > 0)
{
	$html_message = implode("<br />", $new_repos_email);

	$to = $core->config('contact_email');
	$subject = 'GOL GitHub Scraper New';

	// Mail it
	if ($core->config('send_emails') == 1)
	{
		$mail = new mailer($core);
		$mail->sendMail($to, $subject, $html_message);
	}
}

echo "Total new: ".$total_added. PHP_EOL . "Last page: ". $page . PHP_EOL;

echo "End of GitHub scraper @ " . date('d-m-Y H:m:s') . ".\nHave a nice day.\n";
