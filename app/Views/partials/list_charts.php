<?php
/**
 * Grilla de gráficos reutilizable para listados de Patrimonio.
 * @var array $charts  byType/byEntity labels+values
 * @var string $currency
 * @var bool $hide
 * @var string $titleType
 * @var string $titleEntity
 * @var string $hintType
 * @var string $hintEntity
 * @var string $unitLabel  'money'|'count'|'pct'
 */
$charts = $charts ?? ['byType' => ['labels' => [], 'values' => []], 'byEntity' => ['labels' => [], 'values' => []]];
$hide = !empty($hide);
$currency = $currency ?? 'ARS';
$titleType = $titleType ?? 'Por tipo';
$titleEntity = $titleEntity ?? 'Por entidad';
$hintType = $hintType ?? '';
$hintEntity = $hintEntity ?? '';
$unitLabel = $unitLabel ?? 'money';
$hideCharts = $hide && $unitLabel === 'money';
$byType = $charts['byType'] ?? ['labels' => [], 'values' => []];
$byEntity = $charts['byEntity'] ?? ['labels' => [], 'values' => []];
if (($byType['labels'] ?? []) === []) {
    $byType = ['labels' => ['Sin datos'], 'values' => [0]];
}
if (($byEntity['labels'] ?? []) === []) {
    $byEntity = ['labels' => ['Sin datos'], 'values' => [0]];
}
$chartPayload = [
    'hide' => $hideCharts,
    'currency' => $currency,
    'unit' => $unitLabel,
    'byType' => $byType,
    'byEntity' => $byEntity,
];
?>
<div class="charts-grid mt">
    <div class="chart-card tone-amber">
        <h2><?= e($titleType) ?></h2>
        <?php if ($hintType !== ''): ?><p class="chart-hint"><?= e($hintType) ?></p><?php endif; ?>
        <?php if ($hideCharts): ?>
            <div class="chart-empty">Cifras ocultas</div>
        <?php else: ?>
            <div class="chart-wrap"><canvas id="chartListByType"></canvas></div>
        <?php endif; ?>
    </div>
    <div class="chart-card tone-blue">
        <h2><?= e($titleEntity) ?></h2>
        <?php if ($hintEntity !== ''): ?><p class="chart-hint"><?= e($hintEntity) ?></p><?php endif; ?>
        <?php if ($hideCharts): ?>
            <div class="chart-empty">Cifras ocultas</div>
        <?php else: ?>
            <div class="chart-wrap"><canvas id="chartListByEntity"></canvas></div>
        <?php endif; ?>
    </div>
</div>
<script type="application/json" id="list-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
