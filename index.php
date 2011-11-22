<?php
# get the package we are going to be searching for
$myPackageNameSearch = false;
if (isset($_GET['package']) && ($_GET['package'] != '')) {
    $myPackageNameSearch = $_GET['package'];
}

# exact string matching or match all strings that have package substring
$myExactMatchBoolean = false;
if (isset($_GET['exact']) && ($_GET['exact'] != '')) {
    $myExactMatchBoolean = $_GET['exact'];
}

#get command line args if they exist
while(count($argv) > 0) {
	$arg = array_shift($argv);
	switch($arg) {
		case '-package':
			$arg_package  = array_shift($argv);
			if (empty($myPackageNameSearch)) {
				$myPackageNameSearch = $arg_package;
			}
			break;
		case '-exact':
			$arg_exact  = array_shift($argv);
			if (empty($myExactMatchBoolean)) {
				$myExactMatchBoolean = $arg_exact;
			}
			break;
	}
}

#check to make sure we have the params we need
if ($myPackageNameSearch == '') {
    die('package arg is empty');
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

# create market search request
$ar = new AppsRequest();
$ar->setQuery($myPackageNameSearch);
$ar->setOrderType(AppsRequest_OrderType::NONE);
$ar->setStartIndex(0);
$ar->setEntriesCount(5);
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
        $aPackageName = $app->getPackageName();
        if ($myExactMatchBoolean) {
            //check if this packagename is substring of search package name
            $pos = strpos($myPackageNameSearch, $aPackageName);
        } else {
            //check if search package name is substring of this package name
            $pos = strpos($aPackageName, $myPackageNameSearch);
        }
        if ($pos !== false) { //if there is a match ...
            $packages[$i]['package_name'] = $aPackageName;
            $packages[$i]['package_id'] = $app->getId();
            $packages[$i]['package_category'] = $app->getExtendedInfo()->getCategory();
            $packages[$i]['package_perm_array'] = $app->getExtendedInfo()->getPermissionIdArray();
            $i++;
        }
    }
}

# output the response - parse and output kept seperate for easy move to MVC later
$packages_count = sizeof($packages);
echo 'package_count=' . $packages_count . "\n";
for ($i = 0; $i < $packages_count; $i++) {
    echo 'name=' . $packages[$i]['package_name'] . "\n";
    echo 'id=' . $packages[$i]['package_id'] . "\n";
    echo 'category=' . $packages[$i]['package_category'] . "\n";
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
    echo "\n";
}
?>
