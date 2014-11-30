<?php
ini_set('set_time_limit', 60*60*60);
ini_set( 'display_errors', 1 );


$db;
$config = [];

// Core: Load View
function load_view($file, $param = [])
{	
	if (!empty($param))
	{
		extract($param);
	}	
	if (!empty($_SESSION['flash']))
	{
		$flash =  $_SESSION['flash'];
	}
	ob_start();
	include ("./views/" . $file);
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}
// Core: Read Cofig
function configure()
{
	global $config;
	global $db;
	$host = getenv('ISU4_DB_HOST') ?: 'localhost';
	$port = getenv('ISU4_DB_PORT') ?: 3306;
	$dbname = getenv('ISU4_DB_NAME') ?: 'isu4_qualifier';
	$username = getenv('ISU4_DB_USER') ?: 'root';
	$password = getenv('ISU4_DB_PASSWORD');
 	$db = null;
 	try {
 		$db = new PDO(
		'mysql:host=' . $host . ';port=' . $port. ';dbname=' . $dbname,
		$username,
		$password,
      		[ PDO::ATTR_PERSISTENT => true,
        	  PDO::MYSQL_ATTR_INIT_COMMAND => 'SET CHARACTER SET `utf8`',
      		]
		);
	} catch (PDOException $e) {
		print "Connection faild: $e";
		exit(-1);	
	}
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$config = [
	    'user_lock_threshold' => getenv('ISU4_USER_LOCK_THRESHOLD') ?: 3,
	    'ip_ban_threshold' => getenv('ISU4_IP_BAN_THRESHOLD') ?: 10
	] ;
               	
}

// Model: Attempt Login
function attempt_login($login, $password)
{
	global $db;
	$stmt = $db->prepare('SELECT * FROM users WHERE login = :login');
	$stmt->bindValue(':login', $login);
	$stmt->execute();
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (ip_banned()) {
	    login_log(false, $login, isset($user['id']) ? $user['id'] : null);
	    return ['error' => 'banned'];
	}
	if (user_locked($user)) {
	    login_log(false, $login, $user['id']);
	    return ['error' => 'locked'];
	}
	if (!empty($user) && calculate_password_hash($password, $user['salt']) == $user['password_hash']) {
    	login_log(true, $login, $user['id']);
    	return ['user' => $user];
  	}

	elseif (!empty($user)) {
   	login_log(false, $login, $user['id']);
    	return ['error' => 'wrong_password'];
  	}

	else {
	login_log(false, $login);
	return ['error' => 'wrong_login'];
	}
}

// Model:login_log
function login_log($succeeded, $login, $user_id=null) {
  global $db;

  $stmt = $db->prepare('INSERT INTO login_log (`created_at`, `user_id`, `login`, `ip`, `succeeded`) VALUES (NOW(),:user_id,:login,:ip,:succeeded)');
  $stmt->bindValue(':user_id', $user_id);
  $stmt->bindValue(':login', $login);
  $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(':succeeded', $succeeded ? 1 : 0);
  $stmt->execute();
}


// Model: 
function ip_banned() {
  global $db;
  $stmt = $db->prepare('SELECT COUNT(1) AS failures FROM login_log WHERE ip = :ip AND id > IFNULL((select id from login_log where ip = :ip AND succeeded = 1 ORDER BY id DESC LIMIT 1), 0)');
  $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
  $stmt->execute();
  $log = $stmt->fetch(PDO::FETCH_ASSOC);

  global $config;
  return $config['ip_ban_threshold'] <= $log['failures'];
}

// Mode: Check Log User?
function user_locked($user) {
  if (empty($user)) { return null; }

  global $db;
  $stmt = $db->prepare('SELECT COUNT(1) AS failures FROM login_log WHERE user_id = :user_id AND id > IFNULL((select id from login_log where user_id = :user_id AND succeeded = 1 ORDER BY id DESC LIMIT 1), 0)');
  $stmt->bindValue(':user_id', $user['id']);
  $stmt->execute();
  $log = $stmt->fetch(PDO::FETCH_ASSOC);

  global $config;
  return $config['user_lock_threshold'] <= $log['failures'];
}
// Model
function calculate_password_hash($password, $salt) {
  return hash('sha256', $password . ':' . $salt);
}
// Model
function current_user() {
  if (empty($_SESSION['user_id'])) {
    return null;
  }

  global $db;

  $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
  $stmt->bindValue(':id', $_SESSION['user_id']);
  $stmt->execute();
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (empty($user)) {
    unset($_SESSION['user_id']);
    return null;
  }

  return $user;
}

// Model
function last_login() {
  $user = current_user();
  if (empty($user)) {
    return null;
  }

  global $db;

  $stmt = $db->prepare('SELECT * FROM login_log WHERE succeeded = 1 AND user_id = :id ORDER BY id DESC LIMIT 2');
  $stmt->bindValue(':id', $user['id']);
  $stmt->execute();
  $stmt->fetch();
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Model
function banned_ips() {
  global $config;
  $threshold = $config['ip_ban_threshold'];
  $ips = [];

  global $db;

  $stmt = $db->prepare('SELECT ip FROM (SELECT ip, MAX(succeeded) as max_succeeded, COUNT(1) as cnt FROM login_log GROUP BY ip) AS t0 WHERE t0.max_succeeded = 0 AND t0.cnt >= :threshold');
  $stmt->bindValue(':threshold', $threshold);
  $stmt->execute();
  $not_succeeded = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  $ips = array_merge($not_succeeded);

  $stmt = $db->prepare('SELECT ip, MAX(id) AS last_login_id FROM login_log WHERE succeeded = 1 GROUP by ip');
  $stmt->execute();
  $last_succeeds = $stmt->fetchAll();

  foreach ($last_succeeds as $row) {
    $stmt = $db->prepare('SELECT COUNT(1) AS cnt FROM login_log WHERE ip = :ip AND :id < id');
    $stmt->bindValue(':ip', $row['ip']);
    $stmt->bindValue(':id', $row['last_login_id']);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($threshold <= $count) {
      array_push($ips, $row['ip']);
    }
  }

  return $ips;
}


// Model
function locked_users() {
  global $config;
  $threshold = $config['user_lock_threshold'];
  $user_ids = [];

  global $db;

  $stmt = $db->prepare('SELECT login FROM (SELECT user_id, login, MAX(succeeded) as max_succeeded, COUNT(1) as cnt FROM login_log GROUP BY user_id) AS t0 WHERE t0.user_id IS NOT NULL AND t0.max_succeeded = 0 AND t0.cnt >= :threshold');
  $stmt->bindValue(':threshold', $threshold);
  $stmt->execute();
  $not_succeeded = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  $user_ids = array_merge($not_succeeded);

  $stmt = $db->prepare('SELECT user_id, login, MAX(id) AS last_login_id FROM login_log WHERE user_id IS NOT NULL AND succeeded = 1 GROUP BY user_id');
  $stmt->execute();
  $last_succeeds = $stmt->fetchAll();

  foreach ($last_succeeds as $row) {
    $stmt = $db->prepare('SELECT COUNT(1) AS cnt FROM login_log WHERE user_id = :user_id AND :id < id');
    $stmt->bindValue(':user_id', $row['user_id']);
    $stmt->bindValue(':id', $row['last_login_id']);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($threshold <= $count) {
      array_push($user_ids, $row['login']);
    }
  }

  return $user_ids;
}


// Page:Index
function pages_index()
{
	if ($_SERVER['REQUEST_METHOD'] != "GET") return; 
	unset($_SESSION['flash']);
	$content = load_view("index.html.php");
	print load_view("base.html.php",["content" => $content]);
}

// Page:Login
function pages_login()
{
	if ($_SERVER['REQUEST_METHOD'] != "POST") return; 
	$result = attempt_login($_POST['login'],$_POST['password']);
	if (!empty($result['user']))
	{
		session_regenerate_id(true);
		redirect_to("/mypage");
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

		redirect_to("/");
	}
}

// Page:Mypage
function pages_mypage()
{
	if ($_SERVER['REQUEST_METHOD'] != "GET") return; 
	$user = current_user();
	if (empty($user))
	{
		flash_reg('notice', 'You must be logged in');
		redirect_to("/");
	}
	else
	{
		$param = ['user' => $user, 'last_login' => last_login()];
		$param['content'] = load_view("mypage.html.php");
		print load_view("base.html.php",$param);
	}
}


// Page:Report
function pages_report()
{
	if ($_SERVER['REQUEST_METHOD'] != "GET") return; 
	return json_encode([
	 'banned_ips' => banned_ips(),
	'locked_users' => locked_users()
	]);	
}



// Redirect
function redirect_to($path)
{
	header("Location : http://54.64.142.159/$path");
}


// Flash

function flash_reg($type, $messsage)
{
	$flash_content_array = [];
	if (!empty($_SESSION['flash']))
	{
	$flash_content_array = $_SESSION['flash'];
	}
	$flash_content_array[$type] = $messsage;
	$_SESSION['flash'] = $flash_content_array;
}




// Route
function route()
{

	session_start();
	$path = explode("/", $_SERVER['REQUEST_URI']);
	// index pages
	if ($path[1] === "login")
	{
		pages_login();
	}
	elseif ($path[1] === "mypage")
	{
		pages_mypage();
	}
	elseif ($path[1] === "report")
	{
		pages_report();
	}
	else
	{
		pages_index();
	}	
}

configure();
route();
?>
