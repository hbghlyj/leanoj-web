<?php
// src/DiscuzBridge.php

class DiscuzBridge
{
    public static function syncSession()
    {
        if (isset($_SESSION['user_id'])) {
            return;
        }

        $configPath = '/var/www/bbs/config/config_global.php';
        if (!file_exists($configPath)) {
            return;
        }

        require $configPath;
        if (!isset($_config['cookie']['cookiepre']) || !isset($_config['security']['authkey'])) {
            return;
        }

        $cookiePre = $_config['cookie']['cookiepre'] .
            substr(md5($_config['cookie']['cookiepath'] . '|' . $_config['cookie']['cookiedomain']), 0, 4) .
            '_';
            
        $authCookie = $cookiePre . 'auth';
        $saltCookie = $cookiePre . 'saltkey';

        if (empty($_COOKIE[$authCookie]) || empty($_COOKIE[$saltCookie])) {
            return;
        }

        $authkey = md5($_config['security']['authkey'] . $_COOKIE[$saltCookie]);
        $rawAuth = rawurldecode($_COOKIE[$authCookie]);
        $data = self::authcode($rawAuth, 'DECODE', $authkey);

        if (empty($data) || strpos($data, "\t") === false) {
            return;
        }

        list($password, $uid) = explode("\t", $data);
        if (!$uid) {
            return;
        }

        $db = $_config['db'][1] ?? null;
        if (!$db || !class_exists('mysqli')) {
            return;
        }

        $mysqli = new mysqli($db['dbhost'], $db['dbuser'], $db['dbpw'], $db['dbname']);
        if ($mysqli->connect_errno) {
            return;
        }

        $table = $db['tablepre'] . 'common_member';
        $stmt = $mysqli->prepare("SELECT username, password FROM $table WHERE uid = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($username, $hash);
        $stmt->fetch();
        $stmt->close();
        $mysqli->close();

        if ($hash !== $password) {
            return;
        }

        // Success! Set up Lean OJ session
        $_SESSION['user_id'] = (int)$uid;
        $_SESSION['username'] = $username;
        
        // ADMIN CHECK: If UID is in the founder list
        $founders = explode(',', str_replace(' ', '', $_config['admincp']['founder']));
        if (in_array((string)$uid, $founders)) {
            $_SESSION['is_admin'] = true;
        } else {
            $_SESSION['is_admin'] = false;
        }
    }

    public static function clearCookies()
    {
        $configPath = '/var/www/bbs/config/config_global.php';
        if (!file_exists($configPath)) return;
        require $configPath;
        
        $cookiePre = $_config['cookie']['cookiepre'] .
            substr(md5($_config['cookie']['cookiepath'] . '|' . $_config['cookie']['cookiedomain']), 0, 4) .
            '_';
        
        $domain = $_config['cookie']['cookiedomain'] ?: '';
        $path = $_config['cookie']['cookiepath'] ?: '/';
        
        $cookies = ['auth', 'saltkey', 'sid', 'lastvisit', 'lastact'];
        foreach ($cookies as $name) {
            setcookie($cookiePre . $name, '', time() - 3600, $path, $domain);
        }
        
        // Also clear Lean OJ session
        session_destroy();
    }

    private static function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if($operation == 'DECODE') {
            if(((int)substr($result, 0, 10) == 0 || (int)substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) === substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    }

    private static function getConfig() {
        $configPath = '/var/www/bbs/config/config_global.php';
        if (!file_exists($configPath)) return [];
        require $configPath;
        return $_config;
    }

    public static function getUsernames($uids) {
        if (empty($uids)) return [];
        $config = self::getConfig();
        $db = $config['db'][1] ?? null;
        if (!$db || !class_exists('mysqli')) {
            return [];
        }

        $mysqli = new mysqli($db['dbhost'], $db['dbuser'], $db['dbpw'], $db['dbname']);
        if ($mysqli->connect_errno) {
            error_log("DiscuzBridge MySQL Connect Error: " . $mysqli->connect_error);
            return [];
        }
        
        $uids_str = implode(',', array_map('intval', array_unique($uids)));
        $table = $db['tablepre'] . 'common_member';
        $res = $mysqli->query("SELECT uid, username FROM $table WHERE uid IN ($uids_str)");
        $map = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[$row['uid']] = $row['username'];
            }
        } else {
            error_log("DiscuzBridge MySQL Query Error: " . $mysqli->error);
        }
        $mysqli->close();
        return $map;
    }
}
