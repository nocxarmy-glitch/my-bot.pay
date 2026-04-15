<?php

// ================= CONFIG =================
$telegram_bot_token = "8735358192:AAGU7LHN7oWFu4WWFchdq851uflp-f4dkGI";
$lgpay_app_id = "YD5038";
$lgpay_key = "WdX2XVTDnV8dmpc2GMl4EaDW9lMH2DTT";
$notify_url = "https://https://zynox-paybot.wasmer.app/callback.php";

// ================= DEBUG LOG =================
file_put_contents("log.txt", file_get_contents("php://input") . PHP_EOL, FILE_APPEND);

// ================= READ TELEGRAM =================
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// अगर कुछ भी नहीं आया → stop
if (!$update) {
    exit;
}

if (isset($update["message"])) {

    $chat_id = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"] ?? '';

    // ================= COMMAND =================
    if (strpos($text, "/pay") === 0) {

        $amount_array = explode(" ", $text);

        if (isset($amount_array[1]) && is_numeric($amount_array[1])) {

            $amount = $amount_array[1];

            $payment_url = getLgPayLink($amount);

            if ($payment_url) {
                sendMessage($chat_id, "Payment Link: " . $payment_url);
            } else {
                sendMessage($chat_id, "Error creating payment link.");
            }

        } else {
            sendMessage($chat_id, "Usage: /pay 100");
        }
    }
}

// ================= LG PAY =================
function getLgPayLink($amount) {

    global $lgpay_app_id, $lgpay_key, $notify_url;

    $url = "https://www.lg-pay.com/api/order/create";

    $order_sn = time() . rand(100,999);

    $params = [
        'app_id' => $lgpay_app_id,
        'trade_type' => 'test',
        'order_sn' => $order_sn,
        'money' => $amount * 100,
        'notify_url' => $notify_url,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'remark' => 'Test Order'
    ];

    ksort($params);

    $stringA = "";
    foreach ($params as $k => $v) {
        if ($v !== "") {
            $stringA .= $k . "=" . $v . "&";
        }
    }

    $sign = strtoupper(md5($stringA . "key=" . $lgpay_key));
    $params['sign'] = $sign;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);

    file_put_contents("log.txt", $response . PHP_EOL, FILE_APPEND);

    $result = json_decode($response, true);

    if ($result && isset($result['status']) && $result['status'] == 1) {
        return $result['data']['pay_url'];
    }

    return false;
}

// ================= TELEGRAM SEND =================
function sendMessage($chat_id, $message) {

    global $telegram_bot_token;

    $url = "https://api.telegram.org/bot".$telegram_bot_token."/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

?>
