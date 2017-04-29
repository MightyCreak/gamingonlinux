<?php
$file_dir = dirname( dirname( dirname(__FILE__) ) );

$db_conf = include $file_dir . '/includes/config.php';

include($file_dir. '/includes/class_db_mysql.php');
$dbl = new db_mysql("mysql:host=".$db_conf['host'].";dbname=".$db_conf['database'],$db_conf['username'],$db_conf['password'], $db_conf['table_prefix']);

include($file_dir . '/includes/class_core.php');
$core = new core($dbl, $file_dir);

if(isset($_GET['q']))
{
	$get_data = $dbl->run("SELECT `module_id`, `nice_title` FROM `modules` WHERE `nice_title` LIKE ? ORDER BY `nice_title` ASC", array('%' . $_GET['q'] . '%'))->fetch_all();
	// Make sure we have a result
	if($get_data)
	{
		foreach ($get_data as $key => $value)
		{
			$data[] = array('id' => $value['module_id'], 'text' => $value['nice_title']);
		}
	}
	else
	{
		$data[] = array('id' => '0', 'text' => 'No modules found that match!');
	}
	echo json_encode($data);
}
?>
