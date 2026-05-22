<?php
// src/services/MenuAdminService.php

class MenuAdminService
{
    private const UPLOAD_MAX_BYTES = 5242880;
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function acceptedImageMimeTypes(): string
    {
        return implode(',', array_keys(self::ALLOWED_MIME_EXTENSIONS));
    }

    public static function acceptedImageFormatsLabel(): string
    {
        return 'Formats acceptés : ' . strtoupper(implode(', ', array_values(self::ALLOWED_MIME_EXTENSIONS)));
    }

    public static function menuPayloadFromRequest(array $source): array
    {
        $payload = [
            'titre'                   => sanitize($source['titre'] ?? ''),
            'description'             => sanitize($source['description'] ?? ''),
            'nombre_personne_minimum' => (int)($source['nombre_personne_minimum'] ?? 2),
            'prix_par_personne'       => (float)($source['prix_par_personne'] ?? 0),
            'quantite_restante'       => self::nullableNaturalInteger($source['quantite_restante'] ?? null),
            'conditions'              => sanitize($source['conditions'] ?? ''),
            'theme_id'                => self::nullablePositiveId($source['theme_id'] ?? null),
            'regime_id'               => self::nullablePositiveId($source['regime_id'] ?? null),
        ];

        if (
            !$payload['titre']
            || $payload['nombre_personne_minimum'] < 1
            || $payload['prix_par_personne'] < 0
            || ($payload['quantite_restante'] !== null && $payload['quantite_restante'] < 0)
        ) {
            throw new InvalidArgumentException('Titre, minimum de personnes et prix valides obligatoires.');
        }

        return $payload;
    }

    public static function platPayloadFromRequest(array $source): array
    {
        $payload = [
            'titre'        => trim($source['titre'] ?? ''),
            'categorie_id' => (int)($source['categorie_id'] ?? 0),
            'allergenes'   => trim($source['allergenes'] ?? ''),
        ];

        if (!$payload['titre'] || !$payload['categorie_id']) {
            throw new InvalidArgumentException('Titre et catégorie obligatoires.');
        }

        return $payload;
    }

    public static function selectedIds(array $source, string $key): array
    {
        if (empty($source[$key]) || !is_array($source[$key])) {
            return [];
        }

        $ids = array_map('intval', $source[$key]);
        $ids = array_filter($ids, fn($id) => $id > 0);
        return array_values(array_unique($ids));
    }

    public static function uploadMenuImages(int $menuId, array $files, int $startOrder): void
    {
        if (empty($files['name'][0])) {
            return;
        }

        self::ensureUploadDirectory();
        $order = $startOrder;

        foreach (($files['tmp_name'] ?? []) as $index => $tmpName) {
            $file = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $tmpName,
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];

            $path = self::storeUploadedImage($file, 'menu_' . $menuId);
            if ($path) {
                MenuModel::addMenuImage($menuId, $path, $order++);
            }
        }
    }

    public static function deleteMenuImageFile(int $imageId): void
    {
        $relativePath = MenuModel::getMenuImagePath($imageId);
        if ($relativePath) {
            if (!str_starts_with($relativePath, 'uploads/')) {
                return;
            }
            $absolutePath = self::publicPath($relativePath);
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
            MenuModel::deleteMenuImage($imageId);
        }
    }

    private static function nullableNaturalInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private static function nullablePositiveId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int)$value;
        return $integer > 0 ? $integer : null;
    }

    private static function storeUploadedImage(array $file, string $prefix): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if ((int)($file['size'] ?? 0) > self::UPLOAD_MAX_BYTES) {
            return null;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!$tmpName || !file_exists($tmpName)) {
            return null;
        }

        $mime = self::imageMimeType($tmpName);
        $extension = self::ALLOWED_MIME_EXTENSIONS[$mime] ?? null;
        if (!$extension) {
            return null;
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relativePath = 'uploads/' . $filename;

        $destination = self::publicPath($relativePath);
        $moved = move_uploaded_file($tmpName, $destination);
        if (!$moved) {
            $moved = rename($tmpName, $destination);
        }
        return $moved ? $relativePath : null;
    }

    private static function imageMimeType(string $tmpName): ?string
    {
        if (class_exists(Finfo::class)) {
            $finfo = new Finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpName);
            if (is_string($mime)) {
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpName);
            return is_string($mime) ? $mime : null;
        }

        return null;
    }

    private static function ensureUploadDirectory(): void
    {
        $directory = self::publicPath('uploads');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private static function publicPath(string $relativePath): string
    {
        return __DIR__ . '/../../public/' . ltrim($relativePath, '/');
    }
}
