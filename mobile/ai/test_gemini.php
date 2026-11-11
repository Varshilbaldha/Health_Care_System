<?php

declare(strict_types=1);

 require_once dirname(
        __DIR__,
        2
    )
    .
    DIRECTORY_SEPARATOR
    .
    'includes'
    .
    DIRECTORY_SEPARATOR
    .
    'gemini.php';

header('Content-Type: text/plain; charset=UTF-8');

$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                [
                    'text' => 'Say hello in one short sentence.'
                ]
            ]
        ]
    ]
];

$json_payload = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json_payload === false) {
    die('Could not create JSON request.');
}

$ch = curl_init(GEMINI_API_URL);

curl_setopt_array($ch, [

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [
        'x-goog-api-key: ' . GEMINI_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ],

    CURLOPT_POSTFIELDS => $json_payload,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_TIMEOUT => 60,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
]);

$response = curl_exec($ch);

$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);

$http_code = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

echo "==============================\n";
echo "HTTP CODE\n";
echo "==============================\n";
echo $http_code . "\n\n";

echo "==============================\n";
echo "CURL ERROR\n";
echo "==============================\n";

if ($curl_errno !== 0) {
    echo "Error #{$curl_errno}: {$curl_error}\n\n";
} else {
    echo "NO CURL ERROR\n\n";
}

echo "==============================\n";
echo "GEMINI RESPONSE\n";
echo "==============================\n";

echo $response;