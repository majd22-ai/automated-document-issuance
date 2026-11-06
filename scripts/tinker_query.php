// Citizens WITH card
$withCard = \App\Models\User::where('role','citizen')
    ->whereNotNull('mock_person_id')
    ->whereHas('mockPerson', fn($q)=>$q->whereHas('identityCard'))
    ->with(['mockPerson.identityCard'])
    ->take(4)->get()
    ->map(fn($u)=>[
        'name'=>$u->name,
        'email'=>$u->email,
        'phone'=>$u->phone,
        'national_id'=>$u->national_id,
        'card'=>$u->mockPerson?->identityCard?->number,
        'status'=>$u->registration_status,
    ]);

// Citizens WITHOUT card
$noCard = \App\Models\User::where('role','citizen')
    ->where(fn($q)=>$q->whereNull('mock_person_id')
        ->orWhereHas('mockPerson', fn($q2)=>$q2->whereDoesntHave('identityCard')))
    ->take(4)->get()
    ->map(fn($u)=>[
        'name'=>$u->name,
        'email'=>$u->email,
        'phone'=>$u->phone,
        'national_id'=>$u->national_id,
        'status'=>$u->registration_status,
        'path'=>$u->identity_path,
    ]);

echo json_encode(['with_card'=>$withCard,'no_card'=>$noCard], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
