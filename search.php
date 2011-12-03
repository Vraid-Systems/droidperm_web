<?php

/**
 * retrieve more information about a certain package and overwrite the input parameters
 * @param string $thePackageNameStr
 * @param string $theAppCategoryStr
 * @param string $thePermCSV
 * @return array - updated [$thePackageNameStr, $theAppCategoryStr, $thePermCSV]
 */
function getPermsAndCategory($thePackageNameStr = '', $theAppCategoryStr = false, $thePermCSV = false) {
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
    while (empty($thePermCSV) && empty($theAppCategoryStr)) {
        #create market search request
        $ar = new AppsRequest();
        $ar->setQuery($thePackageNameStr);
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
            if (!empty($thePermCSV) && !empty($theAppCategoryStr))
                break; //do not overwrite old values
            $appsResponse = $rg->getAppsResponse();
            $apps = $appsResponse->getAppArray();
            foreach ($apps as $app) {
                if (!empty($thePermCSV) && !empty($theAppCategoryStr))
                    break; //do not overwrite old values
                $myPackageTotalCount++;
                $aPackageName = $app->getPackageName();
                if ($thePackageNameStr == $aPackageName) { //if there is a match ... grab first
                    if (empty($theAppCategoryStr)) {
                        $theAppCategoryStr = $app->getExtendedInfo()->getCategory();
                    }
                    if (empty($thePermCSV)) {
                        $aPermArray = $app->getExtendedInfo()->getPermissionIdArray();
                        $thePermCSV = implode(",", $aPermArray);
                    }
                    break;
                }
            }
        }

        $mySearchLoopCount++;

        if ($mySearchLoopCount >= $mySearchLoopCountLimit)
            break;
    }

    return (Array($thePackageNameStr, $theAppCategoryStr, $thePermCSV));
}

?>
