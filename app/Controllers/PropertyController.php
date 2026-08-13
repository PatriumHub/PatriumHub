<?php

declare(strict_types=1);

final class PropertyController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        $rows = Database::pdo()->query(
            "SELECT p.*, e.name AS entity_name
             FROM properties p
             INNER JOIN entities e ON e.id = p.entity_id
             ORDER BY e.name, p.name"
        )->fetchAll();
        view('properties/index', [
            'title' => 'Propiedades',
            'rows' => $rows,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        view('properties/form', [
            'title' => 'Nueva propiedad',
            'property' => [
                'entity_id' => request_int('entity_id'),
                'property_type' => 'house',
                'currency_code' => 'USD',
                'ownership_pct' => 100,
                'include_in_net_worth' => 1,
                'status' => 'active',
                'current_value' => 0,
            ],
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'liabilities' => self::openLiabilities(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $entityId = request_int('entity_id');
        $name = post_string('name');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
            redirect('/propiedades/nueva');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO properties
             (entity_id, name, property_type, location_text, ownership_pct, currency_code,
              current_value, valuation_method, valued_at, linked_liability_id,
              include_in_net_worth, status, notes)
             VALUES
             (:entity_id, :name, :property_type, :location_text, :ownership_pct, :currency_code,
              :current_value, :valuation_method, :valued_at, :linked_liability_id,
              :include_in_net_worth, :status, :notes)'
        );
        $stmt->execute(self::payload($entityId, $name));
        $id = (int) Database::pdo()->lastInsertId();
        AuditService::log('create', 'property', $id, 'Alta propiedad: ' . $name);
        flash('success', 'Propiedad creada.');
        redirect('/propiedades');
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        $property = self::find((int) $id);
        if (!$property) {
            flash('error', 'Propiedad no encontrada.');
            redirect('/propiedades');
        }
        view('properties/form', [
            'title' => 'Editar propiedad',
            'property' => $property,
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'liabilities' => self::openLiabilities(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $propertyId = (int) $id;
        $before = self::find($propertyId);
        if (!$before) {
            flash('error', 'Propiedad no encontrada.');
            redirect('/propiedades');
        }
        $entityId = request_int('entity_id');
        $name = post_string('name');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
            redirect('/propiedades/' . $propertyId . '/editar');
        }
        $payload = self::payload($entityId, $name);
        $payload['id'] = $propertyId;
        Database::pdo()->prepare(
            'UPDATE properties SET entity_id = :entity_id, name = :name, property_type = :property_type,
             location_text = :location_text, ownership_pct = :ownership_pct, currency_code = :currency_code,
             current_value = :current_value, valuation_method = :valuation_method, valued_at = :valued_at,
             linked_liability_id = :linked_liability_id, include_in_net_worth = :include_in_net_worth,
             status = :status, notes = :notes WHERE id = :id'
        )->execute($payload);
        AuditService::log('update', 'property', $propertyId, 'Edición propiedad: ' . $name, $before, self::find($propertyId));
        flash('success', 'Propiedad actualizada.');
        redirect('/propiedades');
    }

    private static function payload(int $entityId, string $name): array
    {
        $liability = request_int('linked_liability_id');
        return [
            'entity_id' => $entityId,
            'name' => $name,
            'property_type' => post_string('property_type', 'other'),
            'location_text' => post_string('location_text') ?: null,
            'ownership_pct' => self::ownershipPctForEntity($entityId),
            'currency_code' => post_string('currency_code', 'USD'),
            'current_value' => post_float('current_value'),
            'valuation_method' => post_string('valuation_method') ?: null,
            'valued_at' => post_string('valued_at') ?: null,
            'linked_liability_id' => $liability,
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'status' => post_string('status', 'active'),
            'notes' => post_string('notes') ?: null,
        ];
    }

    private static function ownershipPctForEntity(int $entityId): float
    {
        $stmt = Database::pdo()->prepare('SELECT type FROM entities WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $entityId]);
        if ((string) $stmt->fetchColumn() === 'company') {
            return 100.0;
        }
        return post_float('ownership_pct', 100);
    }

    private static function openLiabilities(): array
    {
        return Database::pdo()
            ->query("SELECT id, name FROM liabilities WHERE status = 'open' ORDER BY name")
            ->fetchAll();
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
