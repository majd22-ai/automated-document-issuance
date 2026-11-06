<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = "
  SELECT u.id, u.name, u.email, u.phone, u.national_id,
         u.registration_status, u.mock_person_id, u.identity_path,
         mic.number AS card_number, mic.status AS card_status,
         mcp.national_id AS person_nid, mcp.full_name AS person_name
  FROM users u
  LEFT JOIN mock_civil_persons mcp ON mcp.id = u.mock_person_id
  LEFT JOIN mock_identity_cards mic ON mic.person_id = mcp.id
  WHERE u.role = 'citizen'
  ORDER BY u.id
  LIMIT 20
";

$rows = DB::select($sql);

$withCard = array_filter($rows, fn($r) => !is_null($r->card_number));
$noCard   = array_filter($rows, fn($r) =>  is_null($r->card_number));

echo "\n=== لديهم بطاقة (" . count($withCard) . ") ===\n";
foreach ($withCard as $r) {
    echo json_encode((array)$r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== ليس لديهم بطاقة (" . count($noCard) . ") ===\n";
foreach ($noCard as $r) {
    echo json_encode((array)$r, JSON_UNESCAPED_UNICODE) . "\n";
}
