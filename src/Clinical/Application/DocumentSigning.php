<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinical\Domain\DocumentRepository;
use Src\Clinical\Domain\DocumentSignatureRecord;
use Src\Clinical\Domain\DocumentSignatureRepository;
use Src\Clinical\Domain\DocumentType;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\SignatureImage;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P2-07: tanda tangan digital informed consent di tablet. Pasien (atau wali) dan saksi menggambar tanda tangan di kanvas; server
 * memvalidasi gambar, menyematkannya ke salinan dokumen yang sudah terbit, membuat PDF, dan menyimpannya di rekam medis (disk
 * privat, dienkripsi bila diatur). Satu dokumen hanya dapat ditandatangani sekali; isi dokumen tidak berubah (hanya-tambah, M6).
 * Bukti memuat SHA-256 isi dan PDF. Penandatanganan dan pengunduhan tercatat di audit dan jejak akses (M10). Izin dicek di
 * Presentation (M9).
 */
final readonly class DocumentSigning
{
    public const ROLES = ['patient' => 'Pasien', 'guardian' => 'Wali pasien'];

    public function __construct(
        private DocumentRepository $documents,
        private DocumentSignatureRepository $signatures,
        private ClinicalDocuments $composer,
        private PdfRenderer $pdf,
        private FileVault $vault,
        private ClinicCalendar $calendar,
        private Transaction $tx,
        private AuditLogger $audit,
        private AccessLogger $access,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * Isi dokumen untuk layar tanda tangan.
     *
     * @return array<string, mixed>
     */
    public function page(string $documentId): array
    {
        $document = $this->documents->find($documentId) ?? throw new EncounterNotFound;
        $data = $this->composer->compose($documentId);
        $this->access->patientRecordOpened($document->patientId, 'documents.sign');
        $signature = $this->signatures->forDocument($documentId);

        return [
            'document' => [
                'id' => $document->id,
                'patient_id' => $document->patientId,
                'number' => $document->number,
                'title' => $document->title,
                'heading' => $data['heading'],
                'blocks' => $data['blocks'],
                'doctor_name' => $document->doctorName,
                'patient_name' => $data['patient']['name'],
                'medical_record_no' => $data['patient']['medical_record_no'],
                'signable' => $document->type === DocumentType::Consent,
                'signed' => $signature !== null,
            ],
            'roles' => array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys(self::ROLES), array_values(self::ROLES)),
        ];
    }

    /**
     * @return string ID bukti tanda tangan
     *
     * @throws InvalidClinicalData
     */
    public function sign(string $actorId, string $documentId, string $signerName, string $signerRole, ?string $witnessName, string $signatureDataUrl, ?string $witnessSignatureDataUrl): string
    {
        $signerName = trim($signerName);
        $witnessName = $witnessName === null || trim($witnessName) === '' ? null : trim($witnessName);
        if ($signerName === '' || mb_strlen($signerName) > 150) {
            throw new InvalidClinicalData('signature.name_required', 'Nama penanda tangan wajib diisi.');
        }
        if (! isset(self::ROLES[$signerRole])) {
            throw new InvalidClinicalData('signature.role_invalid', 'Pilih pasien atau wali pasien sebagai penanda tangan.');
        }
        if ($witnessName !== null && mb_strlen($witnessName) > 150) {
            throw new InvalidClinicalData('signature.name_required', 'Nama saksi terlalu panjang.');
        }
        $witnessImageGiven = $witnessSignatureDataUrl !== null && $witnessSignatureDataUrl !== '';
        if ($witnessImageGiven !== ($witnessName !== null)) {
            throw new InvalidClinicalData('signature.witness_incomplete', 'Isi nama dan tanda tangan saksi sekaligus, atau kosongkan keduanya.');
        }
        $image = SignatureImage::fromDataUrl($signatureDataUrl);
        $witnessImage = $witnessImageGiven ? SignatureImage::fromDataUrl((string) $witnessSignatureDataUrl) : null;

        return $this->tx->run(function () use ($actorId, $documentId, $signerName, $signerRole, $witnessName, $image, $witnessImage): string {
            $document = $this->documents->find($documentId) ?? throw new EncounterNotFound;
            if ($document->type !== DocumentType::Consent) {
                throw new InvalidClinicalData('signature.not_signable', 'Hanya informed consent yang ditandatangani di layar.');
            }
            if ($this->signatures->forDocument($documentId) !== null) {
                throw new InvalidClinicalData('signature.already_signed', 'Dokumen ini sudah ditandatangani dan tidak dapat diubah.');
            }

            $now = $this->clock->now();
            $data = $this->composer->compose($documentId);
            $data['signature'] = [
                'signer_name' => $signerName,
                'role_label' => self::ROLES[$signerRole],
                'image' => $image->dataUrl(),
                'witness_name' => $witnessName,
                'witness_image' => $witnessImage?->dataUrl(),
                'signed_at' => $now->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('d/m/Y H:i'),
            ];
            $contents = $this->pdf->render(new PdfRequest('pdf.document', $data, $data['heading'].' '.$data['number'].' (bertanda tangan)'));
            $stored = $this->vault->put($document->patientId, $contents, 'pdf');

            $record = new DocumentSignatureRecord(
                $this->ids->generate(), $documentId, $document->patientId, $signerName, $signerRole, $witnessName, $stored['path'], $stored['encrypted'],
                hash('sha256', $contents), hash('sha256', $document->body), $actorId, $now,
            );
            $this->signatures->add($record);
            $this->audit->record(new AuditEntry($actorId, 'document.signed', 'clinical_document', $documentId, null, [
                'number' => $document->number, 'patient_id' => $document->patientId, 'signer_role' => $signerRole, 'witness' => $witnessName !== null, 'pdf_sha256' => $record->pdfSha256,
            ]));

            return $record->id;
        });
    }

    /**
     * PDF bertanda tangan dari rekam medis; membuka tercatat di jejak akses dan audit.
     *
     * @return array{contents: string, file_name: string}
     */
    public function signedPdf(string $actorId, string $documentId): array
    {
        $document = $this->documents->find($documentId) ?? throw new EncounterNotFound;
        $signature = $this->signatures->forDocument($documentId) ?? throw new InvalidClinicalData('signature.not_signed', 'Dokumen ini belum ditandatangani.');
        $this->access->patientRecordOpened($document->patientId, 'documents.signed');
        $this->audit->record(new AuditEntry($actorId, 'document.signed_downloaded', 'clinical_document', $documentId, null, ['number' => $document->number]));

        return [
            'contents' => $this->vault->get($signature->pdfPath, $signature->pdfEncrypted),
            'file_name' => strtolower(str_replace('/', '-', $document->number)).'-ttd',
        ];
    }
}
