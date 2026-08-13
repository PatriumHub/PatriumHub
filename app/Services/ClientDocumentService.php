<?php

declare(strict_types=1);

/**
 * Archivos de ficha de cliente: contrato (PDF) y documentación extra.
 * Usa la tabla documents (related_type + related_id).
 */
final class ClientDocumentService
{
    public const TYPE_CONTRACT = 'company_client_contract';
    public const TYPE_EXTRA = 'company_client_file';

    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        // documents ya existe en el schema; no-op si está.
        self::$ready = true;
    }

    public static function contract(int $entityId, int $clientId): ?array
    {
        $rows = self::list($entityId, $clientId, self::TYPE_CONTRACT);
        return $rows[0] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public static function extras(int $entityId, int $clientId): array
    {
        return self::list($entityId, $clientId, self::TYPE_EXTRA);
    }

    /** @return list<array<string,mixed>> */
    public static function list(int $entityId, int $clientId, string $type): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM documents
             WHERE entity_id = :e AND related_type = :t AND related_id = :rid
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['e' => $entityId, 't' => $type, 'rid' => $clientId]);
        return $stmt->fetchAll();
    }

    public static function findForClient(int $entityId, int $clientId, int $docId): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM documents
             WHERE id = :id AND entity_id = :e AND related_id = :rid
               AND (related_type = :c OR related_type = :x)
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $docId,
            'e' => $entityId,
            'rid' => $clientId,
            'c' => self::TYPE_CONTRACT,
            'x' => self::TYPE_EXTRA,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function storeContract(int $entityId, int $clientId, array $file, ?int $userId): array
    {
        $check = self::validateUpload($file, true);
        if (!$check['ok']) {
            return $check;
        }
        // Un solo contrato: borrar el anterior.
        foreach (self::list($entityId, $clientId, self::TYPE_CONTRACT) as $old) {
            self::deleteFileRow($entityId, $clientId, (int) $old['id']);
        }
        return self::store($entityId, $clientId, self::TYPE_CONTRACT, $file, 'Contrato', $userId);
    }

    /**
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function storeExtra(int $entityId, int $clientId, array $file, string $title, ?int $userId): array
    {
        $check = self::validateUpload($file, false);
        if (!$check['ok']) {
            return $check;
        }
        $title = trim($title);
        if ($title === '') {
            $title = (string) ($file['name'] ?? 'Documento');
        }
        return self::store($entityId, $clientId, self::TYPE_EXTRA, $file, $title, $userId);
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(int $entityId, int $clientId, int $docId): array
    {
        $row = self::findForClient($entityId, $clientId, $docId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Archivo no encontrado.'];
        }
        self::deleteFileRow($entityId, $clientId, $docId);
        return ['ok' => true];
    }

    public static function absolutePath(array $doc): string
    {
        $rel = (string) ($doc['file_path'] ?? '');
        return self::appRoot() . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }

    private static function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Carpeta base escribible por el servidor web. */
    private static function storageBaseRel(): string
    {
        $root = self::appRoot();
        // Preferir uploads (suele ser 0777); fallback a client_docs.
        foreach (['storage/uploads/client_docs', 'storage/client_docs'] as $rel) {
            $abs = $root . '/' . $rel;
            if (is_dir($abs) && is_writable($abs)) {
                return $rel;
            }
            if (self::ensureDir($abs) === null && is_writable($abs)) {
                return $rel;
            }
        }
        return 'storage/uploads/client_docs';
    }

    /** @return string|null error message */
    private static function ensureDir(string $abs): ?string
    {
        if (is_dir($abs)) {
            if (!is_writable($abs)) {
                @chmod($abs, 0775);
            }
            return is_writable($abs) ? null : 'Sin permiso de escritura en el directorio de archivos.';
        }
        $parent = dirname($abs);
        if ($parent !== $abs && !is_dir($parent)) {
            $err = self::ensureDir($parent);
            if ($err !== null) {
                return $err;
            }
        }
        if (!is_dir($parent) || !is_writable($parent)) {
            return 'El servidor no puede escribir en storage. Pedile al admin permiso de escritura (www-data) en storage/uploads.';
        }
        $prev = umask(0);
        $ok = @mkdir($abs, 0775, true);
        umask($prev);
        if (!$ok && !is_dir($abs)) {
            return 'No se pudo crear el directorio de archivos.';
        }
        @chmod($abs, 0775);
        return is_writable($abs) ? null : 'Directorio creado pero sin permiso de escritura.';
    }

    /**
     * @return array{ok:bool,id?:int,error?:string}
     */
    private static function store(
        int $entityId,
        int $clientId,
        string $type,
        array $file,
        string $title,
        ?int $userId
    ): array {
        $baseRel = self::storageBaseRel();
        $dirRel = $baseRel . '/' . $entityId . '/' . $clientId;
        $dirAbs = self::appRoot() . '/' . $dirRel;
        $dirErr = self::ensureDir($dirAbs);
        if ($dirErr !== null) {
            return ['ok' => false, 'error' => $dirErr];
        }
        self::ensureDenyHtaccess(self::appRoot() . '/' . $baseRel);

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = $type === self::TYPE_CONTRACT ? 'pdf' : 'bin';
        }
        $safe = bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
        $relPath = $dirRel . '/' . $safe;
        $absPath = $dirAbs . '/' . $safe;

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'Archivo de subida inválido.'];
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $absPath)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo en disco.'];
        }
        @chmod($absPath, 0664);

        $mime = (string) ($file['type'] ?? 'application/octet-stream');
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO documents (entity_id, related_type, related_id, title, file_path, mime_type, uploaded_by)
             VALUES (:e, :t, :rid, :title, :path, :mime, :uid)'
        )->execute([
            'e' => $entityId,
            't' => $type,
            'rid' => $clientId,
            'title' => mb_substr($title, 0, 180),
            'path' => $relPath,
            'mime' => mb_substr($mime, 0, 120),
            'uid' => $userId,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    private static function deleteFileRow(int $entityId, int $clientId, int $docId): void
    {
        $row = self::findForClient($entityId, $clientId, $docId);
        if (!$row) {
            return;
        }
        $abs = self::absolutePath($row);
        if (is_file($abs)) {
            @unlink($abs);
        }
        Database::pdo()->prepare(
            'DELETE FROM documents WHERE id = :id AND entity_id = :e AND related_id = :rid'
        )->execute(['id' => $docId, 'e' => $entityId, 'rid' => $clientId]);
    }

    /** @return array{ok:bool,error?:string} */
    private static function validateUpload(array $file, bool $pdfOnly): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Elegí un archivo.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 15 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El archivo debe pesar entre 1 byte y 15 MB.'];
        }
        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = $pdfOnly
            ? ['pdf']
            : ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array($ext, $allowed, true)) {
            return [
                'ok' => false,
                'error' => $pdfOnly
                    ? 'El contrato debe ser un PDF.'
                    : 'Formato no permitido. Usá PDF, imagen u Office.',
            ];
        }
        if ($pdfOnly) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file((string) $file['tmp_name']);
            if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
                return ['ok' => false, 'error' => 'El archivo no es un PDF válido.'];
            }
        }
        return ['ok' => true];
    }

    private static function ensureDenyHtaccess(string $dir): void
    {
        self::ensureDir($dir);
        $ht = rtrim($dir, '/\\') . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }
}
