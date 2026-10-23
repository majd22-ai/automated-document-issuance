<?php

namespace App\Services\Issuance;

use App\Models\CitizenDocument;
use App\Models\DocumentCopy;
use App\Models\GovernmentService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElectronicCopies
{
    public function generate(CitizenDocument $document, User $actor): DocumentCopy
    {
        if (! array_key_exists($document->document_type_code, GovernmentService::DOCUMENT_TYPES)) {
            throw new \RuntimeException('Unsupported internal document template');
        }
        $path = 'copies/'.Str::uuid().'.html';
        try {
            $html = view('documents.copies.'.$document->document_type_code, ['document' => $document])->render();
            if (! Storage::disk('documents')->put($path, $html)) {
                throw new \RuntimeException('Document storage unavailable');
            }
            $copy = (new DocumentCopy)->forceFill(['citizen_document_id' => $document->id, 'storage_path' => $path, 'sha256' => hash('sha256', $html), 'byte_size' => strlen($html), 'template' => 'internal-'.$document->document_type_code.'-v1', 'created_by' => $actor->id]);
            $copy->save();
            if ($copy->contents() === null) {
                throw new \RuntimeException('Document copy integrity failed');
            }

            return $copy;
        } catch (\Throwable $error) {
            $this->discard($path);
            throw $error;
        }
    }

    public function discard(?string $path): void
    {
        if ($path && preg_match('~^copies/[a-f0-9-]{36}\.html$~D', $path)) {
            try {
                Storage::disk('documents')->delete($path);
            } catch (\Throwable) {
            }
        }
    }
}
