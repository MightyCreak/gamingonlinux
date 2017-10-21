<?php
class core
{	
	protected $db;	
	// the current date and time for the mysql
	public static $date;
	
	// the time and date right now in the MySQL timestamp format
	public static $sql_date_now;

	// the users ip address
	public static $ip;

	// how many pages their are in the pagination being done
	public $pages;

	// pagination number to start from for query
	public $start = 0;

	// any message for image uploader
	public $error_message;
	
	public static $user_graphs_js;
	
	public static $editor_js;

	public static $config = [];
	
	public static $allowed_modules = [];
	
	public static $current_module = [];
	
	public static $top_bar_links = [];

	function __construct()
	{	
		header('X-Frame-Options: SAMEORIGIN');
		ini_set('session.cookie_httponly', 1);
		date_default_timezone_set('UTC');
		
		core::$date = strtotime(gmdate("d-n-Y H:i:s"));
		core::$sql_date_now = date('Y-m-d H:i:s');
		core::$ip = $this->get_client_ip();
		$this->db = db_mysql::instance();
	}
	
	// check in_array for a multidimensional array
	public static function in_array_r($needle, $haystack) 
	{
		foreach($haystack as $array)
		{
			if(in_array($needle, $array, true))
			{
				return true;
			}
		}
		return false;
	}

	public static function make_safe($text)
	{
		if (is_array($text))
		{
			foreach ($text as $k => $unsafe_text)
			{
				$text[$k] = htmlspecialchars($unsafe_text, ENT_QUOTES);
				$text[$k] = str_replace('{', '&#123;', $unsafe_text);
				$text[$k] = trim($unsafe_text);
			}
		}
		else
		{
			$text = htmlspecialchars($text, ENT_QUOTES);
			$text = str_replace('{', '&#123;', $text);
			$text = trim($text);
		}

		return $text;
	}

	// for validating numbers a bit more thoroughly, for things like ID numbers to use in the database
	// this will work on arrays as well as single digits
	public static function is_number($data)
	{
		if (isset($data))
		{
			if (is_array($data))
			{
				foreach ($data as $test_id)
				{
					if (!is_numeric($test_id))
					{
						return false;
					}
				}
			}
			else if (!is_numeric($data))
			{
				return false;
			}
		}
		else
		{
			return false;
		}
		return true;
	}

	// simple helper function to make sure we always have a page number set
	public static function give_page()
	{
		$page = 1;
		if (!isset($_GET['page']) || $_GET['page'] <= 0)
		{
		  $page = 1;
		}

		else if (core::is_number($_GET['page']))
		{
		  $page = $_GET['page'];
		}

		return $page;
	}

	public static function file_get_contents_curl($url, $type = NULL, $post_fields = NULL, $headers = NULL) 
	{
	    $ch = curl_init($url);
	    
	    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		if ($type == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		}
		if ($headers != NULL)
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		$data = curl_exec($ch);
		
		if (curl_getinfo ( $ch )['http_code'] != 200)
		{
			curl_close ( $ch );
			return false;
		}
		else 
		{
			curl_close ( $ch );
			return $data;
		}
	}

	// secure way of grabbing a remote image, for avatars
	function remoteImage($url)
	{
		$ch = curl_init ($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
		curl_setopt($ch, CURLOPT_RANGE, "0-10240");

		$fn = "partial.jpg";
		$raw = curl_exec($ch);
		$result = array();

		if(file_exists($fn))
		{
			unlink($fn);
		}

		if ($raw !== false) 
		{
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($status == 200 || $status == 206) 
			{
				$result["w"] = 0;
				$result["h"] = 0;

				$fp = fopen($fn, 'x');
				fwrite($fp, $raw);
				fclose($fp);

				$size = getImageSize($fn);

				if ($size===false) {
				//  Cannot get file size information
				} else {
				//  Return width and height
					list($result["w"], $result["h"]) = $size;
				}
			}
		}

		curl_close ($ch);
		return $result;
	}
	
	// grab a config key
	public function config($key)
	{
		if (empty(self::$config))
		{
			// get config
			$get_config = $this->db->run("SELECT `data_key`, `data_value` FROM config")->fetch_all();
			foreach ($get_config as $config_set)
			{
				self::$config[$config_set['data_key']] = $config_set['data_value'];
			}
		}

		// return the requested key with the value in place
		return self::$config[$key];
	}

	// update a single config var
	function set_config($value, $key)
	{
		$this->db->run("UPDATE `config` SET `data_value` = ? WHERE `data_key` = ?", [$value, $key]);

		// invalidate the cache
		self::$config = array();
	}

	function get_client_ip()
	{
		if (isset ($_SERVER ['HTTP_X_FORWARDED_FOR']))
		{
			$clientIP = $_SERVER ['HTTP_X_FORWARDED_FOR'];
		}

		elseif (isset ($_SERVER ['HTTP_X_REAL_IP']))
		{
			$clientIP = $_SERVER ['HTTP_X_REAL_IP'];
		}

		elseif (isset ($_SERVER['REMOTE_ADDR']))
		{
			$clientIP = $_SERVER['REMOTE_ADDR'];
		}

		else
		{
			$clientIP = "127.10.10.1"; //We have no IP, maybe running from CLI?
		}

		return $clientIP;
	}

	// find the current page we are on
	// only used in old mysql class, can remove once thats removed
	function current_page()
	{
		return substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);
	}

	public static function current_page_url()
	{
		$page_url = 'http';
		if (isset($_SERVER["HTTPS"]))
		{
			$page_url .= "s";
		}
		$page_url .= '://' . $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		return $page_url;
	}

	// $date_to_format = a timestamp to make more human readable
	function human_date($timestamp, $format = "j F Y \a\\t g:i a")
	{
		$text = date($format, $timestamp);

		return $text . ' UTC';
	}

	// per page = how many rows to show per page
	// total = total number of rows
	// targetpage = the page to append the pagination target page onto
	// extra = anything extra to add like "#comments" to go to the comments
	function pagination_link($per_page, $total, $targetpage, $page, $extra = NULL)
	{
		// what row number for the query to start from
		if ($page != 1 && $page > 0)
		{
			$this->start = ($page - 1) * $per_page;
		}
		else
		{
			$this->start = 0;
		}

		// make sure it's an int not a string - have to use this as it kept turning into a string somehow when i only pass numbers to it?
		$this->start = intval($this->start);

		//previous page is page - 1
		$prev = $page - 1;

		//next page is page + 1
		$next = $page + 1;

		//lastpage is = total pages / items per page, rounded up.
		$lastpage = ceil($total/$per_page);

		// sort out the pagination links
		$pagination = "";
		if($lastpage > 1)
		{
			$pagination .= "<div class=\"fnone\"><ul class=\"pagination\">";

			//previous button
			if ($page > 1)
			{
				$pagination.= "<li class=\"previouspage\"><a data-page=\"{$prev}\" href=\"{$targetpage}page=$prev$extra\">&laquo;</a></li>";
			}

			// current page
			$pagination .= "<li class=\"active\"><a href=\"#\">$page</a></li>";

			// seperator
			$pagination .= "<li class=\"pagination-disabled\"><a href=\"#\">/</a></li>";

			// sort out last page link, no link if on last page
			if ($page == $lastpage)
			{
				$pagination .= "<li class=\"pagination-disabled\"><a href=\"#\">{$lastpage}</a></li>";
			}

			else
			{
				$pagination .= "<li><a data-page=\"{$lastpage}\" href=\"{$targetpage}page={$lastpage}$extra\">{$lastpage}</a></li>";
			}

			// next button
			if ($page < $lastpage)
			{
				$pagination .= "<li class=\"nextpage\"><a data-page=\"{$next}\" href=\"{$targetpage}page=$next$extra\">&raquo;</a></li>";
			}

			$pagination .= "</ul>";

			$pagination .= "</div> <div class=\"fnone\">
			<form name=\"form2\" class=\"form-inline\">
			 &nbsp; Go to: <select class=\"wrap ays-ignore pagination\" name=\"jumpmenu\">";

			for ($i = 1; $i <= $lastpage; $i++)
			{
				$selected = '';
				if ($page == $i)
				{
					$selected = 'selected';
				}
				$pagination .= "<option data-page=\"{$i}\" value=\"{$targetpage}page={$i}{$extra}\" $selected>$i</option>";
			}

			$pagination .= '</select></form></div>';
		}

		return $pagination;
	}

	// per page = how many rows to show per page
	// total = total number of rows
	// targetpage = the page to append the pagination target page onto
	// extra = anything extra to add like "#comments" to go to the comments
	function head_pagination($per_page, $total, $targetpage, $page, $extra = NULL)
	{
		// what row number for the query to start from
		if ($page != 1 && $page > 0)
		{
			$this->start = ($page - 1) * $per_page;
		}
		else
		{
			$this->start = 0;
		}

		// make sure it's an int not a string - have to use this as it kept turning into a string somehow when i only pass numbers to it?
		$this->start = intval($this->start);

		//previous page is page - 1
		$prev = $page - 1;

		//next page is page + 1
		$next = $page + 1;

		//lastpage is = total pages / items per page, rounded up.
		$lastpage = ceil($total/$per_page);

		// sort out the pagination links
		$pagination = "";
		if ($lastpage > 1)
		{
			$pagination .= "Page: ";
		}
		if($lastpage > 1)
		{
			//previous button
			if ($page > 1)
			{
				$pagination.= "<a data-page=\"{$prev}\" href=\"{$targetpage}page=$prev$extra\"><span class=\"previouspage\">&laquo;</span></a>";
			}

			// current page
			$pagination .= "<span class=\"pagination-disabled\">$page</span>";
			// seperator
			$pagination .= "<span class=\"pagination-disabled\">/</span>";

			// sort out last page link, no link if on last page
			if ($page == $lastpage)
			{
				$pagination .= "<a href=\"#\"><span class=\"pagination-disabled\">{$lastpage}</span></a>";
			}
			else
			{
				$pagination .= "<a data-page=\"{$lastpage}\" href=\"{$targetpage}page={$lastpage}$extra\"><span>{$lastpage}</span></a>";
			}

			// next button
			if ($page < $lastpage)
			{
				$pagination .= "<a data-page=\"{$next}\" href=\"{$targetpage}page=$next$extra\"><span class=\"nextpage\">&raquo;</span></a>";
			}

			$pagination .= "<form name=\"form2\" class=\"form-inline\">&nbsp; Go to: <select class=\"wrap ays-ignore\" name=\"jumpmenu\">";

			for ($i = 1; $i <= $lastpage; $i++)
			{
				$selected = '';
				if ($page == $i)
				{
					$selected = 'selected';
				}
				$pagination .= "<option data-page=\"{$i}\" value=\"{$targetpage}page={$i}{$extra}\" $selected>$i</option>";
			}

			$pagination .= '</select></form>';
		}

		return $pagination;
	}

	// $message = what to show them
	function message($message, $urgent = 0)
	{
		global $templating;

		if (!is_object($templating)) return; //your globals are fucked, bail

		$templating->load('messages');
		$templating->block('message');

		if ($urgent == 0)
		{
			$templating->set('type', '');
		}

		else if ($urgent == 1)
		{
			$templating->set('type', 'error');
		}

		else if ($urgent == 2)
		{
			$templating->set('type', 'warning');
		}

		$templating->set('message', $message);
	}

	// check for multiple things being empty and return the name of what was empty
	public static function mempty()
	{
		foreach(func_get_args()[0] as $key => $arg)
		{
			if(empty($arg))
			{
				return $key;
			}
			else
			{
				continue;
			}
		}
		return true;
	}

	// $message = what to ask them
	// $action_url = whatever the press it will go to this page
	// $act = a hidden $_POST box to define where to send them on the action url
	// $act2 = if we need a second stage act for any reason
	// $act2_custom_name = incase we aren't using "act2" (maybe i should go through and re-name all custom second bits like "moderator_options" to "act2" to just simplify it?)
	function yes_no($message, $action_url, $act = NULL, $act2 = NULL, $act2_custom_name = 'act2', $extra_content = NULL)
	{
		global $templating;

		$templating->load('messages');
		$templating->block('yes_no');
		$extra = '';
		if ($extra_content != NULL)
		{
			$extra = $extra_content;
		}
		$templating->set('extra_content', $extra);
		$templating->set('message', $message);
		$templating->set('action_url', $action_url);
		$templating->set('act', $act);

		if ($act2 == NULL)
		{
			$act2_text = '';
		}

		else
		{
			$act2_text = "<input type=\"hidden\" name=\"$act2_custom_name\" value=\"$act2\" />";
		}
		$templating->set('act2', $act2_text);
	}
	
	// a yes/no confirmation box
	// this better one is to eventually replace the older one above
	function confirmation($details)
	{
		global $templating;

		$templating->load('messages');
		$templating->block('confirmation');
		$templating->set('title', $details['title']);
		
		$text = '';
		if (isset($details['text']))
		{
			$text = $details['text'];
		}
		$templating->set('text', $text);
		
		$templating->set('action_url', $details['action_url']);
		$templating->set('act', $details['act']);

		if (!isset($details['act2']))
		{
			$act2_text = '';
		}

		else
		{
			$act2_text = "<input type=\"hidden\" name=\"{$details['act_2_name']}\" value=\"{$details['act_2_value']}\" />";
		}
		$templating->set('act2', $act2_text);
	}
	
	public static function nice_title($title)
	{
		$clean = trim($title);
		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[\/_|+ -]+/", '-', $clean);

		return $clean;
	}
	
	public static function check_url($link)
	{
		$url = parse_url($link);
		if((!isset($url['scheme'])) || (isset($url['scheme']) && $url['scheme'] != 'https' && $url['scheme'] != 'http'))
		{
			$link = 'http://' . $link;
		}
		
		return $link;
	}

	// move previously uploaded tagline image to correct directory
	function move_temp_image($article_id, $file, $text)
	{
		$types = array('jpg', 'png', 'gif');
		$full_file_big = $this->config('path') . "uploads/articles/tagline_images/temp/" . $file;
		$full_file_thumbnail = $this->config('path') . "uploads/articles/tagline_images/temp/thumbnails/" . $file;

		if (!file_exists($full_file_big))
		{
			$this->error_message = "Could not find temp image to load? $full_file_big";
			return false;
		}

		if (!file_exists($full_file_thumbnail))
		{
			$this->error_message = "Could not find temp thumbnail image to load? $full_file_thumbnail";
			return false;
		}

		else
		{
			$image_info = getimagesize($full_file_big);
			$image_type = $image_info[2];
			$file_ext = '';
			if( $image_type == IMAGETYPE_JPEG )
			{
				$file_ext = 'jpg';
			}

			else if( $image_type == IMAGETYPE_GIF )
			{
				$file_ext = 'gif';
			}

			else if( $image_type == IMAGETYPE_PNG )
			{
				$file_ext = 'png';
			}

			// give the image a random file name
			$imagename = rand() . 'id' . $article_id . 'gol.' . $file_ext;

			// the actual image
			$source = $full_file_big;

			// where to upload to
			$target = $this->config('path') . "uploads/articles/tagline_images/" . $imagename;

			// the actual image
			$source_thumbnail = $full_file_thumbnail;

			// where to upload to
			$target_thumbnail = $this->config('path') . "uploads/articles/tagline_images/thumbnails/" . $imagename;

			if (rename($source, $target) && rename($source_thumbnail, $target_thumbnail))
			{
				$image = $this->db-run("SELECT `tagline_image` FROM `articles` WHERE `article_id` = ?", array($article_id))->fetch();

				// remove old image
				if (isset($image))
				{
					if (!empty($image['tagline_image']))
					{
						unlink($this->config('path') . 'uploads/articles/tagline_images/' . $image['tagline_image']);
						unlink($this->config('path') . 'uploads/articles/tagline_images/thumbnails/' . $image['tagline_image']);
					}
				}
				
				// replace the temp filename with the new filename
				$text = preg_replace('/(<img src=".+temp\/thumbnails\/.+" \/>)/', '<img src="/uploads/articles/tagline_images/thumbnails/'.$imagename.'" />', $text);
				$text = preg_replace('/<img src=".+temp\/.+" \/>/', '<img src="/uploads/articles/tagline_images/'.$imagename.'" />', $text);

				$this->db->run("UPDATE `articles` SET `tagline_image` = ?, `gallery_tagline` = 0, `text` = ? WHERE `article_id` = ?", array($imagename, $text, $article_id));
				return true;
			}

			else
			{
				$this->error_message = 'Could not move temp file to tagline images uploads folder!';
				return false;
			}
		}
	}

	/* For generating a bbcode editor form, options are:
	name - name of the textarea
	content
	article_editor
	disabled
	anchor_name
	ays_ignore
	editor_id
	*/
	// include this anywhere to show the bbcode editor
	function editor($custom_options)
	{
		global $templating;
		
		if (!is_array($custom_options))
		{
			die('BBCode editor not setup correctly!');
		}
		
		// sort some defaults
		$editor['article_editor'] = 0;
		$editor['disabled'] = 0;
		$editor['ays_ignore'] = 0;
		$editor['content'] = '';
		$editor['anchor_name'] = 'commentbox';
		
		foreach ($custom_options as $option => $value)
		{
			$editor[$option] = $value;
		}
		
		$templating->load('editor');
		$templating->block('editor');
		$templating->set('this_template', $this->config('website_url') . 'templates/' . $this->config('template'));
		$templating->set('url', $this->config('website_url'));
		$templating->set('name', $editor['name']);
		$templating->set('content', $editor['content']);
		$templating->set('anchor_name', $editor['anchor_name']);
		
		$disabled = '';
		if ($editor['disabled'] == 1)
		{
			$disabled = 'disabled';
		}
		$templating->set('disabled', $disabled);

		$page_button = '';
		$timer_button = '';
		if ($editor['article_editor'] == 1)
		{
			$page_button = '<li data-snippet="<*PAGE*>">page</li>';
			//$timer_button = '<li data-snippet="[timer=timer1]'.date('Y/m/d H:m:s').'[/timer]">timer</li>';
			$timer_button = '<ul><li class="dropdown">Timer<ul class="timer"><li data-snippet="[timer=timer1*time-only]'.date('Y-m-d H:m:s').'[/timer]">time only</li><li data-snippet="[timer=timer1]'.date('Y-m-d H:m:s').'[/timer]">time and date</li></ul></li></ul>';
		}
		$templating->set('page_button', $page_button);
		$templating->set('timer_button', $timer_button);

		$ays_check = '';
		if ($editor['ays_ignore'] == 1)
		{
			$ays_check = 'class="ays-ignore"';
		}
		$templating->set('ays_ignore', $ays_check);
		
		$templating->set('limit_youtube', $this->config('limit_youtube'));
		
		$templating->set('editor_id', $editor['editor_id']);
		
		core::$editor_js[] = 'gol_editor(\''.$editor['editor_id'].'\');';
	}
	
	/* For generating a bbcode editor form, options are:
	name - name of the textarea
	content
	article_editor
	disabled
	anchor_name
	ays_ignore
	editor_id
	*/
	// include this anywhere to show the bbcode editor
	function article_editor($custom_options)
	{
		global $templating;
		
		if (!is_array($custom_options))
		{
			die('CKEditor editor not setup correctly!');
		}
		
		// sort some defaults
		$editor['disabled'] = 0;
		$editor['ays_ignore'] = 0;
		$editor['content'] = '';
		$editor['anchor_name'] = 'commentbox';
		
		foreach ($custom_options as $option => $value)
		{
			$editor[$option] = $value;
		}
		
		$templating->load('ckeditor');
		$templating->block('editor');
		$templating->set('this_template', $this->config('website_url') . 'templates/' . $this->config('template'));
		$templating->set('url', $this->config('website_url'));
		$templating->set('content', $editor['content']);
		$templating->set('anchor_name', $editor['anchor_name']);
		
		$disabled = '';
		if ($editor['disabled'] == 1)
		{
			$disabled = 'disabled';
		}
		$templating->set('disabled', $disabled);

		$ays_check = '';
		if ($editor['ays_ignore'] == 1)
		{
			$ays_check = 'class="ays-ignore"';
		}
		$templating->set('ays_ignore', $ays_check);
	}

	// convert bytes to human readable stuffs, only up to MB as we will never be uploading more than MB files directly
	public static function readable_bytes($bytes, $decimals = 2)
	{
	  $kilobyte = 1024;
	  $megabyte = $kilobyte * 1024;

	  if (($bytes >= 0) && ($bytes < $kilobyte))
		{
	    return $bytes . ' B';
	  }
		else if (($bytes >= $kilobyte) && ($bytes < $megabyte))
		{
	    return round($bytes / $kilobyte, $decimals) . ' KB';
	  }
		else if (($bytes >= $megabyte))
		{
	    return round($bytes / $megabyte, $decimals) . ' MB';
	  }
		// not really needed, but in case i accidentally don't put something to even 1KB on upload limits
		else
		{
	    return $bytes . ' B';
	  }
	}

	public static function random_id($length = 10)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@-_()';
		$characters_length = strlen($characters);
		$random_string = '';
		for ($i = 0; $i < $length; $i++)
		{
			$random_string .= $characters[rand(0, $characters_length - 1)];
		}
		return $random_string;
	}

	function trends_charts($name, $order = '')
	{
		$dates = array();
		$chart_ids = array();
		$labels = array();

		// get each chart along with the date they were generated to make the axis
		$get_charts = $this->db->run("SELECT `id`, `name`, `h_label`, `generated_date`, `total_answers` FROM `user_stats_charts` WHERE `name` = ?", array($name))->fetch_all();
		if ($get_charts)
		{
			foreach ($get_charts as $chart_info)
			{
				if ($chart_info['total_answers'] > 0)
				{
					$chart_ids[] = $chart_info['id'];

					$make_time = strtotime($chart_info['generated_date']);
					$dates[] = "'".date("M-Y", $make_time) . "'";
				}
			}

			$chart_ids_sql = implode(',', $chart_ids);

			if (count($chart_ids) > 0)
			{
				// get the names of all the labels
				$get_labels = $this->db->run("SELECT DISTINCT(`name`) FROM `user_stats_charts_labels` WHERE `chart_id` IN ($chart_ids_sql)")->fetch_all();

				// how many data points in total we need for each label
				$total_points = count($dates);

				// only grab the top 10 labels, so graphs don't get messy with tons of labels
				$top_10_labels = array_slice($get_labels, 0, 10);
				if ($name == 'RAM' || $name == 'Resolution')
				{
					uasort($top_10_labels, function($a, $b) { return strnatcmp($a["name"], $b["name"]); });
				}
				foreach ($top_10_labels as $sort_labels)
				{
					$get_data = $this->db->run("SELECT l.`label_id`, l.`name`, d.`data`, c.`generated_date`, c.`total_answers` FROM `user_stats_charts_labels` l LEFT JOIN `user_stats_charts_data` d ON d.label_id = l.label_id LEFT JOIN `user_stats_charts` c ON c.id = l.chart_id WHERE l.`chart_id` IN ($chart_ids_sql) AND `l`.name = '{$sort_labels['name']}' GROUP BY c.generated_date, l.`name` ASC, d.`data`, c.`total_answers`, l.`label_id` LIMIT 10")->fetch_all();

					$total_data = count($get_data);

					// calculate how many data points are missing
					$missing_data = $total_points - $total_data;

					$label_add = '';
					if ($name == 'RAM')
					{
						$label_add = 'GB';
					}

					// adjust the data points for this label if it started late (not enough data points), so the data point starts at the right place
					for ($data_counter = 0; $data_counter < $missing_data; $data_counter++)
					{
						$labels[$sort_labels['name'] . $label_add][] = 0;
					}
					// add in the actual data we do have for this label
					foreach ($get_data as $data)
					{
						$percent = round(($data['data'] / $data['total_answers']) * 100, 2);
						$labels[$data['name'] . $label_add][] = $percent;
					}
				}

				$colours = array(
				'#a6cee3',
				'#1f78b4',
				'#b2df8a',
				'#33a02c',
				'#fb9a99',
				'#e31a1c',
				'#fdbf6f',
				'#ff7f00',
				'#cab2d6',
				'#6a3d9a'
				);

				$graph_name = str_replace(' ', '', $name); // Replaces all spaces with hyphens.
				$graph_name = preg_replace('/[^A-Za-z0-9\-]/', '', $graph_name); // Removes special chars.

				$get_graph['graph'] = '<canvas id="'.$graph_name.'" width="400" height="200"></canvas>';

				$total_array = count($labels);

				$data_sets = '';
				$counter = 0;
				foreach ($labels as $key => $data)
				{
					$colour = $colours[$counter];
					if ($key == 'Intel')
					{
						$colour = "#1f78b4";
					}
					if ($key == 'AMD' || $key == 'Proprietary')
					{
						$colour = "#e31a1c";
					}
					if ($key == 'Nvidia' || $key == 'Open Source')
					{
						$colour = "#33a02c";
					}

					$data_sets .= "{
					label: '".$key."',
					fill: false,
					data: [";
					$data_sets .= implode(',', $data);
					$data_sets .= "],
					borderColor: '$colour',
					borderWidth: 1
					}";
					$counter++;
					if ($counter != $total_array)
					{
						$data_sets .= ',';
					}
				}

				$javascript = "<script>
				var ".$graph_name." = document.getElementById('".$graph_name."');
				var myChart = new Chart.Line(".$graph_name.", {
				type: 'bar',
				data: {
				labels: [".implode(',', $dates)."],
				datasets: [$data_sets]
					},
					options: {
				scales: {
				yAxes: [{
					ticks: {
					beginAtZero:true
					},
								scaleLabel: {
							display: true,
							labelString: 'Percentage of users'
						}
				}]
				},
						tooltips:
						{
							callbacks: {
								label: function(tooltipItem, data) {
					var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
									var label = data.datasets[tooltipItem.datasetIndex].label;
					return label + ' ' + value + '%';
						}
						},
						},
				}
				});
				</script>";

				core::$user_graphs_js .= $javascript;

				return $get_graph;
			}
		}

	}

	function process_livestream_users($livestream_id, $user_ids)
	{
		if (isset($livestream_id) && is_numeric($livestream_id))
		{
			// find existing users, if any
			$current_users = $this->db->run("SELECT `user_id` FROM `livestream_presenters` WHERE `livestream_id` = ?", array($livestream_id))->fetch_all(PDO::FETCH_COLUMN);

			// if the existing users aren't in the new list, remove them
			if ($current_users)
			{
				foreach ($current_users as $current_user)
				{
					if (!in_array($current_user, $user_ids))
					{
						$this->db->run("DELETE FROM `livestream_presenters` WHERE `livestream_id` = ? AND `user_id` = ?", array($livestream_id, $current_user));
					}
				}
			}
			
			// we have a list of user ids
			if (!empty($user_ids) && is_array($user_ids))
			{
				foreach($user_ids as $streamer_id)
				{
					// if this user_id isn't in the current list, add them
					if (!in_array($streamer_id, $current_users))
					{
						$this->db->run("INSERT INTO `livestream_presenters` SET `livestream_id` = ?, `user_id` = ?", array($livestream_id, $streamer_id));
					}
				}				
			}
		}
	}

	function check_old_pc_info($user_id)
	{
		global $templating;

		if (isset($user_id) && $user_id != 0)
		{
			$checker = $this->db->run("SELECT `date_updated` FROM `user_profile_info` WHERE `user_id` = ?", array($user_id))->fetch();

			if ($checker['date_updated'] != NULL)
			{
				$minus_4months = strtotime('-4 months');

				if (strtotime($checker['date_updated']) < $minus_4months)
				{
					if (!isset($_COOKIE['gol_announce_pc_info']))
					{
						$templating->load('announcements');
						$templating->block('announcement_top', 'announcements');
						$templating->block('announcement', 'announcements');
						$templating->set('text', 'You haven\'t updated your PC information in over 4 months! <a href="/usercp.php?module=pcinfo">Click here to go and check</a>. You can simply update if nothing has changed to be included in our statistics!');
						
						$dismiss = '<span class="fright"><a href="#" class="remove_announce" title="Hide Announcement" data-announce-id="pc_info">&#10799;</a></span>';
						$templating->set('dismiss', $dismiss);

											
						$templating->block('announcement_bottom', 'announcements');
					}
				}
			}
		}
	}

	function check_ip_from_stopforumspam($ip)
	{
		$url = "https://api.stopforumspam.org/api?f=json&ip=" . $ip;
		$json = self::file_get_contents_curl($url);
		$json = json_decode($json, true);
		if ( $json["ip"]["appears"] == 1 )
		{
			$_SESSION['message'] = 'spam';
			header('Location: /index.php?module=home');
			die();
		}
	}

	// this makes an auto-generated list of all timezones
	public static function timezone_list($current_timezone = NULL)
	{
		$timezone_list = '<select name="timezone">';
		$tz_list = DateTimeZone::listIdentifiers( DateTimeZone::ALL );

		$timezone_offsets = array();
		foreach( $tz_list as $timezone )
		{
			$tz = new DateTimeZone($timezone);
			$timezone_offsets[$timezone] = $tz->getOffset(new DateTime);
		}
		
		foreach( $timezone_offsets as $timezone => $offset )
		{
			$offset_prefix = $offset < 0 ? '-' : '+';
			$offset_formatted = gmdate( 'H:i', abs($offset) );

			$pretty_offset = "UTC${offset_prefix}${offset_formatted}";
			
			$selected = '';
			if ($current_timezone != NULL && !empty($current_timezone) && $current_timezone == $timezone)
			{
				$selected = 'selected';
			}
			else if ($current_timezone == NULL || empty($current_timezone) && $timezone == 'UTC')
			{
				$selected = 'selected';
			}
			$timezone_list .= '<option value="'.$timezone.'" '.$selected.'>'.$timezone.' ('.$pretty_offset.')</option>';
		}
		
		$timezone_list .= '</select>';

		return $timezone_list;
	}
	
	public static function adjust_time($date, $from = 'UTC', $to = 'UTC', $show_zone = 1)
	{
		if (empty($from) || $from == NULL)
		{
			$from = 'UTC';
		}
		if (empty($to) || $to == NULL)
		{
			$to = 'UTC';
		}
		$given = new DateTime($date, new DateTimeZone($from));
		$given->setTimezone(new DateTimeZone($to));
		
		$output = $given->format("Y-m-d H:i:s"); 
		if ($show_zone == 1)
		{
			$output .= ' (' . $to . ')';
		}
		
		return $output;
	}
	
	public function load_modules($options)
	{
		$module_links = '';
		$fetch_modules = $this->db->run('SELECT `module_id`, `module_file_name`, `nice_title`, `nice_link`, `sections_link` FROM `'.$options['db_table'].'` WHERE `activated` = 1 ORDER BY `nice_title` ASC')->fetch_all();
		foreach ($fetch_modules as $modules)
		{
			// modules allowed for loading
			self::$allowed_modules[$modules['module_file_name']] = $modules;
			
			if ($modules['sections_link'] == 1)
			{
				// sort out links to be placed in the navbar
				$section_link = self::config('website_url') . 'index.php?module=' . $modules['module_file_name'];
				if ($this->config('pretty_urls') == 1 && !empty($modules['nice_link']) && $modules['nice_link'] != NULL)
				{
					$section_link = self::config('website_url') . $modules['nice_link'];
				}
				self::$top_bar_links[] = '<li><a href="'.$section_link.'">'.$modules['nice_title'].'</a></li>';
			}
		}

		// modules loading, first are we asked to load a module, if not use the default
		if (isset($_GET['module']))
		{
			if (array_key_exists($_GET['module'], self::$allowed_modules))
			{
				self::$current_module = self::$allowed_modules[$_GET['module']];
			}
			else
			{
				self::$current_module = self::$allowed_modules['404'];
			}
		}

		else
		{
			self::$current_module = self::$allowed_modules[$this->config('default_module')];
		}
	}
}
?>
