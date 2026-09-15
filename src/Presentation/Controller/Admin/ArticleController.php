<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\ArticleStatus;
use Aster\Domain\Enum\SchemaType;
use Aster\Domain\Exception\HttpException;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\CrudOperations;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Storage\FileUploader;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\HtmlSanitiser;
use Aster\Presentation\Http\Response as HttpResponse;
use Aster\Presentation\View\View;

/**
 * The Health Knowledge Hub CMS.
 *
 * Two rules distinguish this from a generic blog editor:
 *
 *  1. Publishing needs the articles.publish permission. A doctor may draft
 *     and edit but not push live, which is what the `review` state exists
 *     for - medical content gets clinician sign-off before patients read it.
 *  2. Body HTML is sanitised on SAVE, not only on render. Storing clean
 *     content means a future template that forgets to escape cannot resurrect
 *     an injected payload.
 */
final class ArticleController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly ArticleRepository $articles,
        private readonly DoctorRepository $doctors,
        private readonly FileUploader $uploader,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $search = $request->input('q');

        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginate($request, 20);

        $total = $this->articles->countAdmin($status, $search);

        return $this->renderAdmin('admin/articles/index', [
            'articles'   => $this->articles->adminList($status, $search, $perPage, $offset),
            'statuses'   => ArticleStatus::all(),
            'filters'    => ['status' => $status, 'q' => $search],
            'pagination' => $this->paginationMeta($total, $page, $perPage),
            'canPublish' => $this->requireUser()->can('articles.publish'),
            'meta'       => ['title' => 'Health Articles', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        $id      = $request->routeInt('id');
        $article = $id > 0 ? $this->articles->findById($id) : null;

        if ($id > 0 && $article === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/articles/form', [
            'article'     => $article,
            'doctors'     => $this->doctors->options(false),
            'statuses'    => ArticleStatus::all(),
            'schemaTypes' => SchemaType::all(),
            'categories'  => array_keys($this->articles->categoryCounts()),
            'canPublish'  => $this->requireUser()->can('articles.publish'),
            'meta'        => [
                'title'   => $article === null ? 'New Article' : 'Edit: ' . $article->title,
                'noindex' => true,
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $user  = $this->requireUser();
        $id    = $request->routeInt('id');
        $title = $request->string('title');

        $formPath = $this->config->adminPath . '/articles' . ($id > 0 ? '/' . $id . '/edit' : '/create');

        if ($title === '') {
            return $this->redirectWithError($formPath, 'The article title is required.');
        }

        $status = ArticleStatus::tryFrom($request->string('status')) ?? ArticleStatus::DRAFT;

        // Publishing is gated on a separate permission from editing; an
        // author without it silently lands in review rather than being
        // blocked, so their work is never lost.
        if ($status->requiresPublishPermission() && !$user->can('articles.publish')) {
            $status = ArticleStatus::REVIEW;
            $this->session->flash(
                'warning',
                'Saved for clinical review - publishing requires additional permission.',
            );
        }

        $data = [
            'title'            => $title,
            'title_am'         => $request->input('title_am'),
            'category'         => $request->string('category', 'General'),
            'excerpt'          => $request->input('excerpt'),
            'excerpt_am'       => $request->input('excerpt_am'),
            // Sanitised at the boundary, so what is stored is already safe.
            'content'          => $this->cleanBody($request->input('content')),
            'content_am'       => $this->cleanBody($request->input('content_am')),
            'author_id'        => $request->nullableInt('author_id'),
            'reviewer_id'      => $request->nullableInt('reviewer_id'),
            'schema_type'      => (SchemaType::tryFrom($request->string('schema_type')) ?? SchemaType::MEDICAL_WEB_PAGE)->value,
            'meta_title'       => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'focus_keyword'    => $request->input('focus_keyword'),
            'cover_alt'        => $request->input('cover_alt'),
            'status'           => $status->value,
        ];

        // read_minutes of 0 makes the entity compute it from the body.
        $readMinutes = $request->int('read_minutes', 0);

        if ($readMinutes > 0) {
            $data['read_minutes'] = min(60, $readMinutes);
        }

        // The CHECK constraint requires published rows to carry a date, and
        // the sitemap and JSON-LD both depend on it.
        if ($status === ArticleStatus::PUBLISHED) {
            $existing = $id > 0 ? $this->articles->findById($id) : null;

            $data['published_at'] = $existing?->publishedAt?->format('Y-m-d H:i:s')
                ?? gmdate('Y-m-d H:i:s');
        }

        $cover = $request->file('cover_image');

        if ($cover !== null) {
            $stored               = $this->uploader->storeMedia($cover, 'articles', 1600);
            $data['cover_image']  = $stored->relativePath;
        }

        if ($id > 0) {
            $before = $this->articles->rawRow($id);

            if ($before === null) {
                throw HttpException::notFound();
            }

            if ((string) $before['title'] !== $title) {
                $data['slug'] = $this->articles->uniqueSlug(CrudOperations::slugify($title), $id);
            }

            $this->articles->update($id, $data);

            // The body is excluded from the diff: storing two full article
            // versions on every save would bloat the audit table and bury
            // the field that actually changed.
            $auditable = $data;
            unset($auditable['content'], $auditable['content_am']);

            $this->audit->recordDiff(
                AuditLogger::CONTENT_UPDATED,
                'article',
                $id,
                $before,
                $auditable,
                'Updated article: ' . $title,
            );

            return $this->redirectWithSuccess(
                $this->config->adminPath . '/articles',
                'Article saved as ' . $status->label() . '.',
            );
        }

        $data['slug'] = $this->articles->uniqueSlug(CrudOperations::slugify($title));

        $newId = $this->articles->create($data);

        $this->audit->record(AuditLogger::CONTENT_CREATED, 'article', $newId, 'Created article: ' . $title);

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/articles',
            'Article created as ' . $status->label() . '.',
        );
    }

    /** Quick status change from the list view. */
    public function updateStatus(Request $request): Response
    {
        $user    = $this->requireUser();
        $id      = $request->routeInt('id');
        $article = $this->articles->findById($id);

        if ($article === null) {
            throw HttpException::notFound();
        }

        $target = ArticleStatus::tryFrom($request->string('status'));

        if ($target === null || !$article->status->canTransitionTo($target)) {
            return $this->redirectWithError(
                $this->config->adminPath . '/articles',
                'That status change is not allowed.',
            );
        }

        if ($target->requiresPublishPermission() && !$user->can('articles.publish')) {
            throw HttpException::forbidden();
        }

        $update = ['status' => $target->value];

        if ($target === ArticleStatus::PUBLISHED && $article->publishedAt === null) {
            $update['published_at'] = gmdate('Y-m-d H:i:s');
        }

        $this->articles->update($id, $update);

        $this->audit->record(
            AuditLogger::CONTENT_UPDATED,
            'article',
            $id,
            sprintf('Article status %s -> %s', $article->status->label(), $target->label()),
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/articles',
            'Article is now ' . $target->label() . '.',
        );
    }

    public function delete(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->articles->softDelete($id);
        $this->audit->record(AuditLogger::CONTENT_DELETED, 'article', $id, 'Deleted article');

        return $this->redirectWithSuccess($this->config->adminPath . '/articles', 'Article deleted.');
    }

    /**
     * Sanitise rich-text input.
     *
     * Clinic staff paste from Word and Google Docs, which brings font tags,
     * class soup and occasionally script fragments. HtmlSanitiser reduces all
     * of it to the allowed element set.
     */
    private function cleanBody(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return HtmlSanitiser::clean($html);
    }
}
