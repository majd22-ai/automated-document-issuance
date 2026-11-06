$rows = \Illuminate\Support\Facades\DB::select("
  SELECT u.id, u.name, u.email, u.phone, u.national_id, u.registration_status, u.mock_person_id,
         mic.number as card_number, mic.status as card_status,
         mcp.national_id as person_nid, mcp.full_name
  FROM users u
  LEFT JOIN mock_civil_persons mcp ON mcp.id = u.mock_person_id
  LEFT JOIN mock_identity_cards mic ON mic.person_id = mcp.id
  WHERE u.role = 'citizen'
  ORDER BY u.id
  LIMIT 20
");
echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
