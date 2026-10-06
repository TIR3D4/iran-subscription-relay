<?php
declare(strict_types=1);
require __DIR__ . '/../src/relay.php';
function check(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; }
$base = relay_config(require __DIR__ . '/../examples/dual-panel.php');
foreach ([200,401,403,429,500,502,503,404] as $status) {
    $calls = [];
    $result = relay_resolve($base, '/TOKEN/clash?x=1', ['User-Agent: test'], function ($url, $config, $headers) use (&$calls, $status) {
        $calls[] = [$url,$headers];
        return ['status'=>count($calls)===1?$status:200,'error'=>false,'headers'=>[],'body'=>'ok'];
    });
    check(count($calls) === ($status===404?2:1), "fallback for HTTP $status");
    check($calls[0][0] === 'https://marzban.example.com/sub/TOKEN/clash?x=1', 'path and query retained');
    check($calls[0][1] === ['User-Agent: test'], 'User-Agent retained');
}
$calls=0;
relay_resolve($base,'/TOKEN',[],function () use (&$calls) { $calls++; return ['status'=>502,'error'=>true,'headers'=>[],'body'=>'']; });
check($calls===1,'transport failure never falls back');
foreach (['marzban','pasarguard'] as $mode) {
    $cfg=relay_config(require __DIR__ . '/../examples/'.$mode.'.php'); $calls=[];
    relay_resolve($cfg,'/TOKEN',[],function($url)use(&$calls){$calls[]=$url;return ['status'=>404,'error'=>false,'headers'=>[],'body'=>''];});
    check(count($calls)===1 && str_contains($calls[0],$mode.'.example.com'),'single mode '.$mode);
}
$calls=0;
$result=relay_resolve($base,'/TOKEN',[],function()use(&$calls){$calls++;return ['status'=>404,'error'=>false,'headers'=>[],'body'=>'missing'];});
check($calls===2 && $result['status']===404,'both missing returns 404');
foreach (['http://example.com/sub','https://user:pass@example.com/sub','https://example.com/sub?x=1'] as $url) {
    try { relay_url($url); check(false,'invalid URL accepted'); } catch (InvalidArgumentException $e) { check(true,'reject unsafe URL'); }
}
