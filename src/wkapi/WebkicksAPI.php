<?php

namespace wkprojects\wkapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class WebkicksAPI {

    private string $server;
    private string $cid;
    private string $username;
    private string $password;
    private string $sid;

    private Client $httpClient;

    public function getServer(): string
    {
        return $this->server;
    }

    public function setServer($server)
    {
        $this->server = $server;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function setCid($cid)
    {
        $this->cid = $cid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function getSid(): string
    {
        return $this->sid;
    }

    public function setSid($sid)
    {
        $this->sid = $sid;
    }

    public function __construct(string $server = null, string $cid = null, string $username = null, string $password = null, string $sid = null)
    {
        if (is_null($server) || is_null($cid)) {
            throw new \Exception("At least server and cid are required");
        }

        $this->server = $server;
        $this->cid = $cid;
        $this->username = $username;
        $this->password = $password;

        $this->httpClient = new Client([
            'base_uri' => "https://{$server}.webkicks.de",
            'timeout'  => 10,
            'headers' => ['User-Agent' => 'wkAPI']
        ]);

        if (!is_null($sid)) {
            $this->sid = $sid;
        } else {
            $this->sid = $this->getApiSid()->sid;
        }

        try {
            $response = $this->httpClient->head("/{$cid}");
        } catch (ClientException $e) {
            throw new \Exception("", $e);
        }
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

    public function getApiSid()
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
    public function checkUser()
    {
        $username = !$username || !$password ? $this->username : $username;
        $sid = !$username || !$password ? $this->sid : static::pw2sid($password);

        $response = $this->httpClient->get("/{$this->cid}/index/{$this->username}/{$this->sid}/start/main")->getBody();
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
    public function login()
    {
        $username = !$username || !$password ? $this->username : $username;
        $password = !$username || !$password ? $this->password : $password;

        $data = ["cid" => $this->cid, "user" => $username, "pass" => $password, "job" => "ok"];
        $lines = $this->httpClient->post("/{$this->cid}", ['form_params' => $data])->getBody();

        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    /*
     * Loggt einen User aus, entweder den im Objekt hinterlegten, oder einen durch Username und Passwort
     * identifizierten.
     */
    public function logout()
    {
        $this->sendeText("/exit");
    }

    /*
     * Lässt einen User, entweder den im Objekt hinterlegten, oder einen durch Username und Passwort identifizierten,
     * einen Text senden.
     */
    public function sendeText($message)
    {
        if (empty($message)) {
            return false;
        }

        $data = ["cid" => $this->cid, "user" => $this->username, "pass" => $this->sid, "message" => $message];
        $this->httpClient->post("/cgi-bin/chat.cgi", ['form_params' => $data]);
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
