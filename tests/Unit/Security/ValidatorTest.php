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
        $this->validator = new Validator();
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

        // Pins the exact surviving content: 127 'é' (254 bytes), the most
        // that fits under the 255-byte cap without splitting the 128th
        // character. A cap that degenerated into discarding the whole name
        // would still pass a bare "<=255 and valid UTF-8" check.
        self::assertSame(str_repeat('é', 127), $result);
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testOriginalNameIsAlwaysValidUtf8(): void
    {
        $bad = 'bad' . "\xFF\xFE" . 'bytes.pdf';

        $result = $this->validator->sanitizeOriginalName($bad);

        // Pins the exact result, not just its validity: the invalid bytes
        // are substituted in place, not used as an excuse to drop the whole
        // name. An empty string is valid UTF-8 too, so checking only
        // mb_check_encoding() would miss a regression that discards
        // everything on invalid input.
        self::assertSame('bad??bytes.pdf', $result);
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testUploadErrorIsReportedPlainly(): void
    {
        $errors = $this->validator->validateUpload([
            'error' => UPLOAD_ERR_INI_SIZE,
            'name'  => 'x.pdf',
            'size'  => 1,
            'tmp_name' => '/tmp/x',
        ], 'pdf', 200 * 1024 * 1024, '200 MB');

        self::assertCount(1, $errors);
        self::assertStringContainsString('size', strtolower($errors[0]));
    }

    public function testMissingErrorKeyIsRejected(): void
    {
        self::assertNotSame([], $this->validator->validateUpload([], 'pdf', 1024, '1 KB'));
    }

    public function testUnknownFormatIsRejected(): void
    {
        $errors = $this->validator->validateUpload([
            'error' => UPLOAD_ERR_OK,
            'name'  => 'x.docx',
            'size'  => 1,
            'tmp_name' => '/tmp/x',
        ], 'docx', 200 * 1024 * 1024, '200 MB');

        self::assertNotSame([], $errors);
    }

    /**
     * The limit is a parameter, so the same Validator reports whichever plan's
     * ceiling the caller is subject to.
     *
     * @dataProvider sizeLimits
     */
    public function testOversizeIsReportedWithTheCallersLabel(
        int $sizeBytes,
        int $maxBytes,
        string $label,
        bool $shouldReject,
    ): void {
        $errors = $this->validator->validateUpload([
            'error'    => UPLOAD_ERR_OK,
            'name'     => 'x.pdf',
            'size'     => $sizeBytes,
            'tmp_name' => '/tmp/definitely-not-an-upload',
        ], 'pdf', $maxBytes, $label);

        if (!$shouldReject) {
            self::assertStringNotContainsString('maximum allowed size', implode(' ', $errors));
            return;
        }

        self::assertStringContainsString($label, implode(' ', $errors));
    }

    public static function sizeLimits(): array
    {
        $mb = 1024 * 1024;

        return [
            'anonymous rejects 15 MB' => [15 * $mb, 10 * $mb, '10 MB', true],
            'free accepts 15 MB'      => [15 * $mb, 20 * $mb, '20 MB', false],
            'free rejects 25 MB'      => [25 * $mb, 20 * $mb, '20 MB', true],
            'premium accepts 150 MB'  => [150 * $mb, 200 * $mb, '200 MB', false],
            'premium rejects 250 MB'  => [250 * $mb, 200 * $mb, '200 MB', true],
        ];
    }
}
