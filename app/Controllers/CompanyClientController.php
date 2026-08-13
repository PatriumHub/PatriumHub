<?php

declare(strict_types=1);

final class CompanyClientController
{
    public static function show(string $id, string $clientId): void
    {
        Auth::requireLogin();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        CompanyClientService::listFromProjection($entityId);
        $client = CompanyClientService::findForCompany($entityId, (int) $clientId);
        if (!$client || empty($client['from_projection'])) {
            flash('error', 'Cliente no encontrado en Estados y proyección.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        view('companies/client_show', [
            'title' => $client['name'] . ' · ' . $company['name'],
            'company' => $company,
            'client' => $client,
            'contract' => ClientDocumentService::contract($entityId, (int) $clientId),
            'extraDocs' => ClientDocumentService::extras($entityId, (int) $clientId),
            'canWrite' => Auth::canWrite(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(string $id): void
    {
        Auth::requireWrite();
        $company = self::requireServicesCompany((int) $id);
        flash('success', 'Los clientes se agregan en Estados y proyección (filas de ingresos por cliente).');
        redirect('/empresas/' . (int) $company['id'] . '?tab=estados-proyeccion');
    }

    public static function store(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        flash('success', 'Los clientes se agregan en Estados y proyección.');
        redirect('/empresas/' . (int) $company['id'] . '?tab=estados-proyeccion');
    }

    public static function edit(string $id, string $clientId): void
    {
        Auth::requireWrite();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        CompanyClientService::listFromProjection($entityId);
        $client = CompanyClientService::findForCompany($entityId, (int) $clientId);
        if (!$client || empty($client['from_projection'])) {
            flash('error', 'Cliente no encontrado en Estados y proyección.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        view('companies/client_form', [
            'title' => 'Ficha · ' . $client['name'],
            'company' => $company,
            'client' => $client,
            'contract' => ClientDocumentService::contract($entityId, (int) $clientId),
            'extraDocs' => ClientDocumentService::extras($entityId, (int) $clientId),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id, string $clientId): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $before = CompanyClientService::findForCompany($entityId, (int) $clientId);
        if (!$before) {
            flash('error', 'Cliente no encontrado.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        $result = CompanyClientService::updateContact($entityId, (int) $clientId, [
            'contact_name' => post_string('contact_name'),
            'email' => post_string('email'),
            'phone' => post_string('phone'),
            'contract_notes' => post_string('contract_notes'),
            'is_active' => post_string('is_active', '1') === '1',
        ]);
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo actualizar.');
            redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
        }
        AuditService::log(
            'update',
            'company_client',
            (int) $clientId,
            'Ficha cliente: ' . ($before['name'] ?? ''),
            $before,
            CompanyClientService::findForCompany($entityId, (int) $clientId)
        );
        flash('success', 'Ficha actualizada.');
        redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
    }

    public static function uploadContract(string $id, string $clientId): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $client = CompanyClientService::findForCompany($entityId, (int) $clientId);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        $user = Auth::user();
        $result = ClientDocumentService::storeContract(
            $entityId,
            (int) $clientId,
            $_FILES['contract_pdf'] ?? [],
            $user ? (int) $user['id'] : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo subir el contrato.');
        } else {
            AuditService::log('upload', 'company_client_contract', (int) $clientId, 'Contrato PDF: ' . ($client['name'] ?? ''));
            flash('success', 'Contrato PDF guardado.');
        }
        redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
    }

    public static function uploadDoc(string $id, string $clientId): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $client = CompanyClientService::findForCompany($entityId, (int) $clientId);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        $user = Auth::user();
        $result = ClientDocumentService::storeExtra(
            $entityId,
            (int) $clientId,
            $_FILES['extra_file'] ?? [],
            post_string('title'),
            $user ? (int) $user['id'] : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo subir el documento.');
        } else {
            AuditService::log('upload', 'company_client_file', (int) $clientId, 'Doc extra: ' . post_string('title'));
            flash('success', 'Documento subido.');
        }
        redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
    }

    public static function deleteDoc(string $id, string $clientId, string $docId): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $result = ClientDocumentService::delete($entityId, (int) $clientId, (int) $docId);
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo eliminar.');
        } else {
            AuditService::log('delete', 'document', (int) $docId, 'Archivo cliente eliminado');
            flash('success', 'Archivo eliminado.');
        }
        redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
    }

    public static function download(string $id, string $clientId, string $docId): void
    {
        Auth::requireLogin();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $doc = ClientDocumentService::findForClient($entityId, (int) $clientId, (int) $docId);
        if (!$doc) {
            flash('error', 'Archivo no encontrado.');
            redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId);
        }
        $abs = ClientDocumentService::absolutePath($doc);
        if (!is_file($abs)) {
            flash('error', 'El archivo no está en disco.');
            redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId);
        }
        $name = (string) ($doc['title'] ?? 'archivo');
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        if ($ext !== '' && !str_ends_with(strtolower($name), '.' . strtolower($ext))) {
            $name .= '.' . $ext;
        }
        $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($abs));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    public static function destroy(string $id, string $clientId): void
    {
        Auth::requireWrite();
        require_csrf();
        $company = self::requireServicesCompany((int) $id);
        $entityId = (int) $company['id'];
        $before = CompanyClientService::find((int) $clientId);
        if (!$before || (int) $before['entity_id'] !== $entityId) {
            flash('error', 'Cliente no encontrado.');
            redirect('/empresas/' . $entityId . '?tab=clientes');
        }
        $result = CompanyClientService::clearContact($entityId, (int) $clientId);
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo limpiar.');
            redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId . '/editar');
        }
        AuditService::log('update', 'company_client', (int) $clientId, 'Limpieza ficha: ' . ($before['name'] ?? ''), $before, null);
        flash('success', 'Se borraron datos de contacto. El cliente sigue en Estados y proyección.');
        redirect('/empresas/' . $entityId . '/clientes/' . (int) $clientId);
    }

    /** @return array<string,mixed> */
    private static function requireServicesCompany(int $entityId): array
    {
        CompanySchema::ensure();
        CompanyClientService::ensureSchema();
        $stmt = Database::pdo()->prepare(
            "SELECT e.*, c.legal_name, c.tax_id, c.industry, c.business_model, c.website
             FROM entities e
             INNER JOIN companies c ON c.entity_id = e.id
             WHERE e.id = :id AND e.type = 'company'
             LIMIT 1"
        );
        $stmt->execute(['id' => $entityId]);
        $company = $stmt->fetch();
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        UserService::requireEntityAccess($entityId);
        if (($company['business_model'] ?? 'services') !== 'services') {
            flash('error', 'Las fichas de cliente son solo para empresas de servicios.');
            redirect('/empresas/' . $entityId);
        }
        return $company;
    }
}
