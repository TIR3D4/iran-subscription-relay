<?php
declare(strict_types=1);
require __DIR__ . '/relay.php';
function fail(int $status, string $message): never {
    http_response_code($status); header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); echo $message; exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)) { header('Allow: GET, HEAD'); fail(405, 'Method not allowed.'); }
if (!is_file(__DIR__ . '/config.php')) fail(503, 'SubRelay is not configured.');
try { $config = relay_config(require __DIR__ . '/config.php'); } catch (Throwable $e) { fail(503, 'Invalid relay configuration.'); }
if (!extension_loaded('curl')) fail(503, 'PHP cURL is required.');
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = explode('?', $uri, 2)[0];
// Permit token and safe page/format suffixes; prohibit traversal and encoded separators.
if (!preg_match('#^/sub/([A-Za-z0-9_-]+)((?:/[A-Za-z0-9_-]+)*/?)$#D', $path)) fail(404, 'Subscription path not found.');
$suffix = substr($path, 4);
$query = explode('?', $uri, 2)[1] ?? '';
if (strlen($query) > 4096 || preg_match('/[\x00-\x20\x7f]/', $query)) fail(400, 'Invalid query.');
if ($query !== '') $suffix .= '?' . $query;
$headers = [];
foreach (['HTTP_USER_AGENT'=>'User-Agent','HTTP_ACCEPT'=>'Accept','HTTP_ACCEPT_LANGUAGE'=>'Accept-Language'] as $server => $name) {
    $value = $_SERVER[$server] ?? '';
    if ($value !== '' && strlen($value) <= 2048 && !preg_match('/[\r\n]/', $value)) $headers[] = $name . ': ' . $value;
}
$result = relay_resolve($config, $suffix, $headers);
if ($result['error']) fail(502, 'Unable to retrieve subscription. Check panel connectivity and TLS.');
// Never send browsers to a panel through an upstream redirect.
if ($result['status'] >= 300 && $result['status'] < 400) fail(502, 'Upstream redirect: configure the final subscription URL.');
if ($result['status'] < 100 || $result['status'] > 599) fail(502, 'Invalid upstream response.');
http_response_code($result['status']);
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
foreach (['content-type','content-disposition','subscription-userinfo','profile-update-interval','profile-title','support-url','profile-web-page-url'] as $key) {
    $value = $result['headers'][$key] ?? null;
    if ($value === null || preg_match('/[\r\n]/', $value)) continue;
    if ($key === 'profile-web-page-url') {
        foreach ($config['panels'] as $panelUrl) {
            if (str_starts_with($value, $panelUrl . '/')) { $value = $config['public_url'] . '/sub' . substr($value, strlen($panelUrl)); break; }
        }
    }
    header($key . ': ' . $value);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') echo $result['body'];
