<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Imaging;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinical\Application\ClinicalFiles;
use Src\Clinical\Application\EncounterGuard;
use Src\Clinical\Application\FileVault;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\FileKind;
use Src\Clinical\Domain\Imaging\DicomReader;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\Tooth;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * P3-03: integrasi rontgen digital. Gambar DICOM dari sensor atau pesawat rontgen dibaca tanpa pustaka, dikonversi ke PNG untuk ditampilkan
 * (file asli DICOM disimpan terpisah dan dapat diunduh), lalu dilampirkan ke kunjungan yang masih berjalan sebagai rontgen. Gambar JPG
 * atau PNG dari folder masuk dilampirkan apa adanya. Nomor RM di dalam DICOM dicocokkan dengan pasien kunjungan sebagai pengaman; selisih
 * menolak impor kecuali petugas menegaskan. Data demografis di dalam DICOM tidak disalin ke sistem selain di berkas asli terenkripsi.
 */
final readonly class ImagingService
{
    public const MAX_DICOM_BYTES = 40 * 1024 * 1024;

    public const INBOX_EXTENSIONS = ['dcm', 'dicom', 'jpg', 'jpeg', 'png'];

    public function __construct(
        private ClinicalFiles $files,
        private ClinicalFileRepository $records,
        private EncounterRepository $encounters,
        private EncounterGuard $guard,
        private FileVault $vault,
        private GrayscaleEncoder $encoder,
        private InboxStore $inbox,
        private PatientLookup $patients,
        private ClinicCalendar $calendar,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * Mengimpor satu berkas DICOM ke kunjungan.
     *
     * @return array{preview_id: string, original_id: string}
     */
    public function importDicom(string $actorId, string $encounterId, string $contents, bool $confirmMismatch, ?int $toothFdi, ?string $caption): array
    {
        $size = strlen($contents);
        if ($size === 0 || $size > self::MAX_DICOM_BYTES) {
            throw new InvalidClinicalData('clinical.file_size', 'Ukuran berkas DICOM harus lebih dari 0 dan maksimal 40 MB.');
        }
        $dicom = DicomReader::read($contents);
        if (! $dicom->renderable()) {
            throw new InvalidClinicalData('clinical.dicom_unsupported', (string) $dicom->unsupportedReason);
        }
        $tooth = $toothFdi === null ? null : (new Tooth($toothFdi))->fdi;
        $png = $this->encoder->png($dicom->toGray8(), $dicom->columns, $dicom->rows);

        return $this->tx->run(function () use ($actorId, $encounterId, $contents, $confirmMismatch, $tooth, $caption, $dicom, $png, $size): array {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);

            $mrn = $this->patients->summaries([$encounter->patientId])[$encounter->patientId]->medicalRecordNo ?? null;
            if ($dicom->patientId !== null && $mrn !== null && strcasecmp(trim($dicom->patientId), $mrn) !== 0 && ! $confirmMismatch) {
                throw new InvalidClinicalData('clinical.dicom_patient_mismatch', 'ID pasien di dalam gambar berbeda dari nomor RM pasien kunjungan ini. Periksa kembali pasiennya, atau tegaskan bahwa gambar ini memang milik pasien tersebut.');
            }

            $now = $this->clock->now();
            $label = $this->label($dicom->modality, $dicom->studyDate, $dicom->columns, $dicom->rows);
            $text = $caption === null || trim($caption) === '' ? $label : mb_substr(trim($caption), 0, 255);

            $preview = $this->vault->put($encounter->patientId, $png, 'png');
            $previewId = $this->records->add($actorId, $encounter->patientId, $encounterId, $tooth, $preview['path'], 'image/png', strlen($png), FileKind::Xray, $preview['encrypted'], $text, $now);
            $original = $this->vault->put($encounter->patientId, $contents, 'dcm');
            $originalId = $this->records->add($actorId, $encounter->patientId, $encounterId, $tooth, $original['path'], 'application/dicom', $size, FileKind::Xray, $original['encrypted'], 'Berkas DICOM asli - '.$text, $now);
            $this->audit->record(new AuditEntry($actorId, 'encounter.dicom_imported', 'encounter', $encounterId, null, [
                'preview_id' => $previewId, 'original_id' => $originalId, 'modality' => $dicom->modality, 'columns' => $dicom->columns, 'rows' => $dicom->rows, 'size' => $size, 'mismatch_confirmed' => $confirmMismatch,
            ]));

            return ['preview_id' => $previewId, 'original_id' => $originalId];
        });
    }

    /**
     * Mengaitkan satu berkas dari folder masuk ke kunjungan lalu memindahkannya ke arsip `imported`.
     */
    public function importFromInbox(string $actorId, string $name, string $encounterId, bool $confirmMismatch, ?int $toothFdi): void
    {
        $contents = $this->inbox->read($name);
        if ($this->isImage($contents)) {
            $this->files->upload($actorId, $encounterId, $contents, (string) $this->mime($contents), 'xray', $toothFdi, 'Rontgen dari folder masuk');
        } else {
            $this->importDicom($actorId, $encounterId, $contents, $confirmMismatch, $toothFdi, null);
        }
        $this->inbox->archive($name, 'imported');
        $this->audit->record(new AuditEntry($actorId, 'imaging.inbox_imported', 'encounter', $encounterId, null, ['size' => strlen($contents)]));
    }

    public function discardFromInbox(string $actorId, string $name): void
    {
        $this->inbox->archive($name, 'rejected');
        $this->audit->record(new AuditEntry($actorId, 'imaging.inbox_rejected', 'imaging_inbox', 'inbox', null, null));
    }

    /**
     * Data halaman folder masuk.
     *
     * @return array{files: list<array{name: string, size: int, modified: string}>, encounters: list<array{value: string, label: string}>}
     */
    public function overview(): array
    {
        $zone = new DateTimeZone($this->calendar->timezone());
        $open = $this->encounters->openList(100);
        $summaries = $this->patients->summaries(array_values(array_unique(array_map(fn ($e) => $e->patientId, $open))));

        return [
            'files' => array_map(fn (array $f) => ['name' => $f['name'], 'size' => $f['size'], 'modified' => $f['modified']->setTimezone($zone)->format('Y-m-d H:i')], $this->inbox->pending()),
            'encounters' => array_map(fn ($e) => [
                'value' => $e->id,
                'label' => ($summaries[$e->patientId]->name ?? 'Pasien').' ('.($summaries[$e->patientId]->medicalRecordNo ?? '-').') - '.$e->startedAt->setTimezone($zone)->format('d/m/Y H:i'),
            ], $open),
        ];
    }

    /** @return int jumlah berkas yang menunggu */
    public function pendingCount(): int
    {
        return count($this->inbox->pending());
    }

    private function isImage(string $contents): bool
    {
        return $this->mime($contents) !== null;
    }

    private function mime(string $contents): ?string
    {
        if (str_starts_with($contents, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($contents, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        return null;
    }

    private function label(?string $modality, ?string $studyDate, int $columns, int $rows): string
    {
        $date = $studyDate !== null && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $studyDate, $m) === 1 ? " {$m[3]}/{$m[2]}/{$m[1]}" : '';

        return 'Rontgen digital'.($modality !== null ? " {$modality}" : '').$date." ({$columns}x{$rows})";
    }
}
