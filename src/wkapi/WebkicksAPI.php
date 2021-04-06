<?php

namespace wkprojects\wkapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;

class WebkicksAPI
{

    private string $server;
    private string $cid;
    private ?string $username;
    private ?string $password;
    private ?string $sid;

    private Client $httpClient;

    public function getServer(): string
    {
        return $this->server;
    }

    public function setServer(string $server)
    {
        $this->server = $server;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function setCid(string $cid)
    {
        $this->cid = $cid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    public function getSid(): string
    {
        return $this->sid;
    }

    public function setSid(string $sid)
    {
        $this->sid = $sid;
    }

    public function __construct(
        string $server = null,
        string $cid = null,
        string $username = null,
        string $password = null,
        string $sid = null
    ) {
        if (is_null($server) || is_null($cid)) {
            throw new \Exception("At least server and cid are required");
        }

        $this->server = $server;
        $this->cid = $cid;
        $this->username = $username;
        $this->password = $password;
        $this->sid = $sid;

        $this->httpClient = new Client([
            'base_uri' => "https://{$server}.webkicks.de",
            'timeout'  => 10,
            'headers' => ['User-Agent' => 'wkAPI']
        ]);

        try {
            $this->httpClient->head("/{$cid}");
        } catch (ClientException $e) {
            throw new \Exception("The chat could not be found", $e);
        }
    }

    public function callWK($method, bool $with_credentials = true, $parameter = null)
    {
        $wkResponse = null;
        $postData = [
            'job' => $method,
            'cid' => $this->getCid()
        ];
        if ($with_credentials === true) {
            $postData['user'] = $this->username;
            $postData['pass'] = $this->password;
        }
        if (!is_null($parameter)) {
            $postData['message'] = $parameter;
        }
        try {
            $wkResponse = $this->httpClient->post("/{$this->getCid()}/api", ['form_params' => $postData]);
        } catch (TransferException  $e) {
            throw new \Exception("The API call failed", $e);
        }
        return json_decode(utf8_encode($wkResponse->getBody()));
    }

    public function getToplist($asAdmin = true)
    {
        $toplist = json_decode(json_encode($this->callWK("get_toplist", $asAdmin)), true);
        uasort($toplist, function ($a, $b) {
            if ($a["totalseconds"] == $b["totalseconds"]) {
                return 0;
            }
            return ($a["totalseconds"] < $b["totalseconds"] ? +1 : -1);
        });

        json_decode(json_encode($toplist), false);
    }

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
        $teamlist = $this->callWK("get_teamlist_json");
        $teamlist->founder = $teamlist->hauptadmin;
        unset($teamlist->hauptadmin);
        return $teamlist;
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

    public function getUserdata($username)
    {
        return $this->callWK("get_userdata", $username);
    }

    public function checkUser()
    {
        $response = $this->httpClient->get("/{$this->cid}/index/{$this->username}/{$this->sid}/start/main")->getBody();
        if (preg_match('@Fehler: Timeout. Bitte neu einloggen.@is', $response)) {
            return 1;
        }
        if (preg_match('@<title>Chat-Input</title>@is', $response)) {
            return 2;
        }
        return 0;
    }

    public function login()
    {
        $data = ["cid" => $this->cid, "user" => $this->username, "pass" => $this->password, "job" => "ok"];
        $lines = $this->httpClient->post("/{$this->cid}", ['form_params' => $data])->getBody();

        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    public function logout()
    {
        $this->sendeText("/exit");
    }

    public function sendeText($message)
    {
        if (empty($message)) {
            return false;
        }

        $data = ["cid" => $this->cid, "user" => $this->username, "pass" => $this->sid, "message" => $message];
        $this->httpClient->post("/cgi-bin/chat.cgi", ['form_params' => $data]);
        return true;
    }

    public function isFounder($username)
    {
        return $this->getTeam()->founder === $username;
    }

    public function hasAdminPrivileges($username)
    {
        return $this->isFounder($username) || in_array($username, $this->getTeam()->admins);
    }

    public function hasModPrivileges($username)
    {
        return in_array($username, $this->getTeam()->mods);
    }
}
