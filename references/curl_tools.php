<?php

$allVpnInterface=getAvailableVpnInterface();
$lastInterface=0;
$curlRetryCount=0;

function sendCurlRequest($url){
	$interface=getNextVpnInterface();
	if ($GLOBALS["curl_debug"]==1) {
		echo $GLOBALS["lineHr"];
		echo "Interface: ".$interface.$GLOBALS["lineBreak"];
		echo "URL: ".$url.$GLOBALS["lineBreak"];
		echo $GLOBALS["lineHr"];
	}


	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	curl_setopt($ch, CURLOPT_TIMEOUT, 2000);
	curl_setopt($ch, CURLOPT_INTERFACE, $interface);
	$content = curl_exec($ch);
	$curlFail=0;
	if (curl_errno($ch) == 28) {
	    $curlFail=1;
	}
	curl_close($ch);
	$html= str_get_html($content);
	if ($curlFail==1) {
		echo $GLOBALS["lineHr"]."CURL FAIL".$GLOBALS["lineHr"].$GLOBALS["lineHr"];
		writeEventLog("CURL FAIL! Interface: ".$interface." URL:".$url,"searchPriceComProductID","ERROR");
		$GLOBALS["curlRetryCount"]++;
		if ($GLOBALS["curlRetryCount"]>5) {
			$html="ERROR";
		}else{
			sleep(5);
			$html=sendCurlRequest($url);
		}	
	}elseif ( strlen($html)==0 || sizeof($html->find('.captcha-text'))==1) {
		echo $GLOBALS["lineHr"]."BLOCK".$GLOBALS["lineHr"].$GLOBALS["lineHr"];
		writeEventLog("BLOCK BY Price.com! Interface: ".$interface." URL:".$url,"searchPriceComProductID","ERROR");
		$GLOBALS["curlRetryCount"]++;
		if ($GLOBALS["curlRetryCount"]>5) {
			$html="ERROR";
		}else{
			sleep(5);
			$html=sendCurlRequest($url);
		}		
	}
	return $html;
}

function reloadAvailableVpnInterface(){
	$GLOBALS["allVpnInterface"]=getAvailableVpnInterface();
}

function showAvailableVpnInterface(){
	echo $GLOBALS["lineHr"];
	for ($i=0; $i < sizeof($GLOBALS["allVpnInterface"]); $i++) { 
		echo $GLOBALS["allVpnInterface"][$i].$GLOBALS["lineBreak"];
	}
	echo $GLOBALS["lineHr"];
}

function getAvailableVpnInterface(){
	$interface =explode("\n",shell_exec("ifconfig | grep 'ppp\|qtunc\|tun2' | awk '{print $1}'"));
	$interface[sizeof($interface)-1]="eth0";
	return $interface;
}

function getNextVpnInterface(){
	if (sizeof($GLOBALS["allVpnInterface"])==0) {
		return "eth0";
	}
	$randomInterface=rand(0,sizeof($GLOBALS["allVpnInterface"])-2);

	if ($GLOBALS["lastInterface"]==sizeof($GLOBALS["allVpnInterface"])-1) {
		$GLOBALS["lastInterface"]=0;
	}
	$nextInterface=$GLOBALS["allVpnInterface"][$GLOBALS["lastInterface"]];
	$GLOBALS["lastInterface"]++;

	$nextInterface=$GLOBALS["allVpnInterface"][$randomInterface];
	return $nextInterface;
}

?>