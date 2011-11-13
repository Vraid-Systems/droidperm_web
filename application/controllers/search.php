<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Search extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->config->load('market.php');
        include('protocolbuffers.inc.php');
        include('market.proto.php');
        $this->load->library('MarketSession');
    }

    public function index($thePackageName, $theExactMatchBoolean = 'true') {
        if ((strpos($theExactMatchBoolean, 't') !== false) || (strpos($theExactMatchBoolean, 'y') !== false)) {
            $theExactMatchBoolean = true;
        } else {
            $theExactMatchBoolean = false;
        }

        $session = new MarketSession();
        $session->login(GOOGLE_EMAIL, GOOGLE_PASSWD);
        $session->setAndroidId(ANDROID_DEVICEID);

        $ar = new AppsRequest();
        $ar->setQuery($thePackageName);
        $ar->setOrderType(AppsRequest_OrderType::NONE);
        $ar->setStartIndex(0);
        $ar->setEntriesCount(5);
        $ar->setWithExtendedInfo(true);

        $reqGroup = new Request_RequestGroup();
        $reqGroup->setAppsRequest($ar);

        $response = $session->execute($reqGroup);

        $groups = $response->getResponsegroupArray();

        $i = 0;
        foreach ($groups as $rg) {
            $appsResponse = $rg->getAppsResponse();
            $apps = $appsResponse->getAppArray();
            foreach ($apps as $app) {
                $aPackageName = $app->getPackageName();
                if ($theExactMatchBoolean) {
                    $pos = strpos($thePackageName, $aPackageName); //check if packagename is in search
                } else {
                    $pos = strpos($aPackageName, $thePackageName); //check if search is in packagename
                }
                if ($pos !== false) {
                    $data['packages'][$i]['package_name'] = $aPackageName;
                    $data['packages'][$i]['package_id'] = $app->getId();
                    $data['packages'][$i]['package_category'] = $app->getExtendedInfo()->getCategory();
                    $data['packages'][$i]['package_perm_array'] = $app->getExtendedInfo()->getPermissionIdArray();
                    $i++;
                }
            }
        }
        $this->load->view('package_output', $data);
    }

}

/* End of file search.php */
/* Location: ./application/controllers/search.php */
