<?php
if(!defined('golapp')) 
{
	die('Direct access not permitted');
}
// blocks
$templating->load('admin_blocks/admin_block_blocks');
$templating->block('content');
