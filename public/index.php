<?php

namespace App;

// Error reporting
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_startup_errors', TRUE);
ini_set('display_errors', TRUE);

// Security
ini_set('session.cookie_httponly', '1');
header('Referrer-Policy: same-origin');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src *;");

function parseEnvFile($filePath)
{
	if (!file_exists($filePath)) {
		// Optionally handle the case where the file does not exist
		throw new \Exception("The file at $filePath does not exist.");
	}

	$envArray = [];
	$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	foreach ($lines as $line) {
		if (strpos(trim($line), '#') === 0) {
			continue; // Skip comments
		}

		list($name, $value) = explode('=', $line, 2);
		$name = trim($name);
		$value = trim($value);

		// Remove surrounding quotes from the value
		$value = trim($value, "\"'");

		$envArray[$name] = $value;
	}

	return $envArray;
}
$envFilePath = '../node.env'; // Replace with the actual path to your .env file
$envVars = parseEnvFile($envFilePath);

$GLOBALS["data-dir"] = "../data";
define('Data_Dir', "../data");
define('CHAIN_SYMBOL', $envVars["CHAIN_SYMBOL"] ?? 'BTC');
define('RPC_USER', $envVars["RPC_USER"] ?? 'bitcoin');
define('RPC_PASSWORD', $envVars["RPC_PASSWORD"] ?? 'password');
define('RPC_PORT', $envVars["RPC_PORT"] ?? '9333');
define('RPC_IP', $envVars["RPC_IP"] ?? '127.0.0.1');

require_once '../src/Autoloader.php';

Autoloader::register();

// Check IP, deny access if not allowed
if (!(empty(Config::ACCESS_IP) || $_SERVER['REMOTE_ADDR'] == "127.0.0.1" || $_SERVER['REMOTE_ADDR'] == "::1" || $_SERVER['REMOTE_ADDR'] == Config::ACCESS_IP)) {
	header('Location: login.html');
	exit;
}

// Create bitcoin core rpc client
if (defined('App\Config::RPC_PORT')) {
	$rpcIp = Config::RPC_IP;
	$rpcPort = Config::RPC_PORT;
} else {
	preg_match("/(.*):([0-9]{1,5})$/", Config::RPC_IP, $matches);
	$rpcIp = $matches[1];
	$rpcPort = $matches[2];
}
$bitcoind = new jsonRPCClient(Config::RPC_USER, Config::RPC_PASSWORD, $rpcIp, $rpcPort);

// Cronjob Rule Run
if ((isset($_GET['job']) && $_GET['job'] === substr(hash('sha256', Config::PASSWORD . "ebe8d532"), 0, 24)) || (isset($argc) && $argv[1] === substr(hash('sha256', Config::PASSWORD . "ebe8d532"), 0, 24))) {
	require_once 'src/Utility.php';
	Rule::run();
	exit;
}


function getSsoClaims()
{
		$headers = apache_request_headers() ?? [];

		$oidcClaims = [];
		foreach ($headers as $key => $value) {
				if (strpos($key, 'OIDC_CLAIM') === 0) {
						$newKey = substr($key, strlen('OIDC_CLAIM_'));
						$oidcClaims[$newKey] = $value;
				}
		}

		return $oidcClaims;
}

// Start check user session
session_start();
$passToken = hash('sha256', Config::PASSWORD . "ibe81rn6");

$userInfo = getSsoClaims();

// Active Session
if ((isset($_SESSION['login']) && $_SESSION['login'] === TRUE) || !empty($userInfo)) {
	// Nothing needs to be done	
	// Login Cookie available	
} elseif (isset($_COOKIE["Login"]) && $_COOKIE["Login"] === $passToken) {
	$_SESSION['login'] = TRUE;
	$_SESSION["csfrToken"] = hash('sha256', random_bytes(20));
	// Password disabled
} elseif (Config::PASSWORD === "") {
	$_SESSION["csfrToken"] = hash('sha256', random_bytes(20));
	// Login		
} elseif (!isset($_SESSION['login']) && isset($_POST['password']) && $_POST['password'] === Config::PASSWORD) {
	$passHashed = hash('sha256', Config::PASSWORD);
	$_SESSION['login'] = TRUE;
	$_SESSION["csfrToken"] = hash('sha256', random_bytes(20));
	if (isset($_POST['stayloggedin'])) {
		setcookie("Login", $passToken, [
			'expires' => time() + 2592000,
			'path' => '*',
			'secure' => FALSE,
			'httponly' => TRUE,
			'samesite' => 'Strict',
		]);
	}

	// Not logged in or invalid data
} else {
	header('Location: login.html');
	exit;
}

// Load ulitily and content creator functions
require_once '../src/Utility.php';
require_once '../src/Content.php';

// Globals
$error = "";
$message = "";
$content = "";

// Content
// Main Page
if (empty($_GET) || $_GET['p'] == "main") {
	try {
		$content = createMainContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'main', 'title' => 'Home', 'content' => $content);

	// Peers Page   
} elseif ($_GET['p'] == "peers") {

	// Check if command
	if (isset($_GET['c']) && $_GET['t'] == $_SESSION["csfrToken"]) {
		// Ban Command
		if ($_GET['c'] == "ban") {
			$err = 0;
			if (preg_match("/^([0-9a-z:\.]{7,39})$/", $_GET['ip'], $match)) {
				$ip = $match[1];
			} else {
				$err += 1;
			}
			if (is_numeric($_GET['time'])) {
				$bantime = intval($_GET['time']);
			} else {
				$err += 1;
			}
			if ($err == 0) {
				try {
					$result = $bitcoind->setban($ip, "add", $bantime);
					// Sleep necessary otherwise peer is still returned by bitcoin core
					sleep(1);
					$message = "Peer successfully banned";
				} catch (\Exception $e) {
					$error = "Peer could not be banned";
				}
			} else {
				$error = "Invalid Peer/Ban time";
			}

			// Disconnect Command
		} elseif ($_GET['c'] == "disconnect") {
			if (preg_match("/^(\[{0,1}[0-9a-z:\.]{7,39}\]{0,1}:[0-9]{1,5})$/", $_GET['ip'], $match)) {
				$ip = $match[1];
				try {
					$result = $bitcoind->disconnectnode($ip);
					// Sleep necessary otherwise peer is still returned by bitcoin core
					sleep(1);
					$message = "Peer successfully disconnected";
				} catch (\Exception $e) {
					$error = "Peer could not be found";
				}
			}
			// Add Hoster Command
		} elseif ($_GET['c'] == "addhoster") {
			if (preg_match("/^[0-9a-zA-Z-,\. ]{3,40}$/", $_GET['n'])) {
				$hosterJson = file_get_contents(dirname(__FILE__) . '/../src/hoster.json');
				$hoster = json_decode($hosterJson);
				$hoster[] = $_GET['n'];
				file_put_contents(dirname(__FILE__) . '/../src/hoster.json', json_encode($hoster));
				updateHosted($_GET['n'], true);
				$message = "Hoster succesfully added";
			} else {
				$error = "Invalid Hoster";
			}
		}
		// Apply rules		  
		elseif ($_GET['c'] == "run") {
			try {
				Rule::run();
			} catch (\Exception $e) {
				$error = "Error while running rules";
			}
			if (empty($e)) {
				$message = "Rules succesfully run. See log file for details";
			}
		}
	}
	try {
		$content = createPeerContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}

	// Create page specfic variables
	$data = array('section' => 'peers', 'title' => 'Peers', 'content' => $content);

	// Hoster Page	
} elseif ($_GET['p'] == "hoster") {

	$hosterList = json_decode(file_get_contents(dirname(__FILE__) . '/../src/hoster.json'), true);

	if (isset($_GET['c']) && $_GET['t'] == $_SESSION["csfrToken"]) {
		// Remove Hoster Command
		if ($_GET['c'] == "remove") {
			if (preg_match("/^[0-9a-zA-Z-,\. ]{3,40}$/", $_GET['n'])) {
				if (($key = array_search($_GET['n'], $hosterList)) != false) {
					unset($hosterList[$key]);
					file_put_contents(dirname(__FILE__) . '/../src/hoster.json', json_encode($hosterList));
					updateHosted($_GET['n'], false);
					$message = "Hoster succesfully removed";
				} else {
					$error = "Hoster not found";
				}
			} else {
				$error = "Invalid Hoster";
			}
			// Add Hoster Command
		} elseif ($_GET['c'] == "add") {
			if (preg_match("/^[0-9a-zA-Z-,\. ]{3,40}$/", $_GET['n'])) {
				if (!in_array($_GET['n'], $hosterList)) {
					$hosterList[] = $_GET['n'];
					file_put_contents(dirname(__FILE__) . '/../src/hoster.json', json_encode($hosterList));
					updateHosted($_GET['n'], true);
					$message = "Hoster succesfully added";
				} else {
					$error = "Hoster already in list";
				}
			} else {
				$error = "Invalid Hoster";
			}
		}
	}

	$content = json_decode(file_get_contents(dirname(__FILE__) . '/../src/hoster.json'), TRUE);
	// Sort Hoster ascending
	natcasesort($content);

	// Create page specfic variables
	$data = array('section' => 'hoster', 'title' => 'Host Manager', 'content' => $content);

	// Ban List Page	
} elseif ($_GET['p'] == "banlist") {

	// Check if commands needs to be run   
	if (isset($_GET['c']) && $_GET['t'] == $_SESSION["csfrToken"]) {
		if ($_GET['c'] == "unban") {
			if (preg_match("/^([0-9a-z:\.]{7,39}\/[0-9]{1,3})$/", $_GET['ip'], $match)) {
				$ip = $match[1];
				try {
					$result = $bitcoind->setban($ip, "remove");
					$message = "Node successfully unbanned";
				} catch (\Exception $e) {
					$error = "Node could not be unbanned";
				}
			} else {
				$error = "Invalid Node";
			}
		} elseif ($_GET['c'] == "clearlist") {
			try {
				$result = $bitcoind->clearbanned();
				$message = "Banlist cleared";
			} catch (\Exception $e) {
				$error = "Could not clear banlist";
			}
		} elseif ($_GET['c'] == "importbanlist") {
			try {
				$imNa = $_FILES['banlist']['tmp_name'];
				$banlist = array_map('str_getcsv', file($imNa));
				unlink($imNa);
				$i = 0;
				foreach ($banlist as $ban) {
					$timestamp = strtotime($ban[2]);
					if (checkIpBanList($ban[0]) && $timestamp !== FALSE) {
						$result = $bitcoind->setban($ban[0], "add", $timestamp, true);
						$i++;
					}
				}
				if ($i !== 0) {
					$message = "Banlist imported";
				} else {
					$error = "No valid data in file";
				}
			} catch (\Exception $e) {
				$error = "IPs already banned or node offline";
			}
		}
	}

	try {
		$content = createBanListContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'banlist', 'title' => 'Ban List', 'content' => $content);

	// Rules Page
} elseif ($_GET['p'] == "rules") {

	$editID = NULL;
	// Check if commands needs to be run   
	if (isset($_GET['c'])  && $_GET['t'] == $_SESSION["csfrToken"]) {
		// Save new or edited rule	
		if ($_GET['c'] == "save") {
			$rule = new Rule();
			$response = $rule->save($_POST);
			if ($response) {
				$message = "Rule succesfully saved";
			} else {
				$error = "Invalid rule data";
			}
			// Apply rules		  
		} elseif ($_GET['c'] == "run") {
			try {
				Rule::run();
			} catch (\Exception $e) {
				$error = "Error while running rules";
			}
			if (empty($e)) {
				$message = "Rules succesfully run. See log file for details";
			}
			// Edit rule		   
		} elseif ($_GET['c'] == "edit") {
			if (ctype_digit($_GET['id'])) {
				$editID = $_GET['id'];
			} else {
				$error = "Invalid rule ID";
			}
			// Delete single rule or all		  
		} elseif ($_GET['c'] == "delete") {
			if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
				$reponse =  Rule::deleteByID($_GET['id']);
				if ($reponse) {
					$message = "Rule succesfully deleted";
				} else {
					$error = "Could not delete rule";
				}
			} elseif (!isset($_GET['id'])) {
				$reponse =  Rule::deleteAll();
				if ($reponse) {
					$message = "Rules succesfully deleted";
				} else {
					$error = "Could not delete rules";
				}
			} else {
				$error = "Invalid rule ID";
			}
			// Reset rule counter
		} elseif ($_GET['c'] == "resetc") {
			$reponse =  Rule::resetCounter();
			if ($reponse) {
				$message = "Counter succesfully reseted";
			} else {
				$error = "Could not reseted counter";
			}
			// Delete logfile
		} elseif ($_GET['c'] == "dellog") {
			$reponse =  Rule::deleteLogfile();
			if ($reponse) {
				$message = "Logfile succesfully deleted";
			} else {
				$error = "Could not delete logfile";
			}
		}
	}

	try {
		$content = createRulesContent($editID);
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'rules', 'title' => 'Rules Manager', 'content' => $content);

	// Memory Pool Page	
} elseif ($_GET['p'] == "mempool") {
	try {
		$content = createMempoolContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'mempool', 'title' => 'Memory Pool', 'content' => $content);

	// Wallet Page
} elseif ($_GET['p'] == "wallet") {
	try {
		$content = createWalletContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'wallet', 'title' => 'Wallet Overview', 'content' => $content);

	// Blocks Page 
} elseif ($_GET['p'] == "blocks") {
	try {
		$content = createBlocksContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'blocks', 'title' => 'Blocks', 'content' => $content);

	// Forks Page 
} elseif ($_GET['p'] == "forks") {
	try {
		$content = createForksContent();
	} catch (\Exception $e) {
		$error = $e->getMessage();
	}
	$data = array('section' => 'forks', 'title' => 'Forks', 'content' => $content);

	// Settings Page	
} elseif ($_GET['p'] == "settings") {
	$geoPeers = Config::PEERS_GEO;
	if (isset($_GET['c'])  && $_GET['t'] == $_SESSION["csfrToken"]) {
		if (isset($_GET['c']) && $_GET['c'] == "geosave") {
			// Check if Geo Peer Tracing was changed
			if (isset($_POST['geopeers']) && $_POST['geopeers'] == "on") {
				$geoPeers = "true";
			} else {
				$geoPeers = "false";
			}

			// Write new settings in config.php
			if (file_exists('src/Config.php')) {
				$conf = file_get_contents('src/Config.php');
				$conf = preg_replace("/PEERS_GEO = (true|false);/i", 'PEERS_GEO = ' . $geoPeers . ';', $conf);
				$resultConfig = file_put_contents('src/Config.php', $conf);
				if ($resultConfig) $message = "Setings succesfully saved";
				else $error = "No permissions to write config file";
			} else {
				$error = "Config file does not exists";
			}
		}
	}
	$data = array('section' => 'settings', 'title' => 'Settings', 'geoPeers' => $geoPeers);

	// About Page	
} elseif ($_GET['p'] == "about") {
	$data = array('section' => 'about', 'title' => 'About');
} elseif ($_GET['p'] == "logout") {
	session_unset();
	session_destroy();
	$_SESSION = array();
	setcookie("Login", "", time() - 3600);
	if (!empty($userInfo)) {
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
		$logoutUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
		header('Location: ssocallback.php?logout=' . urlencode($logoutUrl));
	} else {
		header('Location: index.php');
	}
	exit;
} else {
	header('Location: index.php');
	exit;
}

// Create HTML output
if (isset($error)) {
	$data['error'] = $error;
}
if (isset($message)) {
	$data['message'] = $message;
}

$data['userInfo'] = !empty($userInfo) ? json_decode(json_encode($userInfo)) : json_decode(json_encode([
	'name' => 'Admin',
]));

$tmpl = new Template($data);
echo $tmpl->render();
