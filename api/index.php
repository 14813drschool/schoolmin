<?php
header('Content-Type: application/json');
$ca = [];
foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $f) { $ca[$f] = is_file($f); }
$t = sys_get_temp_dir();
@file_put_contents($t.'/x.txt', 'ok');
echo json_encode([
  'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'gd' => extension_loaded('gd'), 'gd_info' => function_exists('gd_info') ? gd_info() : null,
  'imagick' => extension_loaded('imagick'), 'ext' => get_loaded_extensions(),
  'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'memory_limit' => ini_get('memory_limit'),
  'tmp' => $t, 'tmp_write' => is_file($t.'/x.txt'), 'symlink' => @symlink(__DIR__, $t.'/lnk'.mt_rand()) , 'ca' => $ca, 'cwd' => getcwd(), 'dir' => __DIR__,
  'opcache' => function_exists('opcache_get_status') ? (bool) ini_get('opcache.enable') : null,
  'env_region' => getenv('VERCEL_REGION'), 'max_exec' => ini_get('max_execution_time')
], JSON_PRETTY_PRINT);
