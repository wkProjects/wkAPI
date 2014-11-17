#wkAPI
=====

Die API für Webkicks-Chats

##ToDo
=====

Derzeit nichts ;-)

##Dokumentation
=====

Die Dokumentation für Version 1 findet sich unter https://www.webkicks.de/forum/wkapi-f27/inoffizielle-api-fur-wkchats-t20451.html
Um diese API einzusetzen, ist zunächst zu prüfen, ob der eigene Webspace diese unterstützt. Die diesbezüglichen Anforderungen sind:

PHP >= 5.3

Mit aktivierem allow_url_fopen muss eine der folgenden Funktionen verfügbar sein:
  file_get_contents
  file

Ohne allow_url_fopen ist erforderlich:
  fsockopen
  
Um zu testen, ob der eigene Webspace diese Anforderungen erfüllt, kann folgendes Script verwendet werden. Sollte die API bei euch nicht funktionieren, wird eine entsprechende Meldung ausgegeben.

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI();
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

Sofern das obige Script eine leere Seite ausgibt und keine Fehlermeldungen in das Fehlerprotokoll eures Webspaces (meist error.log genannt) schreibt, wird die API funktionieren.

Um die API tatsächlich zu benutzen, sollte sie mit den Werten eures Chats initialisiert werden. Hierzu erzeugt ihr eine Instanz der Klasse wkAPI:

```PHP
<?php
require("api_v2.php");

/*
 * Hier wird der Chat wkchat auf server3 verwendet
 */
$chat = new wkAPI(3, "wkchat");
?>
```

Alternativ könnt ihr auch ein leeres Objekt erzeugen und es anschließend mit euren Daten füllen:

```PHP
<?php
require("api_v2.php");

/*
 * Hier wird der Chat wkchat auf server3 verwendet
 */
$chat = new wkAPI();
$chat->setServer(3);
$chat->setCid("wkchat");
?>
```

Ebenso könnt ihr das Objekt auch direkt mit Benutzernamen und Kennwort versehen. Die SID existiert im Gegensatz zur Version 1 nur noch intern und muss nicht mehr übergeben werden.

```PHP
<?php
require("api_v2.php");

/*
 * Hier wird der Chat wkchat auf server3 verwendet
 */
$chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Kennwort");
?>
```

Natürlich können Benutzername und Kennwort auch nachträglich gesetzt werden:

```PHP
<?php
require("api_v2.php");

/*
 * Hier wird der Chat wkchat auf server3 verwendet
 */
$chat = new wkAPI();
$chat->setServer(3);
$chat->setCid("wkchat");
$chat->setUsername("Linus");
$chat->setPassword("Super-Geheimes-Kennwort");
?>
```

Varianten mit nachträglichem Setzen der Werte ist vor allem dann nützlich, wenn ihr API-Funktionen mit mehreren Accounts oder für mehrere Chats nutzen wollt, da so nicht immer ein neues Objekt erzeugt werden muss, was sich irgendwann in der Performance niederschlägt.

Zu beachten ist, dass die API ihre Ergebnisse zwischenspeichert. Das heißt, dass bei 10000 Aufrufen von bspw. getToplist nur der erste Aufruf tatsächlich an die Webkicks-Server gesendet wird. Alle weiteren Anfragen während der Laufzeit des Scripts werden aus dem Zwischenspeicher zurückgegeben. Dieser wird jedoch bei jedem Aufruf der set-Funktionen setServer, setCid, setUsername und setPassword zurückgesetzt, sodass ihr für neue Chats auch die richtigen Ergebnisse bekommt. Dieses Verhalten kann auch genutzt werden, um den Zwischenspeicher zu umgehen, dazu aber später mehr.

Außerdem eine Anmerkung: Die Reihenfolge der Attribute eines von der API zurückgegebenen Objekts ist zufällig. Lediglich die Topliste ist sortiert. Arrays (z.B. getAllUsers) sind davon nicht betroffen.

###Funktionen
====

####checkUser:
Erwartet: Benutzername, Kennwort (sonst werden die in new wkAPI() eingetragenen gewählt)
Rückgabe: 0: Passwort nicht korrekt/User existiert nicht
1: User und Passwort korrekt, der User ist aber Offline
2: User und Passwort korrekt, der User ist Online

####login:
Erwartet: Benutzername, Kennwort (sonst werden die in new wkAPI() eingetragenen gewählt)
Rückgabe: True, wenn der Login erfolgreich war, sonst false

####logout:
Erwartet: Benutzername, Kennwort (sonst werden die in new wkAPI() eingetragenen gewählt)
Rückgabe: - (Loggt den User aus)

####sendeText:
Erwartet: Nachricht, Benutzername, Kennwort (sonst werden die in new wkAPI() eingetragenen gewählt)
Rückgabe: True, wenn Nachricht gesendet wurde, sonst false

####isHauptadmin:
Erwartet: Username
Rückgabe: true, wenn der User Hauptadmin ist, sonst false

####isAdmin:
Erwartet: Username
Rückgabe: true, wenn der User Admin oder Hauptadmin ist, sonst false

####isMod:
Erwartet: Username
Rückgabe: true, wenn der User Mod ist, sonst false

####getUserdata:
Erwartet: Username
Rückgabe: Objekt, das alle Daten des Users enthält. Als Beispiel:

```
stdClass Object
(
    [country] => Deutschland
    [points] => 4
    [loginmessage] => tut so als wäre er on gekommen.
    [gbentries] => 2
    [registered] => 
    [ip] => 127.0.0.1
    [logoutmessage] => geht wirklich :o
    [logins] => 2119
    [plz] => 12345
    [username] => Linus
    [mail] => linus@wkprojects.org
    [mailreminder] => 
    [messenger] => 123456789
    [hp] => https://wkprojects.org
    [profile] => false
    [sex] => m
    [newsletter] => true
    [status] => frei
    [level] => Administrator
    [lastseen] => 13.11.2014 [18:02]
    [alias] => <b><FONT COLOR="#00AAAA">L</FONT><FONT COLOR="#108F8F">i</FONT><FONT COLOR="#207575">n</FONT><FONT COLOR="#305A5A">u</FONT><FONT COLOR="#404040">s</FONT></b>
)
```

####getAnkuendigungen:
Erwartet: -
Rückgabe: Objekt mit den Ankündigungen. Beispiel (keine Team-Ankündigung gesetzt):

```
stdClass Object
(
    [registered] => Willkommen, registrierter User!
    [team] => 
    [guest] => Willkommen, Gast!
)
```

####getReplacers:
Erwartet: -
Rückgabe: Objekt mit den Replacern. Beispiel (natürlich gekürzt):

```
stdClass Object
(
    [:weird] => weird.gif
    [:gruebel] => gruebel.gif
    [:schimpf] => schimpf.gif
    [:liebe] => liebe.gif
    [:pizza] => pizza.gif
    [:sleep] => sleep.gif
    [:hm] => hm.gif
    [:]] => ].gif
)
```

####getToplist:
Erwartet: true/false
Rückgabe: Wenn false (oder irgendetwas anderes als true) übergeben wird, die öffentliche Topliste, ansonsten die Admin-Topliste. Format:

```
stdClass Object
(
    [wkQB] => stdClass Object
        (
            [hours] => 17
            [seconds] => 28
            [days] => 679
            [totalseconds] => 58728508
            [minutes] => 28
        )

    [Linus] => stdClass Object
        (
            [hours] => 0
            [seconds] => 38
            [totalseconds] => 9072638
            [days] => 105
            [minutes] => 10
        )
)
```

####getTeam:
Erwartet: -
Rückgabe: Objekt mit den Teammitgliedern. Admins und Mods sind jeweils Arrays, der Hauptadmin ein String. Format:

```
stdClass Object
(
    [mods] => Array
        (
        )

    [hauptadmin] => Linus
    [admins] => Array
        (
            [0] => dennis
            [1] => DG
            [2] => regreb99
            [3] => wkQB
        )

)
```

####getAllUsers:
Erwartet: -
Rückgabe: Array, das jeden User enthält:

```
Array
(
    [0] => Dennis
    [1] => DG
    [2] => Linus
    [3] => regreb99
    [4] => Linus
)
```

####getKickedUsers:
Erwartet: -
Rückgabe: Array, das jeden gekickten User enthält. Format s. getAllUsers

####getBannedUsers:
Erwartet: -
Rückgabe: Array, das jeden gebannten User enthält. Format s. getAllUsers, IPs werden wie Usernamen hinterlegt:

```
Array
(
    [0] => 127.0.0.1
)
```

####getMutedUsers:
Erwartet: -
Rückgabe: Array, das jeden geknebelten User enthält. Format s. getAllUsers

####getLockedUsers:
Erwartet: -
Rückgabe: Array, das jeden nicht freigeschalteten User enthält. Format s. getAllUsers

####getChannels:
Erwartet: -
Rückgabe: Objekt mit Array aller Räume und Infos, ob Räume aktiviert und auf der Loginseite wählbar sind. Format: 

```
stdClass Object
(
    [list] => Array
        (
            [0] => Hauptchat
            [1] => Away
        )

    [onloginpage] => false
    [active] => true
)
```

####getOnlineUsers:
Erwartet: -
Rückgabe: Ein Array mit allen Usern, die derzeit online sind. Format:

```
stdClass Object
(
    [main] => Array
        (
            [0] => stdClass Object
                (
                    [name] => Linus
                    [iconid] => 2
                    [away] => 0
                    [awayreason] => 
                    [rang] => admin
                    [profil] => 1
                )

        )
    [Away] => Array
        (
            [0] => stdClass Object
                (
                    [profil] => 0
                    [name] => wkQB
                    [awayreason] => 
                    [rang] => admin
                    [away] => 0
                    [iconid] => 2
                )

        )


)
```

####getReglog:
Erwartet: -
Rückgabe: Array der zuletzt registrierten User (Timestamp, Datum, Uhrzeit, Nick). Format:

```
Array
(
    [0] => stdClass Object
        (
            [username] => Kebot
            [deleted] => false
            [time] => 15:10
            [date] => 15.01.2013
            [timestamp] => 1358259048
        )

    [1] => stdClass Object
        (
            [date] => 09.01.2013
            [time] => 19:01
            [username] => lila2436
            [deleted] => false
            [timestamp] => 1357754517
        )

    [2] => stdClass Object
        (
            [time] => 00:51
            [date] => 30.11.2012
            [deleted] => false
            [username] => xWayne
            [timestamp] => 1354233082
        )
)
```

####getCmdLog:
Erwartet: -
Rückgabe: Array des Befehlslogs (Timestamp, Datum, Uhrzeit, Nick, Aktion, Ziel). Format:

```
Array
(
    [0] => stdClass Object
        (
            [timestamp] => 1416252234
            [username] => Linus
            [date] => 17.11.2014
            [action] => bann
            [time] => 20:23
            [subject] => 8.8.8.8
        )

    [1] => stdClass Object
        (
            [time] => 21:23
            [subject] => wkQB
            [action] => passchange
            [date] => 12.11.2014
            [username] => Linus
            [timestamp] => 1415823813
        )
)
```

####getFehlzugriffe:
Erwartet: -
Rückgabe: Array der letzten Fehlzugriffe (Timestamp, Datum, Uhrzeit, Nick, IP)

```
Array
(
    [0] => stdClass Object
        (
            [date] => 07.09.2014
            [timestamp] => 1410123810
            [IP] => 127.0.0.1
            [time] => 23:03
            [username] => Linus
        )

    [1] => stdClass Object
        (
            [username] => Linus
            [time] => 15:58
            [IP] => 127.0.0.1
            [date] => 27.03.2013
            [timestamp] => 1364396286
        )
)
```

####getDeletelog:
Erwartet: Username, SID (sonst Werte aus new Webkicks())
Rückgabe: Array der letzten Userlöschungen (Datum, Uhrzeit, Nick)

```
Array
(
    [0] => stdClass Object
        (
            [date] => 22.05.2012
            [time] => 17:19
            [timestamp] => 1337699967
            [username] => Testi
            [by] => Testi
        )

    [1] => stdClass Object
        (
            [date] => 02.02.2012
            [time] => 15:13
            [timestamp] => 1328191988
            [username] => jamie
            [by] => wk_autodel
        )

    [2] => stdClass Object
        (
            [date] => 28.01.2012
            [time] => 15:07
            [timestamp] => 1327759630
            [by] => wk_autodel
            [username] => judith
        )
)
```

####getSettings:
Erwartet: 
Rückgabe: Objekt der Einstellungen auf der Einstellungsseite. Format:

```
stdClass Object
(
    [guestwhisper_send] => true
    [replacer] => true
    [showusers] => true
    [owncmds] => true
    [guestwhisper_receive] => true
    [profiles] => true
    [webspace] => false
    [guestaccess] => true
    [openreg] => true
    [toplist] => 10
    [login] => true
    [showusernum] => true
    [google] => false
    [loginpage] => http://chat.wkprojects.org
    [nobanner] => 
)
```

###Bemerkungen
====

Zwar sind im Vergleich zur Version 1 einige Funktionen weggefallen und andere umbenannt werden, trotzdem kann diese API (bis auf das Setzen der Einstellungen) alle Funktionen aus Version 1 nachbilden.

##Beispiele
====

Die folgenden Beispiele existieren bereits für Version 1 und wurden nun für diese Version angepasst.

###Alle User samt Gesamtzahl anzeigen
===

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort);
    $registrierte=$chat->getAllUsers();
    $anzahl=count($registrierte);
echo "Wir haben {$anzahl} registrierte Benutzer!<br /><br />\n\nListe:<br />\n".implode("<br />\n",$registrierte);
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

###Details eines einzelnen Users anzeigen 
====

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort");
    $user="Linus";
    $details=$chat->getUserdata($user);
    echo "Details für ".$user.":<br><br>";
    echo "Alias: {$details->alias}<br>";
    echo "Mail: {$details->mail}<br>";
    echo "ICQ: {$details->messenger}<br>";
    echo "Userlevel: {$details->level}<br>";
    echo "Status: {$details->status}<br>";
    echo "Letzte IP: {$details->ip}<br>";
    echo "Angemeldet am: {$details->registered}<br>";
    echo "Letzter Login: {$details->lastseen}<br>";
    echo "Homepage: {$details->hp}<br>";
    echo "Logins: {$details->logins}<br>";
    echo "Loginnachricht: {$details->loginmessage}<br>";
    echo "Logoutnachricht: {$details->logoutmessage}<br>";
    echo "Profillink: <a href='{$details->profil}'>Klick!</a><br>";
    echo "Newsletterempfang: {$details->newsletter}<br>";
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

###Eine Onlineliste
====

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort");
    $online = $chat->getOnlineList();
    $onlineMain = $chat->getOnlineList()->main;
    $count = 0;
    $listTotal = array();

    foreach ($online as $channel => $list) {
        $count += count($list);
        foreach ($list as $index => $user) {
            $listTotal[] = $user->name;
        }
    }
    $listMain = array();

    foreach ($onlineMain as $index => $user) {
        $listMain[] = $user->name;
    }

    $wort = ($anzahl == 1) ? "ist" : "sind";
    $wort2 = ($anzahl_im_Hauptchat == 1) ? "befindet" : "befinden";
    echo "Momentan " . $wort . " " . count($listTotal) . " Benutzer online!<br />Liste:<br />" . implode("<br />", $listTotal);
    echo "<br /><br />Davon " . $wort2 . " sich " . count($listMain) . " Benutzer im Hauptchat!<br />Liste:<br />" . implode("<br />", $listMain);
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

###Eine Teamliste
====

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort");
    $team = $chat->getTeam();
    $mods = $team->mods;
    $admins = $team->admins;
    $anzahl_mods = count($team->mods);
    $anzahl_admins = count($team->admins);
    $anzahl_team = $anzahl_mods + $anzahl_admins;
    echo "<div align='center'>Unser Team besteht aus " . $anzahl_team . " Leuten, davon " . $anzahl_admins . " Admins und " . $anzahl_mods . " Mods!";
    echo "<br>Liste:";
    echo "<br><font color='#FF0000'>Admins:</font>";
    foreach ($team->admins AS $admin) {
        echo "<div style='font-weight:bold;color:#F00'>" . $admin . "</div>";
    }
    echo "<hr>";
    echo "<font color='#006600'>Mods:</font>";
    foreach ($team->mods AS $mod) {
        echo "<div style='font-weight:bold;color:#060'>" . $mod . "</div>";
    }
    echo "</div>";
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

###Ist ein User online?
====

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort");
    $username = $_REQUEST['user'];

    $loggedIn = false;

    foreach ($chat->getOnlineList() as $channel => $list) {
        foreach ($list as $index => $user) {
            if (strtolower($user->name) === strtolower($username)) {
                $loggedIn = true;
                break 2;
            }
        }
    }
    if ($loggedIn) {
        $status = "eingeloggt";
    } else {
        $status = "nicht eingeloggt";
    }
    if (in_array(strtolower($username), array_map("strtolower", $chat->getAllUsers()))) {
        $rang = "registriert";
    } else {
        $rang = "nicht angemeldet";
    }
    echo "$username ist $rang und $status!";
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```

###Einen User einloggen und etwas schreiben lassen
====

```PHP
<?php
if (version_compare(PHP_VERSION, '5.3.0') >= 0){
  require("api_v2.php");
  try {
    $chat = new wkAPI(3, "wkchat", "Linus", "Super-Geheimes-Passwort");
    $chat->login();
    sleep(1);
    $chat->sendeText("Ich bin eingeloggt!");
    sleep(1);
    $chat->logout();
  } catch (Exception $e){
    print $e->getMessage();
  }
} else {
  echo "Deine PHP-Version ist zu alt!";
}
?>
```
