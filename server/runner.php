<?php
/**
 The MIT License (MIT)

 Copyright (c) 2014 DZCP-Community
 DZCP - deV!L`z ClanPortal Steam Proxy Server
 http://www.dzcp.de

 Permission is hereby granted, free of charge, to any person obtaining a copy of
 this software and associated documentation files (the "Software"), to deal in
 the Software without restriction, including without limitation the rights to
 use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 the Software, and to permit persons to whom the Software is furnished to do so,
 subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

define('basePath', dirname(__FILE__));
date_default_timezone_set("Europe/Berlin");
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

echo '***************************************************************'."\n";
echo 'DZCP 1.6 Steam Status Proxy Server'."\n";
echo 'Fur PHP 5.4 > x86/x64 Version 1.0'."\n";
echo 'Powered by Hammermaps.de'."\n";
echo '***************************************************************'."\n \n";

/** Require **/
require_once(basePath.'/system/core.php');
require_once(basePath.'/system/snoopy.php');
require_once(basePath.'/system/pdo.php');
require_once(basePath.'/system/memcache.php');
require_once(basePath.'/system/conjob.php');
require_once(basePath.'/config.php');

$start_up = true;
$snoopy = new Snoopy;
$db = db::getInstance('default');
while(true) {
    if($start_up) {
        echo date("[H:i:s]").' Proxy Server started!'."\n";

        if(console_print_text)
            echo date("[H:i:s]").' Infos enabled'."\n";
    }
    $sleep_full = false; $sleep_api = false;

    //Full Updates
    $db->select('SELECT id FROM `steam_data` WHERE `time_data` <= '.time());
    $for_count = ceil($db->rowCount()/50); $pointer = 1;
    if($for_count >= 1) {
        $sleep_full = false;
        for ($i = 0; $i < $for_count; $i++) {
            foreach($db->select_foreach('SELECT id,steamid FROM `steam_data` WHERE `time_data` <= '.time().' LIMIT '.($i*50).','.($pointer*50)) as $user) {
                conjob::user_update_com($user);
            }

            $pointer++;
        }
    }
    else
        $sleep_full = true;

    //Fast Updates
    $db->select('SELECT id FROM `steam_data` WHERE `time_data_api` <= '.time());
    $for_count = ceil($db->rowCount()/50); $pointer = 1;
    if($for_count >= 1) {
        $sleep_full = false;
        for ($i = 0; $i < $for_count; $i++) {
            foreach($db->select_foreach('SELECT id,data_api,steamid,communityid FROM `steam_data` WHERE `time_data_api` <= '.time().' LIMIT '.($i*50).','.($pointer*50)) as $user) {
                conjob::user_update_api($user);
            }

            $pointer++;
        }
    }
    else
        $sleep_api = true;

    $start_up = false;
    if($sleep_api && $sleep_full) {
        sleep(15); // No Updates, Sleep
    }
}