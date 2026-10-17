<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak templat dokumen klinis (RM-09, RM-10) untuk Clinical. Hanya templat aktif yang ditawarkan.
 */
interface DocumentTemplateCatalog
{
    /**
     * @param  'consent'|'certificate'|'referral'  $type
     * @return list<DocumentTemplate> urut nama
     */
    public function active(string $type): array;

    public function find(string $id): ?DocumentTemplate;
}
