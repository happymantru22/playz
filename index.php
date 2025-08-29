<?php
// ======== INITIAL SETTINGS ========
error_reporting(0);
set_time_limit(30);
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
$portal = "http://z.zcatt.cc/stalker_portal";
$mac    = "00:1A:79:B8:26:FE";
$sn     = "8DB8DE5515499";
$device_id = "8C0100B4FC1B10AD006F79699ED1B86BC716D3881DB7CC13D4AAE67F0B3A1414";

// ======== INPUT CHECK ========
$channel = isset($_GET['ch']) ? trim($_GET['ch']) : exit("Channel ID missing (?ch=xxx)");

// ======== CURL REQUEST FUNCTION ========
function stalker_request($url, $headers = [], $post = null) {
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
        // Set short timeout values to reduce lag
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($ch);
}

// ======== HEADER BUILDER ========
function build_headers($token = '') {
    global $mac, $portal;
    return [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 MAG200 stbapp",
        "Authorization: Bearer $token",
        "Accept: */*",
        "Referer: {$portal}/c/",
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Cookie: mac=$mac; stb_lang=en; timezone=Asia/Kolkata;",
    ];
}

// ======== STEP 1: HANDSHAKE ========
$handshake_url = "{$portal}/server/load.php?type=stb&action=handshake&JsHttpRequest=1-xml";
$handshake_data = json_decode(stalker_request($handshake_url, build_headers()), true);
$token = isset($handshake_data["js"]["token"]) ? $handshake_data["js"]["token"] : exit("Handshake failed");

// ======== STEP 2: AUTHORIZE ========
$auth_url = "{$portal}/server/load.php?type=stb&action=authorize&JsHttpRequest=1-xml";
$auth_payload = [
    "mac"       => $mac,
    "sn"        => $sn,
    "device_id" => $device_id,
    "device_id2"=> $device_id
];
$auth_data = json_decode(stalker_request($auth_url, build_headers($token), $auth_payload), true);
if (!isset($auth_data["js"]["id"])) {
    exit("Authorization failed");
}

// ======== STEP 3: GET PROFILE ========
$profile_url = "{$portal}/server/load.php?type=stb&action=get_profile&JsHttpRequest=1-xml";
$profile_data = json_decode(stalker_request($profile_url, build_headers($token)), true);
if (!isset($profile_data["js"]["id"])) {
    exit("Profile retrieval failed");
}

// ======== STEP 4: FETCH STREAM LINK ========
$stream_url = "{$portal}/server/load.php?type=itv&action=create_link&cmd=ffmpeg%20http://localhost/ch/{$channel}&JsHttpRequest=1-xml";
$stream_data = json_decode(stalker_request($stream_url, build_headers($token)), true);
$cmd = isset($stream_data["js"]["cmd"]) ? $stream_data["js"]["cmd"] : exit("Stream link fetch failed");

// ======== FINAL STEP: REDIRECT TO STREAM ========
header("Location: $cmd");
exit;
?>
