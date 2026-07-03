<?php
declare(strict_types=1);

require_once 'common_constants.php';
require_once 'wtva.php';

// ---------------------------------------------------------------------------------------------------------------------
function send_json_response(
    int $status,
    array $payload
) : never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------------------------------------------------
function encode_warning_header(
    array $messages
) : string
{
    $json = json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return base64_encode($json === false ? '[]' : $json);
}

// ---------------------------------------------------------------------------------------------------------------------

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data))
{
    send_json_response(400, [
        'ok' => false,
        'messages' => ['Request body must be valid JSON.'],
    ]);
}

$wtva = new wtva();
[ $validation, $messages, $data ] = $wtva->checkData($data);
if ($validation !== true)
{
    send_json_response(400, [
        'ok' => false,
        'messages' => $messages,
    ]);
}

[ $validation, $messages, $kmzPath ] = $wtva->generateKMZ();
if ($validation !== true)
{
    send_json_response(400, [
        'ok' => false,
        'messages' => $messages,
    ]);
}
if (file_exists($kmzPath))
{
    http_response_code(200);
    header('Content-Type: application/vnd.google-earth.kmz');
    header('Content-Disposition: attachment; filename="model.kmz"');
    header('X-WTVA-Messages: ' . encode_warning_header($messages));
    header('X-WTVA-Message-Count: ' . count($messages));
    header('Cache-Control: no-store');

    readfile($kmzPath);

    @unlink($kmzPath);
    exit;
}
send_json_response(500, [
    'ok' => false,
    'messages' => ['Failed to create KMZ.'],
]);
