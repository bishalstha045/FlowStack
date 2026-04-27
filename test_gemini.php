<?php
$key = 'AIzaSyCviQ5OLRoO2Xr9rfURBpKC3JdXI5pf7fE';

// gemini-2.0-flash is alive (429 = quota but model exists). Try v1 instead of v1beta
$tests = [
    'v1beta/gemini-2.0-flash'       => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $key,
    'v1/gemini-2.0-flash'           => 'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . $key,
    'v1beta/gemini-2.0-flash-lite'  => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $key,
    'v1beta/gemini-2.5-flash'       => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $key,
    'v1beta/gemini-2.5-flash-preview-04-17' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent?key=' . $key,
];

foreach ($tests as $label => $url) {
    $data = json_encode(['contents'=>[['parts'=>[['text'=>'Say hello.']]]],'generationConfig'=>['maxOutputTokens'=>20]]);
    $opts = ['http'=>['method'=>'POST','header'=>'Content-type: application/json','content'=>$data,'timeout'=>12]];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    $code = isset($http_response_header[0]) ? $http_response_header[0] : 'no response';
    echo "$label → $code\n";
    if ($res !== false && strpos($code, '200') !== false) {
        $json = json_decode($res, true);
        echo "  ✅ Reply: " . trim($json['candidates'][0]['content']['parts'][0]['text'] ?? 'N/A') . "\n";
    } elseif ($res !== false) {
        $j = json_decode($res, true);
        echo "  Body: " . ($j['error']['message'] ?? substr($res,0,120)) . "\n";
    }
}
