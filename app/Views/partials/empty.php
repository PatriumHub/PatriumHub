<?php
/** @var string $message */
/** @var string|null $ctaHref */
/** @var string|null $ctaLabel */
$message = $message ?? 'Sin datos.';
$ctaHref = $ctaHref ?? null;
$ctaLabel = $ctaLabel ?? null;
?>
<div class="panel empty">
    <p><?= e($message) ?></p>
    <?php if ($ctaHref && $ctaLabel): ?>
        <p class="mt"><a class="btn" href="<?= e(url($ctaHref)) ?>"><?= e($ctaLabel) ?></a></p>
    <?php endif; ?>
</div>
