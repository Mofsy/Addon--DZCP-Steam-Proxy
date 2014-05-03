<?php
/**
 * The class makes it easier to work with memcached servers and provides hints in the IDE like Zend Studio
 * @author Grigori Kochanov http://www.grik.net/
 * @version 1
 *
 */
class Cache {
/**
 * Resources of the opend memcached connections
 * @var array [memcache objects]
 */
protected $mc_servers = array();
/**
 * Quantity of servers used
 * @var int
 */
protected $mc_servers_count;

static $mc_servers_online = false;
static $mc_servers_new = array();
static $instance;

static function ping_server(){
    global $mc_servers_config;
    self::$mc_servers_new = $mc_servers_config;
    foreach (self::$mc_servers_new as $servers) {
        foreach ($servers as $key => $var)
        if(self::ping($key,$var))
            self::$mc_servers_online = true;
    }
}

/**
 * Singleton to call from all other functions
 */
static function singleton(){
    self::$instance || self::$instance = new Cache(self::$mc_servers_new);
    return self::$instance;
}

static function ping($address='',$port=0000,$timeout=0.2,$udp=false)
{
    $errstr = NULL; $errno = NULL;
    if($fp = @fsockopen(($udp ? "udp://".$ip : $ip), $port, $errno, $errstr, $timeout)) {
        unset($ip,$port,$errno,$errstr,$timeout);
        @fclose($fp);
        return true;
    }

    return false;
}

/**
 * Accepts the 2-d array with details of memcached servers
 *
 * @param array $servers
 */
protected function __construct(array $servers){
    if (!$servers){
        trigger_error('No memcache servers to connect',E_USER_WARNING);
    }
    for ($i = 0, $n = count($servers); $i < $n; ++$i){
        ( $con = memcache_pconnect(key($servers[$i]), current($servers[$i])) )&&
            $this->mc_servers[] = $con;
    }

    $this->mc_servers_count = count($this->mc_servers);
    if (!$this->mc_servers_count){
        $this->mc_servers[0]=null;
    }
}
/**
 * Returns the resource for the memcache connection
 *
 * @param string $key
 * @return object memcache
 */
protected function getMemcacheLink($key){
    if ( $this->mc_servers_count <2 ){
        //no servers choice
        return $this->mc_servers[0];
    }
    return $this->mc_servers[(crc32($key) & 0x7fffffff)%$this->mc_servers_count];
}

/**
 * Clear the cache
 *
 * @return void
 */
static function flush() {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    $x = self::singleton()->mc_servers_count;
    for ($i = 0; $i < $x; ++$i){
        $a = self::singleton()->mc_servers[$i];
        self::singleton()->mc_servers[$i]->flush();
    }
}

/**
 * Returns the value stored in the memory by it's key
 *
 * @param string $key
 * @return mix
 */
static function get($key) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->get($key);
}

/**
 * Store the value in the memcache memory (overwrite if key exists)
 *
 * @param string $key
 * @param mix $var
 * @param bool $compress
 * @param int $expire (seconds before item expires)
 * @return bool
 */
static function set($key, $var, $compress=0, $expire=0) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->set($key, $var, $compress?MEMCACHE_COMPRESSED:null, $expire);
}
/**
 * Set the value in memcache if the value does not exist; returns FALSE if value exists
 *
 * @param sting $key
 * @param mix $var
 * @param bool $compress
 * @param int $expire
 * @return bool
 */
static function add($key, $var, $compress=0, $expire=0) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->add($key, $var, $compress?MEMCACHE_COMPRESSED:null, $expire);
}

static function stats() {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink(1)->getStats();
}

/**
 * Replace an existing value
 *
 * @param string $key
 * @param mix $var
 * @param bool $compress
 * @param int $expire
 * @return bool
 */
static function replace($key, $var, $compress=0, $expire=0) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->replace($key, $var, $compress?MEMCACHE_COMPRESSED:null, $expire);
}
/**
 * Delete a record or set a timeout
 *
 * @param string $key
 * @param int $timeout
 * @return bool
 */
static function delete($key, $timeout=0) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->delete($key, $timeout);
}
/**
 * Increment an existing integer value
 *
 * @param string $key
 * @param mix $value
 * @return bool
 */
static function increment($key, $value=1) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->increment($key, $value);
}

/**
 * Decrement an existing value
 *
 * @param string $key
 * @param mix $value
 * @return bool
 */
static function decrement($key, $value=1) {
    self::ping_server();
    if(!self::$mc_servers_online) return false;
    return self::singleton()->getMemcacheLink($key)->decrement($key, $value);
}


//class end
}