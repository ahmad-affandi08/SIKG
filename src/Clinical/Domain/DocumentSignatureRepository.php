<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Penyimpanan bukti tanda tangan dokumen. Hanya-tambah, satu per dokumen (M5, M6).
 */
interface DocumentSignatureRepository
{
    public function add(DocumentSignatureRecord $signature): void;

    public function forDocument(string $documentId): ?DocumentSignatureRecord;

    /**
     * @param  list<string>  $documentIds
     * @return array<string, DocumentSignatureRecord> document_id => bukti
     */
    public function forDocuments(array $documentIds): array;
}
