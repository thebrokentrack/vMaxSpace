<?php
// Enable CORS for frontend access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Base");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Target Mail APIs
$defaultBases = ['https://api.mail.tm', 'https://api.mail.gw'];

// Get headers & inputs
$headers = getallheaders();
$customBase = isset($_SERVER['HTTP_X_API_BASE']) ? $_SERVER['HTTP_X_API_BASE'] : (isset($headers['X-Api-Base']) ? $headers['X-Api-Base'] : null);
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '/domains';
$method = $_SERVER['REQUEST_METHOD'];
$inputBody = file_get_contents('php://input');

// Determine API base list order
$apisToTry = $defaultBases;
if ($customBase && in_array($customBase, $defaultBases)) {
    $apisToTry = array_merge([$customBase], array_diff($defaultBases, [$customBase]));
}

$response = null;
$httpCode = 500;
$workingBase = '';

// Failover execution loop
foreach ($apisToTry as $baseUrl) {
    $targetUrl = $baseUrl . $endpoint;
    
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $requestHeaders = ['Content-Type: application/json'];
    
    // Pass Bearer Token if present
    if (isset($headers['Authorization'])) {
        $requestHeaders[] = 'Authorization: ' . $headers['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $requestHeaders[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    
    if ($method === 'POST' && !empty($inputBody)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $inputBody);
    }
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($res !== false && $code >= 200 && $code < 500) {
        $response = $res;
        $httpCode = $code;
        $workingBase = $baseUrl;
        break;
    }
}

if ($response !== null) {
    header("X-Working-Base: " . $workingBase);
    http_response_code($httpCode);
    echo $response;
} else {
    http_response_code(502);
    echo json_encode(["error" => "All backend mail APIs failed to connect."]);
}
?>