<?php
/**
 * % de cada KPI principal sobre activos totales.
 * @var array $summary
 * @var bool $hide
 * @var list<array{label:string,value:float,tone?:string}>|null $items
 */
$hide = $hide ?? hide_amounts();
$totalAssets = (float) ($summary['total_assets'] ?? 0);
if (!isset($items) || !is_array($items)) {
    $items = [
        ['label' => 'Neto / activos', 'value' => (float) ($summary['net_worth'] ?? 0), 'tone' => 'accent'],
        ['label' => 'Activos / activos', 'value' => $totalAssets, 'tone' => 'tone-blue'],
        ['label' => 'Pasivos / activos', 'value' => (float) ($summary['liabilities'] ?? 0), 'tone' => 'tone-rose'],
        ['label' => 'Liquidez / activos', 'value' => (float) ($summary['liquidity'] ?? 0), 'tone' => 'tone-green'],
    ];
}
$fmtPct = static function (float $value, float $base): string {
    if ($base <= 0.009) {
        return '—';
    }
    return number_format(round(($value / $base) * 1000) / 10, 1, ',', '.') . '%';
};
?>
<div class="stat-row stat-row-pct" data-skeleton-target>
    <?php foreach ($items as $item):
        $tone = (string) ($item['tone'] ?? '');
        $val = (float) ($item['value'] ?? 0);
        $pctLabel = $hide ? '••••' : $fmtPct($val, $totalAssets);
        ?>
        <div class="stat stat-pct<?= $tone !== '' ? ' ' . e($tone) : '' ?>">
            <span class="stat-label"><?= e((string) ($item['label'] ?? '')) ?></span>
            <span class="stat-value"><?= e($pctLabel) ?></span>
            <span class="stat-hint muted">Sobre activos totales</span>
        </div>
    <?php endforeach; ?>
</div>
