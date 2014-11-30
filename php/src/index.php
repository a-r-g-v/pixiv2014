<?php

// load view
function load_view($file, $param = [])
{	
	if (!empty($param))
	{
		extract($param);
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


?>
