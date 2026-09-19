<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Support;

/**
 * Groups the live `permissions` table by the natural `resource.` prefix
 * every real permission already follows, into the five domains Role
 * Management's checklist is organised around (spec §4.2): Clinical,
 * Diagnostics, Prescriptions, Financial/Billing, Administrative.
 *
 * Deliberately NOT a second permission catalogue - the grouping is a
 * display concern layered over whatever rows `permissions` actually
 * holds, so a slug this map has never seen still shows up (under
 * Administrative, the catch-all) rather than silently vanishing from the
 * Role Management checklist the moment a future migration adds one.
 */
final class PermissionCatalog
{
    private const array DOMAINS = [
        'Clinical'             => ['patients', 'encounters', 'clinical_notes'],
        'Diagnostics'          => ['diagnostics'],
        'Prescriptions'        => ['prescriptions'],
        'Financial / Billing'  => ['billing', 'payments', 'payment_methods', 'reports'],
        'Administrative'       => [], // catch-all, listed last
    ];

    /**
     * @param list<string> $slugs every permission slug from the `permissions` table
     * @return array<string, list<string>> domain label => sorted slugs, in DOMAINS order
     */
    public static function grouped(array $slugs): array
    {
        $buckets = array_fill_keys(array_keys(self::DOMAINS), []);

        foreach ($slugs as $slug) {
            $buckets[self::domainFor($slug)][] = $slug;
        }

        foreach ($buckets as &$bucket) {
            sort($bucket);
        }

        return array_filter($buckets, static fn (array $b): bool => $b !== []);
    }

    private static function domainFor(string $slug): string
    {
        // dashboard.finance is the one slug whose resource prefix
        // (dashboard) does not match its actual domain - it is revenue
        // reporting, not a general dashboard toggle.
        if ($slug === 'dashboard.finance') {
            return 'Financial / Billing';
        }

        $resource = strstr($slug, '.', true) ?: $slug;

        foreach (self::DOMAINS as $domain => $resources) {
            if (in_array($resource, $resources, true)) {
                return $domain;
            }
        }

        return 'Administrative';
    }
}
