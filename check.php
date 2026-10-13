<?php
// Chẩn đoán chỉ chạy bằng PHP CLI trên VPS, không cung cấp qua HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__.'/tools/diagnostics/Check.php';
