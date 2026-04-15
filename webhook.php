<?php
// 1. आपकी जरूरी जानकारी (अपने असली डिटेल्स यहाँ डालें)
$telegram_bot_token = "8735358192:AAGU7LHN7oWFu4WWFchdq851uflp-f4dkGI"; // BotFather से मिला टोकन
$lgpay_app_id = "YD5038";
$lgpay_key = "WdX2XVTDnV8dmpc2GMl4EaDW9lMH2DTT"; 
$notify_url = "https://zynox-paybot.wasmer.app/callback.php"; // पेमेंट के बाद का कॉलबैक

// 2. टेलीग्राम से आया हुआ मैसेज पढ़ना
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"]; // जैसे: /pay 2000

    // चेक करें कि मैसेज /pay से शुरू होता है या नहीं
    if (strpos($text, "/pay") === 0) {
        // मैसेज से अमाउंट निकालें (जैसे 2000)
        $amount_array = explode(" ", $text);
        if(isset($amount_array[1]) && is_numeric($amount_array[1])) {
            $amount = $amount_array[1];
            
            // LGPay को रिक्वेस्ट भेजने का फंक्शन कॉल करें
            $payment_url = getLgPayLink($amount, $lgpay_app_id, $lgpay_key, $notify_url);
            
            if($payment_url) {
                sendMessage($chat_id, "यह रहा आपका पेमेंट लिंक (QR): " . $payment_url, $telegram_bot_token);
            } else {
                sendMessage($chat_id, "पेमेंट लिंक बनाने में कोई दिक्कत आई है।", $telegram_bot_token);
            }
        } else {
            sendMessage($chat_id, "कृपया सही फॉर्मेट का इस्तेमाल करें। उदाहरण: /pay 2000", $telegram_bot_token);
        }
    }
}

// 3. LGPay से लिंक माँगने का फंक्शन (डॉक्यूमेंट के आधार पर)
function getLgPayLink($amount, $app_id, $key, $notify_url) {
    $url = "https://www.lg-pay.com/api/order/create";
    
    // ऑर्डर नंबर बनाना (ताकि हर ऑर्डर अलग हो)
    $order_sn = time() . rand(100, 999); 
    
    // LGPay के पैरामीटर्स (डॉक्यूमेंट के अनुसार)
    $params = [
        'app_id' => $app_id,
        'trade_type' => 'test', // अभी टेस्ट मोड
        'order_sn' => $order_sn,
        'money' => $amount * 100, // रुपये को पैसे (units) में बदलना
        'notify_url' => $notify_url,
        'ip' => '127.0.0.1', // सर्वर का IP या यूजर का IP
        'remark' => 'Cloud Kitchen Food Order' // कोई भी रिमार्क
    ];

    // Signature Algorithm (डॉक्यूमेंट के नियम: ASCII sort -> string -> md5 -> uppercase)
    ksort($params); // A-Z के क्रम में सजाना
    $stringA = "";
    foreach ($params as $k => $v) {
        if ($v !== "") {
            $stringA .= $k . "=" . $v . "&";
        }
    }
    $stringSignTemp = $stringA . "key=" . $key;
    $sign = strtoupper(md5($stringSignTemp));
    
    $params['sign'] = $sign;

    // LGPay को POST रिक्वेस्ट भेजना
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // रिजल्ट को समझना
    $result = json_decode($response, true);
    if ($result && $result['status'] == 1) {
        return $result['data']['pay_url']; // लिंक वापस करना
    } else {
        return false;
    }
}

// 4. टेलीग्राम पर मैसेज भेजने का फंक्शन
function sendMessage($chat_id, $message, $token) {
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chat_id;
    $url .= "&text=" . urlencode($message);
    file_get_contents($url);
}
?>
