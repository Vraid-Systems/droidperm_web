<?php

$myCarriageReturnChar = "\r";
$myNewLineChar = "\n";

#get the package we are going to be searching for
$myPackageNameSearch = false;
if (isset($_GET['package']) && ($_GET['package'] != '')) {
    $myPackageNameSearch = $_GET['package'];
}

#get category of app
$myCategoryStr = false;
if (isset($_GET['category']) && ($_GET['category'] != '')) {
    $myCategoryStr = $_GET['category'];
}

#the permission string CSV
$myPermCSV = false;
if (isset($_GET['perms']) && ($_GET['perms'] != '')) {
    $myPermCSV = $_GET['perms'];
}

#get command line args if they exist
while (count($argv) > 0) {
    $arg = array_shift($argv);
    switch ($arg) {
        case '-package':
            $arg_package = array_shift($argv);
            if (empty($myPackageNameSearch)) {
                $myPackageNameSearch = $arg_package;
            }
            break;
        case '-category':
            $arg_category = array_shift($argv);
            if (empty($myCategoryStr)) {
                $myCategoryStr = $arg_category;
            }
            break;
        case '-perms':
            $arg_perms = array_shift($argv);
            if (empty($myPermCSV)) {
                $myPermCSV = $arg_perms;
            }
            break;
    }
}

#check to make sure we have the params we need
if (empty($myPackageNameSearch)) {
    echo 'web usage: ?package=[com.namespace.title]&category='
    . '[Music & Audio|Tools|Cards & Casino|etc (optional)]&perms=[CSV like - '
    . 'android.permission.GET_ACCOUNTS,android.permission.INTERNET (optional)]'
    . $myNewLineChar;
    echo 'terminal usage: -package [com...] -category [Tools ... (optional)] '
    . '-perms [permission string CSV (optional)]' . $myNewLineChar;
    echo 'notes: please fill out as many fields as possible. '
    . $myNewLineChar;
    die();
}

#include necessary for queries against local sqlite3 db
include("permission_db.php");

#check if package is already in db for local retrieval of perm data
if (empty($myPermCSV) || empty($myCategoryStr)) {
    $myCategoryStr = p_getPackageCategory($myPackageNameSearch);
    $aPermCSV = p_getPackagePermissions($myPackageNameSearch, $myCategoryStr);
    if ($aPermCSV && !empty($myCategoryStr)) { //already have perm CSV from DB
        $myPermCSV = $aPermCSV;
    } else { //need to grab perm data from market
        #php market api - includes must be in this order, especially middle two
        include("MarketSession_config.php");
        include("proto/protocolbuffers.inc.php");
        include("proto/market.proto.php");
        include("MarketSession.php");

        # create session, and auth with google servers --> auth token
        $session = new MarketSession();
        $session->login(GOOGLE_EMAIL, GOOGLE_PASSWD);
        $session->setAndroidId(ANDROID_DEVICEID);

        #the search loop
        $myEntriesFetchCount = 10;
        $myPackageTotalCount = 0; //number of apps had to search through
        $mySearchLoopCountLimit = 10;
        $mySearchLoopCount = 0;
        while (empty($myPermCSV) && empty($myCategoryStr)) {
            #create market search request
            $ar = new AppsRequest();
            $ar->setQuery($myPackageNameSearch);
            $ar->setOrderType(AppsRequest_OrderType::NEWEST);
            $ar->setStartIndex(($mySearchLoopCount * $myEntriesFetchCount));
            $ar->setEntriesCount($myEntriesFetchCount);
            $ar->setWithExtendedInfo(true);

            $reqGroup = new Request_RequestGroup();
            $reqGroup->setAppsRequest($ar);

            #query the Android market servers using request and auth token
            $response = $session->execute($reqGroup);

            #get the response
            $groups = $response->getResponsegroupArray();

            #grab the first matching package
            foreach ($groups as $rg) {
                if (!empty($myPermCSV) && !empty($myCategoryStr))
                    break; //do not overwrite old values
                $appsResponse = $rg->getAppsResponse();
                $apps = $appsResponse->getAppArray();
                foreach ($apps as $app) {
                    if (!empty($myPermCSV) && !empty($myCategoryStr))
                        break; //do not overwrite old values
                    $myPackageTotalCount++;
                    $aPackageName = $app->getPackageName();
                    if ($myPackageNameSearch == $aPackageName) { //if there is a match ... grab first
                        if (empty($myCategoryStr)) {
                            $myCategoryStr = $app->getExtendedInfo()->getCategory();
                        }
                        if (empty($myPermCSV)) {
                            $aPermArray = $app->getExtendedInfo()->getPermissionIdArray();
                            $myPermCSV = implode(",", $aPermArray);
                        }
                        break;
                    }
                }
            }

            $mySearchLoopCount++;

            if ($mySearchLoopCount >= $mySearchLoopCountLimit)
                break;
        }
    }
} else { //preprocess user input for extra leading/trailing commas/spaces
    $myPermCSV = trim($myPermCSV, " ,");
}

#store complete response data for later if not already in DB
if (!empty($myPermCSV) && !empty($myCategoryStr)) {
    p_setPackage($myPackageNameSearch, $myCategoryStr, $myPermCSV);
}

#score permissions of app - TODO: add category considerations
$myDangerScore = 0;
$myPermArray = explode(",", $myPermCSV);
foreach ($myPermArray as $aPerm) {
    $myDangerScore += p_getWatchedPermissionValue($aPerm);
}
if ($myDangerScore > 9) {
    $myDangerScore = 9;
} elseif ($myDangerScore < 0) {
    $myDangerScore = 0;
}

#output the response
echo 'name=' . $myPackageNameSearch . $myNewLineChar;
echo 'category=' . $myCategoryStr . $myNewLineChar;
echo 'perms=' . $myPermCSV . $myNewLineChar;
echo 'danger_level=' . $myDangerScore . $myNewLineChar;
echo 'danger_about=' . $myNewLineChar;
?>
