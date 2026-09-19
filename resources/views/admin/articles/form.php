<?php
/**
 * Article editor.
 *
 * The SEO panel is deliberately prominent: this hub exists to win organic
 * search traffic, and the schema type plus the focus keyword are what make
 * the difference between a plain blue link and a rich health result.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Article|null $article
 * @var array<int,string> $doctors
 * @var list<\Aster\Domain\Enum\ArticleStatus> $statuses
 * @var list<\Aster\Domain\Enum\SchemaType> $schemaTypes
 * @var list<string> $categories
 * @var bool $canPublish
 * @var array<string,string> $old
 */

declare(strict_types=1);

use Aster\Domain\Enum\ArticleStatus;
use Aster\Domain\Enum\Locale;

$action = $article === null
    ? $view->adminUrl('articles')
    : $view->adminUrl('articles/' . $article->id);

$val = static fn (string $key, mixed $current = ''): string => (string) ($old[$key] ?? $current ?? '');
?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1.5fr_0.5fr]">
    <?= $view->csrfField() ?>

    <!-- ---------------- Content ---------------- -->
    <div class="grid gap-6">
        <div class="card-pad grid gap-5">
            <div class="field">
                <label class="label" for="title">Title (English) <span class="text-rose-500" aria-hidden="true">*</span></label>
                <input class="input text-lg font-bold" type="text" id="title" name="title" required
                       value="<?= $view->e($val('title', $article?->title)) ?>">
            </div>

            <div class="field">
                <label class="label" for="excerpt">Excerpt (English)</label>
                <textarea class="textarea" id="excerpt" name="excerpt" rows="2"
                          maxlength="500"><?= $view->e($val('excerpt', $article?->excerpt)) ?></textarea>
                <span class="hint">Shown on cards and used as the fallback meta description.</span>
            </div>

            <div class="field">
                <label class="label" for="content">Body (English)</label>
                <textarea class="textarea font-mono text-xs" id="content" name="content" rows="18"><?= $view->e($val('content', $article?->content)) ?></textarea>
                <span class="hint">
                    HTML is allowed but filtered to a safe set on save:
                    p, h2-h4, ul, ol, li, strong, em, a, img, blockquote, table.
                </span>
            </div>
        </div>

        <div class="card-pad grid gap-5">
            <h2 class="text-base font-extrabold text-medical-900">Amharic translation</h2>

            <div class="field">
                <label class="label" for="title_am">Title (Amharic)</label>
                <input class="input font-ethiopic text-lg font-bold" type="text" id="title_am" name="title_am" lang="am-ET"
                       value="<?= $view->e($val('title_am', $article?->titleAm)) ?>">
            </div>

            <div class="field">
                <label class="label" for="excerpt_am">Excerpt (Amharic)</label>
                <textarea class="textarea font-ethiopic" id="excerpt_am" name="excerpt_am" rows="2" lang="am-ET"
                          maxlength="500"><?= $view->e($val('excerpt_am', $article?->excerptAm)) ?></textarea>
            </div>

            <div class="field">
                <label class="label" for="content_am">Body (Amharic)</label>
                <textarea class="textarea font-ethiopic text-sm" id="content_am" name="content_am" rows="18"
                          lang="am-ET"><?= $view->e($val('content_am', $article?->contentAm)) ?></textarea>
                <span class="hint">
                    Leave blank if not translated yet - the article falls back to English and is
                    excluded from the Amharic hreflang alternate.
                </span>
            </div>
        </div>

        <div class="card-pad grid gap-5">
            <h2 class="text-base font-extrabold text-medical-900">Afaan Oromoo translation</h2>

            <div class="field">
                <label class="label" for="title_om">Title (Afaan Oromoo)</label>
                <input class="input text-lg font-bold" type="text" id="title_om" name="title_om" lang="om-ET"
                       value="<?= $view->e($val('title_om', $article?->titleOm)) ?>">
            </div>

            <div class="field">
                <label class="label" for="excerpt_om">Excerpt (Afaan Oromoo)</label>
                <textarea class="textarea" id="excerpt_om" name="excerpt_om" rows="2" lang="om-ET"
                          maxlength="500"><?= $view->e($val('excerpt_om', $article?->excerptOm)) ?></textarea>
            </div>

            <div class="field">
                <label class="label" for="content_om">Body (Afaan Oromoo)</label>
                <textarea class="textarea text-sm" id="content_om" name="content_om" rows="18"
                          lang="om-ET"><?= $view->e($val('content_om', $article?->contentOm)) ?></textarea>
                <span class="hint">
                    Leave blank if not translated yet - the article falls back to English and is
                    excluded from the Afaan Oromoo hreflang alternate.
                </span>
            </div>
        </div>
    </div>

    <!-- ---------------- Sidebar ---------------- -->
    <aside class="grid h-max gap-6">
        <div class="card-pad grid gap-4">
            <h2 class="text-base font-extrabold text-medical-900">Publishing</h2>

            <div class="field">
                <label class="label" for="status">Status</label>
                <select class="select" id="status" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <?php if ($status->requiresPublishPermission() && !$canPublish) { continue; } ?>
                        <option value="<?= $view->e($status->value) ?>"
                            <?= $view->attr(($article?->status->value ?? 'draft') === $status->value, 'selected') ?>>
                            <?= $view->e($status->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$canPublish): ?>
                    <span class="hint text-amber-600">
                        You can draft and submit for review; publishing needs additional permission.
                    </span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="category">Category</label>
                <input class="input" type="text" id="category" name="category" list="category-list"
                       value="<?= $view->e($val('category', $article?->category ?? 'General')) ?>">
                <datalist id="category-list">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $view->e($category) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label class="label" for="author_id">Author</label>
                <select class="select" id="author_id" name="author_id">
                    <option value="">Not set</option>
                    <?php foreach ($doctors as $id => $name): ?>
                        <option value="<?= (int) $id ?>" <?= $view->attr($article?->authorId === (int) $id, 'selected') ?>>
                            <?= $view->e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="reviewer_id">Medically reviewed by</label>
                <select class="select" id="reviewer_id" name="reviewer_id">
                    <option value="">Not reviewed</option>
                    <?php foreach ($doctors as $id => $name): ?>
                        <option value="<?= (int) $id ?>" <?= $view->attr($article?->reviewerId === (int) $id, 'selected') ?>>
                            <?= $view->e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">
                    A named clinical reviewer is emitted in the page's structured data and is what
                    signals trustworthy health content.
                </span>
            </div>

            <div class="field">
                <label class="label" for="read_minutes">Read time (minutes)</label>
                <input class="input" type="number" id="read_minutes" name="read_minutes" min="0" max="60"
                       value="<?= $view->e($val('read_minutes', (string) ($article?->readMinutes ?? 0))) ?>">
                <span class="hint">0 calculates it from the body automatically.</span>
            </div>
        </div>

        <div class="card-pad grid gap-4">
            <h2 class="text-base font-extrabold text-medical-900">Search optimisation</h2>

            <div class="field">
                <label class="label" for="schema_type">Structured data type</label>
                <select class="select" id="schema_type" name="schema_type">
                    <?php foreach ($schemaTypes as $type): ?>
                        <option value="<?= $view->e($type->value) ?>"
                            <?= $view->attr(($article?->schemaType->value ?? 'MedicalWebPage') === $type->value, 'selected') ?>>
                            <?= $view->e($type->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="focus_keyword">Focus keyword</label>
                <input class="input" type="text" id="focus_keyword" name="focus_keyword"
                       placeholder="e.g. heart health addis ababa"
                       value="<?= $view->e($val('focus_keyword', $article?->focusKeyword)) ?>">
            </div>

            <div class="field">
                <label class="label" for="meta_title">Meta title</label>
                <input class="input" type="text" id="meta_title" name="meta_title" maxlength="190"
                       value="<?= $view->e($val('meta_title', $article?->metaTitle)) ?>">
                <span class="hint">Aim for under 60 characters. Falls back to the article title.</span>
            </div>

            <div class="field">
                <label class="label" for="meta_description">Meta description</label>
                <textarea class="textarea" id="meta_description" name="meta_description" rows="3"
                          maxlength="320"><?= $view->e($val('meta_description', $article?->metaDescription)) ?></textarea>
                <span class="hint">Aim for 150-160 characters.</span>
            </div>
        </div>

        <div class="card-pad grid gap-4">
            <h2 class="text-base font-extrabold text-medical-900">Cover image</h2>

            <?php if ($article?->hasCover()): ?>
                <img src="<?= $view->media($article->coverImage) ?>" alt=""
                     class="h-32 w-full rounded-2xl object-cover" width="320" height="128">
            <?php endif; ?>

            <div class="field">
                <input class="input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                <span class="hint">Converted to WebP automatically.</span>
            </div>

            <div class="field">
                <label class="label" for="cover_alt">Image description (alt text)</label>
                <input class="input" type="text" id="cover_alt" name="cover_alt"
                       value="<?= $view->e($val('cover_alt', $article?->coverAlt)) ?>">
                <span class="hint">Describe the image for screen-reader users.</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary flex-1"><?= $article === null ? 'Create article' : 'Save changes' ?></button>
            <a href="<?= $view->adminUrl('articles') ?>" class="btn-secondary">Cancel</a>
        </div>
    </aside>
</form>
