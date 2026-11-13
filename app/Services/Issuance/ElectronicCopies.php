<?php

namespace App\Services\Issuance;

use App\Models\CitizenDocument;
use App\Models\DocumentCopy;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElectronicCopies
{
    public function generate(CitizenDocument $document, User $actor): DocumentCopy
    {
        if (! in_array($document->document_type_code, ['identity', 'passport', 'driving_license', 'birth_certificate'], true)) {
            throw new \RuntimeException('Unsupported internal document template');
        }
        $path = 'copies/'.Str::uuid().'.html';
        try {
            $identityPhoto = null;
            $snapshot = $document->holder_snapshot['identity_card_photo'] ?? null;
            if ($snapshot) {
                $photo = \App\Models\CitizenAttachment::find($snapshot['attachment_id']);
                if (! $photo || $photo->user_id !== $document->user_id || $photo->citizen_service_request_id !== $document->citizen_service_request_id
                    || ! in_array($photo->mime_type, ['image/png', 'image/jpeg'], true) || ! hash_equals($snapshot['sha256'], $photo->sha256) || ! $photo->intact()) {
                    throw new \RuntimeException('Issued identity photo integrity failure');
                }
                $identityPhoto = 'data:'.$photo->mime_type.';base64,'.base64_encode(Storage::disk('attachments')->get($photo->storage_path));
            }
            $html = view('documents.copies.'.$document->document_type_code, compact('document', 'identityPhoto'))->render();
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
