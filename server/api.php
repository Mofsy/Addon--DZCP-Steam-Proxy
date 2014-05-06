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

/** Require **/
require_once(basePath.'/system/core.php');
require_once(basePath.'/system/snoopy.php');
require_once(basePath.'/system/pdo.php');
require_once(basePath.'/system/memcache.php');
require_once(basePath.'/config.php');

$db = db::getInstance('default');
$snoopy = new Snoopy;
$snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
$snoopy->referer = "http://www.hammermaps.de/";
$snoopy->rawheaders["Pragma"] = "no-cache";

function com_user($data_input=array()) {
    global $db,$snoopy;
    $user = Cache::get('user_'.$data_input['steamid']);
    $user = !empty($user) && $user ? unserialize(base64_decode($user)) : false;

    $get = $db->select("SELECT data FROM `steam_data` WHERE `steamid` = ?",array($data_input['steamid']));
    if(!$user || !$db->rowCount()) { //Im Memcache suchen
        //In Datenbank suchen
        $get = $db->select("SELECT data FROM `steam_data` WHERE `steamid` = ?",array($data_input['steamid']));
        if($db->rowCount()) {
            $data = gzuncompress(hex2bin($get['data']));
            Cache::set('user_'.$data_input['steamid'], $data,true,120); //Cache
            return array('status' => 'available', 'data' => unserialize(base64_decode($data)));
        }
        else
        {
            //Nicht in Datenbank gefunden, neu anlegen
            if($snoopy->fetch('http://steamcommunity.com/id/'.$data_input['steamid'].'/?xml=1')) {
                if(!empty($snoopy->results)) {
                    $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
                    if(array_key_exists('error',$data)) {
                        if($snoopy->fetch('http://steamcommunity.com/profiles/'.$data_input['steamid'].'/?xml=1')) {
                            if(!empty($snoopy->results)) {
                                $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
                            }
                            else
                                return array('status' => 'no_discover', 'data' => '');
                        }
                    }

                    if(array_key_exists('error',$data))
                        return array('status' => 'no_discover', 'data' => '');

                    Cache::set('user_'.$data_input['steamid'], base64_encode(serialize($data)),true,120); //Cache

                    $db->select("SELECT id FROM `steam_data` WHERE `steamid` = ?",array($data_input['steamid']));
                    if(!$db->rowCount())
                        $db->insert("INSERT INTO `steam_data` (`id`, `steamid`, `communityid`, `data`, `time_data`, `time_data_api`) VALUES
                                    (NULL, '".$data_input['steamid']."', '".$data['steamID64']."', '".bin2hex(gzcompress(base64_encode(serialize($data))))."', '".(time()+24*60*60)."', '0');");

                     return array('status' => 'available', 'data' => $data);
                }
                else
                    return array('status' => 'no_discover', 'data' => '');
            }
            else
                return array('status' => 'no_discover', 'data' => '');
        }
    }
    else
        return array('status' => 'available', 'data' => $user);

    return array('status' => 'no_discover', 'data' => '3');
}

function api_user($data_input=array()) {
    global $db,$snoopy;
    $user = Cache::get('user_api_'.$data_input['interface'].'_'.$data_input['method'].'_'.$data_input['version'].'_'.$data_input['steamid']);
    $user = !empty($user) && $user ? unserialize(base64_decode($user)) : false;
    if(!$user) { //Im Memcache suchen
        //In Datenbank suchen
        $get = $db->select("SELECT id,data_api,communityid FROM `steam_data` WHERE `steamid` = ?",array($data_input['steamid']));
        if($db->rowCount()) {
            if(!empty($get['data_api'])) {
                $data = gzuncompress(hex2bin($get['data_api']));
                Cache::set('user_api_'.$data_input['interface'].'_'.$data_input['method'].'_'.$data_input['version'].'_'.$data_input['steamid'], $data,true,30); //Cache
                return array('status' => 'available', 'data' => unserialize(base64_decode($data)));
            } else {
                $send_data_api = array('format' => 'xml', 'key' => steam_api_key, 'steamids' => $get['communityid']);
                if($snoopy->fetch('http://api.steampowered.com/'.$data_input['interface'].'/'.$data_input['method'].'/'.$data_input['version'].'/?'.http_build_query($send_data_api))) {
                    if(!empty($snoopy->results)) {
                        $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
                        if(array_key_exists('error',$data) || !count($data['players']))
                            return array('status' => 'no_discover', 'data' => '');

                        $data = $data['players']['player'];

                        //User im Memcache, aktualisieren
                        Cache::set('user_api_'.$data_input['interface'].'_'.$data_input['method'].'_'.$data_input['version'].'_'.$data_input['steamid'], base64_encode(serialize($data)),true,30); //Cache

                        //Datenbank Update
                        $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+30)."', `data_api` = ? WHERE `id` = ?",array(bin2hex(gzcompress(base64_encode(serialize($data)))),$get['id']));
                        return array('status' => 'available', 'data' => $data);
                    }
                    else
                        return array('status' => 'no_discover', 'data' => '');
                }
                else
                    return array('status' => 'no_discover', 'data' => '');
            }
        } else {
            //Nicht in Datenbank gefunden, neu anlegen
            $com_user_data = com_user($data_input);
            if($com_user_data['status'] == 'no_discover')
                return array('status' => 'no_discover', 'data' => '');

            $com_user_data = $com_user_data['data'];
            $send_data_api = array('format' => 'xml', 'key' => steam_api_key, 'steamids' => $com_user_data['steamID64']);
            if($snoopy->fetch('http://api.steampowered.com/'.$data_input['interface'].'/'.$data_input['method'].'/'.$data_input['version'].'/?'.http_build_query($send_data_api))) {
                if(!empty($snoopy->results)) {
                    $data_api = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
                    if(array_key_exists('error',$data_api) || !count($data_api['players']))
                        return array('status' => 'no_discover', 'data' => '');

                    $data_api = $data_api['players']['player'];

                    //User im Memcache, aktualisieren
                    Cache::set('user_api_'.$data_input['interface'].'_'.$data_input['method'].'_'.$data_input['version'].'_'.$data_input['steamid'], base64_encode(serialize($data_api)),true,30); //Cache

                    //Datenbank Update
                    $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+30)."', `data_api` = ? WHERE `steamid` = ?",array(bin2hex(gzcompress(base64_encode(serialize($data_api)))),$data_input['steamid']));
                    return array('status' => 'available', 'data' => $data_api);
                }
                else
                    return array('status' => 'no_discover', 'data' => '');
            }
            else
                return array('status' => 'no_discover', 'data' => '');
        }
    }
    else
        return array('status' => 'available', 'data' => $user);

    return array('status' => 'no_discover', 'data' => '');
}

if(isset($_GET['proxy']) && isset($_GET['data']) && !empty($_GET['data'])) {
    $data_input = unserialize(base64_decode(gzuncompress(hex2bin($_GET['data'])))); //Decode
    switch ($data_input['call']) {
        case 'com': //Full User Update
            echo bin2hex(gzcompress(base64_encode(serialize(com_user($data_input))))); //Endcode
        break;
        case 'api': //Fast API Update
            echo bin2hex(gzcompress(base64_encode(serialize(api_user($data_input))))); //Endcode
        break;
        default: die('No Call!'); break;
    }
}

$db->disconnect();
ob_end_flush();