<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select("
  SELECT u.id, u.name, u.email, u.phone, u.national_id,
         u.registration_status, u.mock_person_id, u.identity_path,
         u.birth_certificate_number,
         mic.number  AS card_number,
         mic.status  AS card_status,
         mcp.national_id AS person_nid,
         mcp.name    AS person_name,
         mcp.phone   AS person_phone
  FROM users u
  LEFT JOIN mock_civil_persons mcp ON mcp.id = u.mock_person_id
  LEFT JOIN mock_identity_cards mic ON mic.person_id = mcp.id
  WHERE u.role = 'citizen'
  ORDER BY u.id
  LIMIT 30
");

$withCard = array_filter($rows, fn($r) => !is_null($r->card_number));
$noCard   = array_filter($rows, fn($r) =>  is_null($r->card_number));

echo "\n=== لديهم بطاقة شخصية ===\n";
foreach (array_slice($withCard, 0, 4) as $r) {
    echo json_encode((array)$r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n---\n";
}

echo "\n=== ليس لديهم بطاقة شخصية ===\n";
foreach (array_slice($noCard, 0, 4) as $r) {
    echo json_encode((array)$r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n---\n";
}
