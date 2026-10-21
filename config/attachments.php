<?php

return [
    'photo_max_kb' => (int) env('ATTACHMENT_PHOTO_MAX_KB', 2048),
    'document_max_kb' => (int) env('ATTACHMENT_DOCUMENT_MAX_KB', 5120),
    'max_pixels' => 20000000,
    // Absolute path to a trusted pdfinfo executable. PDFs fail closed when unavailable.
    'pdfinfo_binary' => env('ATTACHMENT_PDFINFO_BINARY'),
];
