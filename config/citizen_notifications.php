<?php
return [
    'mail_enabled'=>(bool)env('CITIZEN_NOTIFICATION_MAIL_ENABLED',false),
    'mail_queue'=>env('CITIZEN_NOTIFICATION_QUEUE','notifications'),
    'mail_max_attempts'=>3,
    // No official reminder periods are assumed. Set an explicit comma-separated list, e.g. your approved periods.
    'expiry_offsets'=>array_values(array_filter(array_map('trim',explode(',',(string)env('DOCUMENT_EXPIRY_ALERT_DAYS',''))),fn($v)=>$v!=='')),
    'expiry_statuses'=>array_values(array_filter(array_map('trim',explode(',',(string)env('DOCUMENT_EXPIRY_ALERT_STATUSES','active'))))),
    'expired_policy'=>env('DOCUMENT_EXPIRED_ALERT_POLICY','skip'),
];
