<?php
/**
 * @var list<array{label:string,clear_url:string}> $chips
 */
$chips = $chips ?? [];
if ($chips === []) {
    return;
}
?>
<div class="chip-row" aria-label="Filtros activos">
    <?php foreach ($chips as $chip): ?>
        <a class="chip" href="<?= e(url($chip['clear_url'])) ?>" title="Quitar filtro">
            <span><?= e($chip['label']) ?></span>
            <span class="chip-x" aria-hidden="true">×</span>
        </a>
    <?php endforeach; ?>
</div>
