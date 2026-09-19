<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\Article;

/**
 * Knowledge Hub persistence.
 *
 * Public reads are always constrained to published rows with a published_at
 * in the past, so a scheduled article cannot leak early through a guessed
 * slug.
 */
final class ArticleRepository
{
    use CrudOperations;

    private const string SELECT_BASE = '
        SELECT a.*,
               author.full_name   AS author_name,
               author.specialty   AS author_specialty,
               reviewer.full_name AS reviewer_name
        FROM articles a
        LEFT JOIN doctors author   ON author.id = a.author_id
        LEFT JOIN doctors reviewer ON reviewer.id = a.reviewer_id
    ';

    public function __construct(private readonly Database $db)
    {
    }

    protected function table(): string
    {
        return 'articles';
    }

    /**
     * Published articles for the public hub.
     *
     * @return list<Article>
     */
    public function publishedList(
        ?string $category = null,
        ?string $search = null,
        int $limit = 12,
        int $offset = 0,
    ): array {
        [$where, $params] = $this->publicFilters($category, $search);

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where . ' ORDER BY a.published_at DESC LIMIT :limit OFFSET :offset',
            $params,
        );

        return array_map(Article::fromRow(...), $rows);
    }

    public function countPublished(?string $category = null, ?string $search = null): int
    {
        [$where, $params] = $this->publicFilters($category, $search);

        return $this->db->fetchInt('SELECT COUNT(*) FROM articles a' . $where, $params);
    }

    /** @return array{string, array<string, mixed>} */
    private function publicFilters(?string $category, ?string $search): array
    {
        $where  = ["a.status = 'published'", 'a.deleted_at IS NULL', 'a.published_at <= UTC_TIMESTAMP()'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[]            = 'a.category = :category';
            $params['category'] = $category;
        }

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(
                ['a.title', 'a.excerpt', 'a.title_am', 'a.focus_keyword'],
                $search,
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . "
             WHERE a.slug = :slug
               AND a.status = 'published'
               AND a.deleted_at IS NULL
               AND a.published_at <= UTC_TIMESTAMP()",
            ['slug' => $slug],
        );

        return $row === null ? null : Article::fromRow($row);
    }

    public function findById(int $id): ?Article
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE a.id = :id AND a.deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : Article::fromRow($row);
    }

    /**
     * Related reading for an article page: same category first, then any
     * other recent piece, never the article itself.
     *
     * @return list<Article>
     */
    public function related(int $excludeId, string $category, int $limit = 3): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . "
             WHERE a.id <> :id
               AND a.status = 'published'
               AND a.deleted_at IS NULL
               AND a.published_at <= UTC_TIMESTAMP()
             ORDER BY (a.category = :category) DESC, a.published_at DESC
             LIMIT :limit",
            ['id' => $excludeId, 'category' => $category, 'limit' => $limit],
        );

        return array_map(Article::fromRow(...), $rows);
    }

    /** @return list<Article> */
    public function adminList(?string $status = null, ?string $search = null, int $limit = 25, int $offset = 0): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]          = 'a.status = :status';
            $params['status'] = $status;
        }

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(['a.title', 'a.category'], $search);

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY a.updated_at DESC LIMIT :limit OFFSET :offset',
            $params,
        );

        return array_map(Article::fromRow(...), $rows);
    }

    public function countAdmin(?string $status = null, ?string $search = null): int
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(['title', 'category'], $search);

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM articles WHERE ' . implode(' AND ', $where),
            $params,
        );
    }

    /**
     * Distinct categories with their published counts, for filter chips.
     *
     * @return array<string, int>
     */
    public function categoryCounts(): array
    {
        return array_map('intval', $this->db->fetchPairs(
            "SELECT category, COUNT(*) FROM articles
             WHERE status = 'published' AND deleted_at IS NULL AND published_at <= UTC_TIMESTAMP()
             GROUP BY category
             ORDER BY category"
        ));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        return $this->updateRow($id, $data);
    }

    /**
     * Increment the view counter.
     *
     * Deliberately not inside the page's main transaction and not awaited:
     * an analytics counter must never be able to fail a reader's page load.
     */
    public function incrementViews(int $id): void
    {
        $this->db->execute('UPDATE articles SET views = views + 1 WHERE id = :id', ['id' => $id]);
    }

    /**
     * Every published URL, for sitemap.xml.
     *
     * @return list<array{slug:string, updated_at:string, published_at:string, has_am:int}>
     */
    public function sitemapEntries(): array
    {
        return $this->db->fetchAll(
            "SELECT slug, updated_at, published_at,
                    (title_am IS NOT NULL AND content_am IS NOT NULL) AS has_am
             FROM articles
             WHERE status = 'published' AND deleted_at IS NULL AND published_at <= UTC_TIMESTAMP()
             ORDER BY published_at DESC"
        );
    }

    /** @return list<Article> Most-read published pieces, for the sidebar. */
    public function mostRead(int $limit = 5): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . "
             WHERE a.status = 'published' AND a.deleted_at IS NULL AND a.published_at <= UTC_TIMESTAMP()
             ORDER BY a.views DESC
             LIMIT :limit",
            ['limit' => $limit],
        );

        return array_map(Article::fromRow(...), $rows);
    }
}
