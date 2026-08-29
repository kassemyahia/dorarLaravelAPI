<?php

namespace App\Services\Enrichment;

final class DorarBookMap
{
    /** Local hadith-json IDs are deliberately not used as Dorar source IDs. */
    private const BOOKS = [
        1 => ['Sahih al-Bukhari', 'صحيح البخاري', 'البخاري'],
        2 => ['Sahih Muslim', 'صحيح مسلم', 'مسلم'],
        3 => ['Sunan Abi Dawud', 'سنن أبي داود', 'أبو داود'],
        4 => ['Jami at-Tirmidhi', 'جامع الترمذي', 'سنن الترمذي', 'الترمذي'],
        5 => ["Sunan an-Nasa'i", 'سنن النسائي', 'النسائي'],
        6 => ['Sunan Ibn Majah', 'سنن ابن ماجه', 'ابن ماجه'],
        7 => ['Muwatta Malik', 'موطأ مالك', 'الموطأ'],
        8 => ['Musnad Ahmad', 'مسند أحمد', 'أحمد'],
        9 => ['Sunan ad-Darimi', 'سنن الدارمي', 'الدارمي'],
    ];

    public function aliases(int|string|null $localId, ?string $name = null): array
    {
        $aliases = self::BOOKS[(int) $localId] ?? [];

        return array_values(array_unique(array_filter([...$aliases, $name])));
    }

    public function verifiedDorarSourceId(int|string|null $localId): ?string
    {
        return null; // No verified mapping is shipped; search safely without s[].
    }
}
