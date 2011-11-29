<?php

/**
 * SQLite3 database and table setup
 * NOTE: be sure to create the db directory with 777 perms on the server
 */
$SQLite3_conn = new SQLite3('db/MarketPerms.db');
$SQLite3_conn->exec("PRAGMA foreign_keys = ON");

$aSqlCreatePackagesTable = "CREATE TABLE IF NOT EXISTS Packages "
        . "(id INTEGER PRIMARY KEY DESC, name TEXT, "
        . "category TEXT, permissions TEXT)";
$SQLite3_conn->exec($aSqlCreatePackagesTable);

$aSqlCreateInstallsTable = "CREATE TABLE IF NOT EXISTS Installs "
        . "(id INTEGER PRIMARY KEY DESC, completed INTEGER, "
        . "packages_id INTEGER, "
        . "FOREIGN KEY(packages_id) REFERENCES Packages(id))";
$SQLite3_conn->exec($aSqlCreateInstallsTable);

$aSqlCreatePermsWatchTable = "CREATE TABLE IF NOT EXISTS PermWatch "
        . "(id INTEGER PRIMARY KEY ASC, perm_string TEXT, start_score REAL, "
        . "current_score REAL)";
$SQLite3_conn->exec($aSqlCreatePermsWatchTable);

/**
 * initialize watched permissions
 */
p_setWatchedPermissionValue("android.permission.ACCESS_FINE_LOCATION", 1.25);
p_setWatchedPermissionValue("android.permission.WRITE_SMS", 3.5);

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
 * update the install table to include a completed tuple for the given package
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $theCategory
 */
function p_incrementCompletedInstallCount($theName, $theCategory) {
    global $SQLite3_conn;
    $aPid = $SQLite3_conn->querySingle("SELECT Packages.id FROM Installs, "
            . "Packages WHERE Installs.packages_id = Packages.id AND "
            . "Packages.name='$theName' AND Packages.category='$theCategory'");
    $SQLite3_conn->exec("INSERT INTO Installs VALUES(NULL, 1, '$aPid')");
}

/**
 * update the install table to include a rejected tuple for the given package
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $theCategory
 */
function p_incrementRejectedInstallCount($theName, $theCategory) {
    global $SQLite3_conn;
    $aPid = $SQLite3_conn->querySingle("SELECT Packages.id FROM Installs, "
            . "Packages WHERE Installs.packages_id = Packages.id AND "
            . "Packages.name='$theName' AND Packages.category='$theCategory'");
    $SQLite3_conn->exec("INSERT INTO Installs VALUES(NULL, 0, '$aPid')");
}

/**
 * how many times was a certain package name in a certain category still
 * installed by the user after seeing our information screen?
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $theCategory
 * @return integer - count of package installed after seeing our screen?
 */
function p_getCompletedInstallCount($theName, $theCategory) {
    global $SQLite3_conn;
    $aCompletedInstallCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "Installs, Packages WHERE Installs.packages_id = Packages.id AND "
            . "Packages.name='$theName' AND Packages.category='$theCategory' "
            . "AND Installs.completed = 1");
    return $aCompletedInstallCount;
}

/**
 * how many times was a certain package name in a certain category rejected
 * by the user after seeing our information screen?
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $theCategory
 * @return integer - count of package rejected after seeing our screen?
 */
function p_getRejectedInstallCount($theName, $theCategory) {
    global $SQLite3_conn;
    $aRejectedInstallCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM "
            . "Installs, Packages WHERE Installs.packages_id = Packages.id AND "
            . "Packages.name='$theName' AND Packages.category='$theCategory' "
            . "AND Installs.completed = 0");
    return $aRejectedInstallCount;
}

/**
 * create a package in the database if one with the same name and category
 * do not already exist
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @param string $theCategory
 * @param string $thePermissionCSV
 * @return boolean - was a package record created?
 */
function p_setPackage($theName, $theCategory, $thePermissionCSV) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM Packages "
            . "WHERE name='$theName' AND category='$theCategory'");
    if ($aResultCount == 0) {
        $SQLite3_conn->exec("INSERT INTO Packages VALUES(NULL, '$theName', "
                . "'$theCategory', '$thePermissionCSV')");
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
 * @param string $theCategory
 * @return boolean or CSV
 */
function p_getPackagePermissions($theName, $theCategory) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM Packages "
            . "WHERE name='$theName' AND category='$theCategory'");
    if ($aResultCount == 1) {
        $results = $SQLite3_conn->query("SELECT permissions FROM Packages "
                . "WHERE name='$theName' AND category='$theCategory'");

        $row = $results->fetchArray(SQLITE3_ASSOC);
        return $row['permissions'];
    } else {
        return false;
    }
}

/**
 * grab the first non-empty category string for a certain package name
 *
 * @global SQLite3 $SQLite3_conn
 * @param string $theName
 * @return string
 */
function p_getPackageCategory($theName) {
    global $SQLite3_conn;
    $aResultCount = $SQLite3_conn->querySingle("SELECT COUNT(*) FROM Packages "
            . "WHERE name='$theName'");
    if ($aResultCount <= 2) {
        $results = $SQLite3_conn->query("SELECT category FROM Packages "
                . "WHERE name='$theName' AND category<>''");

        $row = $results->fetchArray(SQLITE3_ASSOC);
        return $row['category'];
    } else {
        return false;
    }
}

?>
