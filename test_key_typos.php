<?php
$keys = [
    'AIzaSyCM0R8swHO2sY8WQQLxlh9MScozMdjD1NM', // current
    'AIzaSyCMOR8swHO2sY8WQQLxlh9MScozMdjD1NM', // O instead of 0
    'AIzaSyCM0R8swH02sY8WQQLxlh9MScozMdjD1NM', // 0 instead of O
    'AIzaSyCMOR8swH02sY8WQQLxlh9MScozMdjD1NM', // O and 0 swapped
    'AIzaSyCM0R8swHO2sY8WQQLxIh9MScozMdjD1NM', // I instead of l
    'AIzaSyCM0R8swHO2sY8WQQLx1h9MScozMdjD1NM', // 1 instead of l
    'AIzaSyCM0R8swHO2sY8WQQLxlh9MSc0zMdjD1NM', // 0 instead of o
];

foreach ($keys as $key) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $key;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['contents' => [['parts' => [['text' => 'Hello']]]]]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Key: $key => HTTP $httpCode\n";
    if ($httpCode == 200) {
        echo "FOUND VALID KEY!\n";
        break;
    }
}
