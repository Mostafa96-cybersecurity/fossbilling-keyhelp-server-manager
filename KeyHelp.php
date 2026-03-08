<?php
declare(strict_types=1);
/*
|--------------------------------------------------------------------------
| FOSSBilling KeyHelp Server Manager
|--------------------------------------------------------------------------
|
| Author: Mostafa Mohamed
| GitHub: https://github.com/Mostafa96-cybersecurity
| Description:
| KeyHelp provisioning module for FOSSBilling.
| Supports automatic account creation, suspension, package changes,
| domain synchronization, and API caching.
|
| License: MIT
|
*/
class Server_Manager_KeyHelp extends Server_Manager
{

    const VERSION = "1.0.0";
    private bool $verifySSL=true;
    private int $apiRetry=3;
    private int $rateLimitDelay=120000;

    private static array $logCache=[];
    private static array $apiCache=[];



    private function log(string $msg):void
    {

        $hash=md5($msg);

        if(isset(self::$logCache[$hash])){
            if(time()-self::$logCache[$hash] < 60){
                return;
            }
        }

        self::$logCache[$hash]=time();

        error_log("KeyHelp: ".$msg);

    }



    public function init():void
    {

        if(!extension_loaded('curl')){
            throw new Server_Exception('cURL extension is not enabled');
        }

        if(empty($this->_config['host'])){
            throw new Server_Exception('KeyHelp host missing');
        }

        if(empty($this->_config['accesshash'])){
            throw new Server_Exception('KeyHelp API key missing');
        }

        $this->_config['accesshash']=trim($this->_config['accesshash']);

    }



    public static function getForm():array
    {

        return [

            'label'=>'KeyHelp',

            'form'=>[

                'credentials'=>[

                    'fields'=>[

                        [
                            'name'=>'host',
                            'type'=>'text',
                            'label'=>'Hostname',
                            'required'=>true
                        ],

                        [
                            'name'=>'accesshash',
                            'type'=>'text',
                            'label'=>'API Key',
                            'required'=>true
                        ]

                    ]

                ]

            ]

        ];

    }


    private function apiRequest(string $method,string $endpoint,?array $data=null)
    {

        $cacheKey=$method.$endpoint;

        if($method==="GET" && isset(self::$apiCache[$cacheKey])){
            $entry=self::$apiCache[$cacheKey];
            if(time() - $entry["time"] < 60){
                return $entry["data"];
            }
        }

        usleep($this->rateLimitDelay);

        $url="https://".$this->_config['host']."/api/v2/".ltrim($endpoint,'/');

        $attempts=$this->apiRetry;

        while($attempts--){

            $ch=curl_init();

            curl_setopt_array($ch,[

                CURLOPT_URL=>$url,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CUSTOMREQUEST=>strtoupper($method),

                CURLOPT_HTTPHEADER=>[
                    "X-API-Key: ".$this->_config['accesshash'],
                    "Accept: application/json",
                    "Content-Type: application/json"
                ],

                CURLOPT_SSL_VERIFYPEER=>$this->verifySSL,
                CURLOPT_SSL_VERIFYHOST=>$this->verifySSL ? 2 : 0,

                CURLOPT_TIMEOUT=>25,
                CURLOPT_CONNECTTIMEOUT=>8

            ]);

            if($data!==null){
                curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
            }

            $response=curl_exec($ch);

            if($response===false){
                $this->log("CURL ERROR ".curl_error($ch));
            }

            $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);

            curl_close($ch);

            if($response===false || $http>=500){
                sleep(1);
                continue;
            }

            if($http<200 || $http>=300){
                $this->log("API HTTP ".$http." endpoint ".$endpoint);
                return null;
            }

            $json=json_decode($response);

            if(json_last_error()!==JSON_ERROR_NONE){
                $this->log("JSON ERROR endpoint ".$endpoint." response ".$response);
                return null;
            }

            if($method==="GET"){
                    self::$apiCache[$cacheKey]=[
                        "time"=>time(),
                        "data"=>$json
                    ];

            }

            return $json;

        }

        return null;

    }



    private function getUserId(?string $username):?int
    {

        if(!$username){
            return null;
        }

        $user=$this->apiRequest("GET","clients/name/".$username);

        if(!$user || empty($user->id)){
            return null;
        }

        return $user->id;

    }



    private function getUserDomains(int $userId):array
    {

        $domains=$this->apiRequest("GET","domains?user_id=".$userId);

        if(!$domains){
            $domains=$this->apiRequest("GET","domains");
        }

        if(!$domains || !is_array($domains)){
            return [];
        }

        $result=[];

        foreach($domains as $d){

            if(isset($d->id_user) && $d->id_user==$userId){

                if(empty($d->is_subdomain) || !$d->is_subdomain){
                    $result[]=$d->domain;
                }

            }

        }

        return $result;

    }



    public function testConnection():bool
    {

        $result=$this->apiRequest("GET","ping");

        if(!$result || !isset($result->response)){
            throw new Server_Exception("KeyHelp connection failed");
        }

        return true;

    }



    public function getLoginUrl(?Server_Account $account):string
    {

        if(!$account){
            return "https://".$this->_config['host'];
        }

        $username=$account->getUsername();

        $login=$this->apiRequest("GET","login/name/".$username);

        if($login && !empty($login->url)){
            return $login->url;
        }

        return "https://".$this->_config['host'];

    }



    public function getResellerLoginUrl(?Server_Account $account):string
    {
        return $this->getLoginUrl($account);
    }



    public function generateUsername(string $domain):string
    {

        $base=strtolower(explode('.',$domain)[0]);
        $base=preg_replace('/[^a-z0-9]/','',$base);

        if(strlen($base)<3){
            $base="user";
        }

        $base=substr($base,0,12);

        for($i=0;$i<=9999;$i++){

            $username=$i===0?$base:$base.$i;

            $check=$this->apiRequest("GET","clients/name/".$username);

            if(!$check || empty($check->id)){
                return $username;
            }

        }

        throw new Server_Exception("Unable to generate username");

    }



    private function generatePassword():string
    {
        return "Fs!".bin2hex(random_bytes(5))."@A";
    }



    public function createAccount(Server_Account $account):bool
    {

        $username=$account->getUsername();

        if($username){

            $existing=$this->apiRequest("GET","clients/name/".$username);

            if($existing && !empty($existing->id)){
                return true;
            }

        }

        $client=$account->getClient();

        $username=$this->generateUsername($account->getDomain());
        $password=$this->generatePassword();

        $plan=$this->apiRequest("GET","hosting-plans/name/".urlencode($account->getPackage()->getName()));

        if(!$plan || empty($plan->id)){
            throw new Server_Exception("Hosting plan not found");
        }

        $user=$this->apiRequest("POST","clients",[

            "username"=>$username,
            "email"=>$client->getEmail(),
            "password"=>$password,
            "language"=>"en",
            "id_hosting_plan"=>$plan->id,
            "create_system_domain"=>false

        ]);

        if(!$user || empty($user->id)){
            throw new Server_Exception("User creation failed");
        }

        $this->apiRequest("POST","domains",[

            "id_user"=>$user->id,
            "domain"=>$account->getDomain()

        ]);

        $account->setUsername($username);
        $account->setPassword($password);

        $this->log("Account created ".$username." for ".$account->getDomain());

        return true;

    }



    public function changeAccountPackage(Server_Account $account,Server_Package $package):bool
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return true;
        }

        $plan=$this->apiRequest("GET","hosting-plans/name/".urlencode($package->getName()));

        if(!$plan){
            return false;
        }

        $this->apiRequest("PUT","clients/".$userId,[

            "id_hosting_plan"=>$plan->id

        ]);

        return true;

    }



    public function changeAccountDomain(Server_Account $account,string $newDomain)
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return false;
        }

        $domains=$this->getUserDomains($userId);

        if(empty($domains)){
            return false;
        }

        $old=$domains[0];

        if(strtolower($old) === strtolower($newDomain)){
            return true;
        }

        $all=$this->apiRequest("GET","domains");

        foreach($all as $d){

            if(strtolower($d->domain) === strtolower($newDomain)){
                $this->log("Domain conflict ".$newDomain);
                return false;
            }

        }

        foreach($all as $d){

            if($d->domain==$old){

                $this->apiRequest("PUT","domains/".$d->id,[

                    "domain"=>$newDomain

                ]);

                $this->log("Domain changed via FOSSBilling ".$old." -> ".$newDomain);

                return true;

            }

        }

        return false;

    }



    public function changeAccountUsername(Server_Account $account,string $newUsername)
    {

        throw new Server_Exception("Changing the hosting username is not allowed because it may cause data loss. 
Please open a support ticket if you need assistance with this request.");

    }



    public function changeAccountPassword(Server_Account $account,string $newPassword):bool
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return true;
        }

        $this->apiRequest("PUT","clients/".$userId,[

            "password"=>$newPassword

        ]);

        return true;

    }



    public function suspendAccount(Server_Account $account):bool
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return true;
        }

        $this->apiRequest("PUT","clients/".$userId,[

            "is_suspended"=>true

        ]);

        return true;

    }



    public function unsuspendAccount(Server_Account $account):bool
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return true;
        }

        $this->apiRequest("PUT","clients/".$userId,[

            "is_suspended"=>false

        ]);

        return true;

    }



    public function cancelAccount(Server_Account $account):bool
    {

        $userId=$this->getUserId($account->getUsername());

        if(!$userId){
            return true;
        }

        $this->apiRequest("DELETE","clients/".$userId);

        $this->log("Account deleted ".$account->getUsername());

        return true;

    }



    public function changeAccountIp(Server_Account $account,string $newIp)
    {
        return false;
    }



    public function synchronizeAccount(?Server_Account $account=null)
    {

        if(!$account){
            return null;
        }

        try{

            $username=$account->getUsername();

            if(!$username){
                return $account;
            }

            $userId=$this->getUserId($username);

            if(!$userId){
                return $account;
            }

            $domains=$this->getUserDomains($userId);

            if(empty($domains)){
                return $account;
            }

            $newDomain=$domains[0];

            $oldDomain=$account->getDomain();

            if(strtolower($oldDomain)!==strtolower($newDomain)){

                $account->setDomain($newDomain);

                $this->log("Domain synced ".$oldDomain." -> ".$newDomain);

            }

        }catch(\Throwable $e){

            $this->log("sync error ".$e->getMessage());

        }

        return $account;

    }

}
