<?php

$packages_count = sizeof($packages);
echo 'package_count=' . $packages_count . "\n";

for ($i = 0; $i < $packages_count; $i++) {
    echo 'name=' . $packages[$i]['package_name'] . "\n";
    echo 'id=' . $packages[$i]['package_id'] . "\n";
    echo 'category=' . $packages[$i]['package_category'] . "\n";
    $package_perm_array = $packages[$i]['package_perm_array'];
    $ppa_size = sizeof($package_perm_array);
    echo 'perms=';
    for ($i = 0; $i < $ppa_size; $i++) {
        $perm = $package_perm_array[$i];
        if ($perm != '') {
            if (($i + 1) == $ppa_size) {
                echo $perm;
            } else {
                echo $package_perm_array[$i] . ',';
            }
        }
    }
    echo "\n";
}
