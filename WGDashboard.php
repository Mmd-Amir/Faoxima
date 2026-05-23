<?php
include('config.php');
require_once 'function.php';
require_once 'request.php';
ini_set('error_log', 'error_log');

function logCurlDiagnostics($context, $status, $curlError = null, $body = null, $jsonError = null)
{
    $message = sprintf(
        '%s response diagnostics - HTTP status: %s',
        $context,
        var_export($status, true)
    );

    if (!empty($curlError)) {
        $message .= sprintf(', curl_error: %s', $curlError);
    }

    if (!empty($jsonError)) {
        $message .= sprintf(', json_error: %s', $jsonError);
    }

    if ($body !== null) {
        $message .= sprintf(', raw: %s', substr((string) $body, 0, 1000));
    }

    error_log($message);
}

function decodeJsonResponse($response, string $context)
{
    if (!is_array($response)) {
        error_log($context . ' returned a non-array response.');
        return [
            'status' => false,
            'msg' => 'Unexpected response from ' . $context,
        ];
    }

    $httpStatus = $response['status'] ?? null;
    $curlError = $response['error'] ?? null;
    $body = $response['body'] ?? '';

    if ($curlError || $httpStatus === 0 || (is_numeric($httpStatus) && $httpStatus >= 400)) {
        logCurlDiagnostics($context, $httpStatus, $curlError, $body);
    }

    $decoded = json_decode((string) $body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logCurlDiagnostics($context, $httpStatus, $curlError, $body, json_last_error_msg());
        return [
            'status' => false,
            'msg' => 'Invalid JSON response from ' . $context,
            'http_status' => $httpStatus,
            'raw_body' => $body,
            'curl_error' => $curlError,
        ];
    }

    return [
        'status' => true,
        'data' => $decoded,
        'http_status' => $httpStatus,
        'curl_error' => $curlError,
    ];
}

function extractAvailableIp(array $availableIpResponse)
{
    if (empty($availableIpResponse['data']) || !is_array($availableIpResponse['data'])) {
        error_log('getAvailableIPs unexpected payload: ' . json_encode($availableIpResponse));
        return [
            'status' => false,
            'msg' => 'No available IPs',
        ];
    }

    foreach ($availableIpResponse['data'] as $value) {
        if (is_array($value)) {
            foreach ($value as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    return [
                        'status' => true,
                        'ip' => $candidate,
                    ];
                }
            }
        } elseif (is_string($value) && trim($value) !== '') {
            return [
                'status' => true,
                'ip' => $value,
            ];
        }
    }

    error_log('getAvailableIPs contained no usable IP entries: ' . json_encode($availableIpResponse['data']));
    return [
        'status' => false,
        'msg' => 'No available IPs',
    ];
}


function getWGPanelConfig($namepanel)
{
    $panelConfig = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (!is_array($panelConfig)) {
        return [
            'status' => false,
            'msg' => 'Panel not found',
        ];
    }

    foreach (["url_panel", "inboundid", "password_panel"] as $requiredKey) {
        if (!array_key_exists($requiredKey, $panelConfig) || $panelConfig[$requiredKey] === null || $panelConfig[$requiredKey] === '') {
            return [
                'status' => false,
                'msg' => 'Panel configuration missing ' . $requiredKey,
            ];
        }
    }

    return [
        'status' => true,
        'config' => $panelConfig,
    ];
}

function get_userwg($username,$namepanel){
    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $query = http_build_query(['configurationName' => $marzban_list_get['inboundid']]);
    $url = $marzban_list_get['url_panel']."/api/getWireguardConfigurationInfo?{$query}";
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    $parsedResponse = decodeJsonResponse($response, 'getWireguardConfigurationInfo');
    if ($parsedResponse['status'] === false) {
        return $parsedResponse;
    }
    $response = $parsedResponse['data'];
    if (!is_array($response)) {
        error_log('getWireguardConfigurationInfo returned an unexpected payload: ' . json_encode($response));
        return [
            'status' => false,
            'msg' => 'Invalid response from getWireguardConfigurationInfo',
        ];
    }
    if (array_key_exists('status', $response) && $response['status'] === false) {
        return $response;
    }
    if (empty($response['data']) || !is_array($response['data'])) {
        error_log('getWireguardConfigurationInfo missing data key: ' . json_encode($response));
        return [
            'status' => false,
            'msg' => 'Missing peer data in getWireguardConfigurationInfo response',
        ];
    }

    $configurationPeers = is_array($response['data']['configurationPeers'] ?? null) ? $response['data']['configurationPeers'] : [];
    $configurationRestrictedPeers = is_array($response['data']['configurationRestrictedPeers'] ?? null) ? $response['data']['configurationRestrictedPeers'] : [];
    $output = [];
    foreach ($configurationPeers as $userinfo){
        if($userinfo['name'] == $username){
            $output = $userinfo;
            break;
        }
    }
    if(count($output) != 0)return $output;
    foreach ($configurationRestrictedPeers as $userinfo){
        if($userinfo['name'] == $username){
            $output = $userinfo;
            $output['configuration']['Status'] = false;
            break;
        }
    }
    return $output;
}

function ipslast($namepanel){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/getAvailableIPs/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    return $response;
}
function downloadconfig($namepanel,$publickey){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel']."/api/downloadPeer/{$configuration}?id=".urlencode($publickey);
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    return $response;
}
function addpear($namepanel, $usernameac){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $pubandprivate = publickey();
    $ipconfig = ipslast($namepanel);
    $parsedIps = decodeJsonResponse($ipconfig, 'getAvailableIPs');
    if ($parsedIps['status'] === false) {
        return $parsedIps;
    }
    $ipconfig = $parsedIps['data'];
    if (!empty($ipconfig['status']) && $ipconfig['status'] == false) {
        return $ipconfig;
    }

    $availableIp = extractAvailableIp($ipconfig);
    if ($availableIp['status'] === false) {
        return $availableIp;
    }
    $ipconfig = $availableIp['ip'];
    $config = array(
        'name' => $usernameac,
        'allowed_ips' => [$ipconfig],
        'private_key' => $pubandprivate['private_key'],
        'public_key' => $pubandprivate['public_key'],
        'preshared_key' => $pubandprivate['preshared_key'],
    );
    $configpanel = json_encode($config);
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/addPeers/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    $result_response = $response['body'];
    $response['body'] = $config;
    $response['body']['response'] = $result_response;
    return $response;
}
function setjob($namepanel,$type,$value,$publickey){
    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $data = json_encode(array(
            "Job" => array(
                "JobID" =>  generateUUID(),
                "Configuration" => $marzban_list_get['inboundid'],
                "Peer" => $publickey,
    		"Field" => $type,
    		"Operator" => "lgt",
    		"Value" => strval($value),
    		"CreationDate" => "",
    		"ExpireDate" => null,
    		"Action" => "restrict"
		)));
	$url = $marzban_list_get['url_panel'].'/api/savePeerScheduleJob';
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($data);
    return $response;

}
function updatepear($namepanel,array $config){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $configpanel = json_encode($config,true);
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/updatePeerSettings/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    return $response;
}
function deletejob($namepanel,array $config){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $configpanel = json_encode($config);
    $url = $marzban_list_get['url_panel'].'/api/deletePeerScheduleJob';
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    return $response;
}
function ResetUserDataUsagewg($publickey, $namepanel){

    $panelResult = getWGPanelConfig($namepanel);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $config = array(
    "id" => $publickey,
    "type" => "total"
    );
    $configpanel = json_encode($config,true);
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/resetPeerData/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    file_put_contents('ss',json_encode($response));
    return $response;
}
function remove_userwg($location,$username){
    $panelResult = getWGPanelConfig($location);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $allowResponse = allowAccessPeers($location,$username);
    if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
        return $allowResponse;
    }
    $marzban_list_get = $panelResult['config'];
    $data_user = json_decode(select("invoice","user_info","username",$username,"select")['user_info'],true)['public_key'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/deletePeers/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post(json_encode(array(
  "peers" => array(
      $data_user
)
)));
    return $response;
}
function allowAccessPeers($location,$username){

    $panelResult = getWGPanelConfig($location);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $data_user = json_decode(select("invoice","user_info","username",$username,"select")['user_info'],true)['public_key'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/allowAccessPeers/'.$configuration;
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post(json_encode(array(
  "peers" => array(
      $data_user
)
)));
    return $response;
}
function restrictPeers($location,$username){

    $panelResult = getWGPanelConfig($location);
    if (!$panelResult['status']) {
        return $panelResult;
    }
    $marzban_list_get = $panelResult['config'];
    $data_user = json_decode(select("invoice","user_info","username",$username,"select")['user_info'],true)['public_key'];
    $configuration = rawurlencode($marzban_list_get['inboundid']);
    $url = $marzban_list_get['url_panel'].'/api/restrictPeers/'.$configuration;
    $payload = json_encode(array(
        "peers" => array(
            $data_user
        )
    ));
    $headers = array(
        'Content-Type: application/json',
        'wg-dashboard-apikey: '.$marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($payload);
    $parsedResponse = decodeJsonResponse($response, 'restrictPeers');
    if ($parsedResponse['status'] === false) {
        return $parsedResponse;
    }
    $response = $parsedResponse['data'];
    if (is_array($response) && array_key_exists('status', $response) && $response['status'] === false) {
        return $response;
    }

    return $response;
}

