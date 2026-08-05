<?php
/** @var int $count */
$count = max(1, (int) ($count ?? 4));
?>
<div class="stat-row skeleton-row" data-skeleton aria-hidden="true">
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="stat skeleton-card">
            <span class="skeleton-line short"></span>
            <span class="skeleton-line"></span>
        </div>
    <?php endfor; ?>
</div>
