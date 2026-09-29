<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$m = app(Cartxis\Core\Services\PaymentGatewayManager::class);

echo 'hesabpay registered : ' . ($m->has('hesabpay') ? 'YES' : 'NO') . PHP_EOL;
echo 'code                : ' . $m->get('hesabpay')->getCode() . PHP_EOL;
echo 'name                : ' . $m->get('hesabpay')->getName() . PHP_EOL;
echo 'supports hesabpay   : ' . var_export($m->get('hesabpay')->supports('hesabpay'), true) . PHP_EOL;
echo 'sandbox base url    : ' . $m->get('hesabpay')->getBaseUrl() . PHP_EOL;
echo 'isConfigured no key : ' . var_export($m->get('hesabpay')->isConfigured(), true) . PHP_EOL;

$method = Cartxis\Core\Models\PaymentMethod::where('code', 'hesabpay')->first();
echo 'seeded row          : ' . ($method ? "yes (active=" . var_export($method->is_active, true) . ", type=" . $method->type . ')' : 'no') . PHP_EOL;

$route = collect(app('router')->getRoutes())->first(
    fn ($r) => $r->getName() === 'hesabpay.webhook'
);
echo 'webhook route       : ' . ($route ? $route->methods()[0] . ' ' . $route->uri() : 'MISSING') . PHP_EOL;

$ret = collect(app('router')->getRoutes())->first(
    fn ($r) => $r->getName() === 'hesabpay.return.success'
);
echo 'success route       : ' . ($ret ? $ret->methods()[0] . ' ' . $ret->uri() : 'MISSING') . PHP_EOL;
