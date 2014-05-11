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

class conjob {
    // Aktualisieren der allgemeinen Informationen
    final static function user_update_com($user=array()) {
        global $snoopy,$db;
        if(console_print_text)
            echo date("[H:i:s]")." Full update UserID: ".$user['id']."\n";

        $snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
        $snoopy->referer = "http://www.hammermaps.de/";
        $snoopy->rawheaders["Pragma"] = "no-cache";

        if($snoopy->fetch('http://steamcommunity.com/id/'.$user['steamid'].'/?xml=1')) {
            $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
            if(array_key_exists('error',$data)) {
                if($snoopy->fetch('http://steamcommunity.com/profiles/'.$user['steamid'].'/?xml=1')) {
                    if(!empty($snoopy->results)) {
                        $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));
                    }
                    else
                    {
                        $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+30)."', `update_fails` = ".($user['update_fails']+1)." WHERE `id` = ?",array($user['id']));
                        return false;
                    }
                }
            }

            if(!array_key_exists('players',$data) || array_key_exists('error',$data) || !count($data['players'])) {
                $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+60+rand(1, 5))."', `update_fails` = ".($user['update_fails']+1)." WHERE `id` = ?",array($user['id']));
                return false;
            }

            //User im Memcache, aktualisieren
            $user_in_mem = Cache::get('user_'.$user['steamid']);
            if(!empty($user_in_mem) && $user_in_mem ? true : false)
                Cache::replace('user_'.$user['steamid'], base64_encode(serialize($data)),true,120); //Cache

            //Datenbank Update
            $db->update("UPDATE `steam_data` SET `time_data` = '".(time()+24*60*60)."', `data` = ?, `update_fails` = 0 WHERE `id` = ?",array(bin2hex(gzcompress(base64_encode(serialize($data)))),$user['id']));
        }
    }

    // Aktualisieren der Steam API Informationen
    final static function user_update_api() {
        global $snoopy,$db;

        $users = index::getIndex(); $steamids64 = ''; $userids = '';
        foreach ($users as $communityid => $data) {
            $steamids64 .= $communityid.',';
            $userids .= $data['id'].',';
        }
        $steamids64 = substr($steamids64, 0, -1);
        $userids = substr($userids, 0, -1);

        if(console_print_text)
            echo date("[H:i:s]")." Fast update for UserIDs: ".$userids."\n";

        $snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
        $snoopy->referer = "http://www.hammermaps.de/";
        $snoopy->rawheaders["Pragma"] = "no-cache";

        $send_data_api = array('format' => 'xml', 'key' => steam_api_key, 'steamids' => $steamids64);
        if($snoopy->fetch('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?'.http_build_query($send_data_api))) {
            $data = objectToArray(simplexml_load_string($snoopy->results, 'SimpleXMLElement', LIBXML_NOCDATA));

            if(array_key_exists('error',$data) || !count($data['players']))
                return false;

            foreach ($data['players'] as $id ) {
                foreach ($id as $player ) {
                    if(!$user = index::get($player['steamid']))
                        continue;

                    //User im Memcache, aktualisieren
                    $user_in_mem = Cache::get('user_api_'.$user['steamid']);
                    if(!empty($user_in_mem) && $user_in_mem ? true : false)
                        Cache::replace('user_api_'.$user['steamid'], base64_encode(serialize($player)),true,60); //Cache

                    //Datenbank Update
                    $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+60+rand(1, 5))."', `data_api` = ?, `update_fails` = 0 WHERE `id` = ?",array(bin2hex(gzcompress(base64_encode(serialize($player)))),$user['id']));
                    index::remove($user['communityid']);
                }

                foreach(index::getIndex() as $communityid => $data) {
                    $db->update("UPDATE `steam_data` SET `time_data_api` = '".(time()+30)."', `update_fails` = ".($data['update_fails']+1)." WHERE `id` = ?",array($data['id']));
                    index::remove($communityid);
                }
            }
        }
    }
}