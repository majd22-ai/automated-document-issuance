<?php

namespace App\Services\Issuance;

use App\Models\BirthCertificate;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BirthCertificateIssuance
{
    public function issue(CitizenDocument $document, CitizenServiceRequest $request, User $actor): BirthCertificate
    {
        $existing = BirthCertificate::where('citizen_service_request_id', $request->id)->first();
        if ($existing) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $number = 'BC-'.now()->format('Y').'-'.Str::ulid();
        $issuedAt = now();
        $data = $request->request_data;
        $verificationUrl = route('birth-certificates.verify', ['token' => $token]);
        $qrCode = Builder::create()
            ->writer(new SvgWriter)
            ->data($verificationUrl)
            ->size(180)
            ->margin(4)
            ->build()
            ->getDataUri();

        $pdf = app('dompdf.wrapper')->loadView('documents.birth-certificate', [
            'data' => $data,
            'number' => $number,
            'issuedAt' => $issuedAt,
            'qrCode' => $qrCode,
        ])->setPaper('a4')->setOptions(['isRemoteEnabled' => false])->output();

        $path = 'birth-certificates/'.Str::uuid().'.pdf';
        if (! Storage::disk('documents')->put($path, $pdf, ['visibility' => 'private'])) {
            throw new \RuntimeException('Birth certificate PDF storage unavailable');
        }

        try {
            $certificate = (new BirthCertificate)->forceFill([
                'citizen_document_id' => $document->id,
                'citizen_service_request_id' => $request->id,
                'certificate_number' => $number,
                'verification_token' => $token,
                'verification_token_hash' => hash('sha256', $token),
                'certificate_data' => $data,
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $pdf),
                'pdf_byte_size' => strlen($pdf),
                'issued_by' => $actor->id,
                'issued_at' => $issuedAt,
            ]);
            $certificate->save();
        } catch (\Throwable $error) {
            try {
                Storage::disk('documents')->delete($path);
            } catch (\Throwable $cleanupError) {
                Log::warning('birth_certificate_file_cleanup_failed', ['exception_type' => get_class($cleanupError)]);
            }
            throw $error;
        }

        return $certificate;
    }
}
