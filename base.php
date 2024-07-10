<?php

// Enable error reporting
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ERROR | E_PARSE);

function getTelegramChannelConfigs($username)
{
    $sourceArray = explode(",", $username);
    foreach ($sourceArray as $source) {
        echo "@{$source} => 0%\n";
        $html = file_get_contents("https://t.me/s/" . $source);
        
        $types = ["vmess", "vless", "trojan", "ss", "tuic", "hysteria", "hysteria2", "hy2"];
        $configs = [];
        foreach ($types as $type) {
            if ($type === "hy2") {
                $configs["hysteria2"] = array_merge(getConfigItems($type, $html), $configs["hysteria2"]);
            } else {
                $configs[$type] = getConfigItems($type, $html);
            }
        }
        echo "@{$source} => 50%\n";
        $output = [];
        $bySource = [];
        $byType = [
            "mix" => '',
            "vmess" => '',
            "vless" => '',
            "trojan" => '',
            "ss" => '',
            "tuic" => '',
            "hysteria" => '',
            "hysteria2" => ''
        ];
        foreach ($configs as $type => $configsArray) {
            foreach ($configsArray as $config) {
                if (is_valid($config)) {
                    $fixedConfig = str_replace("amp;", "", removeAngleBrackets($config));
                    $correctedConfig = correctConfig("{$fixedConfig}", $type, $source);
                    $bySource[$source] .= "{$correctedConfig}\n";
                    $byType[$type] .= "{$correctedConfig}\n";
                    $byType["mix"] .= "{$correctedConfig}\n";
                }
            }
        }
        echo "@{$source} => 100%\n";
    }
    return [
        "updateTime" => time(),
        "byType" => $byType,
        "bySource" => $bySource
    ];
}

function configParse($input, $configType)
{
    if ($configType === "vmess") {
        $vmess_data = substr($input, 8);
        $decoded_data = json_decode(base64_decode($vmess_data), true);
        return $decoded_data;
    } elseif (in_array($configType, ["vless", "trojan", "tuic", "hysteria", "hysteria2", "hy2"])) {
        $parsedUrl = parse_url($input);
        $params = [];
        if (isset($parsedUrl["query"])) {
            parse_str($parsedUrl["query"], $params);
        }
        $output = [
            "protocol" => $configType,
            "username" => isset($parsedUrl["user"]) ? $parsedUrl["user"] : "",
            "hostname" => isset($parsedUrl["host"]) ? $parsedUrl["host"] : "",
            "port" => isset($parsedUrl["port"]) ? $parsedUrl["port"] : "",
            "params" => $params,
            "hash" => isset($parsedUrl["fragment"])
                ? $parsedUrl["fragment"]
                : "TVC" . getRandomName(),
        ];

        if ($configType === "tuic") {
            $output["pass"] = isset($parsedUrl["pass"])
                ? $parsedUrl["pass"]
                : "";
        }
        return $output;
    } elseif ($configType === "ss") {
        $url = parse_url($input);
        if (isBase64($url["user"])) {
            $url["user"] = base64_decode($url["user"]);
        }
        list($encryption_method, $password) = explode(
            ":",
            $url["user"]
        );
        $server_address = $url["host"];
        $server_port = $url["port"];
        $name = isset($url["fragment"]) ? urldecode($url["fragment"]) : "TVC" . getRandomName();
        $server = [
            "encryption_method" => $encryption_method,
            "password" => $password,
            "server_address" => $server_address,
            "server_port" => $server_port,
            "name" => $name,
        ];
        return $server;
    }
}

function reparseConfig($configArray, $configType)
{
    if ($configType === "vmess") {
        $encoded_data = base64_encode(json_encode($configArray));
        $vmess_config = "vmess://" . $encoded_data;
        return $vmess_config;
    } elseif (in_array($configType, ["vless", "trojan", "tuic", "hysteria", "hysteria2", "hy2"])) {
        $url = $configType . "://";
        $url .= addUsernameAndPassword($configArray);
        $url .= $configArray["hostname"];
        $url .= addPort($configArray);
        $url .= addParams($configArray);
        $url .= addHash($configArray);
        return $url;
    } elseif ($configType === "ss") {
        $user = base64_encode(
            $configArray["encryption_method"] . ":" . $configArray["password"]
        );
        $url = "ss://$user@{$configArray["server_address"]}:{$configArray["server_port"]}";
        if (!empty($configArray["name"])) {
            $url .= "#" . str_replace(" ", "%20", $configArray["name"]);
        }
        return $url;
    }
}

function addUsernameAndPassword($obj)
{
    $url = "";
    if ($obj["username"] !== "") {
        $url .= $obj["username"];
        if (isset($obj["pass"]) && $obj["pass"] !== "") {
            $url .= ":" . $obj["pass"];
        }
        $url .= "@";
    }
    return $url;
}

function addPort($obj)
{
    $url = "";
    if (isset($obj["port"]) && $obj["port"] !== "") {
        $url .= ":" . $obj["port"];
    }
    return $url;
}

function addParams($obj)
{
    $url = "";
    if (!empty($obj["params"])) {
        $url .= "?" . http_build_query($obj["params"]);
    }
    return $url;
}

function addHash($obj)
{
    $url = "";
    if (isset($obj["hash"]) && $obj["hash"] !== "") {
        $url .= "#" . str_replace(" ", "%20", $obj["hash"]);
    }
    return $url;
}

/*function is_reality($input)
{
    $type = detect_type($input);
    if (stripos($input, "reality") !== false && $type === "vless") {
        return true;
    }
    return false;
}*/

function isBase64($input)
{
    if (base64_encode(base64_decode($input)) === $input) {
        return true;
    }

    return false;
}

function getRandomName() {
    $alphabet = 'abcdefghijklmnopqrstuvwxyz';
    $name = '';
    for ($i = 0; $i < 10; $i++) {
      // Get a random letter from the alphabet
      $randomLetter = $alphabet[rand(0, strlen($alphabet) - 1)];
      // Add the letter to the name string
      $name .= $randomLetter;
    }
    return $name;
  }

function correctConfig ($config, $type, $source) {
    $configsHashName = [
        "vmess" => "ps",
        "vless" => "hash",
        "trojan" => "hash",
        "tuic" => "hash",
        "hysteria" => "hash",
        "hysteria2" => "hash",
        "hy2" => "hash",
        "ss" => "name",
    ];
    $configHashName = $configsHashName[$type];

    $parsedConfig = configParse($config, $type);
    $configHashTag = generateName($parsedConfig, $type, $source);
    $parsedConfig[$configHashName] = $configHashTag;

    $rebuildedConfig = reparseConfig($parsedConfig, $type);
    return $rebuildedConfig;
}

function is_ip($string)
{
    $ip_pattern = '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/';
    if (preg_match($ip_pattern, $string)) {
        return true;
    } else {
        return false;
    }
}

function convertToJson($input) {
    // Split the input string by newline
    $lines = explode("\n", $input);

    // Initialize an empty array to store the key-value pairs
    $data = [];

    // Loop through each line
    foreach ($lines as $line) {
        // Split the line by the equals sign
        $parts = explode("=", $line);

        // If the line has an equals sign and is not empty
        if (count($parts) == 2 && !empty($parts[0]) && !empty($parts[1])) {
            // Trim any whitespace from the key and value
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Add the key-value pair to the data array
            $data[$key] = $value;
        }
    }

    // Convert the data array to a JSON string
    $json = json_encode($data);

    return $json;
}

function ip_info($ip)
{
    // Check if the IP is from Cloudflare
    /*if (is_cloudflare_ip($ip)) {
        $traceUrl = "http://$ip/cdn-cgi/trace";
        $traceData = convertToJson(file_get_contents($traceUrl));
        $country = $traceData['loc'] ?? "CF";
        return (object) [
            "country" => $country,
        ];
    }*/

    if (is_ip($ip) === false) {
        $ip_address_array = dns_get_record($ip, DNS_A);
        if (empty($ip_address_array)) {
            return null;
        }
        $randomKey = array_rand($ip_address_array);
        $ip = $ip_address_array[$randomKey]["ip"];
    }

    // List of API endpoints
    $endpoints = [
        "https://ipapi.co/{ip}/json/",
        "https://ipwhois.app/json/{ip}",
        "http://www.geoplugin.net/json.gp?ip={ip}",
        "https://api.ipbase.com/v1/json/{ip}",
    ];

    // Initialize an empty result object
    $result = (object) [
        "country" => "XX",
    ];

    // Loop through each endpoint
    foreach ($endpoints as $endpoint) {
        // Construct the full URL
        $url = str_replace("{ip}", $ip, $endpoint);

        $options = [
            "http" => [
                "header" =>
                    "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n", // i.e. An iPad
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response);

            // Extract relevant information and update the result object
            if ($endpoint == $endpoints[0]) {
                // Data from ipapi.co
                $result->country = $data->country_code ?? "XX";
            } elseif ($endpoint == $endpoints[1]) {
                // Data from ipwhois.app
                $result->country = $data->country_code ?? "XX";
            } elseif ($endpoint == $endpoints[2]) {
                // Data from geoplugin.net
                $result->country = $data->geoplugin_countryCode ?? "XX";
            } elseif ($endpoint == $endpoints[3]) {
                // Data from ipbase.com
                $result->country = $data->country_code ?? "XX";
            }
            // Break out of the loop since we found a successful endpoint
            break;
        }
    }

    return $result;
}

function is_cloudflare_ip($ip)
{
    // Get the Cloudflare IP ranges
    $cloudflare_ranges = file_get_contents('https://raw.githubusercontent.com/ircfspace/cf-ip-ranges/main/export.ipv4');
    $cloudflare_ranges = explode("\n", $cloudflare_ranges);

    foreach ($cloudflare_ranges as $range) {
        if (cidr_match($ip, $range)) {
            return true;
        }
    }

    return false;
}

function cidr_match($ip, $range) {
    list($subnet, $bits) = explode('/', $range);
    if ($bits === null) {
        $bits = 32;
    }
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;
    return ($ip & $mask) == $subnet;
}

function getFlags($country_code)
{
    $flag = mb_convert_encoding(
        "&#" . (127397 + ord($country_code[0])) . ";",
        "UTF-8",
        "HTML-ENTITIES"
    );
    $flag .= mb_convert_encoding(
        "&#" . (127397 + ord($country_code[1])) . ";",
        "UTF-8",
        "HTML-ENTITIES"
    );
    return $flag;
}

function generateName($config, $type, $source) {
    $configsTypeName = [
        "vmess" => "VM",
        "vless" => "VL",
        "trojan" => "TR",
        "tuic" => "TU",
        "hysteria" => "HY",
        "hysteria2" => "HY2",
        "hy2" => "HY2",
        "ss" => "SS",
    ];
    $configsIpName = [
        "vmess" => "add",
        "vless" => "hostname",
        "trojan" => "hostname",
        "tuic" => "hostname",
        "hysteria" => "hostname",
        "hysteria2" => "hostname",
        "hy2" => "hostname",
        "ss" => "server_address",
    ];
    $configsPortName = [
        "vmess" => "port",
        "vless" => "port",
        "trojan" => "port",
        "tuic" => "port",
        "hysteria" => "port",
        "hysteria2" => "port",
        "hy2" => "port",
        "ss" => "server_port",
    ];

    $configIpName = $configsIpName[$type];
    $configPortName = $configsPortName[$type];
    
    $configIp = $config[$configIpName];
    $configPort = $config[$configPortName];
    $configLocation = ip_info($configIp)->country ?? "XX";
    $configFlag = $configLocation === "XX" ? "❔" : ($configLocation === "CF" ? "🚩" : getFlags($configLocation));
    $isEncrypted = isEncrypted($config, $type) ? "🔒" : "🔓";
    $configType = $configsTypeName[$type];
    $configNetwork = getNetwork($config, $type);
    $configTLS = getTLS($config, $type);

    $lantency = ping($configIp, $configPort, 1);

    return "🆔{$source} {$isEncrypted} {$configType}-{$configNetwork}-{$configTLS} {$configFlag} {$configLocation} {$lantency}";
}

function getNetwork($config, $type) {
    if ($type === "vmess") return strtoupper($config['net']);
    if (in_array($type, ["vless", "trojan"])) return strtoupper($config['params']['type']);
    if (in_array($type, ["tuic", "hysteria", "hysteria2", "hy2"])) return "UDP";
    if ($type === "ss") return "TCP";
    return null;
}

function getTLS($config, $type) {
    if ($type === "vmess" && $config['tls'] === "tls") return "TLS";
    if ($type === "vmess" && $config['tls'] === "") return "N/A";
    if (in_array($type, ["vless", "trojan"]) && $config['params']['security'] === "tls") return "TLS";
    if (in_array($type, ["vless", "trojan"]) && $config['params']['security'] === "none") return "N/A";
    if (in_array($type, ["vless", "trojan"]) && empty($config['params']['security'])) return "N/A";
    if (in_array($type, ["tuic", "hysteria", "hysteria2", "hy2"])) return "N/A";
    if ($type === "ss") return "TCP";
    return null;
}

function isEncrypted($config, $type) {

    if ($type === "vmess" && $config['tls'] !== "" && $config['scy'] !== "none") {
        return true;
    } elseif (in_array($type, ["vless", "trojan"]) && !empty($config['params']['security']) && $config['params']['security'] !== "none") {
        return true;
    } elseif (in_array($type, ["ss", "tuic", "hysteria", "hysteria2", "hy2"])) {
        return true;
    } 
    return false;
}

function getConfigItems($prefix, $string) {
    $regex = '~[a-z]+://\\S+~i';;
    preg_match_all($regex, $string, $matches);
    $count = strlen($prefix) + 3;
    $output = [];
    foreach($matches[0] as $match) {
        $newMatches = explode("<br/>", $match);
        foreach ($newMatches as $newMatch) {
            if (substr($newMatch, 0, $count) === "{$prefix}://"){
                $output[] = $newMatch;
            }
        }
    }
    return $output;
}


function is_valid($input)
{
    if (stripos($input, "…") !== false or stripos($input, "...") !== false) {
        return false;
    }
    return true;
}

function removeAngleBrackets($link) {
    return preg_replace('/<.*?>/', '', $link);
}

function ping($host, $port, $timeout) {
    $tB = microtime(true);
    $fP = fSockOpen($host, $port, $errno, $errstr, $timeout);
    if (!$fP) {
        return "down";
    }
    $tA = microtime(true);
    return round((($tA - $tB) * 1000), 0) . "ms";
}


function sendMessage($botToken, $chatId, $message, $parse_mode, $keyboard)
{
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $data = [
        "chat_id" => $chatId,
        "text" => $message,
        "parse_mode" => $parse_mode,
        "disable_web_page_preview" => true,
        "reply_markup" => json_encode([
            "inline_keyboard" => $keyboard,
        ]),
    ];

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($curl);

    curl_close($curl);

    echo $response;
}

function generateHiddifyTags() {
    $profileTitle = base64_encode("HiN Miner 🫧");
    return "#profile-title: base64:{$profileTitle}\n#profile-update-interval: 1\n#subscription-userinfo: upload=0; download=0; total=10737418240000000; expire=2546249531\n#support-url: https://hingroup.t.me\n#profile-web-page-url: https://Here_is_Nowhere.t.me
";
}

function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

function getTehranTime() {
    // Set the timezone to Tehran
    date_default_timezone_set('Asia/Tehran');

    // Get the current date and time in Tehran
    $date = new DateTime();

    // Get the day of the week in English
    $dayOfWeek = $date->format('D');

    // Get the day of the month
    $day = $date->format('d');

    // Get the month and year
    $month = (int)$date->format('m');
    $year = (int)$date->format('Y');

    // Convert Gregorian date to Jalali date
    list($jy, $jm, $jd) = gregorianToJalali($year, $month, $day);

    // Map Persian month names to their short forms
    $monthNames = [
        1 => 'FAR',
        2 => 'ORD',
        3 => 'KHORDAD',
        4 => 'TIR',
        5 => 'MORDAD',
        6 => 'SHAHRIVAR',
        7 => 'MEHR',
        8 => 'ABAN',
        9 => 'AZAR',
        10 => 'DEY',
        11 => 'BAHMAN',
        12 => 'ESFAND'
    ];
    $shortMonth = $monthNames[$jm];

    // Get the time in 24-hour format
    $time = $date->format('H:i');

    // Construct the final formatted string
    $formattedString = sprintf('%s-%02d-%s-%04d 🕑 %s', $dayOfWeek, $jd, $shortMonth, $jy, $time);

    return $formattedString;
}

function generateUpdateTime() {
    $tehranTime = getTehranTime();
    return "vless://aaacbbc-cbaa-aabc-dacb-acbacbbcaacb@127.0.0.1:1080?security=tls&type=tcp#⚠️%20FREE%20TO%20USE!\nvless://aaacbbc-cbaa-aabc-dacb-acbacbbcaacb@127.0.0.1:1080?security=tls&type=tcp#🔄%20LATEST-UPDATE%20📅%20{$tehranTime}\n";
}

function generateEndofConfiguration() {
    return "vless://acbabca-acab-bcaa-abdc-bbccaabaccab@127.0.0.1:8080?security=tls&type=tcp#👨🏻‍💻%20DEVELOPED-BY%20@YEBEKHE\nvless://acbabca-acab-bcaa-abdc-bbccaabaccab@127.0.0.1:8080?security=tls&type=tcp#📌%20SUPPORT-CONTACT @HiNGROUP.T.ME";
}

$source = file_get_contents("source.conf");
$configs = getTelegramChannelConfigs($source);

foreach ($configs as $sort => $sortedConfigs) {
    if ($sort === "byType") {
        foreach ($sortedConfigs as $type => $sortedConfigsList){
           $configsList = generateUpdateTime() . $sortedConfigsList . generateEndofConfiguration();
           $configsListHiddify = generateHiddifyTags() . "\n" . $configsList;
           file_put_contents('subscription/normal/' . $type, $configsList);
           file_put_contents('subscription/base64/' . $type, base64_encode($configsList));
           file_put_contents('subscription/hiddify/' . $type, base64_encode($configsListHiddify));
        }
    } elseif ($sort === "bySource") {
        foreach ($sortedConfigs as $source => $sortedConfigsList) {
            $configsList = generateUpdateTime() . $sortedConfigsList . generateEndofConfiguration();
            $configsListHiddify = generateHiddifyTags() . "\n" . $configsList;
            file_put_contents('subscription/source/normal/' . $source, $configsList);
            file_put_contents('subscription/source/base64/' . $source, base64_encode($configsList));
            file_put_contents('subscription/source/hiddify/' . $source, base64_encode($configsListHiddify));
        }
    }
}

file_put_contents("result.json", json_encode($configs, JSON_PRETTY_PRINT));

$tehranTime = getTehranTime();
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$keyboard = [
    [
        [
            "text" => "🚹 گیتهاب HiN VPN 🚹", 
            "url" => "https://github.com/itsyebekhe/HiN-VPN/tree/main/subscription"
        ]
    ]
];

$message = "🔺 لینک های اشتراک HiN بروزرسانی شدن! 🔻

⏱ آخرین آپدیت: 
{$tehranTime}

🔎 MiX: <code>https://raw.githubusercontent.com/itsyebekhe/HiN-VPN/main/subscription/base64/mix</code>
🔎 SHADOWSOCKS: <code>https://raw.githubusercontent.com/itsyebekhe/HiN-VPN/main/subscription/base64/ss</code>

💥 برای لینک های بیشتر وارد گیتهاب پروژه بشید

🌐 <a href='https://t.me/Here_is_Nowhere'>𝗛.𝗜.𝗡 🫧</a>";

//sendMessage($botToken, -1002043507701, $message, "html", $keyboard);
