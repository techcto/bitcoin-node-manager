<?php

namespace App;

function createMainContent(){
  $peersData = getPeerData();
  $peerCount = count($peersData["peers"]);
  $banListInfo = createBanListContent();

  $content = [];
  $content['bannedPeers'] = $banListInfo['totalBans'];
  $content['last24h'] = $banListInfo['lastCount'];
  $content['node'] = new Node();
  $content['geo'] = Config::PEERS_GEO;
  if(isset($peersData['countryList'])) $content['map'] = createMapJs($peerCount, $peersData['countryList']);
  if(isset($peersData['api'])) $content['api'] = $peersData['api'];
  $content['nPeers'] = $peersData["newPeersCount"];
  $content['chartData'] = getTopClients($peersData["peers"]);

  // Current peers traffic
  $content['cTrafficIn'] = round($peersData["cTrafficIn"]/1000, 2);
  $content['cTrafficOut'] = round($peersData["cTrafficOut"]/1000, 2);

  return $content;
}

function createPeerContent(){
	global $bitcoind;

	$peersData = getPeerData();
	$netinfo = $bitcoind->getnettotals();
	$content = getMostPop($peersData["peers"]);
	$content['peers'] = $peersData["peers"];
	$content['tPeers'] = count($peersData["peers"]);
	$content['nPeers'] = $peersData["newPeersCount"];
	$content['cTraffic'] = round($peersData["cTraffic"]/1000,2);
	$content['cTrafficIn'] = round($peersData["cTrafficIn"]/1000, 2);
	$content['cTrafficOut'] = round($peersData["cTrafficOut"]/1000, 2);
	$content['tTraffic'] = round(max(1, $netinfo["totalbytesrecv"] + $netinfo["totalbytessent"])/1000000000,2);
	$content['tTrafficOutP'] = round(($netinfo["totalbytessent"]/max(1, $netinfo["totalbytesrecv"] + $netinfo["totalbytessent"])),2)*100;
	$content['cTrafficP'] = round($content['cTraffic']/($content['tTraffic'] > 0 ? $content['tTraffic'] : 1),2)*100;
	$content['geo'] = Config::PEERS_GEO;
  if(isset($peersData['api'])) $content['api'] = $peersData['api'];

	return $content;
}

function createBanListContent(){
	global $bitcoind, $error;

	$banlist = $bitcoind->listbanned();

	$content = [];
	$lastCount = 0;
	$autoCount = 0;
	$avgTime = 0;

	// Total Bans
	$totalBans = count($banlist);

	foreach($banlist as &$ban){
		 // In last 24 hours
		if($ban['ban_created'] >= time()-24*3600){
			$lastCount++;
		}

		// Sum up all ban time
		$avgTime += $ban['banned_until']-$ban['ban_created'];

		$ban['ban_duration'] = round(($ban['banned_until'] - $ban['ban_created'])/86400,1);
		$ban['ban_created'] = getDateTime($ban['ban_created']);
		$ban['banned_until'] = getDateTime($ban['banned_until']);
		if(!checkIpBanList($ban['address'])){
			$error = "Invalid ban list IP";
			return false;
		}
		$ban['ipv6'] = checkIfIpv6($ban['address']);
	}

	// Calculate and format avergae ban time
	$content['avgTime'] = ($totalBans > 0 ? round($avgTime/(86400*$totalBans),0): 0);

	$content['totalBans'] = $totalBans;
	$content['lastCount'] = $lastCount;

	// List of all banned peers
	$content['banList'] = $banlist;
	

	return $content;
}

function createBlocksContent() {
    global $bitcoind;
    $content = ['blocks' => [], 'totalTx' => 0, 'totalFees' => 0, 'totalSize' => 0];
    $blockHash = $bitcoind->getbestblockhash();
    $feesAvailable = true;
    for ($i = 0; $i < Config::DISPLAY_BLOCKS; $i++) {
        $block = $bitcoind->getblock($blockHash);
        $fees = null;
        if ($block['height'] === 0) {
            $fees = 0;
        } elseif ($feesAvailable) {
            try {
                $stats = $bitcoind->getblockstats($blockHash, ['totalfee']);
                $fees = $stats['totalfee'] / 100000000;
            } catch (\RuntimeException $exception) {
                $feesAvailable = false;
            }
        }
        $versionHex = $block['versionHex'] ?? sprintf('%08x', $block['version']);
        $content['blocks'][$block['height']] = [
            'hash' => $block['hash'],
            'size' => round($block['size'] / 1000000, 2),
            'versionhex' => $versionHex,
            'voting' => getVoting($versionHex),
            'asicboost' => checkAsicBoost($versionHex),
            'time' => getDateTime($block['time']),
            'mediantime' => getDateTime($block['mediantime'] ?? $block['time']),
            'timeago' => round((time() - $block['time']) / 60),
            'coinbasetx' => $block['tx'][0],
            'fees' => $fees === null ? 'Unavailable' : round($fees, 8),
            'txcount' => count($block['tx']),
        ];
        $content['totalSize'] += $block['size'];
        $content['totalTx'] += count($block['tx']);
        $content['totalFees'] += $fees ?? 0;
        if (empty($block['previousblockhash'])) { break; }
        $blockHash = $block['previousblockhash'];
    }
    $count = count($content['blocks']);
    $content['avgTxSize'] = round($content['totalSize'] / max(1, $content['totalTx']) / 1000, 2);
    $content['avgSize'] = round($content['totalSize'] / max(1, $count) / 1000000, 2);
    $content['totalSize'] = round($content['totalSize'] / 1000000, 2);
    $content['avgFee'] = $feesAvailable ? round($content['totalFees'] / max(1, $count), 8) : 'Unavailable';
    $content['totalFees'] = $feesAvailable ? round($content['totalFees'], 8) : 'Unavailable';
    $content['numberOfBlocks'] = $count;
    $last = end($content['blocks']);
    $content['timeframe'] = round(($last['timeago'] ?? 0) / 60);
    return $content;
}

function createForksContent(){
	global $bitcoind;

	// Count forks in last 24h
	$timeAgo = time()-86400;
	$content["recentForks"] = 0;

	$forks = $bitcoind->getchaintips();
	$i = 0;
	$lastTime = 0;

	foreach($forks as $fork){
		if($i == Config::DISPLAY_FORKS){
			break;
		}

		$content["blocks"][$i]["height"] = $fork["height"];
		$content["blocks"][$i]["hash"] = $fork["hash"];
		$content["blocks"][$i]["forklength"] = $fork["branchlen"];
		$content["blocks"][$i]["status"] = $fork["status"];
		$content["blocks"][$i]["succeeded"] = $fork["height"];

		if($fork["status"] != "headers-only" AND $fork["status"] != "unknown"){
			$block = $bitcoind->getblock($fork["hash"]);
			$content["blocks"][$i]["size"] = round($block["size"]/1000000,2);
			$content["blocks"][$i]["versionhex"] = $block["versionHex"];
			$content["blocks"][$i]["voting"] = getVoting($block["versionHex"]);
			$content["blocks"][$i]["time"] = getDateTime($block["time"]);
			$lastTime = $block["time"];
			$content["blocks"][$i]["mediantime"] = getDateTime($block["mediantime"]);
			$content["blocks"][$i]["time"] = $block["time"];
			$content["blocks"][$i]["timeago"] = round((time() - $block["time"])/ (($i === 0) ? 60 : 86400));
			$content["blocks"][$i]["txcount"] = count($block["tx"]);

			if($content["blocks"][$i]["time"] >= $timeAgo){
				$content["recentForks"]++;
			}
		}
		$i++;
	}

	// How far to go back (days)
	$content["timeframe"] = round((time()-$lastTime)/86400);
	$content["forkCount"] = $i - 1;

	return $content;
}

/**
 * @param null $editID
 * @return mixed
 */
function createRulesContent($editID = NULL){

	$rulesContent['rules'] = Rule::getRules();
	$rulesContent['jobToken'] = substr(hash('sha256', CONFIG::PASSWORD."ebe8d532"),0,24);
	$rulesContent['editRule'] = new Rule();

	if (file_exists($GLOBALS["data-dir"].'/rules.log')){
		$log = file_get_contents($GLOBALS["data-dir"].'/rules.log');
	}else{
		$log = "No logs available";
	}
	$rulesContent['log'] = $log;


	if(!is_null($editID)){
		$response = Rule::getByID($_GET['id']);
		if($response != FALSE){
			$rulesContent['editRule'] = $response;
		// TODO: Return repsonse to controller
		}else{
			$error = "Couldn't find Rule!";
		}
	}

	return $rulesContent;
}

function createMempoolContent(){
	$content['node'] = new Node();

	return $content;
}

function createWalletContent(){
	global $bitcoind, $error;
	
	try{
		$unspents = $bitcoind->listunspent();
		$walletInfo = $bitcoind->getwalletinfo();
		try {
            $balances = $bitcoind->getbalances();
        } catch (\RuntimeException $exception) {
            if ($exception->getCode() !== -32601) { throw $exception; }
            // Older Dogecoin nodes report wallet balances in getwalletinfo.
            $balances = ['mine' => [
                'trusted' => $walletInfo['balance'] ?? 0,
                'untrusted_pending' => $walletInfo['unconfirmed_balance'] ?? 0,
                'immature' => $walletInfo['immature_balance'] ?? 0,
            ]];
        }
	}catch(\Exception $e){
		if ($e->getCode() == -18) {
			$error = "No wallet is loaded on this node. Load or create one through the node wallet API.";
		} else {
			$error = $e->getCode() === -19 ? "Multiple wallets are loaded. Select a wallet in the node API." : "Wallet information is unavailable: " . $e->getMessage();
		}
		return "";
	}

	$content['wallet']["walletversion"] = checkInt($walletInfo["walletversion"]);	
	$content['wallet']["balance"] = checkInt($balances["mine"]["trusted"]);	
  if(isset($balances["watchonly"])) {
    $content['wallet']["watchonly"] = checkInt($balances["watchonly"]["trusted"]);
  } else {
    $content['wallet']["watchonly"] = 0;
  }
	$content['wallet']["unconfirmed_balance"] = checkInt($balances["mine"]["untrusted_pending"]);	
	$content['wallet']["immature_balance"] = checkInt($balances["mine"]["immature"]);	
	$content['wallet']["txcount"] = checkInt($walletInfo["txcount"]);	
	
	$i = 0;

	foreach($unspents as $unspent){
		$content["utxo"][$i]["hash"] = $unspent["txid"];
		$content["utxo"][$i]["vout"] = $unspent["vout"];
		$content["utxo"][$i]["address"] = $unspent["address"];
		$content["utxo"][$i]["label"] = ($unspent["label"] ?? $unspent["account"] ?? '');
		$content["utxo"][$i]["amount"] = $unspent["amount"];
		$content["utxo"][$i]["confs"] = $unspent["confirmations"];
		$content["utxo"][$i]["spendable"] = $unspent["spendable"];
		$content["utxo"][$i]["solvable"] = $unspent["solvable"];
		$content["utxo"][$i]["safe"] = ($unspent["safe"] ?? false);

		$i++;
	}
	
	return $content;
}
?>
