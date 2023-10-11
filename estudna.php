<?php

/**
 * CML - How to get value of water level for you eStudna device
 * 
 * 2022-05-17 v1-01
 * a) Project foundation
 */

// ----------------------------------------------------------------------------
// --- Configuration
// ----------------------------------------------------------------------------

// --- Configuration
$user = 'user@email.com';
$password = 'supersecretpassword';
$sn = 'SB821035';   // Serial number of your eSTUDNA



// ----------------------------------------------------------------------------
// --- Code
// ----------------------------------------------------------------------------

function httpPost($url,$data,$header)
{
    
    // use key 'http' even if you send the request to https://...
    $options = array
    (
        'http' => array
        (
            'header'  => 'Content-Type: application/json\r\n' . $header,
            'method'  => 'POST',
            'content' => json_encode($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE)
    {
        throw new Exception('HTTP request failed!');
    }

    return $result;
}

function httpGet($url,$header)
{
    
    // use key 'http' even if you send the request to https://...
    $options = array
    (
        'http' => array
        (
            'header'  => $header,
            'method'  => 'GET',
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE)
    {
        throw new Exception('HTTP request failed!');
    }

    return $result;
}

/**
 * Objekt pro pristup k Thingsboardu
 */
class ThingsBoard
{
    public $server =  'https://cml.seapraha.cz';
    public $userToken = null;
    public $customerId = null;

    /**
     * Prihlaseni uzivatele
     */
    public function login($user,$password)
    {
        // Login
        $url = $this->server . '/api/auth/login';
        $result = httpPost($url,array('username' => $user, 'password' => $password),"");

        $response = json_decode($result);
        $this->userToken = $response->token;    // User token

        // Get customer ID
        $url = $this->server . '/api/auth/user';
        $result = httpGet($url,'X-Authorization: Bearer ' . $this->userToken );
        
        $response = json_decode($result);
        $this->customerId = $response->customerId->id; // Customer ID
            
    }

    /**
     * Vyhledani zarizeni podle nazvu
     */
    public function getDevicesByName($name)
    {
        $url = $this->server . '/api/customer/' . $this->customerId . '/devices?pageSize=100&page=0&&textSearch='.urlencode($name);
        
        $result = httpGet($url, 'X-Authorization: Bearer ' . $this->userToken);
        $response = json_decode($result);
        if ($response->totalElements < 1)
            throw new Exception('Device SN ' . $name . ' has not been found!');

        return $response->data;         // Return list of devices
    }

    /**
     * Cteni aktualnich hodnot ze zarizeni
     */
    public function getDeviceValues($deviceId, $keys)
    {
        $url = $this->server . '/api/plugins/telemetry/DEVICE/' . $deviceId .'/values/timeseries?keys=' . $keys;
        
        $result = httpGet($url,'X-Authorization: Bearer ' . $this->userToken);
        $response = json_decode($result);
  
        return $response;
    }

    public function getDeviceValuesHistory($deviceId, $keys, $startTs, $endTs)
    {
        $url = $this->server . '/api/plugins/telemetry/DEVICE/' . $deviceId .'/values/timeseries?keys=' . $keys . '&startTs=' . $startTs . '&endTs=' . $endTs;
        
        $result = httpGet($url,'X-Authorization: Bearer ' . $this->userToken);
        $response = json_decode($result);
  
        return $response;
    }
}

/**
 * Cteni hladiny ve studni
 */
function eStudna_GetWaterLevel($user,$password,$serialNumber)
{
    $tb = new ThingsBoard();
    $tb->login($user,$password);
    $devices = $tb->getDevicesByName( '%' . $serialNumber);
    $values = $tb->getDeviceValues($devices[0]->id->id,'ain1');
    return $values->ain1[0]->value;  
}

function eStudna_GetWaterLevelHistory($user,$password,$serialNumber,$from,$until)
{
    $tb = new ThingsBoard();
    $tb->login($user,$password);
    $devices = $tb->getDevicesByName( '%' . $serialNumber);
    $values = $tb->getDeviceValues($devices[0]->id->id,'ain1');
    return $values;
}


// ----------------------------------------------------------------------------
// --- Main code
// ----------------------------------------------------------------------------
#TODO: try the new function
try
{
  $level = eStudna_GetWaterLevelHistory($user, $password, $sn, 1696848456, 1697194056);
  echo('<pre><b>' . $sn . '</b></pre>');
  echo('<pre>WATER LEVEL [m]: '.$level.'</pre>');
} catch (Exception $e) {
    echo('<pre>[ EXCEPTION] - ' . $e->getMessage() . '</pre>');
}

?>
