<?php

/**
 * VULNERABLE TO SQL INJECTIONS
 * thought you should know that this is not production quality code
 *
 *
 * SQLite3 database and table setup
 * NOTE: be sure to create the db directory with 777 perms on the server
 */
$SQLite3_conn = new SQLite3('db/MarketPerms.db');
$SQLite3_conn->exec("PRAGMA foreign_keys = ON");

$aSqlCreatePackagesTable = "CREATE TABLE IF NOT EXISTS Packages "
        . "(id INTEGER PRIMARY KEY ASC, name TEXT, permissions TEXT)";
$SQLite3_conn->exec($aSqlCreatePackagesTable);

$aSqlCreateInstallsTable = "CREATE TABLE IF NOT EXISTS Installs "
        . "(id INTEGER PRIMARY KEY ASC, completed INTEGER, "
        . "interface_id TEXT, package_id INTEGER, "
        . "FOREIGN KEY(package_id) REFERENCES Packages(id))";
$SQLite3_conn->exec($aSqlCreateInstallsTable);

$aSqlCreatePermsWatchTable = "CREATE TABLE IF NOT EXISTS PermWatch "
        . "(id INTEGER PRIMARY KEY ASC, perm_string TEXT, start_score REAL, "
        . "current_score REAL)";
$SQLite3_conn->exec($aSqlCreatePermsWatchTable);

/**
 * initialize watched permissions
 * scores are set based on need to 1) mitigate larger group damage, 2) minimze
 * privacy violations, 3) stop property damage
 *
 * Paranoid - 2
 * Normal - 5
 * Lax - 7
 */
p_setWatchedPermissionValue("android.permission.ACCESS_COARSE_LOCATION", 1.00);
p_setWatchedPermissionValue("android.permission.ACCESS_FINE_LOCATION", 2.75);
p_setWatchedPermissionValue("android.permission.BLUETOOTH_ADMIN", 1.50);
p_setWatchedPermissionValue("android.permission.BRICK", 9.00);
p_setWatchedPermissionValue("android.permission.CALL_PHONE", 3.00);
p_setWatchedPermissionValue("android.permission.CALL_PRIVILEGED", 9.00);
p_setWatchedPermissionValue("android.permission.DEVICE_POWER", 1.50);
p_setWatchedPermissionValue("android.permission.INSTALL_PACKAGES", 4.25);
p_setWatchedPermissionValue("android.permission.MODIFY_PHONE_STATE", 0.25);
p_setWatchedPermissionValue("android.permission.NFC", 0.75);
p_setWatchedPermissionValue("android.permission.READ_CALENDAR", 1.25);
p_setWatchedPermissionValue("android.permission.READ_CONTACTS", 3.25);
p_setWatchedPermissionValue("android.permission.READ_HISTORY_BOOKMARKS", 0.50);
p_setWatchedPermissionValue("android.permission.READ_OWNER_DATA", 5.50);
p_setWatchedPermissionValue("android.permission.READ_PROFILE", 4.25);
p_setWatchedPermissionValue("android.permission.READ_SMS", 6.25);
p_setWatchedPermissionValue("android.permission.REBOOT", 7.00);
p_setWatchedPermissionValue("android.permission.RECEIVE_BOOT_COMPLETED", 0.25);
p_setWatchedPermissionValue("android.permission.RECEIVE_MMS", 4.00);
p_setWatchedPermissionValue("android.permission.RECEIVE_SMS", 5.00);
p_setWatchedPermissionValue("android.permission.SEND_SMS", 4.00);
p_setWatchedPermissionValue("android.permission.USE_SIP", 5.25);
p_setWatchedPermissionValue("android.permission.UPDATE_DEVICE_STATS", 8.25);
p_setWatchedPermissionValue("android.permission.WRITE_SETTINGS", 6.50); //and read

/**
 * set the current score (and start score if new) for a permission string
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $thePermStr
 * @param real $thePermScore
 */
function p_setWatchedPermissionValue($thePermStr, $thePermScore) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "PermWatch WHERE perm_string='$thePermStr'");
    if ($aResultCount == 0) {
        $SQLite3_conn->exec("INSERT INTO PermWatch VALUES(NULL, '$thePermStr', "
                . "$thePermScore, $thePermScore)");
    } elseif ($aResultCount == 1) {
        $SQLite3_conn->exec("UPDATE PermWatch SET current_score=$thePermScore "
                . "WHERE perm_string='$thePermStr'");
    }
}

/**
 * get the permission string score from the database
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $thePermStr
 * @return real
 */
function p_getWatchedPermissionValue($thePermStr) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "PermWatch WHERE perm_string='$thePermStr'");
    if ($aResultCount == 1) {
        $aResultScore = $SQLite3_conn->querySingle("SELECT current_score FROM "
                . "PermWatch WHERE perm_string='$thePermStr'");
        return $aResultScore;
    } else {
        return 0;
    }
}

/**
 * add a tuple to the Installs table for the given interface_id and package_id
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theInterfaceId
 * @param string $theName - the package name [com.whatever]
 * @param integer $theInstalledFlag [0|1]
 */
function p_incrementInstallCount($theInterfaceId, $theName, $theInstalledFlag) {
    global $SQLite3_conn;
    $aPid = $SQLite3_conn->querySingle("SELECT id FROM Packages WHERE name='$theName'");
    if (is_int($aPid) && (($theInstalledFlag == 0) || ($theInstalledFlag == 1))) {
        $SQLite3_conn->exec("INSERT INTO Installs VALUES(NULL, $theInstalledFlag, '$theInterfaceId', $aPid)");
    }
}

/**
 * how many times was a certain package name still
 * installed by the user after seeing our information screen?
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @return integer - count of package installed after seeing our screen?
 */
function p_getCompletedInstallCount($theName) {
    global $SQLite3_conn;
    $aCompletedInstallCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "Installs, Packages WHERE Installs.package_id = Packages.id AND "
            . "Packages.name='$theName' AND Installs.completed = 1");
    return $aCompletedInstallCount;
}

/**
 * how many times was a certain package name rejected
 * by the user after seeing our information screen?
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @return integer - count of package rejected after seeing our screen?
 */
function p_getRejectedInstallCount($theName) {
    global $SQLite3_conn;
    $aRejectedInstallCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "Installs, Packages WHERE Installs.package_id = Packages.id AND "
            . "Packages.name='$theName' AND Installs.completed = 0");
    return $aRejectedInstallCount;
}

/**
 * create a package in the database if one with the same name
 * does not already exist
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $thePermissionCSV
 * @return boolean - was a package record created?
 */
function p_setPackage($theName, $thePermissionCSV) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM Packages "
            . "WHERE name='$theName'");
    if ($aResultCount == 0) {
        $SQLite3_conn->exec("INSERT INTO Packages VALUES(NULL, '$theName', "
                . "'$thePermissionCSV')");
        return true;
    } else {
        return false;
    }
}

/**
 * rerieve a single package permission CSV string or
 * boolean false on error/no results
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @return boolean or CSV
 */
function p_getPackagePermissions($theName) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM Packages "
            . "WHERE name='$theName'");
    if ($aResultCount == 1) {
        $results = $SQLite3_conn->query("SELECT permissions FROM Packages "
                . "WHERE name='$theName'");

        $row = $results->fetchArray(SQLITE3_ASSOC);
        return $row['permissions'];
    } else {
        return false;
    }
}

?>
