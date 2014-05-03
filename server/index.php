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
ob_start('ob_gzhandler');
date_default_timezone_set("Europe/Berlin");
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_GET['stats'])) {
    /** Require **/
    require_once(basePath.'/system/core.php');
    require_once(basePath.'/system/snoopy.php');
    require_once(basePath.'/system/pdo.php');
    require_once(basePath.'/system/memcache.php');
    require_once(basePath.'/system/array2xml.php');
    require_once(basePath.'/config.php');

    $stats = Cache::get('stats');
    if(!$stats || empty($stats)) {
        $db = db::getInstance('default');
        $db->select("SELECT id FROM `steam_data`");
        $stats = array_merge(array('db_users' => $db->rowCount()),$db->tablesize('steam_data'),Cache::stats());
        Cache::set('stats', base64_encode(serialize($stats)),10);
        $db->disconnect();
    }
    else
        $stats = unserialize(base64_decode($stats));

    switch($_GET['stats']) {
        case 'xml':
            $xml = Array2XML::createXML('stats', $stats);
            die($xml->saveXML());
        break;
        case 'json': die(json_encode($stats, JSON_FORCE_OBJECT)); break;
        default:
            echo '<pre>';
                print_r($stats);
            echo '</pre>';
        break;
    }

    ob_end_flush();
}
else {
    exit('DZCP - deV!L`z ClanPortal Steam Proxy Server<p><b>Use: <a href="'.$_SERVER['PHP_SELF'].'?stats=text" target="_self">?stats=text</a> |
            <a href="'.$_SERVER['PHP_SELF'].'?stats=xml" target="_self">?stats=xml</a>  or <a href="'.$_SERVER['PHP_SELF'].'?stats=json" target="_self">?stats=json</a></b>');
}