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
 */
class wkAPI
{
    /*
     * Basis-Informationen zum Chat
     */
    private $server;
    private $cid;
    private $username;
    private $password;
    private $sid;

    /*
     * Hilfsvariablen für die API
     */
    private $cache = array();
    private $baseURL;
    private $apiURL;
    private static $urlMethod;


    /*
     * Getter und Setter für die Basis-Informationen
     */
    public function getServer()
    {
        return $this->server;
    }

    public function setServer($server)
    {
        $this->server = intval($server);
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

    /*
     * Dummy-Konstruktur, zählt nur die Argumente und ruft dann __construct0, __construct2 oder __construct4 auf
     */
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

    /*
     * Dummy-Konstruktor, nur erforderlich, um die Funktion der API ohne Angabe eines Chats zu testen.
     */
    private function __construct0(){

    }

    /*
     * Konstruktur für die Initialisierung mit Server und Chatname
     */
    private function __construct2($server, $cid)
    {
        $this->server = intval($server);
        $this->cid = $cid;
        $this->apiURL = "https://server{$server}.webkicks.de/{$cid}/api/";
        $headers = get_headers("https://server{$server}.webkicks.de/{$cid}/", 1);

        if (strpos($headers[0], "200") !== false) {
            $this->baseURL = "https://server{$server}.webkicks.de/{$cid}/";
        } elseif (strpos($headers[0], "307") !== false) {
            $this->baseURL = "http://server{$server}.webkicks.de/{$cid}/";
        } else {
            throw new Exception("Chat nicht gefunden.");
        }
    }

    /*
     * Konstruktor für die Initialisierung mit Server, Chatname, Benutzername und Passwort
     */
    private function __construct4($server, $cid, $username, $password)
    {
        $this->__construct2($server, $cid);
        $this->username = $username;
        $this->password = $password;
        $this->sid = static::pw2sid($password);
    }

    /*
     * Hilfsmethode, ermittelt, welche Methoden zum URL-Aufruf verwendet werden können:
     *
     * Nur mit allow_url_fopen:
     * file_get_contents
     * file
     *
     * Ansonsten:
     * fsockopen
     */
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

    /*
     * Hilfsfunktion, ruft Inhalte einer URL ab
     */
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

    /*
     * Hilfsfunktion, sendet Daten an eine URL und gibt die Antwort zurück
     */
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

    /*
     * Hilfsfunktion, berechnet die SID aus dem Passwort
     */
    static function pw2sid($password)
    {
        $sid = preg_replace("/[^a-zA-Z0-9.$]/", "", crypt($password, "88"));
        return $sid;
    }

    /*
     * Hilfsfunktion, ruft alle von Webkicks bereitgestellten APIs auf und gibt die resultierenden Objekte zurück
     */
    private function callWK($method, $data = false, $adminRequest = true)
    {
        if (array_key_exists($method, $this->cache)) {
            return $this->cache[$method . "|" . $data . "|" . $adminRequest];
        }

        if ($adminRequest !== true || empty($this->username) || empty($this->password)) {
            if (!$data) {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode(static::getContents($this->apiURL . "{$method}")));
            } else {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode(static::getContents($this->apiURL . "{$method}/{$data}")));
            }
        } else {
            if (!$data) {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode(static::getContents($this->apiURL . "{$this->username}/{$this->password}/{$method}")));
            } else {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode(static::getContents($this->apiURL . "{$this->username}/{$this->password}/{$method}/{$data}")));
            }
        }
    }

    /*
     * Ruft die Topliste ab
     *
     * Wenn $asAdmin auf false steht, wird die Anfrage an die öffentliche Topliste gestellt, ansonsten wird die im Admin-
     * Menü abrufbare Toplist abgefragt. Dazu sind natürlich gültige Admin-Daten erforderlich.
     * Außerdem wird die Topliste hier absteigend nach Zeit sortiert.
     */
    public function getToplist($asAdmin = true)
    {
        if (array_key_exists(__METHOD__, $this->cache)) {
            return $this->cache[__METHOD__];
        }

        $toplist = json_decode(json_encode($this->callWK("get_toplist", false, $asAdmin)), true);
        uasort($toplist, function ($a, $b) {
            if ($a["totalseconds"] == $b["totalseconds"]) {
                return 0;
            }
            return ($a["totalseconds"] < $b["totalseconds"] ? +1 : -1);

        });

        return $this->cache[__METHOD__] = json_decode(json_encode($toplist), false);
    }

    /*
     * Die folgenden Funktionen sind nur Wrapper für die Webkicks-APIs und geben das empfangene JSON schlicht als Objekt
     * zurück.
     */
    public function getReplacers()
    {
        return $this->callWK("get_replacers");
    }

    public function getSettings()
    {
        return $this->callWK("get_settings");
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

    /*
     * Diese Funktion erwartet noch einen Benutzernamen, über den die Infos aus der Datenbank geholt werden sollen.
     */
    public function getUserdata($username)
    {
        return $this->callWK("get_userdata", $username);
    }

    /*
     * checkUser prüft Logindaten (entweder die im Objekt hinterlegten oder die für diese Methode übergebenen) auf ihre
     * Gültigkeit.
     *
     * Rückgabewerte:
     * 0 = Logindaten sind nicht gültig, User gekickt o.ä.
     * 1 = Logindaten sind korrekt, der User ist aber nicht eingeloggt
     * 2 = Logindaten sind korrekt, außerdem ist der User eingeloggt.
     */
    public function checkUser($username = false, $password = false)
    {
        $username = !$username || !$password ? $this->username : $username;
        $sid = !$username || !$password ? $this->sid : static::pw2sid($password);

        $response = static::getContents($this->baseURL . "index/{$username}/{$sid}/start/main");
        if (preg_match('@Fehler: Timeout. Bitte neu einloggen.@is', $response)) {
            return 1;
        }
        if (preg_match('@<title>Chat-Input</title>@is', $response)) {
            return 2;
        }
        return 0;
    }

    /*
     * Loggt einen User ein, entweder den im Objekt hinterlegten, oder einen durch Username und Passwort
     * identifizierten.
     */
    public function login($username = false, $password = false)
    {
        $username = !$username || !$password ? $this->username : $username;
        $password = !$username || !$password ? $this->password : $password;

        $data = array("cid" => $this->cid, "user" => $username, "pass" => $password, "job" => "ok");
        $lines = static::postContents($this->baseURL, $data);
        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    /*
     * Loggt einen User aus, entweder den im Objekt hinterlegten, oder einen durch Username und Passwort
     * identifizierten.
     */
    public function logout($username = false, $password = false)
    {
        $this->sendeText("/exit", $username, $password);
    }

    /*
     * Lässt einen User, entweder den im Objekt hinterlegten, oder einen durch Username und Passwort identifizierten,
     * einen Text senden.
     */
    public function sendeText($message, $username = false, $password = false)
    {
        if (!isset($message) || empty($message)) {
            return false;
        }
        $username = !$username || !$password ? $this->username : $username;
        $sid = !$username || !$password ? $this->sid : static::pw2sid($password);
        $data = array("cid" => $this->cid, "user" => $username, "pass" => $sid, "message" => $message);
        static::postcontents($this->baseURL ."cgi-bin/chat.cgi", $data);
        return true;
    }

    /*
     * Methoden, um den Rang eines Users abzufragen. isAdmin gibt dabei auch für Hauptadmins true zurück
     */
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

