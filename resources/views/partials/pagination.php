<?php
/**
 * Pagination control.
 *
 * Real <a> links, so pages are crawlable and the browser back button works.
 *
 * @var \Aster\Presentation\View\View $view
 * @var array<string,int|bool>        $pagination
 * @var string                        $basePath
 * @var callable(array):string|null   $query      builds the query string
 */

declare(strict_types=1);

if (($pagination['lastPage'] ?? 1) <= 1) {
    return;
}

$page     = (int) $pagination['page'];
$lastPage = (int) $pagination['lastPage'];

$link = static function (int $target) use ($view, $basePath, $query): string {
    $suffix = is_callable($query) ? $query(['page' => $target > 1 ? $target : null]) : ($target > 1 ? '?page=' . $target : '');

    return $view->url($basePath . $suffix);
};

/**
 * Window of page numbers around the current page, so a 40-page archive does
 * not render 40 links on a phone.
 */
$window = [];
$from   = max(1, $page - 2);
$to     = min($lastPage, $page + 2);

for ($i = $from; $i <= $to; $i++) {
    $window[] = $i;
}
?>
<nav class="mt-10 flex flex-wrap items-center justify-between gap-4" aria-label="Pagination">
    <p class="text-xs text-slate-500">
        <?= (int) $pagination['from'] ?>&ndash;<?= (int) $pagination['to'] ?> of <?= (int) $pagination['total'] ?>
    </p>

    <div class="flex flex-wrap items-center gap-1.5">
        <?php if ($pagination['hasPrev']): ?>
            <a href="<?= $link($page - 1) ?>" class="chip" rel="prev">&larr;</a>
        <?php endif; ?>

        <?php if ($from > 1): ?>
            <a href="<?= $link(1) ?>" class="chip">1</a>
            <?php if ($from > 2): ?>
                <span class="px-1 text-slate-400" aria-hidden="true">&hellip;</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($window as $number): ?>
            <a href="<?= $link($number) ?>"
               class="chip <?= $number === $page ? 'chip-active' : '' ?>"
               <?= $number === $page ? 'aria-current="page"' : '' ?>>
                <?= $number ?>
            </a>
        <?php endforeach; ?>

        <?php if ($to < $lastPage): ?>
            <?php if ($to < $lastPage - 1): ?>
                <span class="px-1 text-slate-400" aria-hidden="true">&hellip;</span>
            <?php endif; ?>
            <a href="<?= $link($lastPage) ?>" class="chip"><?= $lastPage ?></a>
        <?php endif; ?>

        <?php if ($pagination['hasNext']): ?>
            <a href="<?= $link($page + 1) ?>" class="chip" rel="next">&rarr;</a>
        <?php endif; ?>
    </div>
</nav>
