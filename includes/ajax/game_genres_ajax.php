<?php
session_start();

define("APP_ROOT", dirname ( dirname ( dirname(__FILE__) ) ) );

require APP_ROOT . "/includes/bootstrap.php";

$cat_array = array();

if(isset($_GET['q']))
{
	$db->sqlquery("SELECT `id`, `name` FROM `game_genres` WHERE `name` LIKE ? ORDER BY `name` ASC", array('%' . $_GET['q'] . '%'));
	$get_data = $db->fetch_all_rows();
	// Make sure we have a result
	if(count($get_data) > 0)
	{
		foreach ($get_data as $key => $value)
		{
			$data[] = array('id' => $value['id'], 'text' => $value['name']);
		}
	}
	else
	{
		$data[] = array('id' => '0', 'text' => 'No categories found that match!');
	}
	echo json_encode($data);
}
?>