<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Odontogram terkini (ODO-04, M8): keadaan terbaru tiap pasangan gigi dan permukaan lintas snapshot. Entri yang lebih baru
 * menimpa yang lebih lama; kondisi seluruh gigi menimpa semua permukaan yang dicatat lebih dulu atau bersamaan.
 */
final class Odontogram
{
    /**
     * @param  list<ToothStateEntry>  $entries  seluruh entri dari semua snapshot pasien, urutan bebas
     * @return array<int, array{whole: ?ToothStateEntry, surfaces: array<string, ToothStateEntry>}> nomor FDI => keadaan efektif
     */
    public static function current(array $entries): array
    {
        usort($entries, fn (ToothStateEntry $a, ToothStateEntry $b) => [$a->setAt, $a->id] <=> [$b->setAt, $b->id]);

        $latest = [];
        foreach ($entries as $entry) {
            $latest[$entry->toothFdi][$entry->surface->value] = $entry;
        }

        $teeth = [];
        foreach ($latest as $fdi => $bySurface) {
            $whole = $bySurface[Surface::Whole->value] ?? null;
            $surfaces = [];
            foreach ($bySurface as $code => $entry) {
                if ($code === Surface::Whole->value) {
                    continue;
                }
                if ($whole !== null && $entry->setAt <= $whole->setAt) {
                    continue;
                }
                $surfaces[$code] = $entry;
            }
            $teeth[$fdi] = ['whole' => $whole, 'surfaces' => $surfaces];
        }
        ksort($teeth);

        return $teeth;
    }

    /**
     * Dentisi yang sesuai dengan gigi yang tercatat: hanya sulung, hanya dewasa, atau campuran.
     *
     * @param  list<int>  $teeth
     */
    public static function dentitionOf(array $teeth): Dentition
    {
        $hasPrimary = false;
        $hasPermanent = false;
        foreach ($teeth as $fdi) {
            (new Tooth($fdi))->dentition() === Dentition::Primary ? $hasPrimary = true : $hasPermanent = true;
        }

        return match (true) {
            $hasPrimary && $hasPermanent => Dentition::Mixed,
            $hasPrimary => Dentition::Primary,
            default => Dentition::Permanent,
        };
    }
}
