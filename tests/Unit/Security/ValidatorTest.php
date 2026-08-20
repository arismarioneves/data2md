<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Security;

use Data2Md\Security\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(200 * 1024 * 1024, '200 MB');
    }

    public function testFormatSupport(): void
    {
        self::assertTrue($this->validator->isAllowedFormat('pdf'));
        self::assertFalse($this->validator->isAllowedFormat('docx'));
        self::assertSame(['pdf'], $this->validator->supportedFormats());
    }

    /** @dataProvider hashes */
    public function testHashValidation(string $hash, bool $expected): void
    {
        self::assertSame($expected, $this->validator->isValidHash($hash));
    }

    public static function hashes(): array
    {
        return [
            ['a1b2c3d4e5f6789', true],
            ['A1B2C3D4E5F6789', false],  // uppercase not produced by us
            ['a1b2c3d4e5f678',  false],  // 14 chars
            ['a1b2c3d4e5f67890', false], // 16 chars
            ['../../etc/passwd', false],
            ['',                 false],
            ['a1b2c3d4e5f678g',  false], // non-hex
        ];
    }

    public function testOriginalNameIsNotHtmlEscaped(): void
    {
        // Escaping belongs to the view. Doing it here corrupts the JSON payload
        // and the Content-Disposition filename.
        $clean = $this->validator->sanitizeOriginalName('relatório & anexo.pdf');

        self::assertSame('relatório & anexo.pdf', $clean);
        self::assertStringNotContainsString('&amp;', $clean);
    }

    /** @dataProvider dangerousNames */
    public function testDangerousNameParts(string $input, string $expected): void
    {
        self::assertSame($expected, $this->validator->sanitizeOriginalName($input));
    }

    public static function dangerousNames(): array
    {
        return [
            ['../../etc/passwd',        '....etcpasswd'],
            ['..\\..\\windows\\sys',    '....windowssys'],
            ["evil\x00.pdf",            'evil.pdf'],
            ["line\nbreak.pdf",         'linebreak.pdf'],
            ['normal.pdf',              'normal.pdf'],
            ['relatório anual.pdf',     'relatório anual.pdf'],
        ];
    }

    public function testOriginalNameIsLengthCapped(): void
    {
        $long = str_repeat('a', 400) . '.pdf';

        self::assertLessThanOrEqual(255, strlen($this->validator->sanitizeOriginalName($long)));
    }

    public function testOriginalNameLengthCapDoesNotSplitAMultibyteCharacter(): void
    {
        // 200 two-byte 'é' characters plus '.pdf' is 404 bytes; a naive
        // substr(0, 255) would cut the 128th 'é' in half at the boundary.
        $long = str_repeat('é', 200) . '.pdf';

        $result = $this->validator->sanitizeOriginalName($long);

        self::assertLessThanOrEqual(255, strlen($result));
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testOriginalNameIsAlwaysValidUtf8(): void
    {
        $bad = "invoice-\xFF\xFE-final.pdf";

        $result = $this->validator->sanitizeOriginalName($bad);

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testUploadErrorIsReportedPlainly(): void
    {
        $errors = $this->validator->validateUpload([
            'error' => UPLOAD_ERR_INI_SIZE,
            'name'  => 'x.pdf',
            'size'  => 1,
            'tmp_name' => '/tmp/x',
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('size', strtolower($errors[0]));
    }

    public function testMissingErrorKeyIsRejected(): void
    {
        self::assertNotSame([], $this->validator->validateUpload([]));
    }

    public function testUnknownFormatIsRejected(): void
    {
        $errors = $this->validator->validateUpload([
            'error' => UPLOAD_ERR_OK,
            'name'  => 'x.docx',
            'size'  => 1,
            'tmp_name' => '/tmp/x',
        ], 'docx');

        self::assertNotSame([], $errors);
    }

    public function testOversizeIsReportedWithTheConfiguredLabel(): void
    {
        $validator = new Validator(1024, '1 KB');

        $errors = $validator->validateUpload([
            'error'    => UPLOAD_ERR_OK,
            'name'     => 'x.pdf',
            'size'     => 2048,
            'tmp_name' => '/tmp/definitely-not-an-upload',
        ]);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('1 KB', implode(' ', $errors));
    }
}
