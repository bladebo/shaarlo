<?php
include 'fct/fct_rss.php';
include 'fct/fct_cache.php';
include 'fct/fct_file.php';
include 'fct/fct_sort.php';
include 'fct/fct_valid.php';
error_reporting(0);
$cache = 'index';

$nbStep = 30;
$sleepBeetweenLoops = 110;

header('Content-Type: text/html; charset=utf-8');
for($j=0; $j < $nbStep; $j++){
	$actualDate = date('Ymd');
	$actualDateFormat = date('d/m/Y');
	$shaarloRss = '<?xml version="1.0" encoding="utf-8"?>
	<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	  <channel>
	    <title>Les discussions de Shaarli du '.$actualDateFormat.'</title>
	    <link>http://shaarli.fr/</link>
	    <description>Shaarli Aggregators</description>
	    <language>fr-fr</language>
	    <copyright>http://shaarli.fr/</copyright>';
		// start profiling
		// xhprof_enable();
	/**
	 * Absolute path to Item 
	 */
	define('XPATH_RSS_ITEM', '/rss/channel/item');
	$rssListFile = 'data/shaarli.txt';
	$potentialShaarlisListFile = 'data/potential_shaarli.txt';
	$potentialShaarlis = array();
	if(is_file($potentialShaarlisListFile)){
		$potentialShaarlis = json_decode(file_get_contents($potentialShaarlisListFile), true);
	}

	if(is_file($rssListFile)){
		$rssList = json_decode(file_get_contents($rssListFile), true);
	}else{
		$rssList = array('sebsauvage' => 'http://sebsauvage.net/links/?do=rss');
		file_put_contents($rssListFile, json_encode($rssList));
	}
	

	$disabledRssList = array();
	$disabledRssListFile = 'data/disabled_shaarli.txt';							
	if(is_file($disabledRssListFile)){
		$disabledRssList = json_decode(file_get_contents($disabledRssListFile), true);
	}

	$deletedRssList = array();
	$deletedRssListFile = 'data/deleted_shaarli.txt';							
	if(is_file($deletedRssListFile)){
		$deletedRssList = json_decode(file_get_contents($deletedRssListFile), true);
	}

	/*
	 * Save the XML to Array
	 */
	/*
	 * Push the shaarli's id in tab
	*/
	$rssListArrayed = array();
	$assocShaarliIdUrl = array();
	foreach($rssList as $rssKey => $rssUrl){
		$content = getRss($rssUrl);
		$xmlContent = getSimpleXMLElement($content);
		if($xmlContent === false){
			continue;
		}
		$rssListArrayed[$rssKey] = convertXmlToTableau($xmlContent, XPATH_RSS_ITEM);
	}				
	
	/*
	 * Push the shaarli's id in tab
	 */
	$assocShaarliIdUrl = array();
	foreach($rssListArrayed as $rssKey => $arrayedRss){
		/*
		 * Récupération des liens
		*/
		foreach($arrayedRss as $rssItem){					
			$guid = $rssItem['guid'];
			$link = $rssItem['link'];
			if($link == 'http://'){
				$assocShaarliIdUrl[md5($guid)] = $guid;
			}else{
				$assocShaarliIdUrl[md5($guid)] = $link;
			}
		}
	}
	$rssContents = array();
	foreach($rssListArrayed as $rssKey => $arrayedRss){
		/*
		 * Récupération des liens
		 */
		foreach($arrayedRss as $rssItem){
	// 		array (
	// 		  0 => 'Rss Title',
	// 		  1 => 'http://links.net/links/?mSpC6w',
	// 		  2 => 'http://xxx.xxx', // Rss Url
	// 		  3 => 'Wed, 05 Jun 2013 10:01:53 +0200', // date
	// 		  4 => 'security', // tags
	// 		  5 => 'description')		
	
			/*
			 * Automatic recuperation of linked rss 
			 */
			$link = $rssItem['link'];
			$guid = $rssItem['guid'];
			$category = $rssItem['category'];
			$rssTimestamp = strtotime($rssItem['pubDate']);
			
			$actualTimestamp = time();
			$diffValue = round(($actualTimestamp - $rssTimestamp) / 60);
			$diffUnity = 'minute(s)';
			if($diffValue > 60){
				$diffValue = round($diffValue/ 60);
				$diffUnity = 'heure(s)';
				if($diffValue > 24){
					$diffValue = round($diffValue/ 24);
					$diffUnity = 'jour(s)';
				}	
			}
				
			if($diffValue <= 0){
				$diffValue = 0;
			}
			
			$uniqRssKey = md5($link);
			$description = sprintf('<b>%s</b>, il y a %s %s <br/> %s<br/>', unMagicQuote($rssKey), $diffValue, $diffUnity, str_replace('<br>', '<br/>', $rssItem['description']));
			$title = $rssItem['title'];
			
			// Delete the Shaarli link and replace it by the 'real' link
			if(array_key_exists($uniqRssKey, $assocShaarliIdUrl)){
				$link = $assocShaarliIdUrl[$uniqRssKey];
				$uniqRssKey = md5($assocShaarliIdUrl[$uniqRssKey]);
			}						
			if($link == 'http://'){
				$link = $guid;
			}
			
			$urlMatches = array();
			preg_match_all('#href=\"(.*?)\"#', $description, $urlMatches);
			foreach($urlMatches[1] as $newsUrl){
				$newsUrl = explode('?', $newsUrl);
				if(isset($newsUrl[1]) && strlen($newsUrl[1]) === 6){
					
					if('index.php' === substr($newsUrl[0], -9)){
						$newsUrl[0] = substr($newsUrl[0], 0, strlen($newsUrl[0]) - 9);
					}

					if('/' !== $newsUrl[0][strlen($newsUrl[0])-1]) {
						$newsUrl[0] .= '/';
					}
					$potentialRssUrl = $newsUrl[0] . '?do=rss';
					$potentialRssUrl = str_replace('https://', 'http://', $potentialRssUrl);
					if(!in_array($potentialRssUrl, $potentialShaarlis) 
						&& !in_array($potentialRssUrl, $rssList) 
						&& !in_array($potentialRssUrl, $deletedRssList)									
						&& !in_array($potentialRssUrl, $disabledRssList)){
						$newRssFlux = is_valid_rss($potentialRssUrl);
						if($newRssFlux !== false){
							$potentialShaarlis[$newRssFlux] = $potentialRssUrl;
						}
					}
				}
			}

			if(!array_key_exists($uniqRssKey, $rssContents) 
			){
				$rssContents[$uniqRssKey] = array('toptopic' => false, 'link' => $link, 'description' => array($rssTimestamp => $description), 'title' => $title, 'date' => $rssTimestamp, 'category' => $category);
			}else{

				$rssContents[$uniqRssKey]['description'][$rssTimestamp] = $description;
				$rssContents[$uniqRssKey]['date'] = max($rssContents[$uniqRssKey]['date'], $rssTimestamp);
				$rssContents[$uniqRssKey]['toptopic'] = true;
			}
		}
	}
	// Obtient une liste de colonnes
	$dateToSort = array();
	foreach ($rssContents as $key => $row) {
		$dateToSort[$key]  = $row['date'];
	}
	
	// Trie les données par volume décroissant, edition croissant
	// Ajoute $data en tant que dernier paramètre, pour trier par la clé commune
	if(count($dateToSort) === count($rssContents)){
		array_multisort($dateToSort, SORT_DESC, $rssContents);
	}
	$limitRss = 100;
	$i=0;
	foreach($rssContents as $rssContent){
		if(date('Ymd', $rssContent['date']) !== $actualDate){
			break;
		}
		
		$i++;

		ksort($rssContent['description']);
		
		$shaarloRss .= sprintf("<item>
							<title>%s</title>
							<link>%s</link>
							<pubDate>%s</pubDate>
							<description>%s</description>
							<category>%s</category>
							</item>",
				htmlspecialchars($rssContent['title']), htmlspecialchars($rssContent['link']), date('r', $rssContent['date']), '<![CDATA[' . implode('<br/>', $rssContent['description']) . ']]>', $rssContent['category']
				);
	}
// 		// stop profiler
// 		$xhprof_data = xhprof_disable();
// 		// display raw xhprof data for the profiler run
// // 		print_r($xhprof_data);
// 		include_once "/var/www/xhprof-master/xhprof_lib/utils/xhprof_lib.php";
// 		include_once "/var/www/xhprof-master/xhprof_lib/utils/xhprof_runs.php";
		
// 		// save raw data for this profiler run using default
// 		// implementation of iXHProfRuns.
// 		$xhprof_runs = new XHProfRuns_Default();
		
// 		// save the run under a namespace "xhprof_foo"
// 		$run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_foo");	
// 		echo $run_id;	
	
	$shaarloRss .= '</channel></rss>';
	
	file_put_contents('rss.xml', sanitize_output($shaarloRss));
	file_put_contents(sprintf('archive/rss_%s.xml', $actualDate), sanitize_output($shaarloRss));
	
	// Save the potential Shaarlis list
	file_put_contents($potentialShaarlisListFile, json_encode($potentialShaarlis));

	if(isset($_GET['oneshoot'])){
		header('Location: index.php');
		return;
	}

	sleep($sleepBeetweenLoops);
}


