<?php

declare(strict_types=1);

final class OwnershipController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = Database::pdo()->query(
            "SELECT o.*, owner.name AS owner_name, company.name AS company_name
             FROM ownerships o
             INNER JOIN entities owner ON owner.id = o.owner_entity_id
             INNER JOIN entities company ON company.id = o.company_entity_id
             ORDER BY company.name, o.ownership_pct DESC"
        )->fetchAll();

        view('ownerships/index', [
            'title' => 'Participaciones',
            'rows' => $rows,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        view('ownerships/form', [
            'title' => 'Nueva participación',
            'item' => [
                'owner_entity_id' => request_int('owner_entity_id'),
                'company_entity_id' => request_int('company_entity_id'),
                'ownership_pct' => 100,
            ],
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireLogin();
        require_csrf();
        $owner = request_int('owner_entity_id');
        $company = request_int('company_entity_id');
        $pct = post_float('ownership_pct', 100);
        if (!$owner || !$company || $pct <= 0 || $pct > 100) {
            flash('error', 'Dueño, empresa y % (0–100] son obligatorios.');
            redirect('/participaciones/nueva');
        }
        try {
            Database::pdo()->prepare(
                'INSERT INTO ownerships (owner_entity_id, company_entity_id, ownership_pct, notes)
                 VALUES (:o, :c, :p, :n)'
            )->execute([
                'o' => $owner,
                'c' => $company,
                'p' => $pct,
                'n' => post_string('notes') ?: null,
            ]);
            $id = (int) Database::pdo()->lastInsertId();
            AuditService::log('create', 'ownership', $id, "Participación {$pct}%");
            flash('success', 'Participación guardada.');
            redirect('/participaciones');
        } catch (Throwable $e) {
            flash('error', 'No se pudo guardar (¿duplicada?): ' . $e->getMessage());
            redirect('/participaciones/nueva');
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireLogin();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Participación no encontrada.');
            redirect('/participaciones');
        }
        view('ownerships/form', [
            'title' => 'Editar participación',
            'item' => $item,
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $ownId = (int) $id;
        $before = self::find($ownId);
        if (!$before) {
            flash('error', 'Participación no encontrada.');
            redirect('/participaciones');
        }
        $owner = request_int('owner_entity_id');
        $company = request_int('company_entity_id');
        $pct = post_float('ownership_pct', 100);
        if (!$owner || !$company || $pct <= 0 || $pct > 100) {
            flash('error', 'Datos inválidos.');
            redirect('/participaciones/' . $ownId . '/editar');
        }
        Database::pdo()->prepare(
            'UPDATE ownerships
             SET owner_entity_id = :o, company_entity_id = :c, ownership_pct = :p, notes = :n
             WHERE id = :id'
        )->execute([
            'o' => $owner,
            'c' => $company,
            'p' => $pct,
            'n' => post_string('notes') ?: null,
            'id' => $ownId,
        ]);
        AuditService::log('update', 'ownership', $ownId, "Participación {$pct}%", $before, self::find($ownId));
        flash('success', 'Participación actualizada.');
        redirect('/participaciones');
    }

    public static function destroy(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $ownId = (int) $id;
        $before = self::find($ownId);
        if ($before) {
            Database::pdo()->prepare('DELETE FROM ownerships WHERE id = :id')->execute(['id' => $ownId]);
            AuditService::log('delete', 'ownership', $ownId, 'Baja participación', $before, null);
        }
        flash('success', 'Participación eliminada.');
        redirect('/participaciones');
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ownerships WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
