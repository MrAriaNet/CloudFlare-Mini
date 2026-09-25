<?php

declare(strict_types=1);

final class CloudflareClient
{
    private string $apiToken;
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct(string $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    public function verifyToken(): array
    {
        return $this->request('GET', '/user/tokens/verify');
    }

    public function listZones(): array
    {
        $zones = [];
        $page = 1;
        do {
            $result = $this->request('GET', '/zones', [
                'page' => $page,
                'per_page' => 50,
            ]);
            $batch = $result['result'] ?? [];
            foreach ($batch as $zone) {
                $zones[] = $zone;
            }
            $totalPages = (int) ($result['result_info']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $zones;
    }

    public function listDnsRecords(string $zoneId): array
    {
        $records = [];
        $page = 1;
        do {
            $result = $this->request('GET', '/zones/' . rawurlencode($zoneId) . '/dns_records', [
                'page' => $page,
                'per_page' => 100,
            ]);
            $batch = $result['result'] ?? [];
            foreach ($batch as $record) {
                $records[] = $record;
            }
            $totalPages = (int) ($result['result_info']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $records;
    }

    public function createDnsRecord(string $zoneId, array $payload): array
    {
        return $this->request('POST', '/zones/' . rawurlencode($zoneId) . '/dns_records', null, $payload);
    }

    public function updateDnsRecord(string $zoneId, string $recordId, array $payload): array
    {
        return $this->request(
            'PUT',
            '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode($recordId),
            null,
            $payload
        );
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): array
    {
        return $this->request(
            'DELETE',
            '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode($recordId)
        );
    }

    private function request(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Cloudflare API connection error: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid Cloudflare API response (HTTP ' . $status . ').');
        }

        if (empty($decoded['success'])) {
            $messages = [];
            foreach ($decoded['errors'] ?? [] as $err) {
                $messages[] = ($err['message'] ?? 'Unknown error') . (isset($err['code']) ? ' [' . $err['code'] . ']' : '');
            }
            throw new RuntimeException('Cloudflare API error: ' . (implode('; ', $messages) ?: 'Request failed'));
        }

        return $decoded;
    }
}
