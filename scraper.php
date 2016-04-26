<?php
# Willoughby Council scraper - ePathway
require 'scraperwiki.php'; 
require 'simple_html_dom.php';
date_default_timezone_set('Australia/Sydney');

## Accept Terms and return Cookies
function accept_terms_get_cookies($terms_url, $button='Next', $postfields=array('mDataGrid:Column0:Property'=>'ctl00$MainBodyContent$mDataList$ctl01$mDataGrid$ctl02$ctl00')) {
    $dom = file_get_html($terms_url);

    foreach ($dom->find('input[type=hidden]') as $data) {
        $postfields = array_merge($postfields, array($data->name => $data->value));
    }
    foreach ($dom->find("input[value=$button]") as $data) {
        $postfields = array_merge($postfields, array($data->name => $data->value));
    }

    $curl = curl_init($terms_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    $terms_response = curl_exec($curl);
    curl_close($curl);
    // get cookie
    // Please imporve it, I am not regex expert, this code changed ASP.NET_SessionId cookie
    // to ASP_NET_SessionId and Path, HttpOnly are missing etc
    // Example Source - Cookie: ASP.NET_SessionId=bz3jprrptbflxgzwes3mtse4; path=/; HttpOnly
    // Stored in array - ASP_NET_SessionId => bz3jprrptbflxgzwes3mtse4
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $terms_response, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    return $cookies;
}


###
### Main code start here
###
$url_base = "https://epathway.willoughby.nsw.gov.au/ePathway/Production/Web/GeneralEnquiry/";
$term_url = "https://epathway.willoughby.nsw.gov.au/ePathway/Production/Web/GeneralEnquiry/EnquiryLists.aspx";
$user_agent = "User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) PlanningAlerts.org.au";

$da_page = $url_base . "EnquirySummaryView.aspx";
$comment_base = "mailto:email@willoughby.nsw.gov.au?subject=Development Application ";

$cookies = accept_terms_get_cookies($term_url, "Next", array('mDataGrid:Column0:Property' => 'ctl00$MainBodyContent$mDataList$ctl01$mDataGrid$ctl02$ctl00'));

# Manually set cookie's key and get the value from array
$request = array(
    'http'    => array(
    'header'  => "Cookie: ASP.NET_SessionId=" .$cookies['ASP_NET_SessionId']. "; path=/; HttpOnly\r\n".
                 "$user_agent\r\n"
    ));
$context = stream_context_create($request);
$dom = file_get_html($da_page, false, $context);

# Assume it is single page, the web site doesn't allow to select period like last month
$dataset  = $dom->find("tr[class=ContentPanel], tr[class=AlternateContentPanel]");

# The usual, look for the data set and if needed, save it
foreach ($dataset as $record) {
    # Slow way to transform the date but it works
    $date_received = explode(' ', (trim($record->find('span',0)->plaintext)), 2);
    $date_received = explode('/', $date_received[0]);
    $date_received = "$date_received[2]-$date_received[1]-$date_received[0]";
    $date_received = date('Y-m-d', strtotime($date_received));
    
    $address   = preg_replace('/\s+/', ' ', trim(html_entity_decode($record->find('div', 2)->plaintext)));
    $address   = rtrim($address, ".");

    # Put all information in an array
    $application = array (
        'council_reference' => trim(html_entity_decode($record->find('div',1)->plaintext)),
        'address'           => $address,
        'description'       => preg_replace('/\s+/', ' ', trim(html_entity_decode($record->find('span', 1)->plaintext))),
        'info_url'          => $term_url,
        'comment_url'       => $comment_base . trim($record->find('a',0)->plaintext),
        'date_scraped'      => date('Y-m-d'),
        'date_received'     => $date_received
    );

    # Check if record exist, if not, INSERT, else do nothing
    $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
    if ((count($existingRecords) == 0) && ($application['council_reference'] !== 'Not on file')) {
        print ("Saving record " . $application['council_reference'] . "\n");
        # print_r ($application);
        scraperwiki::save(array('council_reference'), $application);
    } else {
        print ("Skipping already saved record or ignore corrupted data - " . $application['council_reference'] . "\n");
    }
}


?>
