<?php
include('../lib/simple_html_dom.php');
include("../db/qhk_connect.php");

$id=array();
$pn=array();
$srp=array();
$std_price=array();

/*
$stmt_getNasList = $qhk_conn->prepare("SELECT PN,PriceComID FROM NAS_PN WHERE PriceComID>0 AND isActive=1 AND isEOL=0");
$stmt_getNasList->execute();
$stmt_getNasList->bind_result($sql_nas_pn, $sql_price_com_id);
while($stmt_getNasList->fetch()){ 
	$id[]=$sql_price_com_id;
	$pn[]=$sql_nas_pn;
}
$stmt_getNasList->close();

$stmt_getNasList = $qhk_conn->prepare("SELECT srp FROM NAS_Price WHERE PN=? AND year=YEAR(CURDATE()) AND month=MONTH(CURDATE())");
for ($i=0; $i < sizeof($pn) ; $i++) { 
	$stmt_getNasList->bind_param("s",$pn[$i]);
	$stmt_getNasList->execute();
	$stmt_getNasList->bind_result($sql_nas_srp);
	while($stmt_getNasList->fetch()){ 
		$srp[$i]=$sql_nas_srp;
		$std_price[$i]=intval($sql_nas_srp*0.97);
	}
}
$stmt_getNasList->close();
$qhk_conn->close();
*/

$stmt_getNasList = $qhk_conn->prepare("SELECT PN,PriceComID,Price,PriceLimit FROM QNAP_Price_Monitor WHERE PriceComID>0");
$stmt_getNasList->execute();
$stmt_getNasList->bind_result($sql_nas_pn, $sql_price_com_id,$sql_price,$sql_priceLimit);
while($stmt_getNasList->fetch()){ 
	$id[]=$sql_price_com_id;
	$pn[]=$sql_nas_pn;
	$srp[]=$sql_price;
	$std_price[]=intval($sql_priceLimit);
}
$stmt_getNasList->close();


$shoplist=array();
$shopstatus=array();
$shopyellow=array();
$shopred=array();
$shopItemIndex=array();

for($i=0;$i<sizeof($id);$i++){
	$url="https://www.price.com.hk/product.php?p=".$id[$i];
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	
	$itemShopID[$i]=array();
	$itemPrice[$i]=array();
	$updateDate[$i]=array();
	$productName[$i]=$pn[$i];

	$html= str_get_html($content);  
	foreach($html->find('.quotation-merchant-name') as $shopname) {
        if (!in_array($shopname->plaintext, $shoplist)) {
        	$shopID=sizeof($shoplist);
        	$shoplist[sizeof($shoplist)]=$shopname->plaintext;
        	foreach ($shopname->find('a') as $shopLink) {
        		$shopLink->href=str_replace("starshop.php?s=","",$shopLink->href);
        		$shopLink->href=str_replace("shop.php?s=","",$shopLink->href);
        		$shopPriceID[$shopID]=$shopLink->href;
        	}
        }else{
        	$shopID=array_search($shopname->plaintext, $shoplist);
        }
        $itemShopID[$i][sizeof($itemShopID[$i])]=$shopID;
	    
	}
	foreach($html->find('span.product-price') as $price) {
	    $price->plaintext=str_replace("HK$","",$price->plaintext);
	    $price->plaintext=str_replace(",","",$price->plaintext);
	    $price->plaintext=str_replace(" ","",$price->plaintext);
	    $itemPrice[$i][sizeof($itemPrice[$i])]=$price->plaintext;
	}
	foreach($html->find('.quote-source') as $d) {
		$d->plaintext=str_replace("更新日期：","",$d->plaintext);
		$d->plaintext=str_replace("由星級商戶更新","",$d->plaintext);
		$d->plaintext=str_replace("檢舉","",$d->plaintext);
		$d->plaintext=str_replace("由商戶更新","",$d->plaintext);
		$d->plaintext=str_replace("由會員更新","",$d->plaintext);
		$d->plaintext=str_replace("更新","",$d->plaintext);
		$d->plaintext=str_replace("請先查詢","",$d->plaintext);
		$d->plaintext=str_replace("少量存貨","",$d->plaintext);
		$d->plaintext=str_replace("大量現貨","",$d->plaintext);
	    $updateDate[$i][sizeof($updateDate[$i])]=$d->plaintext;
	}
}

for($i=0;$i<sizeof($shoplist);$i++){
	$shopItemIndex[$i]=array();
	$shopred[$i]=0;
	$shopyellow[$i]=0;
	for($j=0;$j<sizeof($id);$j++){
		if(in_array($i,$itemShopID[$j])){
			if($itemPrice[$j][array_search($i, $itemShopID[$j])]<$std_price[$j]){
				$shopItemIndex[$i][sizeof($shopItemIndex[$i])]=$j;
				if (($std_price[$j] - $itemPrice[$j][array_search($i, $itemShopID[$j])])>100) {
					$shopred[$i]++;
				}else{
					$shopyellow[$i]++;
				}
			}
		}
	}
}

echo '<div class="row container"><div class="col-lg-12">';
for($i=0; $i<sizeof($shoplist); $i++){
	if($shopred[$i]==0 && $shopyellow[$i]==0){
		continue;
	}
	echo '<div class="col-md-3"><p>'.$shoplist[$i]."</p><p>";
	for($j=0; $j<sizeof($shopItemIndex[$i]); $j++){
		echo '<a href="http://www.price.com.hk/product.php?p='.$id[$shopItemIndex[$i][$j]].'">'.$productName[$shopItemIndex[$i][$j]]."</a> : ".$itemPrice[$shopItemIndex[$i][$j]][array_search($i, $itemShopID[$shopItemIndex[$i][$j]])]." -> ".$std_price[$shopItemIndex[$i][$j]].'~'.$srp[$shopItemIndex[$i][$j]].'<br/>';
	}
	echo "</p></div>";
}
echo "</div></div>";

echo '<div class="row container"><div class="table-responsive" class="col-lg-12"><table border="1">';
//model
echo '<tr><th width="300" colspan="3">'.date(DATE_COOKIE).'</th>';
for($i=0;$i<sizeof($id);$i++){
	echo '<th width="100"><a href="http://www.price.com.hk/product.php?p='.$id[$i].'">'.$pn[$i]."</a></th>";
}
echo '<th width="300" colspan="3">'.date(DATE_COOKIE).'</th></tr>';
//srp
echo '<tr><th>Y</th><th>R</th><th>SRP</th>';
for($i=0;$i<sizeof($id);$i++){
	echo '<th>$'.$srp[$i]."</th>";
}
echo "<th>Y</th><th>R</th><th>SRP</th></tr>";
//std price
echo '<tr><th>Y</th><th>R</th><th>STD</th>';
for($i=0;$i<sizeof($id);$i++){
	echo '<th>$'.$std_price[$i]."</th>";
}
echo "<th>Y</th><th>R</th><th>STD</th></tr>";


//shop and price
for($i=0;$i<sizeof($shoplist);$i++){
	echo "<tr>";
	if($shopyellow[$i]>0){
		echo '<th bgcolor="yellow">'.$shopyellow[$i]."</th>";
	}else{
		echo "<th>".$shopyellow[$i]."</th>";
	}
	if($shopred[$i]>0){
		echo '<th bgcolor="red">'.$shopred[$i]."</th>";
	}else{
		echo "<th>".$shopred[$i]."</th>";
	}
	if($shopred[$i]>0){
		echo '<th bgcolor="red">';
	}else if($shopyellow[$i]>0){
		echo '<th bgcolor="yellow">';
	}else{
		echo '<th>';
	}
	echo $shoplist[$i].'<br/><a href="https://www.price.com.hk/shop.php?s='.$shopPriceID[$i].'">'.$shopPriceID[$i]."</a></th>";
	
	for($j=0;$j<sizeof($id);$j++){
		if(in_array($i,$itemShopID[$j])){
			
			if($itemPrice[$j][array_search($i, $itemShopID[$j])]<$std_price[$j]){
				if (($std_price[$j] - $itemPrice[$j][array_search($i, $itemShopID[$j])])>100) {
					echo '<td bgcolor="red">';
				}else{
					echo '<td bgcolor="yellow">';
				}
			}else{
				if($itemPrice[$j][array_search($i, $itemShopID[$j])]>$srp[$j]){
					echo '<td bgcolor="chartreuse">';
				}else{
					echo '<td>';
				}
			}
			echo "$".$itemPrice[$j][array_search($i, $itemShopID[$j])]."<br>".$updateDate[$j][array_search($i, $itemShopID[$j])]."</td>";
		}else{
			echo "<td><center>-</center></td>";	
		}
	}

	if($shopyellow[$i]>0){
		echo '<th bgcolor="yellow">'.$shopyellow[$i]."</th>";
	}else{
		echo "<th>".$shopyellow[$i]."</th>";
	}
	if($shopred[$i]>0){
		echo '<th bgcolor="red">'.$shopred[$i]."</th>";
	}else{
		echo "<th>".$shopred[$i]."</th>";
	}
	if($shopred[$i]>0){
		echo '<th bgcolor="red">';
	}else if($shopyellow[$i]>0){
		echo '<th bgcolor="yellow">';
	}else{
		echo '<th>';
	}
	echo $shoplist[$i].'<br/><a href="https://www.price.com.hk/shop.php?s='.$shopPriceID[$i].'">'.$shopPriceID[$i]."</a></th>";
	echo "</tr>";
}

echo "</table></div></div>";




?>