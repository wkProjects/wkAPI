<?php

/**
 * Created by PhpStorm.
 * User: Bastian
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
    private $urlMethod;

    function __construct()
    {
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this, $f = '__construct' . $i)) {
            $this->urlMethod = $this->chooseURLMethod();
            if ($this->urlMethod === false) {
                throw new Exception("Es konnte keine Methode gefunden werden, HTTP-Anfragen zu stellen.");
            }
            call_user_func_array(array($this, $f), $a);
        } else {
            throw new Exception("Ungültige Anzahl an Argumenten.");
        }
    }

    private function chooseURLMethod()
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

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param mixed $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @return mixed
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * @param mixed $cid
     */
    public function setCid($cid)
    {
        $this->cid = $cid;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
        $this->sid = $this->pw2sid($password);
    }

    static function pw2sid($password)
    {
        $sid = preg_replace("/[^a-zA-Z0-9.$]/", "", crypt($password, "88"));
        return $sid;
    }

    function __construct2($server, $cid)
    {
        $this->server = intval($server);
        $this->cid = $cid;
        $this->baseURL = $this->generateBaseURL($server, $cid);

        $headers = get_headers($this->baseURL, 1);
        if ($headers[0] !== "HTTP/1.1 200 OK") {
            throw new Exception("Chat nicht gefunden.");
        }

    }

    private function generateBaseURL($server, $cid)
    {
        return sprintf("http://server%d.webkicks.de/%s/", $server, $cid);
    }

    function __construct4($server, $cid, $username, $password)
    {
        __construct2($server, $cid);
        $this->username = $username;
        $this->password = $password;
        $this->sid = $this::pw2sid($password);
    }

    public function getReplacers()
    {
        return $this->callWK("get_replacers");
    }

    private function callWK($method, $data = false)
    {
        if (!$data) {
            return json_decode(utf8_encode($this->getContents($this->getApiURL() . "/{$this->username}/{$this->password}/{$method}")));
        } else {
            return json_decode(utf8_encode($this->getContents($this->getApiURL() . "/{$this->username}/{$this->password}/{$method}/{$data}")));
        }
    }

    private function getContents($url)
    {
        $return = "";
        $response = "";
        switch ($this->urlMethod) {
            case "file_get_contents":
                $return = file_get_contents($url);
                break;

            case "file":
                $content = file($url);
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

    private function getApiURL()
    {
        return $this->baseURL . "api/";
    }

    public function getToplist()
    {
        $toplist = json_decode(json_encode($this->callWK("get_toplist")), true);
        uasort($toplist, function ($a, $b) {
            if ($a["totalseconds"] == $b["totalseconds"]) {
                return 0;
            }
            return ($a["totalseconds"] < $b["totalseconds"] ? +1 : -1);

        });
        return json_decode(json_encode($toplist), false);
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
        $sid = $pw ? $this->toSid($pw) : $this->sid;
        if (!$username || !$sid) {
            return false;
        }
        $response = $this->getContents('http://server' . intval($this->server) . '.webkicks.de/' . $this->cid . '/index/' . strtolower($username) . '/' . $sid . '/start/main');
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
        $lines = $this->postContents("http://server$server.webkicks.de/$cid/", $data);
        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    private function postContents($url, $data)
    {
        $return = "";
        $response = "";
        switch ($this->urlMethod) {
            case "file_get_contents":
                $postdata = http_build_query($data);
                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
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
                        'header' => 'Content-type: application/x-www-form-urlencoded',
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

    //Loggt einen User aus

    public function logout($username = false, $password = false)
    {
        $this->sendeText("/exit", $username, $password);
    }

    //Laesst einen User einen Text senden
    public function sendeText($message, $username = false, $password = false)
    {
        if (!isset($message) || empty($message)) {
            return false;
        }
        if ($username === false) {
            $username = $this->username;
        }
        $sid = $password ? $this::pw2sid($password) : $this->sid;
        $server = $this->server;
        $cid = $this->cid;
        if (!$username || !$sid) {
            return false;
        }
        $data = array("cid" => $cid, "user" => $username, "pass" => $sid, "message" => $message);
        $fp = fsockopen("server$server.webkicks.de", 80, $errno, $errstr, 30);
        $this->postcontents("http://server$server.webkicks.de/cgi-bin/chat.cgi", $data);
        return true;
    }
}
