<?php
opcache_reset();
error_reporting(E_ALL);
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__.'/../lib/simple_html_dom.php');
include_once(__DIR__.'/xf_database.php');
include_once(__DIR__.'/price_com_database.php');
include_once(__DIR__.'/xf_log.php');
include_once(__DIR__.'/curl_tools.php');

$_SESSION['User_ID']="System";
$lineBreak="\n";
$lineHr="----\n";
$curl_debug=1;

/*

showAvailableVpnInterface();
$productData=searchPriceComProductID("QNAP 863",1,"");
//$productData=searchPriceComCategoryAndBrand("100031","QNAP",1,"");
//saveTempPriceComIDResult($productData);

for ($i=0; $i < sizeof($productData) ; $i++) { 
	echo "Product Count: ".$i.$lineBreak;

	

	echo $productData[$i]->productName.$lineBreak;
	echo $productData[$i]->productID.$lineBreak;
	echo $productData[$i]->categoryID.$lineBreak.$lineBreak;

	
	$productPriceData=getPriceComPriceByProduct($productData[$i]->productID);
	updatePriceComPrice(NULL,$productPriceData);
	echo $GLOBALS["lineHr"];
	for ($j=0; $j < sizeof($productPriceData) ; $j++) { 
		echo $productPriceData[$j]->priceChanged.$lineBreak;
		echo $productPriceData[$j]->productID.$lineBreak;
		echo $productPriceData[$j]->shopName.$lineBreak;
		echo $productPriceData[$j]->shopID.$lineBreak;
		echo $productPriceData[$j]->price.$lineBreak;
		echo $productPriceData[$j]->d.$lineBreak.$lineBreak;
	}
	echo $GLOBALS["lineHr"];

	//saveAutoPriceComIDResult($productData,3);
	
}
*/


function searchPriceComCategoryAndBrand($category,$brand,$page,$productData){
	$url="https://www.price.com.hk/category.php?c=".$category."&brand=".$brand."&page=".$page;
	$html= sendCurlRequest($url);
	if ( strlen($html)==0){
		echo "<hr/>ERROR<hr/>";
		return;
	}

	if (!is_array($productData)) {
		$productData=array();
		$productCount=0;
	}else{
		$productCount=sizeof($productData);
	}	

	if (sizeof($html->find('.next-btn'))==0) {
		$haveNextPage=1;
	}else{
		$haveNextPage=sizeof($html->find('li.next-btn[class=next-btn disabled]'));
		$nextPage=$html->find('.next-btn a')[0]->href;
		$pagePos=strpos($nextPage,"page=");
		$nextPage=substr($nextPage, $pagePos+strlen("page="));
	}
	foreach($html->find('.club-list-row') as $productRow) {
        foreach($productRow->find('.column-02 .line-01') as $productName) {
        	$productData[$productCount] = new stdClass();
    		$productData[$productCount]->productName=trim($productName->plaintext);
        }
        foreach($productRow->find('.item-inner') as $productID) {
        	$productData[$productCount]->productID=$productID->attr["data-id"];
        }
        $productData[$productCount]->categoryID=$category;
        $productCount++;
	}
	if ($haveNextPage==0) {
		$productData=searchPriceComCategoryAndBrand($category,$brand,$nextPage,$productData);
	}
	return $productData;
}

function getPriceComCategoryID($id){
	$url="https://www.price.com.hk/product.php?p=".$id;
	$html= sendCurlRequest($url);
	if ( strlen($html)==0){
		echo "<hr/>ERROR<hr/>";
		return;
	}
	$categoryID=$html->find('.breadcrumb-product')[0];
	$categoryIDsize=sizeof($categoryID->find('div a'));
	$categoryID=$categoryID->find('div a')[$categoryIDsize-2]->href;
	$categoryID=str_replace("category.php?c=", "", $categoryID);
	return $categoryID;
}


function searchPriceComProductID($search,$page,$productData){
	$search=preg_replace('/\s+/', "+", $search);
	$url="https://www.price.com.hk/search.php?q=".$search."&page=".$page;
	$html= sendCurlRequest($url);
	if ( strlen($html)==0){
		echo "<hr/>ERROR<hr/>";
		return;
	}

	if (!is_array($productData)) {
		$productData=array();
		$productCount=0;
	}else{
		$productCount=sizeof($productData);
	}	
	
	if (sizeof($html->find('.next-btn'))==0) {
		$haveNextPage=1;
	}else{
		$haveNextPage=sizeof($html->find('li.next-btn[class=next-btn disabled]'));
		$nextPage=$html->find('.next-btn a')[0]->href;
		$pagePos=strpos($nextPage,"page=");
		$nextPage=substr($nextPage, $pagePos+strlen("page="));
	}

	foreach($html->find('.club-list-row') as $productRow) {
        foreach($productRow->find('.column-02 .line-01') as $productName) {
        	$productData[$productCount] = new stdClass();
    		$productData[$productCount]->productName=trim($productName->plaintext);
        }
        foreach($productRow->find('.item-inner') as $productID) {
        	$productData[$productCount]->productID=$productID->attr["data-id"];
        }
        $productData[$productCount]->categoryID=getPriceComCategoryID($productData[$productCount]->productID);
        $productCount++;
	}
	if ($haveNextPage==0) {
		$productData=searchPriceComProductID($search,$nextPage,$productData);
	}
	return $productData;	
}


function getPriceComPriceByProduct($id){
	$url="https://www.price.com.hk/product.php?p=".$id;
	$html= sendCurlRequest($url);
	if (strlen($html)==0){
		echo "<hr/>ERROR<hr/>";
		return;
	}

	$productData=array();
	
	$shopCount=0;
	foreach($html->find('.quotation-merchant-name') as $shopName) {
        $productData[$shopCount] = new stdClass();
        $productData[$shopCount]->productID=$id;
        $productData[$shopCount]->shopName=$shopName->plaintext;
        $shopID=trim($shopName->find('a')[0]->href);
        $shopID=str_replace("starshop.php?s=","",$shopID);
        $shopID=str_replace("shop.php?s=","",$shopID);
        $productData[$shopCount]->shopID=$shopID;
        $shopCount++;
	}
	$priceCount=0;
	foreach($html->find('span.product-price') as $price) {
		if ($priceCount>=$shopCount) {
	    	continue;
	    }
	    $price->plaintext=str_replace("HK$","",$price->plaintext);
	    $price->plaintext=str_replace(",","",$price->plaintext);
	    $price->plaintext=str_replace(" ","",$price->plaintext);
	    $productData[$priceCount]->price=trim($price->plaintext);
	    $priceCount++;
	}
	$dateCount=0;
	foreach($html->find('.quote-source') as $d) {
		if ($dateCount>=$shopCount) {
	    	continue;
	    }
		$d->plaintext=str_replace("更新日期：","",$d->plaintext);
		$d->plaintext=str_replace("由星級商戶更新","",$d->plaintext);
		$d->plaintext=str_replace("檢舉","",$d->plaintext);
		$d->plaintext=str_replace("由商戶更新","",$d->plaintext);
		$d->plaintext=str_replace("由會員更新","",$d->plaintext);
		$d->plaintext=str_replace("更新","",$d->plaintext);
		$d->plaintext=str_replace("請先查詢","",$d->plaintext);
		$d->plaintext=str_replace("少量存貨","",$d->plaintext);
		$d->plaintext=str_replace("大量現貨","",$d->plaintext);
	    $productData[$dateCount]->d=trim($d->plaintext);
	    $dateCount++;
	}
	return $productData;
}

function getFuturePrice($categories){
	$url="https://buymore.hk/shop/diy.php";
	$request='cat_id='.$categories.'&act=diy_option&keyword=&min_price=0&max_price=999999&brand=0';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('application/x-www-form-urlencoded; charset=UTF-8'));
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$request);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$productsLength=sizeof($dataJSON);
	for ($i=0; $i < $productsLength ; $i++) { 
		$productDesc=$dataJSON[$i]->goods_name;
		$productPrice=$dataJSON[$i]->shop_price;
		$productPrice=str_replace("HK$", "", $productPrice);
		$productPrice=(int)$productPrice;
		if ($productPrice==1) {
			continue;
		}
		updateProductPrice($productDesc,$productPrice,"Future");
	}
}

function getWcslPrice($categories){
	$url="https://www.wcslmall.com/collections/".($categories).'.json';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$products_count=$dataJSON->collection->products_count;
	$pageCount=ceil($products_count/250);
	for ($i=1; $i <= $pageCount ; $i++) { 
		getWcslPrice2($categories,$i);
	}
}

function getWcslPrice2($categories,$page){
	$url="https://www.wcslmall.com/collections/".$categories."/products.json?limit=250&page=".$page;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$products=$dataJSON->products;
	$productsLength=sizeof($products);
	for ($i=0; $i < $productsLength ; $i++) { 
		$productDesc=$products[$i]->title;
		$productPrice=intval($products[$i]->variants[0]->price);
    	updateProductPrice($productDesc,$productPrice,"WCSL");
	}
}

function getFarollPrice($categories){
	$url="https://www.faroll.com/api/products/".($categories);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$products=$dataJSON->products;
	$productsLength=sizeof($products);
	for ($i=0; $i < $productsLength ; $i++) { 
		$productDesc=$products[$i]->product_name;
		$productPrice=$products[$i]->options[0]->price;
		$productDiscount=$products[$i]->options[0]->discount;
		updateProductPrice($productDesc,$productPrice,"Faroll");
	}
}

function getTerminalhkPrice($categories){
	$url="https://www.terminalhk.com/api/public/product?category=".urlencode($categories);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$products=$dataJSON->products;
	$productsLength=$dataJSON->count;
	for ($i=0; $i < $productsLength ; $i++) { 
		$productDesc=$products[$i]->name;
		$productPrice=$products[$i]->price;
		updateProductPrice($productDesc,$productPrice,"Terminal");
	}
}

function getPegasusPrice($categories,$cursor){
	$url="https://shop.pegasus.hk/graphql";
	$requestJSON='{"operationName":"products","variables":{"limit":100,"filter":{"categories":["'.$categories.'"],"query":"","tags":[]},"orderBy":"menuOrder_ASC"'.$cursor.'},"query":"query products($cursor: ID, $limit: Int, $filter: ProductFilter, $orderBy: ProductOrderBy) {\n  products(cursor: $cursor, limit: $limit, filter: $filter, orderBy: $orderBy) {\n    edges {\n      id\n      name\n      image(cdn: true, height: 72)\n      price\n      sellingPrice\n      categories {\n        id\n        __typename\n      }\n      __typename\n    }\n    pageInfo {\n      endCursor\n      totalCount\n      hasNextPage\n      __typename\n    }\n    __typename\n  }\n}\n"}';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$requestJSON);
	$content = curl_exec($ch);
	curl_close($ch);
	$dataJSON=json_decode($content);
	$products=$dataJSON->data->products->edges;
	$productsLength=sizeof($products);
	for ($i=0; $i < $productsLength ; $i++) { 
		$productDesc=$products[$i]->name;
		$productPrice=$products[$i]->sellingPrice;
    	updateProductPrice($productDesc,$productPrice,"Pegasus");
	}
	$pageInfo=$dataJSON->data->products->pageInfo;
	if ($pageInfo->hasNextPage==true) {
		$endCursor=$pageInfo->endCursor;
		getPegasusPrice($categories,',"cursor":"'.$endCursor.'"');
	}
}

function getCentralfieldPrice($productURL){
	$url="https://www.centralfield.com/price-list/".$productURL."/";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$html= str_get_html($content);  
	foreach($html->find('.row-hover') as $list) {
		foreach ($list->find('tr') as $listline) {
			$productDesc=$listline->find('.column-1', 0)->plaintext;
			$productPrice=$listline->find('.column-2', 0)->plaintext;
			if (strlen($productDesc)==0) {
				continue;
			}
			updateProductPrice($productDesc,$productPrice,"Centralfield");
		}
	}
}

function getJumboPrice($productURL){
	$url="http://www.jumbo-computer.com/pricelist.aspx?id=".$productURL;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	$content = curl_exec($ch);
	curl_close($ch);
	$html= str_get_html($content);  
	foreach($html->find('.gvProducts') as $list) {
		foreach ($list->find('tr') as $listline) {
			$productDesc=$listline->find('td', 0)->plaintext;
			$productPrice=$listline->find('td', 1)->plaintext;
			$productPrice=str_replace("HK$ ", "", $productPrice);
			if (strlen($productDesc)==0) {
				continue;
			}
			if ($productPrice=="IN STOCK") {
				continue;
			}
			updateProductPrice($productDesc,$productPrice,"Jumbo");
		}
	}
}

function getSePrice($categories){
	$url="http://www.secomputer.com.hk/pricelist.php";
	$request='ProductTypeID='.$categories.'&Keywords=&ProductID=&ProductCode=&ProductName=&Price=';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('application/x-www-form-urlencoded; charset=UTF-8'));
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$request);
	$content = curl_exec($ch);
	curl_close($ch);
	$html= str_get_html($content);  
	foreach($html->find('tr[style*=background-Color : #FFFFFF]') as $list) {
		$productDesc=$list->find('span', 0)->plaintext;
		$productPrice=$list->find('span', 1)->plaintext;
		echo "SE<br/>";
		echo $productDesc.$lineBreak;
		echo $productPrice.$lineBreak.$lineBreak;
	}
	foreach($html->find('tr[style*=background-Color : #DBF3D4]') as $list) {
		$productDesc=$list->find('span', 0)->plaintext;
		$productPrice=$list->find('span', 1)->plaintext;
		updateProductPrice($productDesc,$productPrice,"SE");
	}
}

function getPriceFromOnlineStore($shopName,$key){
	switch ($shopName) {
		case 'Centralfield':
			getCentralfieldPrice($key);
			break;
		case 'Jumbo':
			getJumboPrice($key);
			break;
		case 'Pegasus':
			getPegasusPrice($key,"");
			break;
		case 'Terminal':
			getTerminalhkPrice($key);
			break;
		case 'Faroll':
			getFarollPrice($key);
			break;
		case 'WCSL':
			getWcslPrice($key);
			break;
		case 'Future':
			getFuturePrice($key);
			break;
		case 'SE':
			getSePrice($key);
			break;
	}
}

function updateProductPrice($productDesc,$productPrice,$shopName){
	echo $shopName.$lineBreak;
	echo $productDesc.$lineBreak;
	echo $productPrice.$lineBreak.$lineBreak;
}

/*
getPriceFromOnlineStore("Centralfield","price-list-mbd");
getPriceFromOnlineStore("Jumbo","1");
getPriceFromOnlineStore("Pegasus","5");
getPriceFromOnlineStore("Terminal","主機板");
getPriceFromOnlineStore("Faroll","4");
getPriceFromOnlineStore("WCSL","motherboard");
getPriceFromOnlineStore("Future","4");
getPriceFromOnlineStore("SE","2");


/*
//getPriceComPriceByProduct(438751);
getSePrice(3);
//getSePrice(2);
getFuturePrice(1);
getWcslPrice("cpu");
//getWcslPrice("motherboard");
getFarollPrice(1);
//getFarollPrice(4);
getTerminalhkPrice("中央處理器");
//getTerminalhkPrice("主機板");
getPegasusPrice(2,"");
//getPegasusPrice(5,"");
//getJumboPrice(3);
//getJumboPrice(86);
//getCentralfieldPrice("price-list-cpu");
getCentralfieldPrice("price-list-mbd");
getCentralfieldPrice("price-list-vga");
getCentralfieldPrice("price-list-ram");
getCentralfieldPrice("price-list-hdd");
getCentralfieldPrice("price-list-ssd");
getCentralfieldPrice("price-sound-card");
getCentralfieldPrice("price-list-cdr");
getCentralfieldPrice("price-list-atx-case");
getCentralfieldPrice("price-list-matx-case");
getCentralfieldPrice("price-fan");
getCentralfieldPrice("price-list-power");
getCentralfieldPrice("price-printer");
getCentralfieldPrice("price-lcd-display");
getCentralfieldPrice("price-list-mbd");
getCentralfieldPrice("price-list-software");
getCentralfieldPrice("price-list-mouse");
*/

?>