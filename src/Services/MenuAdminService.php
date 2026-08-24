<?php

namespace App\Services;

use App\Models\MenuModel;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
        $rawMinimum = trim((string) ($source['nombre_personne_minimum'] ?? '2'));
        $rawPrice = str_replace(',', '.', trim((string) ($source['prix_par_personne'] ?? '')));
        if (filter_var($rawMinimum, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || $rawPrice === ''
            || !is_numeric($rawPrice)
            || (float) $rawPrice < 0
        ) {
            throw new InvalidArgumentException('Titre, minimum de personnes et prix valides obligatoires.');
        }

        $payload = [
            'titre' => trim((string) ($source['titre'] ?? '')),
            'description' => trim((string) ($source['description'] ?? '')),
            'nombre_personne_minimum' => (int) $rawMinimum,
            'prix_par_personne' => round((float) $rawPrice, 2),
            'quantite_restante' => self::nullableNaturalInteger($source['quantite_restante'] ?? null),
            'conditions' => trim((string) ($source['conditions'] ?? '')),
            'theme_id' => self::nullablePositiveId($source['theme_id'] ?? null),
            'regime_id' => self::nullablePositiveId($source['regime_id'] ?? null),
        ];

        if ($payload['titre'] === '') {
            throw new InvalidArgumentException('Titre, minimum de personnes et prix valides obligatoires.');
        }

        return $payload;
    }

    public static function platPayloadFromRequest(array $source): array
    {
        $categorieId = filter_var($source['categorie_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $payload = [
            'titre' => trim((string) ($source['titre'] ?? '')),
            'categorie_id' => $categorieId === false ? 0 : (int) $categorieId,
            'allergen_ids' => self::selectedIds($source, 'allergen_ids'),
        ];

        if ($payload['titre'] === '' || $payload['categorie_id'] < 1) {
            throw new InvalidArgumentException('Titre et catégorie obligatoires.');
        }

        return $payload;
    }

    public static function selectedIds(array $source, string $key): array
    {
        if (empty($source[$key]) || !is_array($source[$key])) {
            return [];
        }

        $ids = [];
        foreach ($source[$key] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new InvalidArgumentException('Sélection de référentiel invalide.');
            }
            $ids[(int) $id] = (int) $id;
        }
        return array_values($ids);
    }

    public static function uploadSiteImage(array $file, string $folder): ?string
    {
        return self::cloudinaryEnabled()
            ? self::storeOnCloudinary($file, $folder)
            : self::storeUploadedImage($file, str_replace('/', '_', $folder));
    }

    /**
     * Prépare physiquement les nouvelles images avant la transaction DB.
     * Si une image annoncée est invalide ou échoue, toutes les images déjà préparées sont nettoyées.
     *
     * @return list<string>
     */
    public static function prepareMenuImages(array $files, bool $required = false): array
    {
        $names = is_array($files['name'] ?? null) ? $files['name'] : [];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
        $errors = is_array($files['error'] ?? null) ? $files['error'] : [];
        $sizes = is_array($files['size'] ?? null) ? $files['size'] : [];
        $types = is_array($files['type'] ?? null) ? $files['type'] : [];

        $paths = [];
        try {
            foreach ($names as $index => $name) {
                if (trim((string) $name) === '' && ($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Une image n’a pas pu être téléversée correctement.');
                }

                $file = [
                    'name' => (string) $name,
                    'type' => (string) ($types[$index] ?? ''),
                    'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                    'error' => $error,
                    'size' => (int) ($sizes[$index] ?? 0),
                ];
                $path = self::cloudinaryEnabled()
                    ? self::storeOnCloudinary($file, 'menus/catalog_pending')
                    : self::storeUploadedImage($file, 'menu_pending');
                if ($path === null) {
                    throw new InvalidArgumentException('Image invalide : JPG, PNG ou WEBP de 5 Mo maximum.');
                }
                $paths[] = $path;
            }

            if ($required && $paths === []) {
                throw new InvalidArgumentException('Au moins une photo valide est obligatoire.');
            }

            return $paths;
        } catch (Throwable $e) {
            self::cleanupStoredImages($paths);
            throw $e;
        }
    }

    /** @param list<string> $paths */
    public static function cleanupStoredImages(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                self::deleteStoredImagePath($path);
            } catch (Throwable $e) {
                error_log('[menu-image] compensation delete failed: ' . $e->getMessage());
            }
        }
    }

    public static function deleteStoredImagePath(string $path): void
    {
        if (str_starts_with($path, 'https://res.cloudinary.com/')) {
            self::deleteFromCloudinary($path);
            return;
        }

        if (str_starts_with($path, 'uploads/')) {
            $absolutePath = self::publicPath($path);
            if (is_file($absolutePath) && !@unlink($absolutePath)) {
                throw new RuntimeException('Impossible de supprimer le fichier image local.');
            }
        }
    }

    /** @deprecated Utiliser prepareMenuImages + CatalogIntegrityService. */
    public static function uploadMenuImages(int $menuId, array $files, int $startOrder): void
    {
        $paths = self::prepareMenuImages($files, false);
        $order = $startOrder;
        foreach ($paths as $path) {
            MenuModel::addMenuImage($menuId, $path, $order++);
        }
    }

    /** @deprecated Utiliser CatalogIntegrityService::detachImage puis deleteStoredImagePath. */
    public static function deleteMenuImageFile(int $imageId): void
    {
        $path = MenuModel::getMenuImagePath($imageId);
        if (!$path) {
            return;
        }
        MenuModel::deleteMenuImage($imageId);
        self::deleteStoredImagePath($path);
    }

    private static function env(string $key): string
    {
        return (string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? '');
    }

    private static function cloudinaryEnabled(): bool
    {
        $name = self::env('CLOUDINARY_CLOUD_NAME');
        $key = self::env('CLOUDINARY_API_KEY');
        $secret = self::env('CLOUDINARY_API_SECRET');

        return $name !== ''
            && $key !== ''
            && $secret !== ''
            && class_exists('Cloudinary\\Configuration\\Configuration');
    }

    private static function cloudinaryConfig(): void
    {
        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => [
                'cloud_name' => self::env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => self::env('CLOUDINARY_API_KEY'),
                'api_secret' => self::env('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    private static function storeOnCloudinary(array $file, string $folder): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > self::UPLOAD_MAX_BYTES) {
            return null;
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !file_exists($tmpName)) {
            return null;
        }
        $mime = self::imageMimeType($tmpName);
        if (!isset(self::ALLOWED_MIME_EXTENSIONS[$mime])) {
            return null;
        }

        try {
            self::cloudinaryConfig();
            $result = (new \Cloudinary\Api\Upload\UploadApi())->upload($tmpName, [
                'folder' => $folder,
                'resource_type' => 'image',
                'format' => 'webp',
                'quality' => 'auto',
                'width' => 1200,
                'crop' => 'limit',
            ]);
            $url = $result['secure_url'] ?? null;
            return is_string($url) && $url !== '' ? $url : null;
        } catch (Throwable $e) {
            error_log('[Cloudinary] Upload FAILED : ' . get_class($e) . ' — ' . $e->getMessage());
            return null;
        }
    }

    private static function deleteFromCloudinary(string $url): void
    {
        if (!preg_match('#/upload/(?:v\d+/)?(.+)\.[a-z]+$#i', $url, $m)) {
            throw new RuntimeException('URL Cloudinary non reconnue.');
        }
        try {
            self::cloudinaryConfig();
            (new \Cloudinary\Api\Upload\UploadApi())->destroy($m[1]);
        } catch (Throwable $e) {
            error_log('[Cloudinary] Delete failed: ' . $e->getMessage());
            throw new RuntimeException('Suppression Cloudinary impossible.', 0, $e);
        }
    }

    private static function nullableNaturalInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($integer === false) {
            throw new InvalidArgumentException('La quantité restante doit être un entier positif ou nul.');
        }
        return (int) $integer;
    }

    private static function nullablePositiveId(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($integer === false) {
            throw new InvalidArgumentException('Référentiel invalide.');
        }
        return (int) $integer;
    }

    private static function storeUploadedImage(array $file, string $prefix): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > self::UPLOAD_MAX_BYTES) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !file_exists($tmpName)) {
            return null;
        }

        $mime = self::imageMimeType($tmpName);
        $extension = self::ALLOWED_MIME_EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            return null;
        }

        self::ensureUploadDirectory();
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relativePath = 'uploads/' . $filename;
        $destination = self::publicPath($relativePath);

        $moved = move_uploaded_file($tmpName, $destination);
        if (!$moved) {
            $moved = @rename($tmpName, $destination);
        }
        return $moved ? $relativePath : null;
    }

    private static function imageMimeType(string $tmpName): ?string
    {
        if (class_exists(\Finfo::class)) {
            $finfo = new \Finfo(FILEINFO_MIME_TYPE);
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
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier d’upload.');
        }
    }

    private static function publicPath(string $relativePath): string
    {
        return __DIR__ . '/../../public/' . ltrim($relativePath, '/');
    }
}
