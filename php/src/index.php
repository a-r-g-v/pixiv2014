<?php

$flash_content_array = [];
// load view
function load_view($file, $param = [])
{	
	global $flash_content_array;
	if (!empty($param))
	{
		extract($param);
	}	
	if (!empty($flash_content_array))
	{
		$flash =  $flash_content_array;
	}
	ob_start();
	include ("./views/" . $file);
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

// Page:Index
function pages_index()
{
	$content = load_view("index.html.php");
	print load_view("base.html.php",["content" => $content]);
}

// Flash

function flash_reg($type, $messsage)
{
	global $flash_content_array;
	$flash_content_array[$type] = $messsage;
}




// Route
function route()
{

	$path = explode("/", $_SERVER['REQUEST_URI']);
	// index pages
	var_dump($path);
	if (empty($path[1]))
	{
		pages_index();
	}
	
}

route();
?>
