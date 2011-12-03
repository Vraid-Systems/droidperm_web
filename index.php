<?php

$myCarriageReturnChar = "\r";
$myNewLineChar = "\n";

#get the package we are going to be searching for
$myPackageNameStr = false;
if (isset($_GET['package']) && ($_GET['package'] != '')) {
    $myPackageNameStr = $_GET['package'];
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
            if (empty($myPackageNameStr)) {
                $myPackageNameStr = $arg_package;
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
if (empty($myPackageNameStr)) {
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
    $myCategoryStr = p_getPackageCategory($myPackageNameStr);
    $aPermCSV = p_getPackagePermissions($myPackageNameStr, $myCategoryStr);
    if ($aPermCSV && !empty($myCategoryStr)) { //already have perm CSV from DB
        $myPermCSV = $aPermCSV;
    } else { //need to grab perm data from market
        include("search.php");
        $aDataArray = getPermsAndCategory($myPackageNameStr, $myCategoryStr, $myPermCSV);
        $myCategoryStr = $aDataArray[1];
        $myPermCSV = $aDataArray[2];
    }
} else { //preprocess user input for extra leading/trailing commas/spaces
    $myPermCSV = trim($myPermCSV, " ,");
}

#store complete response data for later if not already in DB
if (!empty($myPermCSV) && !empty($myCategoryStr)) {
    p_setPackage($myPackageNameStr, $myCategoryStr, $myPermCSV);
}

#score permissions of app
$myDangerScore = 0;
$myPermArray = explode(",", $myPermCSV);
$aPermCounted = 0;
foreach ($myPermArray as $aPerm) {
    $aRetValue = p_getWatchedPermissionValue($aPerm);
    if ($aRetValue > 0) {
        $aPermCounted++;
    }
    $myDangerScore += $aRetValue;
}
$myDangerScore = $myDangerScore / $aPermCounted;
if ($myDangerScore > 9) {
    $myDangerScore = 9;
} elseif ($myDangerScore < 0) {
    $myDangerScore = 0;
}
if ((($myCategoryStr == 'Books & Reference') && ($myDangerScore > 2))
        || (($myCategoryStr == 'Business') && ($myDangerScore > 5))
        || (($myCategoryStr == 'Comics') && ($myDangerScore > 4))) {
    $myDangerScore++;
}

#output the response
echo 'name=' . $myPackageNameStr . $myNewLineChar;
echo 'category=' . $myCategoryStr . $myNewLineChar;
echo 'perms=' . $myPermCSV . $myNewLineChar;
echo 'danger_level=' . round($myDangerScore, 2) . $myNewLineChar;
echo 'danger_about=' . $myNewLineChar;
?>
