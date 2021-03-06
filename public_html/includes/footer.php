<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted: footer.');
}
$templating->load('footer');
$templating->block('footer');
$templating->set('jsstatic', JSSTATIC);
$templating->set('url', url);
$templating->set('year', date('Y'));

$article_rss = '';
if ($core->config('articles_rss') == 1)
{
	$article_rss = '<li><a title="Full article RSS" class="tooltip-top" href="'.$core->config('website_url').'article_rss.php" target="_blank"><img alt src="'.$core->config('website_url').'templates/'.$core->config('template').'/images/network-icons/white/rss-website.svg" width="30" height="30" /></a></li>
	<li><a title="Article title RSS" class="tooltip-top" href="'.$core->config('website_url').'article_rss.php?mini" target="_blank"><img alt src="'.$core->config('website_url').'templates/'.$core->config('template').'/images/network-icons/white/rss-website.svg" width="30" height="30" /></a></li>';
}
$templating->set('article_rss', $article_rss);

// don't set the rel tag for GOL's Mastodon on user profiles
$masto_rel = '';
if (!isset($_GET['module']) || isset($_GET['module']) && $_GET['module'] == 'home')
{
	$masto_rel = 'rel="me"';
}
$templating->set('masto_rel', $masto_rel);

$forum_rss = '';
if ($core->config('forum_rss') == 1)
{
	$forum_rss = '<li><a title="Forum RSS" class="tooltip-top" href="'.$core->config('website_url').'forum_rss.php" target="_blank"><img alt src="'.$core->config('website_url').'templates/'.$core->config('template').'/images/network-icons/white/rss-forum.svg" width="30" height="30" /></a></li>';
}
$templating->set('forum_rss', $forum_rss);

$ckeditor_js = '';
if ($core->current_page() == 'admin.php' || (isset($_GET['module']) && $_GET['module'] == 'submit_article'))
{
	$ckeditor_js = $templating->block_store('ckeditor', 'footer');
	$ckeditor_js = $templating->store_replace($ckeditor_js, array('jsstatic' => JSSTATIC));
}
$templating->set('ckeditor_js', $ckeditor_js);

// info for admins to see execution time and mysql queries per page
$debug = '';
if ($user->check_group(1) && $core->config('show_debug') == 1)
{
	$timer_end = microtime(true);
	$time = number_format($timer_end - $timer_start, 3);
	
	$total_queries = $dbl->counter;

	$debug = "<div class=\"box\"><div class=\"head\">Debug</div>
	<div class=\"body group\">Page generated in {$time} seconds</div>
	<div class=\"head\">MySQL queries: {$total_queries}</div>";
	foreach ($dbl->debug_queries as $key => $debug_query)
	{
		$debug .= '<div class="body group">(' . $key . ') ' . htmlentities($debug_query['query'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "<br />" . $debug_query['time'] . '</div>';
	}
	//$debug .= print_r($dbl::$debug_queries, true);
	$debug .= print_r($_SESSION, true);
	$debug .= 'Stored user details: ' . print_r($user->user_details, true);
	$debug .= '</div>';
}
$templating->set('debug', $debug);

// user stat trending charts
$svg_js = '';
if (!empty(core::$user_chart_js) || isset(core::$user_chart_js))
{
	$svg_js = '<script src="'.JSSTATIC.'/Chart.min.js?v=2.9.3"></script>
	<script src="'.JSSTATIC.'/chartjs-plugin-trendline.min.js"></script>
	<script>var style = getComputedStyle(document.body);
		var textcolor = style.getPropertyValue("--svg-text-color");
		Chart.defaults.global.defaultFontColor = textcolor;
		' . core::$user_chart_js . '</script>';
}
$templating->set('user_chart_js', $svg_js);
echo $templating->output();

// close the mysql connection
$dbl = NULL;
?>
