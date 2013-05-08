<?php
// Webkicks API Klasse
// Version vom 24.03.2012
// Klasse programmiert von Kiba (Wolfspirit)
// erweitert durch DG (1. FC Keller) und Linus
// https://wkprojects.org
class Webkicks{
    var $cid;
    var $server;
    var $username;
    var $pw;
    var $sid;
    function Webkicks($cid, $server, $username=false, $pw=false, $sid=false){
        //Beim Anlegen des Objekts wird keine Gueltigkeitspruefung durchgefuert. Dies muss ggf. anhand der gegebenen Methoden manuell gemacht werden.
        $this->cid=$cid;
        $this->server=intval($server);
        $this->username=$username;
        $this->pw=$pw;
        $this->sid=$sid?$sid:($pw?$this->pw2sid($pw):false);
        $this->modes=array();
        $this->allow_url_fopen = ini_get("allow_url_fopen");
        $this->disabled_functions = explode(",",ini_get("disable_functions"));
        if (!in_array("fsockopen",$this->disabled_functions)){
            $this->modes[] = "fsock";  //fsockopen() kann benutzt werden
        }
        if ($this->allow_url_fopen==true && (!in_array("file_get_contents",$this->disabled_functions) || !in_array("file",$this->disabled_functions))){
            if (!in_array("file",$this->disabled_functions)){
                $this->modes[] = "file";  //file() kann benutzt werden
            }
            if (!in_array("file_get_contents",$this->disabled_functions)){
                $this->modes[] = "fgc";  //file_get_contents() kann benutzt werden
            }
        }
        if (count($this->modes)===0){die("Leider kannst du die API nicht benutzen! Bitte frage bei deinem Hoster nach, ob er für dich allow_url_fopen auf On setzen kann oder ob er die Funktion fsockopen freigibt :-)");}
    }
        
    function getcontents($url){
        $retval=false;
        if (in_array("fgc",$this->modes)){
            $retval = file_get_contents($url);
        }elseif (in_array("file",$this->modes)){
            $content = file($url);
            foreach ($content as $line){
                $retval.=$line;
            }
        }elseif (in_array("fsock",$this->modes)){
            $components = parse_url($url);
            $fp = fsockopen($components['host'], 80, $errno, $errstr, 30);  
            if (!$fp){  
                return false;
            }  
            $request = "GET ".$components ['path'].(isset($components['query'])?"?".$components['query']:"")." HTTP/1.0\r\n";  
            $request .= "Host: ".$components ['host']."\r\n";  
            $request .= "User-Agent: wkAPI\r\n";
            $request .= "Connection: Close\r\n\r\n";
            fwrite ($fp, $request);  
            while (!feof($fp)){  
                $response .= fgets($fp,1024);  
            }  
            fclose($fp);  
            $responseSplit = explode("\r\n\r\n",$response,2);  
            $retval = $responseSplit[1];  
        }
        return $retval;
    }
    function postcontents($url,$data){
        $retval=false;
        if (in_array("fgc",$this->modes)){
            $postdata = http_build_query($data);
            $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
            );
            $context  = stream_context_create($opts);
            $retval = file_get_contents($url, false, $context);
        }elseif (in_array("file",$this->modes)){
            $postdata = http_build_query($data);
            $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
            );
            $context  = stream_context_create($opts);
            $result = file($url, false, $context);
            foreach ($result as $line){
                $retval.=$line;
            }
        }elseif (in_array("fsock",$this->modes)){
            $components = parse_url($url);
            $postdata = http_build_query($data);
            $fp = fsockopen($components['host'], 80, $errno, $errstr, 30);  
            if (!$fp){  
                return false;
            }  
            $request = "POST ".$components ['path'].(isset($components['query'])?"?".$components['query']:"")." HTTP/1.0\r\n";  
            $request .= "Host: ".$components ['host']."\r\n"; 
            $request .= "Content-type: application/x-www-form-urlencoded\r\n";
            $request .= "User-Agent: wkAPI\r\n";
            $request .= "Content-length: ".strlen($postdata)."\r\n";
            $request .= "Connection: Close\r\n\r\n"; 
            $request .= $postdata;
            fwrite ($fp, $request);  
            while (!feof($fp)){  
                $response .= fgets($fp,1024);  
            }  
            fclose($fp);  
            $responseSplit = explode("\r\n\r\n",$response,2);  
            $retval = $responseSplit[1];  
        }
        return $retval;
    }
   
    //Wandelt PW in SID um
    function pw2sid($password){
        $sid = preg_replace("/[^a-zA-Z0-9.$]/", "", crypt($password,"88"));
        return $sid;
    }
    //Gibt SID zurueck, egal ob PW oder SID angegeben wurde (Unsicherheit: PW kann auch wie SID aussehen!)
    function toSid($password){
        $sid = (strlen($password)<=13 && strlen($password)>=11 && substr($password,0,2)=="88")?$password:$this->pw2sid($password);
        return $sid;
    }
    //Prueft, ob die Logindaten eines Users korrekt sind
    function checkuser($username=false, $pw=false){
        $username = $username?$username:$this->username;
        $sid = $pw?$this->toSid($pw):$this->sid;
        if(!$username || !$sid){
            return false;
        }
        if (isset($this->Logindaten[$username][$pw])){
            return $this->Logindaten[$username][$pw];
        }
        $this->Logindaten[$username][$pw]=0;
        $file=$this->getcontents('http://server'.intval($this->server).'.webkicks.de/'.$this->cid.'/index/'.strtolower($username).'/'.$sid.'/start/main');
        if(preg_match('@Fehler: Timeout. Bitte neu einloggen.@is',$file)){
            $this->Logindaten[$username][$pw]=1; //Login korrekt, nicht eingeloggt
        }
        if(preg_match('@<title>Chat-Input</title>@is',$file)){
            $this->Logindaten[$username][$pw]=2; //Login korrekt, eingeloggt
        }
        return $this->Logindaten[$username][$pw];
    }
        
 
    //Prueft, ob der User Hauptadmin ist
    function isHauptadmin($nick){
        $nick = strtolower($nick);
        if (isset($this->hadmin[$nick])){
            return $this->hadmin[$nick];
        }
        $team = $this->getTeam();
        $this->hadmin[$nick] = false;
        if(strtolower($team[0][0])==$nick){
            //ist $nick der Hauptadmin?
            $this->hadmin[$nick] = true;
        }
        return $this->hadmin[$nick];
    }
    
    //Prueft, ob der User Admin ist
    function isAdmin($nick){
        $nick = strtolower($nick);
        if (isset($this->admin[$nick])){
            return $this->admin[$nick];
        }
        $team = $this->getTeam();
        $this->admin[$nick] = false;
        if(strtolower($team[0][0])==$nick){
            //ist $nick der Hauptadmin?
            $this->admin[$nick] = true;
        }else{
            //ist $nick ein normaler Admin?
            $this->admin[$nick] = in_array($nick, array_map('strtolower', $team[1]));
        }
        return $this->admin[$nick];
    }
    //Prueft, ob der User Mod ist
    function isMod($nick){
        $nick = strtolower($nick);
        if (isset($this->mod[$nick])){
            return $this->mod[$nick];
        }
        $team = $this->getTeam();
        $this->mod[$nick] = in_array($nick, array_map('strtolower', $team[2]));
        return $this->mod[$nick];
    }
    //Ermittelt die Daten eines Users aus dem Admin-Menue
    function getDetails($nick, $adminname=false, $pw=false){
        if (isset($this->Details[$nick])){
            return $this->Details[$nick];
        }
        $adminname = $adminname?$adminname:$this->username;
        $pw = $pw?$pw:$this->pw;
        $cid = $this->cid;
        $server = intval($this->server);
        $data = $this->getcontents("http://server$server.webkicks.de/$cid/api/$adminname/$pw/get_userdata/$nick");
		echo "http://server$server.webkicks.de/$cid/api/$adminname/$pw/get_userdata/$nick";
        $this->Details[$nick] = json_decode($data, true);
		echo json_last_error();
        return $this->Details[$nick];
    }
    //Ermittelt die Ankuendigungen des Chats
    function getAnkuendigungen($adminname=false, $sid=false){
        if (isset($this->Ankuendigungen)){
            return $this->Ankuendigungen;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        if(!$adminname || !$sid){
            return false;
        }
        $adminname = strtolower($adminname);
        $data=array("cid" => $cid, "user" => $adminname, "pass" => $sid);
        $announce = $this->postcontents("http://server$server.webkicks.de/$cid/announce",$data);
        $check=$cm_check=$am_check=$gm_check=false;
        $cm_box=$am_box=$gm_box=0;
        $cm_feld=$am_feld=$gm_feld="";
        preg_match_all('/<textarea class="input" name="(?:c|a|g)m_feld" rows="6" cols="66">(.+?)<\/textarea>/si',$announce,$rawdata);
        $cm_feld = trim($rawdata[1][0]);
        $am_feld = trim($rawdata[1][1]);
        $gm_feld = trim($rawdata[1][2]);
        preg_match_all('/<input type="checkbox" name="(?:c|a|g)m_box" value="checkbox" onClick="(?:c|a|g)M_Dis\(\)"\s*(checked)?>/si',$announce,$rawdata2);
        $cm_box = ($rawdata2[1][0]=="checked")?1:0;
        $am_box = ($rawdata2[1][1]=="checked")?1:0;
        $gm_box = ($rawdata2[1][2]=="checked")?1:0;
        $this->Ankuendigungen = array($cm_box, $cm_feld, $am_box, $am_feld, $gm_box, $gm_feld);
        return $this->Ankuendigungen;
    }
   
    //Ermittelt die Replacerliste
    function getReplacers(){
        if (isset($this->Replacers)){
            return $this->Replacers;
        }
        $cid = $this->cid;
        $server = intval($this->server);
        $rlist = $this->getcontents("http://server$server.webkicks.de/$cid/api/get_replacers");
        $replacers = json_decode($rlist, true);
        $this->Replacers = $replacers;
        return $this->Replacers;
    }
    
    //Ermittelt die Topliste
    function getToplist($adminname=false, $sid=false){
        if (isset($this->Topliste)){
            return $this->Topliste;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $toplist=$this->getcontents("http://server$server.webkicks.de/$cid/api/get_toplist");
        $toplist = json_decode($toplist, true);
        $this->Topliste = $toplist;
        return $this->Topliste;
    }
    
    //Ermittelt das Chat-Team
    function getTeam(){
        if (isset($this->Teamliste)){
            return $this->Teamliste;
        }
        $team_raw=$this->getcontents("http://server".$this->server.".webkicks.de/".$this->cid."/api/get_teamlist");
        preg_match_all("%<hauptadmin>([a-zA-Z0-9_]+)</hauptadmin>%i",$team_raw,$hauptadmin_raw);
        preg_match_all("%<admin>([a-zA-Z0-9_]+)</admin>%i",$team_raw,$admins_raw);
        preg_match_all("%<mod>([a-zA-Z0-9_]+)</mod>%i",$team_raw,$mods_raw);
        natcasesort($admins_raw[1]);
        natcasesort($mods_raw[1]);
        $this->Teamliste=array($hauptadmin_raw[1],$admins_raw[1],$mods_raw[1]);
        return $this->Teamliste;
    }
    //Ermittelt alle User
    function getAllUsers($adminname=false, $sid=false){
        if (isset($this->Userliste)){
            return $this->Userliste;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/stats/$adminname/$sid/all");
        preg_match_all("/(\w+)<\/li>\s*/",$overview,$auArray);
        $this->Userliste = $auArray[1];
        return $this->Userliste;
    }
   
    //Ermittelt die gekickten User
    function getKickedUsers($adminname=false, $sid=false){
        if (isset($this->Gekickt)){
            return $this->Gekickt;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/stats/$adminname/$sid/kicked");
        preg_match_all("/(\w+)<\/li>\s*/",$overview,$kuArray);
        $this->Gekickt = $kuArray[1];
        return $this->Gekickt;
    }
    //Ermittelt die gebannten User
    function getBannedUsers($adminname=false, $sid=false){
        if (isset($this->Gebannt)){
            return $this->Gebannt;
        }
        $banned=array();
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/stats/$adminname/$sid/banned");
        preg_match_all("/<td><li>([0-9\.]{5,10},.[0-9:]{2,5}):\s+<\/li><\/td>\s+<td width=\"120\">\s+<div align=\"center\">([0-9\.]+)<\/div>\s+<\/td>\s+<td>([a-z0-9A-Z_]{2,20})<\/td>/im",$overview,$buArray);
        $i=0;
        array_shift($buArray);
        $times=$buArray[0];
        $names=$buArray[1];
        $ips=$buArray[2];
        for ($i=0;$i<count($times);$i++){
            $banned[$i]=array($times[$i],$names[$i],$ips[$i]);
        }
        $this->Gebannt = $banned;
        return $this->Gebannt;
    }
   
    //Ermittelt die geknebelten User
    function getMutedUsers($adminname=false, $sid=false){
        if (isset($this->Geknebelt)){
            return $this->Geknebelt;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/stats/$adminname/$sid/banned");
        preg_match_all("/(\w+)<\/li>\s*/",$overview,$muArray);
        $this->Geknebelt = $muArray[1];
        return $this->Geknebelt;
    }
   
    //Ermittelt die nicht freigeschalteten User
    function getLockedUsers($adminname=false, $sid=false){
        if (isset($this->Mailpause)){
            return $this->Mailpause;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/stats/$adminname/$sid/wait");
        preg_match_all("/(\w+)<\/li>\s*/",$overview,$luArray);
        $this->Mailpause = $luArray[1];
        return $this->Mailpause;
    }
   
    //Ermittelt alle Raeume
    function getRooms($adminname=false, $sid=false){
        if (isset($this->Raeume)){
            return $this->Raeume;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $server = intval($this->server);
        $cid = $this->cid;
        if(!$adminname || !$sid){
            $this->Raeume = false;
        }
        $raeume = array();
        $adminname = strtolower($adminname);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/chanconf/$adminname/$sid");
        preg_match_all("/<tr>\s+<td>([0-9a-zA-Z_]+)<\/td>/si",$overview,$rawrooms);
        $this->Raeume = $rawrooms[1];
        return $this->Raeume;
    }
    
    //Loggt einen User ein
    function login($username=false, $pw=false){
        $username = $username?$username:$this->username;
        $pw = $pw?$pw:$this->pw;
        if(!$pw){
            return false;
        }
        $server = $this->server;
        $cid = $this->cid;
        $data = array("cid" => $cid, "user" => $username, "pass" => $pw, "job" => "ok");
        $lines = $this->postcontents("http://server$server.webkicks.de/$cid/",$data);
        if (preg_match("/Fehler:/",$lines)==1){
            return false;
        }
        return true;
    }
   
    //Loggt einen User aus
    function logout($username=false, $sid=false){
        $this->sendeText("/exit", $username, $sid);
    }
    //Laesst einen User einen Text senden
    function sendeText($message, $username=false, $sid=false){
        if(!isset($message) || empty($message)){
            return false;
        }
        $username = $username?$username:$this->username;
        $sid = $sid?$sid:$this->sid;
        $server = $this->server;
        $cid = $this->cid;
        if(!$username || !$sid){
            return false;
        }
        $data = array("cid" => $cid, "user" => $username, "pass" => $sid, "message" => $message);
        $fp = fsockopen("server$server.webkicks.de", 80, $errno, $errstr, 30);
        $this->postcontents("http://server$server.webkicks.de/cgi-bin/chat.cgi",$data);
        return true;
    }
   
    //Ermittelt alle User, die derzeit online sind
    function getOnlineUsers($raumn=0){
        if (isset($this->Online[$raumn])){
            return $this->Online[$raumn];
        }
        $server = $this->server;
        $cid = $this->cid;
        if($raumn && $raumn != 0){
            $raum = strToLower($raum)=="hauptchat"?"main":$raum;
            $raum = "&raum=$raum";
        }
        $ol=$this->getcontents("http://server$server.webkicks.de/cgi-bin/raw.cgi?cid=$cid$raum");
        preg_match_all("/\(([^\)]+)\)/", $ol, $result);
        $this->Online[$raumn] = $result[1];
        return $this->Online[$raumn];
    }
    
    //Erweiterte Onlineliste
    function getExtendedOnlinelist(){
        if (isset($this->EOnline)){
            return $this->EOnline;
        }
        $server = $this->server;
        $cid = $this->cid;
        $ol=$this->getcontents("http://server$server.webkicks.de/$cid/api/get_onlinelist");
        preg_match_all("%<onlineuser>(.+?)</onlineuser>%sim", $ol, $result);
        foreach ($result[1] as $userdata){
            preg_match_all("%<name>(.+?)</name>%i",$userdata,$namedata);
            preg_match_all("%<channel>(.+?)</channel>%i",$userdata,$channeldata);
            preg_match_all("%<rang>(admin|mod|chatter|gast)</rang>%i",$userdata,$rangdata);
            preg_match_all("%<profil>(0|1)</profil>%i",$userdata,$profildata);
            preg_match_all("%<away>(0|1)</away>%i",$userdata,$awaydata);
            preg_match_all("%<awayreason>(.*?)</awayreason>%i",$userdata,$awayreasondata);
            $resultArray[] = array(
                'name' => $namedata[1][0],
                'channel' => $channeldata[1][0],
                'profil' => $profildata[1][0],
                'rang' => $rangdata[1][0],
                'away' => $awaydata[1][0],
                'awayreason' => $awayreasondata[1][0]
            );
        }
        $this->EOnline = $resultArray;
        return $this->EOnline;
    }
    
    //Prüft, ob der Chat erreichbar ist (d.h. ob man sich einloggen kann)
    function chatStatus(){
        if (isset($this->Login)){
            return $this->Login;
        }
        $cid = $this->cid;
        $server = intval($this->server);
        $seite=$this->getcontents("http://server$server.webkicks.de/$cid");
        if ($seite === false || preg_match("/deaktiviert/i",$seite)==1){
            $this->Login = false;
        }else{
            $this->Login = true;
        }
        return $this->Login;
    }
   
    //Prueft, ob der Gastzugang aktiviert ist
    function checkGuestLogin(){
        if (isset($this->GastLogin)){
            return $this->GastLogin;
        }
        $cid = $this->cid;
        $server = intval($this->server);
        $seite=$this->getcontents("http://server$server.webkicks.de/$cid");
        if ($seite === false || preg_match("/oder als Gast/i",$seite)==0){
            $this->GastLogin = false;
        }else{
            $this->GastLogin = true;
        }
        return $this->GastLogin;
    }
    //Prueft, ob die Registrierung aktiviert ist
    function checkAnmeldung(){
        if (isset($this->Registrierung)){
            return $this->Registrierung;
        }
        $cid = $this->cid;
        $server = intval($this->server);
        $seite=$this->getcontents("http://server$server.webkicks.de/$cid/register");
        if ($seite === false || preg_match("/gesperrt/i",$seite)==1){
            $this->Registrierung = false;
        }else{
            $this->Registrierung = true;
        }
        return $this->Registrierung;
    }
   
    //Ermittelt das Anmeldedatum des Chats
    function getChatanmeldung($adminname=false, $sid=false){
        if (isset($this->Chatanmeldung)){
            return $this->Chatanmeldung;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $data = array("cid" => $cid, "user" => $adminname, "pass" => $sid);
        $overview = $this->postcontents("http://server$server.webkicks.de/$cid/stats", $data);
        preg_match_all("/<td>(.+)<\/td>/",$overview,$data);
        $this->Chatanmeldung=preg_replace("/<td>(.+)<\/td>/","$1",$data[0][0])." - 00:00 h";
        return $this->Chatanmeldung;
    }
    //Ermittelt die letzten Anmeldungen
    function getRegisterlog($adminname=false, $sid=false){
        if (isset($this->Anmeldungen)){
            return $this->Anmeldungen;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/registerlog/$adminname/$sid");
        $lines = explode("\n",$overview);
        foreach ($lines as $tmp){
            if(preg_match("/<tr>/", $tmp)){
                $i++;
            }
            if($i>1){
                if(!isset($regUsers[$i])){
                    $regUsers[$i]=array();
                }
                $tmp1=strip_tags($tmp);
                $tmp1=preg_replace("/\s+/", " ", $tmp1);
                $tmp1=preg_replace("/^\s/", "", $tmp1);
                $tmp1=explode(" ", $tmp1);
                if(preg_match("/[a-zA-Z0-9>]<\/font>/", $tmp)){
                    $regUsers[$i][]=$tmp1[0];
                }
            }
        }
        array_pop($regUsers);
        array_pop($regUsers);
        $this->Anmeldungen=$regUsers;
        return $this->Anmeldungen;
    }
   
    //Ermittelt die letzten Loeschungen
    function getDeletelog($adminname=false, $sid=false){
        $delusers=array();
        if (isset($this->Loeschungen)){
            return $this->Loeschungen;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/delchatterlog/$adminname/$sid");
        preg_match_all('%<tr>\s+<td width="100">\s+<div align="center"><font size="\d+">\s+([0-9.]+)</font></div>\s+</td>\s+<td width="100">\s+<div align="center"><font size="\d+">\s+([0-9:]+)</font></div>\s+</td>\s+<td width="100">\s+<div align="center"><font size="\d+">\s+([0-9a-zA-Z_]+)</font></div>\s+</td>\s+<td width="100">\s+<div align="center"><font size="\d+">\s+([0-9a-zA-Z_]+)</font></div>\s+</td>%si',$overview,$rawdel);
        foreach ($rawdel[1] as $num=>$entry){
            $delusers[]=array($entry,$rawdel[2][$num],$rawdel[3][$num],$rawdel[4][$num]);
        }
        $this->Loeschungen=$delusers;
        return $this->Loeschungen;
    }
   
    //Ermittelt den Befehlslog
    function getBefehlslog($adminname=false, $sid=false){
        if (isset($this->Befehle)){
            return $this->Befehle;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/adminlog/$adminname/$sid");
        $lines = explode("\n",$overview);
        foreach ($lines as $tmp){
            if(preg_match("/<tr>/", $tmp)){
                $i++;
            }
            if($i>1){
                if(!isset($log[$i])){
                    $log[$i]=array();
                }
                $tmp1=strip_tags($tmp);
                $tmp1=preg_replace("/\s+/", " ", $tmp1);
                $tmp1=preg_replace("/^\s/", "", $tmp1);
                $tmp1=explode(" ", $tmp1);
                if(preg_match("/[a-zA-Z0-9> ()]<\/font>/", $tmp)){
                    $log[$i][]=$tmp1[0];
                }
            }
        }
        array_pop($log);
        array_pop($log);
        $this->Befehle=$log;
        return $this->Befehle;
    }
   
    //Ermittelt die letzten Fehlzugriffe
    function getFehlzugriffe($adminname=false, $sid=false){
        if (isset($this->Fehlzugriffe)){
            return $this->Fehlzugriffe;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $overview = $this->getcontents("http://server$server.webkicks.de/$cid/fehlzugriffe/$adminname/$sid");
        $lines = explode("\n",$overview);
        foreach ($lines as $tmp){
            if(preg_match("/<tr>/", $tmp)){
                $i++;
            }
            if($i>1){
                if(!isset($fehler[$i])){
                    $fehler[$i]=array();
                }
                $tmp1=strip_tags($tmp);
                $tmp1=preg_replace("/\s+/", " ", $tmp1);
                $tmp1=preg_replace("/^\s/", "", $tmp1);
                $tmp1=explode(" ", $tmp1);
                if(preg_match("/[a-zA-Z0-9> ()]<\/font>/", $tmp)){
                    $fehler[$i][]=$tmp1[0];
                }
            }
        }
        array_shift($fehler);
        array_shift($fehler);
        array_pop($fehler);
        array_pop($fehler);
        $this->Fehlzugriffe=$fehler;
        return $this->Fehlzugriffe;
    }
    function getWerbebefreiung($adminname=false, $sid=false){
        if (isset($this->Werbefreiheit)){
            return $this->Werbefreiheit;
        }
        $adminname = $adminname?$adminname:$this->username;
        $sid = $sid?$sid:$this->sid;
        $cid = $this->cid;
        $server = intval($this->server);
        $lines = $this->getcontents("http://server$server.webkicks.de/cgi-bin/ad_info.cgi?cid=$cid&user=$adminname&pass=$sid");
        preg_match_all('%<div align="right"><b>([0-9.]+) \(<a href=".+?ad_info.+?">Werbeframe verwalten</a>\)</b></div>%',$lines,$raw);
        $this->Werbefreiheit=(empty($raw[1][0]))?"-":$raw[1][0];
        return $this->Werbefreiheit;
    }
    
    function getSettings($adminname = false, $sid = false){
      $settings = array();
      if (isset($this->settings)){
        return $this->settings;
      }
      $adminname = $adminname?$adminname:$this->username;
      $sid = $sid?$sid:$this->sid;
      $cid = $this->cid;
      $server = intval($this->server);
      $amdump = $this->postcontents('http://server'.$server.'.webkicks.de/'.$cid.'/settings',array('cid' => $cid, 'user' => $adminname, 'pass' => $sid));
      preg_match_all('/<input type="checkbox" name="gastzugang" value="true"(?: (checked))?>/i',$amdump,$gz_setting);
      $settings['gz'] = ($gz_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="profilsystem" value="true"(?: (checked))?>/i',$amdump,$pr_setting);
      $settings['profiles'] = ($pr_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="loginusernum" value="true"(?: (checked))?>/i',$amdump,$lun_setting);
      $settings['loginusernum'] = ($lun_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="anzw" value="true"(?: (checked))?>/i',$amdump,$lul_setting);
      $settings['loginuserlist'] = ($lul_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="gf" value="true"(?: (checked))?>/i',$amdump,$gf_setting);
      $settings['guestwhisp_act'] = ($gf_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="gfs" value="true"(?: (checked))?>/i',$amdump,$gfs_setting);
      $settings['guestwhisp_psv'] = ($gfs_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="regsperre" value="1"(?: (checked))?>/i',$amdump,$reg_setting);
      $settings['reg'] = ($reg_setting[1][0] == 'checked')?false:true;
      preg_match_all('/<input type="checkbox" name="aktiv" value="1"(?: (checked))?>/i',$amdump,$log_setting);
      $settings['login'] = ($log_setting[1][0] == 'checked')?false:true;
      preg_match_all('/<input type="checkbox" name="suchbox" value="true"(?: (checked))?>/i',$amdump,$gb_setting);
      $settings['searchbox'] = ($gb_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="smilies" value="1"(?: (checked))?>/i',$amdump,$rl_setting);
      $settings['smilies'] = ($rl_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="seg" value="1"(?: (checked))?>/i',$amdump,$seg_setting);
      $settings['owncmds'] = ($seg_setting[1][0] == 'checked')?true:false;
      preg_match_all('/<input type="checkbox" name="webspacelink" value="1"(?: (checked))?>/i',$amdump,$ws_setting);
      $settings['webspace'] = ($ws_setting[1][0] == 'checked' || count($ws_setting[1]) == 0)?true:false;
      preg_match_all('/<input class="input" type="text" name="login_url" maxlength="150" value="(.*?)" size="40">/i',$amdump,$lp_setting);
      $settings['ownlogin'] = $lp_setting[1][0];
      preg_match_all('/<option value="(\d+)" selected>(.*?)<\/option>/i',$amdump,$tl_setting);
      $settings['toplist'] = $tl_setting[1][0];
      $this->settings = $settings;
      return $this->settings;
    }
    
    function setSettings($data, $adminname = false, $sid = false){
      $newData = array();
      $adminname = $adminname?$adminname:$this->username;
      $sid = $sid?$sid:$this->sid;
      $cid = $this->cid;
      $server = intval($this->server);
      $original = $this->getSettings();
      $newData['user'] = strtolower($adminname);
      $newData['pass'] = $sid;
      $newData['cid'] = $cid;
      $newData['job'] = 'change';
      
      if ($data['gz'] != $original['gz'] && isset($data['gz'])){
        if ($data['gz'] == true){
          $newData['gastzugang'] = 'true';
        }
      }else{
        if ($original['gz'] == true){
          $newData['gastzugang'] = 'true';
        }
      }
      
      if ($data['profiles'] != $original['profiles'] && isset($data['profiles'])){
        if ($data['profiles'] == true){
          $newData['profilsystem'] = 'true';
        }
      }else{
        if ($original['profiles'] == true){
          $newData['profilsystem'] = 'true';
        }
      }
      
      if ($data['loginusernum'] != $original['loginusernum'] && isset($data['loginusernum'])){
        if ($data['loginusernum'] == true){
          $newData['loginusernum'] = 'true';
        }
      }else{
        if ($original['loginusernum'] == true){
          $newData['loginusernum'] = 'true';
        }
      }
      
      if ($data['loginuserlist'] != $original['loginuserlist'] && isset($data['loginuserlist'])){
        if ($data['loginuserlist'] == true){
          $newData['anzw'] = 'true';
        }
      }else{
        if ($original['loginuserlist'] == true){
          $newData['anzw'] = 'true';
        }
      }
      
      if ($data['guestwhisp_act'] != $original['guestwhisp_act'] && isset($data['guestwhisp_act'])){
        if ($data['guestwhisp_act'] == true){
          $newData['gf'] = 'true';
        }
      }else{
        if ($original['guestwhisp_act'] == true){
          $newData['gf'] = 'true';
        }
      }
      
      if ($data['guestwhisp_psv'] != $original['guestwhisp_psv'] && isset($data['guestwhisp_psv'])){
        if ($data['guestwhisp_psv'] == true){
          $newData['gfs'] = 'true';
        }
      }else{
        if ($original['guestwhisp_psv'] == true){
          $newData['gfs'] = 'true';
        }
      }
      
      if ($data['reg'] != $original['reg'] && isset($data['reg'])){
        if ($data['reg'] == false){
          $newData['regsperre'] = '1';
        }
      }else{
        if ($original['reg'] == false){
          $newData['regsperre'] = '1';
        }
      }
      
      if ($data['login'] != $original['login'] && isset($data['login'])){
        if ($data['login'] == false){
          $newData['aktiv'] = '1';
        }
      }else{
        if ($original['login'] == false){
          $newData['aktiv'] = '1';
        }
      }
      
      if ($data['webspace'] != $original['webspace'] && isset($data['webspace'])){
        if ($data['webspace'] == true){
          $newData['webspacelink'] = '1';
        }
      }else{
        if ($original['webspace'] == true){
          $newData['webspacelink'] = '1';
        }
      }
      
      if ($data['owncmds'] != $original['owncmds'] && isset($data['owncmds'])){
        if ($data['owncmds'] == true){
          $newData['seg'] = '1';
        }
      }else{
        if ($original['owncmds'] == true){
          $newData['seg'] = '1';
        }
      }
      
      if ($data['smilies'] != $original['smilies'] && isset($data['smilies'])){
        if ($data['smilies'] == true){
          $newData['smilies'] = '1';
        }
      }else{
        if ($original['smilies'] == true){
          $newData['smilies'] = '1';
        }
      }
      
      if ($data['searchbox'] != $original['searchbox'] && isset($data['searchbox'])){
        if ($data['searchbox'] == true){
          $newData['suchbox'] = '1';
        }
      }else{
        if ($original['searchbox'] == true){
          $newData['suchbox'] = '1';
        }
      }
      
      if ($data['ownlogin'] != $original['ownlogin'] && isset($data['ownlogin'])){
        $newData['login_url'] = $data['ownlogin'];
      }else{
        $newData['login_url'] = $original['ownlogin'];
      }
      
      if ($data['toplist'] != $original['toplist'] && isset($data['toplist'])){
        $newData['topliste'] = $data['toplist'];
      }else{
        $newData['topliste'] = $original['toplist'];
      }
      
      unset($this->settings);
      $this->postcontents('http://server'.$server.'.webkicks.de/'.$cid.'/settings',$newData);
    }
}
?>
