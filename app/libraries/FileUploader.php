<?php
namespace JanKavach\Libraries;

use JanKavach\Core\Config;
use JanKavach\Core\Logger;

/**
 * Hardened file upload handling for beneficiary documents and imports.
 *
 * Every upload is checked on four independent axes before it is stored:
 *   1. the PHP upload error code and is_uploaded_file()
 *   2. the file size against the configured ceiling
 *   3. the extension against an allow-list
 *   4. the REAL MIME type read from the file contents with finfo, cross
 *      checked against the extension - the browser-supplied type is never
 *      trusted
 *
 * Images are additionally verified with getimagesize(), and PDFs must start
 * with the %PDF- signature.  Files are stored outside the web root under
 * storage/documents with a generated name, so a malicious upload has no URL
 * and no original extension to be served under.
 */
class FileUploader
{
    /** Extension => the MIME types that extension may legitimately carry. */
    private const EXTENSION_MIME = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
    ];

    private array $errors = [];

    /**
     * Validate and store a document upload.
     *
     * @return array|null  file metadata on success, null on failure
     */
    public function storeDocument(array $file, string $subdirectory = 'general'): ?array
    {
        $this->errors = [];

        $maxSize = (int)(Config::setting('max_document_size_mb') !== null
            ? ((int)Config::setting('max_document_size_mb') * 1024 * 1024)
            : Config::get('upload.max_size', 5242880));

        $allowedExt = Config::setting('allowed_extensions')
            ? array_map('trim', explode(',', (string)Config::setting('allowed_extensions')))
            : Config::get('upload.allowed_ext', ['pdf', 'jpg', 'jpeg', 'png']);

        if (!$this->validate($file, $maxSize, $allowedExt)) {
            return null;
        }

        $extension = $this->extension($file['name']);
        $directory = DOCUMENT_PATH . '/' . $this->safeSegment($subdirectory) . '/' . date('Y/m');
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->errors[] = 'The storage folder could not be created. Please contact the administrator.';
            return null;
        }
        $this->protectDirectory($directory);

        // Generated name: nothing of the user's filename reaches the disk.
        $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(10)) . '.' . $extension;
        $fullPath   = $directory . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->errors[] = 'The uploaded file could not be saved.';
            Logger::error('move_uploaded_file failed', ['target' => $fullPath]);
            return null;
        }
        @chmod($fullPath, 0644);

        return [
            'stored_name'   => $storedName,
            'original_name' => $this->sanitiseOriginalName($file['name']),
            // Relative path: the absolute location can change between servers.
            'file_path'     => $this->safeSegment($subdirectory) . '/' . date('Y/m') . '/' . $storedName,
            'absolute_path' => $fullPath,
            'file_size'     => (int)$file['size'],
            'mime_type'     => $this->detectMime($fullPath),
            'file_hash'     => hash_file('sha256', $fullPath),
            'extension'     => $extension,
        ];
    }

    /** Store an uploaded spreadsheet for the scheme import. */
    public function storeImport(array $file): ?array
    {
        $this->errors = [];

        $maxSize    = (int)Config::get('upload.import_max_size', 10485760);
        $allowedExt = Config::get('upload.import_ext', ['xlsx', 'xls', 'csv']);

        if (!$this->validate($file, $maxSize, $allowedExt, false)) {
            return null;
        }

        if (!is_dir(IMPORT_PATH) && !@mkdir(IMPORT_PATH, 0775, true) && !is_dir(IMPORT_PATH)) {
            $this->errors[] = 'The import folder could not be created.';
            return null;
        }
        $this->protectDirectory(IMPORT_PATH);

        $extension  = $this->extension($file['name']);
        $storedName = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $fullPath   = IMPORT_PATH . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->errors[] = 'The uploaded workbook could not be saved.';
            return null;
        }
        @chmod($fullPath, 0644);

        return [
            'stored_name'   => $storedName,
            'original_name' => $this->sanitiseOriginalName($file['name']),
            'absolute_path' => $fullPath,
            'file_size'     => (int)$file['size'],
            'extension'     => $extension,
        ];
    }

    // =================================================================
    // Validation
    // =================================================================

    private function validate(array $file, int $maxSize, array $allowedExt, bool $strictContent = true): bool
    {
        // 1. Upload mechanics
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->uploadErrorMessage($error, $maxSize);
            return false;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            // Guards against a crafted $_FILES pointing at a server path.
            $this->errors[] = 'The upload could not be verified.';
            Logger::warning('is_uploaded_file() rejected an upload', ['tmp' => $file['tmp_name'] ?? null]);
            return false;
        }

        // 2. Size
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            $this->errors[] = 'The file is empty.';
            return false;
        }
        if ($size > $maxSize) {
            $this->errors[] = sprintf('The file is %s; the maximum allowed is %s.',
                $this->humanSize($size), $this->humanSize($maxSize));
            return false;
        }

        // 3. Extension
        $extension = $this->extension($file['name']);
        if ($extension === '' || !in_array($extension, array_map('strtolower', $allowedExt), true)) {
            $this->errors[] = 'Only ' . strtoupper(implode(', ', $allowedExt)) . ' files may be uploaded.';
            return false;
        }
        // A double extension such as report.pdf.php must never get through.
        if (preg_match('/\.(php|phtml|phar|pht|phps|cgi|pl|py|sh|exe|bat|cmd|js|html?|htaccess)\b/i', $file['name'])) {
            $this->errors[] = 'That file type is not permitted.';
            Logger::warning('Blocked an executable-looking upload', ['name' => $file['name']]);
            return false;
        }

        // 4. Real content type
        $mime = $this->detectMime($file['tmp_name']);
        $expected = self::EXTENSION_MIME[$extension] ?? [];
        if ($expected !== [] && !in_array($mime, $expected, true)) {
            $this->errors[] = sprintf('The file contents (%s) do not match a %s file.', $mime, strtoupper($extension));
            Logger::warning('MIME / extension mismatch on upload', [
                'name' => $file['name'], 'detected' => $mime, 'extension' => $extension,
            ]);
            return false;
        }

        if ($strictContent && !$this->verifyContents($file['tmp_name'], $extension)) {
            return false;
        }

        return true;
    }

    /** Deeper content checks for the formats that allow them. */
    private function verifyContents(string $path, string $extension): bool
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $info = @getimagesize($path);
            if ($info === false) {
                $this->errors[] = 'The image could not be read. Please upload a valid JPG or PNG.';
                return false;
            }
            $allowedTypes = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG];
            if (($info[2] ?? null) !== $allowedTypes[$extension]) {
                $this->errors[] = 'The image content does not match its file extension.';
                return false;
            }
            // A polyglot image carrying PHP would still be inert (the file is
            // stored outside the web root) but is rejected regardless.
            $head = (string)@file_get_contents($path, false, null, 0, 1024);
            if (stripos($head, '<?php') !== false || stripos($head, '<script') !== false) {
                $this->errors[] = 'The image contains embedded code and was rejected.';
                Logger::warning('Rejected an image containing script markers', ['path' => $path]);
                return false;
            }
            return true;
        }

        if ($extension === 'pdf') {
            $head = (string)@file_get_contents($path, false, null, 0, 5);
            if ($head !== '%PDF-') {
                $this->errors[] = 'The file does not appear to be a valid PDF.';
                return false;
            }
            return true;
        }

        return true;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return mime_content_type($path) ?: 'application/octet-stream';
    }

    private function uploadErrorMessage(int $code, int $maxSize): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'The file is larger than the maximum of ' . $this->humanSize($maxSize) . '.',
            UPLOAD_ERR_PARTIAL    => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder configured for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
            default               => 'The file could not be uploaded.',
        };
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: '');
    }

    private function safeSegment(string $segment): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $segment);
        return $clean !== '' ? $clean : 'general';
    }

    private function sanitiseOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $name);
        return mb_substr((string)$name, 0, 255);
    }

    /** Drop a deny-all .htaccess so the folder is unreachable over HTTP. */
    private function protectDirectory(string $directory): void
    {
        $file = $directory . '/.htaccess';
        if (!is_file($file)) {
            @file_put_contents($file, "Require all denied\nDeny from all\nOptions -Indexes\n");
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /** Remove a stored document, given its path relative to storage/documents. */
    public static function delete(?string $relativePath): bool
    {
        if (!$relativePath) {
            return false;
        }
        // Never let a stored path escape the documents root.
        $full = realpath(DOCUMENT_PATH . '/' . ltrim($relativePath, '/'));
        $root = realpath(DOCUMENT_PATH);
        if ($full === false || $root === false || !str_starts_with($full, $root)) {
            Logger::warning('Refused to delete a file outside the documents root', ['path' => $relativePath]);
            return false;
        }
        return @unlink($full);
    }

    /** Absolute path for a stored document, or null if it escapes the root. */
    public static function resolve(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        $full = realpath(DOCUMENT_PATH . '/' . ltrim($relativePath, '/'));
        $root = realpath(DOCUMENT_PATH);
        if ($full === false || $root === false || !str_starts_with($full, $root) || !is_file($full)) {
            return null;
        }
        return $full;
    }
}
