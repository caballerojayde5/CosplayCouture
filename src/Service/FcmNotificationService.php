<?php

namespace App\Service;

class FcmNotificationService
{
    private string $projectId;
    private string $credentialsPath;

    public function __construct(string $projectId = '', string $credentialsPath = '')
    {
        $this->projectId = $projectId ?: ($_ENV['FIREBASE_PROJECT_ID'] ?? '');
        $this->credentialsPath = $credentialsPath ?: (dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? ''));
    }

    private function getAccessToken(): string
    {
        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        $now = time();
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = $this->buildJwt($payload, $credentials['private_key']);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $response['access_token'];
    }

    private function buildJwt(array $payload, string $privateKey): string
    {
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body   = base64_encode(json_encode($payload));
        $input  = $header . '.' . $body;

        openssl_sign($input, $signature, $privateKey, 'SHA256');

        return $input . '.' . base64_encode($signature);
    }

    public function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';

        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'payload' => ['aps' => ['sound' => 'default']],
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log the response for debugging
            file_put_contents(dirname(__DIR__, 2) . '/var/log/fcm_debug.log', 
                date('Y-m-d H:i:s') . ' HTTP:' . $httpCode . ' Response:' . $result . "\n", 
                FILE_APPEND
            );

            return $result !== false && $httpCode === 200;
        }
}