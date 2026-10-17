<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Src\Clinical\Domain\DocumentSignatureRecord;
use Src\Clinical\Domain\DocumentSignatureRepository;
use Src\Clinical\Infrastructure\Eloquent\DocumentSignatureModel;

final class EloquentDocumentSignatureRepository implements DocumentSignatureRepository
{
    public function add(DocumentSignatureRecord $signature): void
    {
        $row = new DocumentSignatureModel;
        $row->id = $signature->id;
        $row->forceFill([
            'document_id' => $signature->documentId, 'patient_id' => $signature->patientId, 'signer_name' => $signature->signerName, 'signer_role' => $signature->signerRole,
            'witness_name' => $signature->witnessName, 'pdf_path' => $signature->pdfPath, 'pdf_encrypted' => $signature->pdfEncrypted, 'pdf_sha256' => $signature->pdfSha256,
            'body_sha256' => $signature->bodySha256, 'signed_by' => $signature->signedBy, 'signed_at' => $signature->signedAt, 'created_at' => $signature->signedAt,
        ])->save();
    }

    public function forDocument(string $documentId): ?DocumentSignatureRecord
    {
        $row = DocumentSignatureModel::query()->where('document_id', $documentId)->first();

        return $row === null ? null : $this->record($row);
    }

    public function forDocuments(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }
        $map = [];
        foreach (DocumentSignatureModel::query()->whereIn('document_id', $documentIds)->get() as $row) {
            $map[$row->document_id] = $this->record($row);
        }

        return $map;
    }

    private function record(DocumentSignatureModel $row): DocumentSignatureRecord
    {
        return new DocumentSignatureRecord(
            $row->id, $row->document_id, $row->patient_id, $row->signer_name, $row->signer_role, $row->witness_name, $row->pdf_path, $row->pdf_encrypted,
            $row->pdf_sha256, $row->body_sha256, $row->signed_by, $row->signed_at->toDateTimeImmutable(),
        );
    }
}
