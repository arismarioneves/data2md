<?php

declare(strict_types=1);

namespace Data2Md\Security;

class Validator
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB

    private const ALLOWED = [
        'pdf' => [
            'extensions' => ['pdf'],
            'mime_types' => ['application/pdf'],
        ],
        // Additional formats registered here as they are implemented
    ];

    public function validateUpload(array $file, string $format = 'pdf'): array
    {
        $errors = [];

        if (!isset($file['error'])) {
            return ['Invalid upload data.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [$this->uploadErrorMessage($file['error'])];
        }

        if (!array_key_exists($format, self::ALLOWED)) {
            return ["Unsupported format: $format"];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'File exceeds the maximum allowed size of 20 MB.';
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid upload source.';
            return $errors;
        }

        $allowed = self::ALLOWED[$format];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed['extensions'], true)) {
            $errors[] = 'File extension not allowed. Expected: ' . implode(', ', $allowed['extensions']) . '.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed['mime_types'], true)) {
            $errors[] = 'File content does not match allowed types for this format.';
        }

        return $errors;
    }

    public function isValidHash(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{15}$/', $hash);
    }

    public function sanitizeOriginalName(string $name): string
    {
        // Strip null bytes, path separators, and control characters
        $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]/', '', $name);
        $name = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return substr($name, 0, 255);
    }

    public function isAllowedFormat(string $format): bool
    {
        return array_key_exists($format, self::ALLOWED);
    }

    public function supportedFormats(): array
    {
        return array_keys(self::ALLOWED);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL   => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was submitted.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write to disk.',
            default => 'Upload failed (error code ' . $code . ').',
        };
    }
}
