<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$kategori_file = __DIR__ . '/../backend/data/kategori.json';
$hidden_file = __DIR__ . '/../backend/data/produk_tersembunyi.json';

$data = ['kategori' => [], 'map' => [], 'hidden' => []];

if (file_exists($kategori_file)) {
    $raw = json_decode(file_get_contents($kategori_file), true);
    $kats = $raw['kategori'] ?? [];
    $visible = array_filter($kats, fn($k) => !empty($k['visible']));
    $data['kategori'] = array_values($visible);
    foreach ($visible as $k) {
        $data['map'][$k['id']] = $k;
    }
}

if (file_exists($hidden_file)) {
    $data['hidden'] = json_decode(file_get_contents($hidden_file), true) ?? [];
}

echo json_encode($data);
