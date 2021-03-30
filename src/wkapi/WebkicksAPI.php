<?php

namespace wkprojects\wkapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class WebkicksAPI {
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
    private Client $httpClient;


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
            call_user_func_array(array($this, $f), $a);
        } else {
            throw new \Exception("no constructor found for arguments");
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
        $this->httpClient = new Client([
            'base_uri' => "https://server{$server}.webkicks.de",
            'timeout'  => 10,
            'headers' => ['User-Agent' => 'wkAPI']
        ]);

        try {
        $response = $this->httpClient->head("/{$cid}/");
        } catch (ClientException $e) {
            throw new \Exception("Chat nicht gefunden.", $e);
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
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode($this->httpClient->get("/{$this->getCid()}/api/{$method}")->getBody()));
            } else {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode($this->httpClient->get("/{$this->getCid()}/api/{$method}/{$data}")->getBody()));
            }
        } else {
            if (!$data) {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode($this->httpClient->get("/{$this->getCid()}/api/{$this->username}/{$this->password}/{$method}")->getBody()));
            } else {
                return $this->cache[$method . "|" . $data . "|" . $adminRequest] = json_decode(utf8_encode($this->httpClient->get("/{$this->getCid()}/api/{$this->username}/{$this->password}/{$method}/{$data}")->getBody()));
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
    
    public function getSid()
    {
        return $this->callWK("get_sid");
    }

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

        $response = $this->httpClient->get($this->baseURL . "index/{$username}/{$sid}/start/main")->getBody();
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
        $lines = $this->httpClient->post($this->baseURL, ['form_params' => $data])->getBody();

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
        $this->httpClient->post("https://server{$this->server}.webkicks.de/cgi-bin/chat.cgi", ['form_params' => $data]);
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