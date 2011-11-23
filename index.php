<?php

$myCarriageReturnChar = "\r";
$myNewLineChar = "\n";

# get the package we are going to be searching for
$myPackageNameSearch = false;
if (isset($_GET['package']) && ($_GET['package'] != '')) {
    $myPackageNameSearch = $_GET['package'];
}

# get category of app
$myCategoryStr = false;
if (isset($_GET['category']) && ($_GET['category'] != '')) {
    $myCategoryStr = $_GET['category'];
}

# exact string matching or match all strings that have package substring
$myExactMatchBoolean = false;
if (isset($_GET['exact']) && ($_GET['exact'] != '')) {
    $myExactMatchBoolean = $_GET['exact'];
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
        case '-exact':
            $arg_exact = array_shift($argv);
            if (empty($myExactMatchBoolean)) {
                $myExactMatchBoolean = $arg_exact;
            }
            break;
    }
}

#check to make sure we have the params we need
if (empty($myPackageNameSearch)
        && empty($myCategoryStr)) {
    echo 'web usage: ?package=[com.namespace.title]&category='
    . '[Music & Audio|Tools|Cards & Casino|etc]&perms=[CSV like - '
    . 'android.permission.GET_ACCOUNTS,android.permission.INTERNET]'
    . '&exact=true' . $myNewLineChar;
    echo 'terminal usage: -package [com...] -category [Tools ...] '
    . '-exact [true]' . $myNewLineChar;
    echo 'notes: please fill out as many fields as possible. '
    . 'do not want exact matching? then do not include that argument.'
    . $myNewLineChar;
    die();
}
if (!empty($myExactMatchBoolean)) {
    $myExactMatchBoolean = true;
}

# includes must be in this order, especially middle two
include("MarketSession_config.php");
include("proto/protocolbuffers.inc.php");
include("proto/market.proto.php");
include("MarketSession.php");

# create session, and auth with google servers --> auth token
$session = new MarketSession();
$session->login(GOOGLE_EMAIL, GOOGLE_PASSWD);
$session->setAndroidId(ANDROID_DEVICEID);

# the search loop
$myEntriesFetchCount = 10;
$myPackageTotalCount = 0;
$myResultThreshold = 1;
$mySearchLoopCountLimit = 10;
$mySearchLoopCount = 0;
while (true) {
    # create market search request
    $ar = new AppsRequest();
    $ar->setQuery($myPackageNameSearch);
    $ar->setOrderType(AppsRequest_OrderType::NEWEST);
    $ar->setStartIndex(($mySearchLoopCount * $myEntriesFetchCount));
    $ar->setEntriesCount($myEntriesFetchCount);
    $ar->setWithExtendedInfo(true);

    $reqGroup = new Request_RequestGroup();
    $reqGroup->setAppsRequest($ar);

    # query the Android market servers using request and auth token
    $response = $session->execute($reqGroup);

    # get the response
    $groups = $response->getResponsegroupArray();

    #parse the response into arrays
    $i = 0;
    foreach ($groups as $rg) {
        $appsResponse = $rg->getAppsResponse();
        $apps = $appsResponse->getAppArray();
        foreach ($apps as $app) {
            $myPackageTotalCount++;
            $aPackageTitle = $app->getTitle();
            $aPackageName = $app->getPackageName();
            if ($myExactMatchBoolean) {
                //check if this packagename is substring of search package name
                $pos = strpos($myPackageNameSearch, $aPackageName);
            } else {
                //check if search package name is substring of this package name
                $pos = strpos($aPackageName, $myPackageNameSearch);
            }
            if ($pos !== false) { //if there is a match ...
                $packages[$i]['package_title'] = $aPackageTitle;
                $packages[$i]['package_name'] = $aPackageName;
                $packages[$i]['package_id'] = $app->getId();
                $packages[$i]['package_category'] = $app->getExtendedInfo()->getCategory();
                $packages[$i]['package_perm_array'] = $app->getExtendedInfo()->getPermissionIdArray();
                $i++;
            }
        }
    }

    $mySearchLoopCount++;

    if (($mySearchLoopCount >= $mySearchLoopCountLimit)
            || ($i >= $myResultThreshold))
        break;
}

# output the response - parse and output kept seperate for easy move to MVC later
echo 'total_packages=' . $myPackageTotalCount . $myNewLineChar;
$packages_count = sizeof($packages);
echo 'out_packages=' . $packages_count . $myNewLineChar;
if ($packages_count > 0)
    echo $myCarriageReturnChar . $myNewLineChar;
for ($i = 0; $i < $packages_count; $i++) {
    echo 'name=' . $packages[$i]['package_name'] . $myNewLineChar;
    echo 'title=' . $packages[$i]['package_title'] . $myNewLineChar;
    echo 'id=' . $packages[$i]['package_id'] . $myNewLineChar;
    echo 'category=' . $packages[$i]['package_category'] . $myNewLineChar;

    $package_perm_array = $packages[$i]['package_perm_array'];
    $ppa_size = sizeof($package_perm_array);
    echo 'perms=';
    for ($i2 = 0; $i2 < $ppa_size; $i2++) {
        $perm = $package_perm_array[$i2];
        if ($perm != '') {
            if (($i2 + 1) == $ppa_size) {
                echo $perm;
            } else {
                echo $perm . ',';
            }
        }
    }
    echo $myNewLineChar;

    echo 'danger_level=';
    if ($ppa_size > 5) {
        echo '5';
    } else {
        echo '0';
    }
    echo $myNewLineChar;

    echo 'danger_about=';
    if ($ppa_size > 5) {
        echo 'more than 5 permissions';
    }
    echo $myNewLineChar;

    if (($i + 1) < $packages_count) {
        echo $myCarriageReturnChar . $myNewLineChar;
    }
}
?>
