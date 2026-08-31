<?php

declare(strict_types=1);

namespace Data2Md\Convert;

/**
 * One upload, with the shape checks $_FILES does not make.
 */
final class UploadedFile
{
    private function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly string $tmpPath,
        public readonly int $error,
        public readonly string $format,
    ) {
    }

    /**
     * @param array<string,mixed> $files $_FILES
     * @param array<string,mixed> $post  $_POST
     * @throws MalformedUploadException
     */
    public static function fromRequest(array $files, array $post): self
    {
        $file = $files['file'] ?? null;

        if ($file === null) {
            throw new MalformedUploadException(
                'No file provided. Send multipart/form-data with a "file" field.'
            );
        }

        // A repeated field ("file[]=@a" twice, "format[]=pdf") turns the
        // expected scalars into arrays, and $_FILES does not enforce the shape.
        if (!is_array($file) || array_filter($file, 'is_array') !== []) {
            throw new MalformedUploadException('Send exactly one file in the "file" field.');
        }

        $format = $post['format'] ?? 'pdf';

        if (!is_string($format)) {
            throw new MalformedUploadException('The "format" field must be a single string value.');
        }

        return new self(
            name:    (string) ($file['name'] ?? ''),
            size:    (int) ($file['size'] ?? 0),
            tmpPath: (string) ($file['tmp_name'] ?? ''),
            error:   (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            format:  strtolower(trim($format)),
        );
    }

    /** The shape Validator::validateUpload() reads. */
    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'size'     => $this->size,
            'tmp_name' => $this->tmpPath,
            'error'    => $this->error,
        ];
    }
}
