<?php
/** @var array $pagination */
/** @var string $pagerRoute */
/** @var array<string, scalar|null> $pagerQuery */
$pagination = $pagination ?? null;
$pagerRoute = $pagerRoute ?? '/';
$pagerQuery = $pagerQuery ?? [];
if (!$pagination || (int) ($pagination['total'] ?? 0) <= (int) ($pagination['per_page'] ?? 50)) {
    if ($pagination && (int) ($pagination['total'] ?? 0) > 0) {
        echo '<p class="pager-meta muted">'
            . e((string) $pagination['from']) . '–' . e((string) $pagination['to'])
            . ' de ' . e((string) $pagination['total'])
            . '</p>';
    }
    return;
}
?>
<nav class="pager" aria-label="Paginación">
    <p class="pager-meta muted">
        <?= e((string) $pagination['from']) ?>–<?= e((string) $pagination['to']) ?>
        de <?= e((string) $pagination['total']) ?>
    </p>
    <div class="pager-links">
        <?php if (!empty($pagination['has_prev'])): ?>
            <a class="btn ghost" href="<?= e(Pagination::url($pagerRoute, $pagerQuery, (int) $pagination['page'] - 1)) ?>">Anterior</a>
        <?php else: ?>
            <span class="btn ghost is-disabled">Anterior</span>
        <?php endif; ?>
        <span class="pager-page muted">Pág. <?= (int) $pagination['page'] ?> / <?= (int) $pagination['total_pages'] ?></span>
        <?php if (!empty($pagination['has_next'])): ?>
            <a class="btn ghost" href="<?= e(Pagination::url($pagerRoute, $pagerQuery, (int) $pagination['page'] + 1)) ?>">Siguiente</a>
        <?php else: ?>
            <span class="btn ghost is-disabled">Siguiente</span>
        <?php endif; ?>
    </div>
</nav>
