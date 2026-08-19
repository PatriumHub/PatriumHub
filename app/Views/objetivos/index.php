<?php
/** @var string $currency */
/** @var list<array<string,mixed>> $currencies */
/** @var list<array<string,mixed>> $ramsey */
/** @var list<array<string,mixed>> $custom */
/** @var int $milestonesDone */
/** @var int $milestonesTotal */
/** @var int $customDone */
/** @var int $customTotal */
/** @var bool $canWrite */
$hide = hide_amounts();
?>
<section class="page objetivos-page">
    <div class="page-head">
        <div>
            <h1>Objetivos fundamentales</h1>
        </div>
        <form method="get" action="<?= e(url('/objetivos')) ?>" class="filters inline">
            <label>Moneda
                <select name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="obj-block">
        <div class="cp-block-head">
            <div>
                <h2>Milestones</h2>
            </div>
            <div class="obj-fulfilled">Cumplidos <?= (int) $milestonesDone ?>/<?= (int) $milestonesTotal ?></div>
        </div>
        <div class="obj-rules">
            <?php foreach ($ramsey as $rule):
                $pct = (float) ($rule['pct'] ?? 0);
                $tone = (string) ($rule['tone'] ?? 'low');
                $width = max(0, min(100, (int) round($pct)));
                $ruleCur = (string) ($rule['currency'] ?? $currency);
                $editUrl = (string) ($rule['edit_url'] ?? '');
                ?>
                <article class="obj-card tone-<?= e($tone) ?><?= !empty($rule['done']) ? ' is-done' : '' ?>">
                    <div class="obj-card-head">
                        <div>
                            <h3><?= e((string) $rule['title']) ?></h3>
                            <p><?= e((string) $rule['description']) ?></p>
                        </div>
                        <strong class="obj-pct"><?= $hide ? '—' : e(number_format($pct, 1, ',', '.') . '%') ?></strong>
                    </div>
                    <div class="obj-bar" role="progressbar" aria-valuenow="<?= (int) $width ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width:<?= (int) $width ?>%"></span>
                    </div>
                    <div class="obj-card-meta">
                        <span><?= e((string) ($rule['hint'] ?? '')) ?></span>
                        <?php if (!$hide && (float) ($rule['target'] ?? 0) > 0.009): ?>
                            <span class="obj-amounts">
                                <?= e(money((float) $rule['current'], $ruleCur)) ?>
                                / <?= e(money((float) $rule['target'], $ruleCur)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($rule['editable']) && $canWrite && $editUrl !== ''): ?>
                        <div class="obj-actions">
                            <form method="post" action="<?= e(url($editUrl)) ?>" class="obj-progress-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                                <?php if (!empty($rule['plan_year'])): ?>
                                    <input type="hidden" name="plan_year" value="<?= e((string) $rule['plan_year']) ?>">
                                <?php endif; ?>
                                <label><?= e((string) ($rule['edit_label'] ?? 'Juntado')) ?>
                                    <input type="number" name="current_amount" step="0.01" min="0"
                                           value="<?= e((string) ((float) ($rule['current'] ?? 0))) ?>" required>
                                </label>
                                <button type="submit" class="btn ghost">Guardar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <hr class="obj-divider" aria-hidden="true">

    <div class="obj-block">
        <div class="cp-block-head">
            <div>
                <h2>Tus objetivos</h2>
            </div>
            <div class="obj-head-actions">
                <div class="obj-fulfilled">Cumplidos <?= (int) $customDone ?>/<?= (int) $customTotal ?></div>
                <?php if ($canWrite): ?>
                    <a class="btn" href="<?= e(url('/objetivos/nuevo?currency=' . urlencode($currency))) ?>">Agregar</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($custom === []): ?>
            <p class="muted obj-empty">Todavía no hay objetivos personalizados en <?= e($currency) ?>.</p>
        <?php else: ?>
            <div class="obj-custom-list">
                <?php foreach ($custom as $goal):
                    $pct = (float) ($goal['pct'] ?? 0);
                    $tone = (string) ($goal['tone'] ?? 'low');
                    $width = max(0, min(100, (int) round($pct)));
                    $gid = (int) $goal['id'];
                    ?>
                    <article class="obj-card tone-<?= e($tone) ?><?= ($goal['status'] ?? '') === 'completed' ? ' is-done' : '' ?>">
                        <div class="obj-card-head">
                            <div>
                                <h3><?= e((string) $goal['name']) ?></h3>
                                <p>
                                    <?= e((string) $goal['starts_on']) ?> → <?= e((string) $goal['ends_on']) ?>
                                    <?php if (($goal['status'] ?? '') === 'completed'): ?>
                                        · Completado
                                    <?php endif; ?>
                                </p>
                            </div>
                            <strong class="obj-pct"><?= $hide ? '—' : e(number_format($pct, 1, ',', '.') . '%') ?></strong>
                        </div>
                        <div class="obj-bar" role="progressbar" aria-valuenow="<?= (int) $width ?>" aria-valuemin="0" aria-valuemax="100">
                            <span style="width:<?= (int) $width ?>%"></span>
                        </div>
                        <div class="obj-card-meta">
                            <span>
                                <?php if ($hide): ?>
                                    Cifras ocultas
                                <?php else: ?>
                                    Juntado <?= e(money((float) $goal['current_amount'], $currency)) ?>
                                    de <?= e(money((float) $goal['target_amount'], $currency)) ?>
                                    · faltan <?= e(money((float) $goal['remaining'], $currency)) ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($goal['time_pct'] !== null && !$hide): ?>
                                <span class="obj-time">Tiempo transcurrido <?= e(number_format((float) $goal['time_pct'], 0, ',', '.')) ?>%</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($goal['notes'])): ?>
                            <p class="obj-notes"><?= e((string) $goal['notes']) ?></p>
                        <?php endif; ?>

                        <?php if ($canWrite): ?>
                            <div class="obj-actions">
                                <form method="post" action="<?= e(url('/objetivos/' . $gid . '/progreso')) ?>" class="obj-progress-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                                    <label>Actualizar juntado
                                        <input type="number" name="current_amount" step="0.01" min="0"
                                               value="<?= e((string) $goal['current_amount']) ?>" required>
                                    </label>
                                    <button type="submit" class="btn ghost">Guardar</button>
                                </form>
                                <form method="post" action="<?= e(url('/objetivos/' . $gid . '/eliminar')) ?>"
                                      onsubmit="return confirm('¿Eliminar este objetivo?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                                    <button type="submit" class="btn danger ghost">Eliminar</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
