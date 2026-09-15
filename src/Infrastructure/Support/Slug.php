<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

/**
 * URL slug generation.
 *
 * This lived as a static method on the CrudOperations trait, which was a
 * mistake: calling a static method on the trait itself rather than on a class
 * that uses it is deprecated in PHP 8.1 and removed in PHP 9. It also did not
 * belong there - DoctorController needs slugs but DoctorRepository does not use
 * the trait, so there was no class to call it on in the first place.
 *
 * A plain final class with a static factory is what this always wanted to be.
 */
final class Slug
{
    /** Longest slug we will emit; the DB columns are VARCHAR(160)-VARCHAR(240). */
    private const int MAX_LENGTH = 150;

    /**
     * Build a URL-safe slug.
     *
     * Ge'ez transliterates to nothing useful, so a title with no Latin
     * characters falls back to a short random token rather than producing an
     * empty slug that would collide with every other one. That is why the
     * Amharic fields are never used as the slug source - the English title is.
     */
    public static function make(string $source): string
    {
        $slug = mb_strtolower(trim($source));

        // Any run of non-letter, non-digit characters becomes a single dash.
        // \p{L} and \p{N} are Unicode-aware, so accented Latin survives to the
        // ASCII filter below instead of being destroyed here.
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        // Keep ASCII only: a Ge'ez slug is unreadable in a URL bar and gets
        // percent-encoded into noise the moment anyone shares the link.
        $ascii = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';
        $ascii = trim(preg_replace('/-+/', '-', $ascii) ?? '', '-');

        if ($ascii === '') {
            return 'item-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        return mb_substr($ascii, 0, self::MAX_LENGTH);
    }
}
