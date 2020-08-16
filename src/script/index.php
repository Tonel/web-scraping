<?php

require_once '../../vendor/autoload.php';

use voku\helper\HtmlDomParser;

$url = 'https://en.wikipedia.org/wiki/COVID-19_pandemic_by_country_and_territory'; // page to scrape
$maxAttempts = 5; // number of attempts before failure
$timeout = 30; // 30 seconds
$sleep = 3; // 3 seconds

$scrapedData = getScrapedData($url, $maxAttempts, $timeout, $sleep);

if ($scrapedData !== false) {
  // creating a csv file
  $csv = fopen('data.csv', 'w');

  // populating the csv file with the scraped data
  foreach ($scrapedData as $fields) {
    fputcsv($csv, $fields);
  }

  fclose($csv);

  echo "Script successfully completed!\n";
} else {
  echo "Script failed!\n";
}

/**
 * @param string $url page to scrape
 * @param int $maxAttempts number of attempts before failing
 * @param int $timeout request timeout (in seconds)
 * @param int $sleep time spent on failure before a new attempt (in seconds)
 * @return false|string[][] scraped data on success, or false on failure
 */
function getScrapedData($url, $maxAttempts = 3, $timeout = 60, $sleep = 5) {

  $attempt = 1;

  while ($attempt <= $maxAttempts) {

    $curl = curl_init();

    // setting a randomly chosen User-Agent
    curl_setopt($curl, CURLOPT_USERAGENT, getRandomUserAgent());
    curl_setopt($curl, CURLOPT_URL, $url);

    // configuring TOR proxy
    curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1");
    curl_setopt($curl, CURLOPT_PROXYPORT, "9050");
    curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

    // setting a timeout
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);

    // certification bundle downloaded here: https://curl.haxx.se/docs/caextract.html
    curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $html = curl_exec($curl);

    // on failure
    if ($html == false) {
      // printing error message
      echo curl_error($curl) . "\n";

      $attempt += 1;

      // waiting $sleep seconds on failure before a new attempt
      sleep($sleep);
    } else {
      $htmlDomParser = HtmlDomParser::str_get_html($html);

      $dataTable = $htmlDomParser->getElementById("thetable");

      $tbodyDataTable = $dataTable->getElementByTagName("tbody");

      $theadDataTable = $tbodyDataTable->getElementByClass("covid-sticky")[0];

      $headerThs = $theadDataTable->getElementsByTagName("th");

      $scrapedData = array(
        // table header
        array(
          $headerThs[0]->find('text', 0)->html,
          $headerThs[1]->find('text', 0)->html,
          $headerThs[2]->find('text', 0)->html,
          $headerThs[3]->find('text', 0)->html
        )
      );

      foreach ($tbodyDataTable->children() as $row) {
        $countryTh = $row->find("th[scope=row]");

        $rowTds = $row->getElementsByTagName("td");

        // if countryTh and rowTds exists
        if ($countryTh->count() > 0 && $rowTds->count() > 0) {
          $country = $countryTh[1]->getElementByTagName("a")->plaintext;
          $cases = $rowTds[0]->plaintext;
          $deaths = $rowTds[1]->plaintext;
          $recoveries = $rowTds[2]->plaintext;

          $scrapedData[] = array($country, $cases, $deaths, $recoveries);
        }
      }

      return $scrapedData;
    }
  }

  return false;
}

/**
 * @return string a random User-Agent
 */
function getRandomUserAgent() {
  // default User-Agent
  $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:71.0) Gecko/20100101 Firefox/71.0";

  // reading a randomly chosen User-Agent string from the User-Agent list file
  if ($file = fopen("user_agents.txt", "r")) {
    $userAgents = array();

    while (!feof($file)) {
      $userAgents[] = fgets($file);
    }

    $userAgent = $userAgents[array_rand($userAgents)];
  }

  return trim($userAgent);
}
