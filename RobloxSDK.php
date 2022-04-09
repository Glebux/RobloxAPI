<?php
/**
 * RobloxSDK
 * @author Glebux
 * @license MIT License
 * @copyright Glebux©2022
 */
    function headersToArray( $str ): array {
        $headers = array();
        $headersTmpArray = explode( "\r\n" , $str );
        for ( $i = 0 ; $i < count( $headersTmpArray ) ; ++$i )
        {
            // we dont care about the two \r\n lines at the end of the headers
            if ( strlen( $headersTmpArray[$i] ) > 0 )
            {
                // the headers start with HTTP status codes, which do not contain a colon so we can filter them out too
                if ( strpos( $headersTmpArray[$i] , ":" ) )
                {
                    $headerName = substr( $headersTmpArray[$i] , 0 , strpos( $headersTmpArray[$i] , ":" ) );
                    $headerValue = substr( $headersTmpArray[$i] , strpos( $headersTmpArray[$i] , ":" )+1 );
                    $headers[$headerName] = $headerValue;
                }
            }
        }
        return $headers;
    }
    function makerobloxrequest(string $url, bool|string $request = false, ?array $postfields = null, ?string $contenttype = null, ?string $cookie = null, ?string $token = null, ?array $headers = null, ?bool $returnheaders = false, ?bool $returncode = false){
        switch ($contenttype){
            case "json":
                $contenttype = "application/json";
            case "text":
                $contenttype = "text/plain";
            case "xml":
                $contenttype = "text/xml";
            default:
                $contenttype = "application/json";
        }
        $post = false;
        $ch = curl_init($url);
        if (isset($cookie)){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (gettype($request) == "bool"){
            $post = $request;
        }
        else{
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
        }
        curl_setopt($ch, CURLOPT_POST, $post);
        if (isset($postfields)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
        }
        $temphd = array(
            "Content-Type: ".$contenttype,
            "Referer: www.roblox.com",
            "Accept: application/json"
        );
        if (isset($token)){
            array_push($temphd, "X-CSRF-Token: ".$token);
        }
        if (isset($headers)){
            foreach ($headers as $k => $v){
                array_push($temphd, $v);
            }
        }
        if ($returnheaders == true) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $temphd);
        $resp = curl_exec($ch);
        if ($returnheaders == true) {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($resp, 0, $header_size);
            return headersToArray($header);
        }
        if ($returncode == true){
            return curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        return $resp;
    }
    class RobloxClient{
        private string $cookie;
        private string $cookieheader;
        public string $userid;
        public string $username;
        public string $displayname;
        protected string $accesspassword = "";
        public function __construct(string $cookie, $accesspassword = "")
        {
            $this->cookie = $cookie;
            $this->cookieheader = ".ROBLOSECURITY=" . $this->cookie . "; Path=/; Domain=.www.roblox.com; HttpOnly; Expires=Mon, 1 Jan " . strval(intval(date("Y") + 50)) . " 00:00:00 GMT";
            $data = json_decode(makerobloxrequest("https://users.roblox.com/v1/users/authenticated", false, null, null, $this->cookieheader, null, null, false), true);
            if (!isset($data["id"])){
                throw new ErrorException("Identification failed or invalid cookie");
            }
            if (isset($accesspassword)){
                $this->accesspassword = $accesspassword;
            }
            $this->userid = $data["id"];
            $this->username = $data["name"];
            $this->displayname = $data["displayName"];
        }
        public function getcookie(string $password = ""){
            if ($password == $this->accesspassword){
                return $this->cookie;
            }
            return "Incorrect password";
        }
        public function getcookieheader(string $password = ""){
            if ($password == $this->accesspassword){
                return $this->cookieheader;
            }
            return "Incorrect password";
        }
        public function setpassword(string $oldpassword, string $newpassword){
            if ($this->accesspassword == $oldpassword){
                $this->accesspassword = $newpassword;
                return true;
            }
            return "Old password is incorrect";
        }
        public function gettokenbypass(string $password = ""){
            if ($this->accesspassword == $password){
                return $this->gettoken();
            }
            return "Incorrect password";
        }
        private function gettoken(): string{
            return makerobloxrequest("https://auth.roblox.com/v2/logout", true, null, null, $this->cookieheader, null, null, true)["x-csrf-token"];
        }
        public function getauthticketbypass(string $password = ""){
            if ($this->accesspassword == $password){
                return $this->getauthticket();
            }
            return "Incorrect password";
        }
        private function getauthticket(): string{
            return substr(makerobloxrequest("https://auth.roblox.com/v1/authentication-ticket/", true, null, null, $this->cookieheader, $this->gettoken(), null, true)["rbx-authentication-ticket"], 1);
        }
        public function joinplaceid($placeid){
            header('Location: roblox-player:1+launchmode:play+gameinfo:'.$this->getauthticket().'+launchtime:1605197413770+placelauncherurl:https%3A%2F%2Fassetgame.roblox.com/game/PlaceLauncher.ashx?request=RequestGame&placeId='.$placeid.'&isPlayTogetherGame=false+robloxLocale:en_us+gameLocale:en_us+channel:');
        }
        public function joinjobid($placeid, $jobid){
            header('Location: roblox-player:1+launchmode:play+gameinfo:'.$this->getauthticket().'+launchtime:1605197413770+placelauncherurl:https%3A%2F%2Fassetgame.roblox.com/game/PlaceLauncher.ashx?request=RequestGame&placeId='.$placeid.'&gameId='.$jobid.'&isPlayTogetherGame=false+robloxLocale:en_us+gameLocale:en_us+channel:');
        }
        public function getfriendcount(){
            return json_decode(makerobloxrequest("https://friends.roblox.com/v1/my/friends/count", false, null, null, $this->cookieheader, $this->gettoken(), null, false), true)["count"];
        }
        public function getfriends(){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$this->userid."/friends?userSort=Alphabetical", false, null, null, $this->cookie, $this->gettoken(), null, false), true);
            $users = array();
            if (!isset($data["data"])){
                return null;
            }
            foreach ($data["data"] as $k => $v){
                $users[] = new RobloxPlayer($v["id"]);
            }
            return $users;
        }
        public function getfriendrequestcount(){
            return json_decode(makerobloxrequest("https://friends.roblox.com/v1/user/friend-requests/count", false, null, null, $this->cookieheader, $this->gettoken(), null, false), true)["count"];
        }
        public function getfriendrequests(){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/my/friends/requests?sortOrder=Desc&limit=100", false, null, null, $this->cookie, $this->gettoken(), null, false), true);
            $users = array();
            if (!isset($data["data"])){
                return null;
            }
            foreach ($data["data"] as $k => $v){
                $users[] = new RobloxPlayer($v["id"]);
            }
            return $users;
        }
        public function addtofriends(int $userid){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$userid."/request-friendship", true, array("friendshipRequestModel" => array("friendshipOriginSourceType" => "Unknown")), null, $this->cookieheader, $this->gettoken(), null, false), true);
            if (isset($data["success"])){
                return true;
            }
            return false;
        }
        public function unfried(int $userid){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$userid."/unfriend", true, null, null, $this->cookieheader, $this->gettoken(), null, false), true);
            if (isset($data["success"])){
                return true;
            }
            return false;
        }
        public function acceptfriendrequest(int $userid){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$userid."/accept-friend-request", true, null, null, $this->cookieheader, $this->gettoken(), null, false), true);
            if (isset($data["success"])){
                return true;
            }
            return false;
        }
        public function declinefriendrequest(int $userid){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$userid."/decline-friend-request", true, null, null, $this->cookieheader, $this->gettoken(), null, false), true);
            if (isset($data["success"])){
                return true;
            }
            return false;
        }
        public function isfriendswith(int $userid){
            $data = $this->getfriends();
            foreach ($data as $v){
                if ($v->userid == $userid){
                    return true;
                }
            }
            return false;
        }
    }
    class RobloxPlayer{
        public int $userid;
        public string $username;
        public string $displayname;
        public ?string $description;
        public string $created;
        public bool $isBanned;
        public function __construct(int $userid)
        {
            $this->userid = $userid;
            $data = json_decode(makerobloxrequest("https://users.roblox.com/v1/users/".strval($userid), false, null, null, null, null, null, false), true);
            $this->description = $data["description"];
            $this->created = $data["created"];
            $this->isBanned = $data["isBanned"];
            $this->username = $data["name"];
            $this->displayname = $data["displayName"];
        }
        public function getfriends(){
            $data = json_decode(makerobloxrequest("https://friends.roblox.com/v1/users/".$this->userid."/friends?userSort=Alphabetical", false, null, null, null, null, null, false), true);
            $users = array();
            if (!isset($data["data"])){
                return null;
            }
            foreach ($data["data"] as $k => $v){
                $users[] = new RobloxPlayer($v["id"]);
            }
            return $users;
        }
        public function addtofriends(RobloxClient $client){
            $client->addtofriends($this->userid);
        }
        public function unfriend(RobloxClient $client){
            $client->unfried($this->userid);
        }
        public function acceptfriendrequest(RobloxClient $client){
            $client->acceptfriendrequest($this->userid);
        }
        public function declinefriendrequest(RobloxClient $client){
            $client->declinefriendrequest($this->userid);
        }
        public function isfriendswith(int $userid){
            $data = $this->getfriends();
            foreach ($data as $v){
                if ($v->userid == $userid){
                    return true;
                }
            }
            return false;
        }
    }
    class RobloxGame{
        public $gameid;
        public $placeid;
        public $universeid;
        public $placename;
        public $description;
        public $creator;
        public $price;
        public $created;
        public $updated;
        public $playing;
        public $visits;
        public $maxplayers;
        public function getuniversalid(): int{
            return json_decode(makerobloxrequest("https://api.roblox.com/universes/get-universe-containing-place?placeid=".$this->gameid, false, null, null, null, null, null, false), true)["UniverseId"];
        }
        public function __construct(int $placeid){
            $this->gameid = $placeid;
            $this->universeid = $this->getuniversalid();
            if (!isset($this->universeid)){
                throw new ErrorException("Invalid game or cannot fetch information");
            }
            $data = json_decode(makerobloxrequest("https://games.roblox.com/v1/games?universeIds=".$this->getuniversalid(), false, null, null, null, null, null, null), true)["data"][0];
            $this->placeid = $data["rootPlaceId"];
            $this->placename = $data["name"];
            $this->description = $data["description"];
            $this->creator = new RobloxPlayer($data["creator"]["id"]);
            switch ($data["price"]){
                case null:
                    $this->price = 0;
                default:
                    $this->price = $data["price"];
            }
            $this->created = $data["created"];
            $this->updated = $data["updated"];
            $this->playing = $data["playing"];
            $this->visits = $data["visits"];
            $this->maxplayers = $data["maxPlayers"];
        }
        public function getservers(?RobloxClient $client = null, ?string $password = ""){
            $data = null;
            if (isset($client) and isset($password)){
                $data = json_decode(makerobloxrequest("https://games.roblox.com/v1/games/".$this->gameid."/servers/Public?sortOrder=Asc&limit=100", false, null, null, $client->getcookieheader($password), $client->gettokenbypass($password), null, false), true);
            }
            else{
                $data = json_decode(makerobloxrequest("https://games.roblox.com/v1/games/".$this->gameid."/servers/Public?sortOrder=Asc&limit=100", false, null, null, null, null, null, false), true);
            }
            $servers = array();
            foreach ($data["data"] as $v) {
                $playertokens = array();
                foreach ($v["playerTokens"] as $p) {
                    $playertokens[] = $p;
                }
                //print_r($players);
                $ping = 0;
                if (isset($v["ping"])){
                    $ping = $v["ping"];
                }
                $serverdata = array(
                    "jobId" => $v["id"],
                    "playing" => $v["playing"],
                    "playerTokens" => $playertokens,
                    "ping" => $ping,
                    "fps" => $v["fps"]
                );
                if (isset($client) and isset($password)){
                    foreach ($v["players"] as $p){
                        $serverdata["players"] = new RobloxPlayer($p["id"]);
                    }
                }
                $servers[] = $serverdata;
            }
            return $servers;
        }
        public function getplaces(){
            $data = json_decode(makerobloxrequest("https://develop.roblox.com/v1/universes/".$this->universeid."/places?sortOrder=Asc&limit=100"), true);
            $places = array();
            foreach ($data["data"] as $v){
                $places[] = new RobloxGame($v["id"]);
            }
            return $places;
        }
        public function setname(RobloxClient $client, string $password, string $name){
            $request = makerobloxrequest("https://develop.roblox.com/v1/places/".$this->gameid, true, array("name" => $name, "description" => $this->description), null, $client->getcookieheader($password), $client->gettokenbypass($password), null, false, true);
            if ($request == 200){return true;}
            return false;
        }
        public function setdescription(RobloxClient $client, string $password, string $description){
            $request = makerobloxrequest("https://develop.roblox.com/v1/places/".$this->gameid, true, array("name" => $this->placename, "description" => $description), null, $client->getcookieheader($password), $client->gettokenbypass($password), null, false, true);
            if ($request == 200){return true;}
            return false;
        }
    }
?>
