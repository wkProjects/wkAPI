<?php

namespace wkprojects\wkapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;

class Chat
{

    private string $server;
    private string $cid;
    private ?string $username;
    private ?string $password;
    private ?string $sid;

    private Client $httpClient;
    private \stdClass $teamlist;

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

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
        $this->sid = null;
    }

    public function getSid(): ?string
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
            throw new \Exception("The chat could not be found", 0, $e);
        }
    }

    public function callWK($method, $parameter = null, bool $with_credentials = true)
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
        return json_decode($wkResponse->getBody());
    }

    public function getToplist($asAdmin = true)
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

    public function getApiSid()
    {
        return $this->callWK("get_sid")->sid;
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
        if (is_null($this->sid)) {
            $this->sid = $this->getApiSid();
        }
        $response = $this->httpClient->get("/{$this->cid}/index/{$this->username}/{$this->sid}/start/main")->getBody();
        if (preg_match('@Falscher Benutzername@is', $response)) {
            return Constants::USER_NOT_FOUND;
        }
        if (preg_match('@pass_remind@is', $response)) {
            return Constants::USER_CREDENTIALS_INCORRECT;
        }
        if (preg_match('@Fehler: Timeout. Bitte neu einloggen.@is', $response)) {
            return Constants::USER_CREDENTIALS_CORRECT;
        }
        if (preg_match('@<title>Chat-Input</title>@is', $response)) {
            return Constants::USER_CREDENTIALS_CORRECT_AND_LOGGED_IN;
        }      
    }

    public function login()
    {
        $data = ["cid" => $this->cid, "user" => $this->username, "pass" => $this->password, "job" => "ok"];
        $lines = $this->httpClient->post("/{$this->cid}/", ['form_params' => $data])->getBody();

        if (preg_match("/Fehler:/", $lines) == 1) {
            return false;
        }
        return true;
    }

    public function logout()
    {
        $this->sendMessage("/exit");
    }

    public function sendMessage($message)
    {
        if (empty($message)) {
            return false;
        }
        if (is_null($this->sid)) {
            $this->sid = $this->getApiSid();
        }
        $data = [
            "cid" => $this->cid,
            "user" => $this->username,
            "pass" => $this->sid,
            "message" => utf8_decode($message)
        ];
        $this->httpClient->post("/cgi-bin/chat.cgi", ['form_params' => $data]);
        return true;
    }

    public function isFounder($username)
    {
        $this->teamlist ??= $this->getTeam();
        return strtolower($this->teamlist->founder) === strtolower($username);
    }

    public function hasAdminPrivileges($username)
    {
        $this->teamlist ??= $this->getTeam();
        return $this->isFounder($username) || in_array(strtolower($username), array_map('strtolower', $this->teamlist->admins));
    }

    public function hasModPrivileges($username)
    {
        $this->teamlist ??= $this->getTeam();
        return $this->hasAdminPrivileges(strtolower($username)) || in_array(strtolower($username), array_map('strtolower', $this->teamlist->mods));
    }
}
