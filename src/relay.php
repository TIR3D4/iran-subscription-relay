<?php
declare(strict_types=1);

// Shared validation and transport. Panel URLs end at the upstream /sub prefix.
function relay_url(string $url): string {
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new InvalidArgumentException('Use an HTTPS URL without credentials, query or fragment.');
    }
    return rtrim($url, '/');
}
function relay_config(array $config): array {
    $config['public_url'] = relay_url((string)($config['public_url'] ?? ''));
    if (parse_url($config['public_url'], PHP_URL_PATH)) throw new InvalidArgumentException('Public URL must be the origin of a dedicated subdomain.');
    $mode = $config['mode'] ?? '';
    if (!in_array($mode, ['marzban','pasarguard','dual'], true)) throw new InvalidArgumentException('Invalid mode.');
    $required = $mode === 'dual' ? ['marzban','pasarguard'] : [$mode];
    foreach ($required as $panel) $config['panels'][$panel] = relay_url((string)($config['panels'][$panel] ?? ''));
    $order = $config['order'] ?? $required;
    if (count($order) !== count($required) || array_diff($required, $order) || array_diff($order, $required)) throw new InvalidArgumentException('Invalid panel order.');
    $config['order'] = $order;
    foreach (['connect_timeout' => 5, 'timeout' => 20, 'max_bytes' => 8388608] as $key => $default) {
        $config[$key] = (int)($config[$key] ?? $default);
        if ($config[$key] < 1) throw new InvalidArgumentException('Limits must be positive.');
    }
    return $config;
}
function relay_request(string $url, array $config, array $requestHeaders): array {
    $body = ''; $headers = []; $tooLarge = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => $config['connect_timeout'], CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $requestHeaders, CURLOPT_ENCODING => '',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (preg_match('#^HTTP/#', $line)) $headers = [];
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $config): int {
            if (strlen($body) + strlen($chunk) > $config['max_bytes']) { $tooLarge = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $ok === false || $tooLarge ? 502 : $status, 'headers' => $headers, 'body' => $body, 'error' => $ok === false || $tooLarge];
}
// Only an actual HTTP 404 advances to the next configured panel.
function relay_resolve(array $config, string $suffix, array $headers, ?callable $transport = null): array {
    $transport ??= 'relay_request';
    foreach ($config['order'] as $panel) {
        $result = $transport($config['panels'][$panel] . $suffix, $config, $headers);
        if ($result['status'] !== 404 || $result['error']) return $result;
    }
    return $result;
}
