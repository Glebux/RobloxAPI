<?php
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
    function makerobloxrequest(string $url, ?bool $post, ?array $postfields, ?string $contenttype, ?string $cookie, ?string $token, ?array $headers, ?bool $returnheaders){
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
        $ch = curl_init($url);
        if (isset($cookie)){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
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
        return $resp;
    }
    class RobloxClient{
        protected string $cookie;
        protected string $cookieheader;
        public string $userid;
        public string $username;
        public string $displayname;
        public function __construct(string $cookie)
        {
            $this->cookie = $cookie;
            $this->cookieheader = ".ROBLOSECURITY=" . $this->cookie . "; Path=/; Domain=.www.roblox.com; HttpOnly; Expires=Mon, 1 Jan " . strval(intval(date("Y") + 50)) . " 00:00:00 GMT";
            $data = json_decode(makerobloxrequest("https://users.roblox.com/v1/users/authenticated", false, null, null, $this->cookieheader, null, null, false), true);
            if (!isset($data["id"])){
                throw new ErrorException("Identification failed or invalid cookie");
            }
            $this->userid = $data["id"];
            $this->username = $data["name"];
            $this->displayname = $data["displayName"];
        }
        public function gettoken(): string{
            return makerobloxrequest("https://auth.roblox.com/v2/logout", true, null, null, $this->cookieheader, null, null, true)["x-csrf-token"];
        }
        public function getauthticket(): string{
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
    }
?>
