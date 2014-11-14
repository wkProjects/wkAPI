<?php

/**
 * wkAPI v2
 *
 * Diese API stellt Funktionen bereit, mit denen Informationen zu
 * Webkicks-Chats ermittelt und ausgegeben werden können.
 *
 * @author Bastian Schwetzel
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @version 2.0
 * Date: 12.11.2014
 * Time: 18:57
 */
class wkAPI
{
    private $server;
    private $cid;
    private $username;
    private $password;
    private $sid;
    private $baseURL;

    private static $urlMethod;
    private $cache = Array();

    public function getServer()
    {
        return $this->server;
    }

    public function setServer($server)
    {
        $this->server = $server;
        $this->cache = array();
    }

    public function getCid()
    {
        return $this->cid;
    }

    public function setCid($cid)
    {
        $this->cid = $cid;
        $this->cache = array();
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        $this->cache = array();
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        $this->sid = static::pw2sid($password);
        $this->cache = array();
    }

    public function __construct()
    {
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this, $f = '__construct' . $i)) {
            static::$urlMethod = static::chooseURLMethod();
            if (static::$urlMethod === false) {
                throw new Exception("Es konnte keine Methode gefunden werden, HTTP-Anfragen zu stellen.");
            }
            call_user_func_array(array($this, $f), $a);
        } else {
            throw new Exception("Ungültige Anzahl an Argumenten.");
        }
    }

    private function __construct2($server, $cid)
    {
        $this->server = intval($server);
        $this->cid = $cid;
        $this->baseURL = $this->generateBaseURL($server, $cid);

        $headers = get_headers("http://" . $this->baseURL, 1);
        if ($headers[0] !== "HTTP/1.1 200 OK") {
            var_dump($headers);
            throw new Exception("Chat nicht gefunden.");
        }
    }

    private function __construct4($server, $cid, $username, $password)
    {
        $this->__construct2($server, $cid);
        $this->username = $username;
        $this->password = $password;
        $this->sid = static::pw2sid($password);
    }

    static private function chooseURLMethod()
    {
        $allow_url_fopen = ini_get("allow_url_fopen");
        $disabled_functions = explode(",", ini_get("disable_functions"));

        if ($allow_url_fopen == true && (!in_array("file_get_contents", $disabled_functions) || !in_array("file", $disabled_functions))) {
            if (!in_array("file_get_contents", $disabled_functions)) {
                return "file_get_contents";  //file_get_contents() kann benutzt werden
            }
            if (!in_array("file", $disabled_functions)) {
                return "file";  //file() kann benutzt werden
            }

        }

        if (!in_array("fsockopen", $disabled_functions)) {
            return "fsockopen";  //fsockopen() kann benutzt werden
        }

        return false;
    }

    static private function getContents($url)
    {
        $return = "";
        $response = "";
        switch (static::$urlMethod) {
            case "file_get_contents":
                $opts = array('http' =>
                    array(
                        'header' => 'User-Agent: wkAPI'
                    )
                );
                $context = stream_context_create($opts);
                $return = file_get_contents($url, false, $context);
                break;

            case "file":
                $opts = array('http' =>
                    array(
                        'header' => 'User-Agent: wkAPI'
                    )
                );
                $context = stream_context_create($opts);
                $content = file($url, false, $context);
                foreach ($content as $line) {
                    $return .= $line;
                }
                break;

            case "fsockopen":
                $components = parse_url($url);
                $fp = fsockopen($components['host'], 80, $errno, $errstr, 30);
                if (!$fp) {
                    return false;
                }
                $request = "GET " . $components ['path'] . (isset($components['query']) ? "?" . $components['query'] : "") . " HTTP/1.0\r\n";
                $request .= "Host: " . $components ['host'] . "\r\n";
                $request .= "User-Agent: wkAPI\r\n";
                $request .= "Connection: Close\r\n\r\n";
                fwrite($fp, $request);
                while (!feof($fp)) {
                    $response .= fgets($fp, 1024);
                }
                fclose($fp);
                $responseSplit = explode("\r\n\r\n", $response, 2);
                $return = $responseSplit[1];
                break;
        }

        return $return;
    }

    static private function postContents($url, $data)
    {
        $return = "";
        $response = "";
        switch (static::$urlMethod) {
            case "file_get_contents":
                $postdata = http_build_query($data);
                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded\r\nUser-Agent: wkAPI',
                        'content' => $postdata
                    )
                );
                $context = stream_context_create($opts);
                $return = file_get_contents($url, false, $context);
                break;

            case "file":
                $postdata = http_build_query($data);
                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded\r\nUser-Agent: wkAPI',
                        'content' => $postdata
                    )
                );
                $context = stream_context_create($opts);
                $result = file($url, false, $context);
                foreach ($result as $line) {
                    $return .= $line;
                }
                break;

            case "fsockopen":
                $components = parse_url($url);
                $postdata = http_build_query($data);
                $fp = fsockopen($components['host'], 80, $errno, $errstr, 30);
                if (!$fp) {
                    return false;
                }
                $request = "POST " . $components ['path'] . (isset($components['query']) ? "?" . $components['query'] : "") . " HTTP/1.0\r\n";
                $request .= "Host: " . $components ['host'] . "\r\n";
                $request .= "Content-type: application/x-www-form-urlencoded\r\n";
                $request .= "User-Agent: wkAPI\r\n";
                $request .= "Content-length: " . strlen($postdata) . "\r\n";
                $request .= "Connection: Close\r\n\r\n";
                $request .= $postdata;
                fwrite($fp, $request);
                while (!feof($fp)) {
                    $response .= fgets($fp, 1024);
                }
                fclose($fp);
                $responseSplit = explode("\r\n\r\n", $response, 2);
                $return = $responseSplit[1];
                break;
        }

        return $return;
    }

    static function pw2sid($password)
    {
        $sid = preg_replace("/[^a-zA-Z0-9.$]/", "", crypt($password, "88"));
        return $sid;
    }

    private function generateBaseURL($server, $cid)
    {
        return sprintf("server%d.webkicks.de/%s/", $server, $cid);
    }

    private function getApiURL()
    {
        return "https://" . $this->baseURL . "api/";
    }

    private function callWK($method, $data = false)
    {
        if (array_key_exists($method, $this->cache)) {
            return $this->cache[$method . $data];
        }

        if (empty($this->username) || empty($this->password) || $this->checkUser() === 0) {
            if (!$data) {
                return $this->cache[$method] = json_decode(utf8_encode(static::getContents($this->getApiURL() . "/{$method}")));
            } else {
                return $this->cache[$method] = json_decode(utf8_encode(static::getContents($this->getApiURL() . "/{$method}/{$data}")));
            }
        } else {
            if (!$data) {
                return $this->cache[$method] = json_decode(utf8_encode(static::getContents($this->getApiURL() . "/{$this->username}/{$this->password}/{$method}")));
            } else {
                return $this->cache[$method] = json_decode(utf8_encode(static::getContents($this->getApiURL() . "/{$this->username}/{$this->password}/{$method}/{$data}")));
            }
        }
    }

    public function getToplist()
    {
        if (array_key_exists(__METHOD__, $this->cache)) {
            return $this->cache[__METHOD__];
        }
        $toplist = json_decode(json_encode($this->callWK("get_toplist")), true);
        uasort($toplist, function ($a, $b) {
            if ($a["totalseconds"] == $b["totalseconds"]) {
                return 0;
            }
            return ($a["totalseconds"] < $b["totalseconds"] ? +1 : -1);

        });

        return $this->cache[__METHOD__] = json_decode(json_encode($toplist), false);
    }

    public function getReplacers()
    {
        return $this->callWK("get_replacers");
    }

    public function getSettings()
    {
        return $this->callWK("get_settings");
    }

    public function getUserdata($username)
    {
        return $this->callWK("get_userdata", $username);
    }

    public function getTeam()
    {
        return $this->callWK("get_teamlist_json");
    }

    public function getOnlineList()
    {
        return $this->callWK("get_onlinelist_json");
    }

    public function getAnnouncements()
    {
        return $this->callWK("get_announcements");
    }

    public function getAllUsers()
    {
        return $this->callWK("get_allusers");
    }

    public function getKickedUsers()
    {
        return $this->callWK("get_kickedusers");
    }

    public function getBannedUsers()
    {
        return $this->callWK("get_bannedusers");
    }

    public function getLockedUsers()
    {
        return $this->callWK("get_lockedusers");
    }

    public function getMutedUsers()
    {
        return $this->callWK("get_mutedusers");
    }

    public function getChannels()
    {
        return $this->callWK("get_channels");
    }

    public function getCmdLog()
    {
        return $this->callWK("get_cmdlog");
    }

    public function getRegLog()
    {
        return $this->callWK("get_reglog");
    }

    public function getInvalidPassLog()
    {
        return $this->callWK("get_invalidpasslog");
    }

    public function getDelLog()
    {
        return $this->callWK("get_dellog");
    }

    public function checkUser($username = false, $pw = false)
    {
        $username = $username ? $username : $this->username;
        $sid = $pw ? $this->pw2sid($pw) : $this->sid;
        if (!$username || !$sid) {
            return 0;
        }
        $response = static::getContents('http://server' . intval($this->server) . '.webkicks.de/' . $this->cid . '/index/' . strtolower($username) . '/' . $sid . '/start/main');
        if (preg_match('@Fehler: Timeout. Bitte neu einloggen.@is', $response)) {
            return 1; //Login korrekt, nicht eingeloggt
        }
        if (preg_match('@<title>Chat-Input</title>@is', $response)) {
            return 2; //Login korrekt, eingeloggt
        }
        return 0;
    }

    public function login($username = false, $password = false)
    {
        if ($username === false) {
            $username = $this->username;
        }
        if ($password === false) {
            $password = $this->password;
        }

        $server = $this->server;
        $cid = $this->cid;
        $data = array("cid" => $cid, "user" => $username, "pass" => $password, "job" => "ok");
        $lines = static::postContents("http://server{$server}.webkicks.de/{$cid}/", $data);
        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    //Loggt einen User aus

    public function logout($username = false, $password = false)
    {
        $this->sendeText("/exit", $username, $password);
    }

    //Lässt einen User einen Text senden
    public function sendeText($message, $username = false, $password = false)
    {
        if (!isset($message) || empty($message)) {
            return false;
        }
        if ($username === false) {
            $username = $this->username;
        }
        $sid = $password ? static::pw2sid($password) : $this->sid;
        $server = $this->server;
        $cid = $this->cid;
        if (!$username || !$sid) {
            return false;
        }
        $data = array("cid" => $cid, "user" => $username, "pass" => $sid, "message" => $message);
        static::postcontents("http://server{$server}.webkicks.de/cgi-bin/chat.cgi", $data);
        return true;
    }

    public function isHauptadmin($username)
    {
        return $this->getTeam()->hauptadmin == $username;
    }

    public function isAdmin($username)
    {
        return $this->isHauptadmin($username) || in_array($username, $this->getTeam()->admins);
    }

    public function isMod($username)
    {
        return in_array($username, $this->getTeam()->mods);
    }
}

