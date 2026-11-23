<?php

// HTTP checks behind Render's TLS proxy. Does not connect to PostgreSQL.
foreach (['RENDER'=>'true','APP_ENV'=>'production','APP_DEBUG'=>'false','APP_URL'=>'https://ssr-render-test.onrender.com'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array','cache.default'=>'array','session.secure'=>true,'session.same_site'=>'lax','database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$checks = 0;
$check = static function (bool $passed, string $message) use (&$checks): void {
    if (!$passed) { throw new RuntimeException('GAGAL: '.$message); }
    echo 'LULUS '.++$checks.' - '.$message.PHP_EOL;
};
$server = ['REMOTE_ADDR'=>'10.10.0.2','HTTP_HOST'=>'ssr-render-test.onrender.com','SERVER_PORT'=>10000,'HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_FORWARDED_PORT'=>443,'HTTP_X_FORWARDED_FOR'=>'203.0.113.5'];
$request = \Illuminate\Http\Request::create('http://ssr-render-test.onrender.com/masuk','GET',server:$server);
$response = $kernel->handle($request);
$check($response->getStatusCode() === 200, 'Halaman login produksi HTTP 200 di balik TLS proxy');
$check($request->isSecure() && $request->getPort() === 443, 'HTTPS dan port publik dikenali dari proxy Render');
$check(str_contains($response->getContent(),'https://ssr-render-test.onrender.com/masuk'), 'Form login memakai tujuan HTTPS');
$check(!str_contains($response->getContent(),'http://ssr-render-test.onrender.com'), 'Tidak ada URL HTTP pada halaman login produksi');
$cookies = $response->headers->getCookies();
$sessionCookies = array_filter($cookies, fn ($cookie) => $cookie->getName() === config('session.cookie'));
$check(count($sessionCookies) === 1 && array_values($sessionCookies)[0]->isSecure() && array_values($sessionCookies)[0]->isHttpOnly(), 'Cookie sesi dikirim dengan Secure dan HttpOnly');
$kernel->terminate($request,$response);
$request = \Illuminate\Http\Request::create('http://ssr-render-test.onrender.com/up','GET',server:$server);
$response = $kernel->handle($request);
$check($response->getStatusCode() === 200, 'Health check Render berjalan tanpa membaca database');
$kernel->terminate($request,$response);
$request = \Illuminate\Http\Request::create('http://ssr-render-test.onrender.com/','GET',server:$server);
$response = $kernel->handle($request);
$check($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://ssr-render-test.onrender.com/masuk', 'Pengunjung diarahkan ke login HTTPS');
$kernel->terminate($request,$response);
$request = \Illuminate\Http\Request::create('http://ssr-render-test.onrender.com/reservasi/live','GET',server:$server);
$request->headers->set('Accept','application/json');
$response = $kernel->handle($request);
$check($response->getStatusCode() === 401, 'Endpoint sinkronisasi tetap terlindungi pada konfigurasi produksi');
$kernel->terminate($request,$response);
echo 'BERHASIL: '.$checks.' pemeriksaan HTTP Render tanpa koneksi database.'.PHP_EOL;
