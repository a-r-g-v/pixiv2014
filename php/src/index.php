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
// Model: Attempt Login
function attempt_login($login, $password)
{
	return ['error' => 'locked'];
}
// Page:Index
function pages_index()
{
	$content = load_view("index.html.php");
	print load_view("base.html.php",["content" => $content]);
}

// Page:Login
function pages_login()
{
	$result = attempt_login($_POST['login'],$_POST['password']);
	if (!empty($result['user']))
	{
		session_regenerate_id(true);
		pages_mypage();
	}
	else
	{
		switch($result['error']) 
		{
			case 'locked':
				flash_reg('notice', 'This account is locked.');
				break;
			case 'banned':
				flash_reg('notice', "You're banned.");
				break;
			default:
				flash_reg('notice', "Wrong username or password");
				break;
		}		
		pages_index();
	}
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
	if ($path[1] === "login")
	{
		pages_login();
	}
	else
	{
		pages_index();
	}	
}

route();
?>
