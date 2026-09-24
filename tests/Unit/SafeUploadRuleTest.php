<?php

namespace Tests\Unit;

use App\Rules\SafeUpload;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Audit SEC-01: nothing a server or browser could run gets onto the public disk. */
class SafeUploadRuleTest extends TestCase
{
    private function fails(UploadedFile $file): bool
    {
        $failed = false;
        (new SafeUpload())->validate('file', $file, function () use (&$failed) { $failed = true; });

        return $failed;
    }

    public static function dangerous(): array
    {
        return [
            'php'              => ['shell.php', '<?php echo 1;'],
            'php hidden as jpg'=> ['photo.jpg', '<?php echo 1;'],
            'double extension' => ['x.php.jpg', 'plain text'],
            'short tag'        => ['a.png', '<?= 1 ?>'],
            'html'             => ['page.html', '<html><script>alert(1)</script></html>'],
            'svg'              => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'],
            'phtml'            => ['a.phtml', 'x'],
            'phar'             => ['a.phar', 'x'],
        ];
    }

    #[DataProvider('dangerous')]
    public function test_script_uploads_are_refused(string $name, string $content): void
    {
        $this->assertTrue($this->fails(UploadedFile::fake()->createWithContent($name, $content)));
    }

    public function test_office_documents_pass(): void
    {
        // A minimal zip header is what a .docx/.xlsx starts with; its MIME must
        // not be caught by the SVG/XML rules (regression, 24 Sep 2026).
        $zip = "PK\x03\x04" . str_repeat("\0", 26) . '[Content_Types].xml';
        $this->assertFalse($this->fails(UploadedFile::fake()->createWithContent('plan.docx', $zip)));
        $this->assertFalse($this->fails(UploadedFile::fake()->createWithContent('stock.xlsx', $zip)));
        $this->assertFalse($this->fails(UploadedFile::fake()->createWithContent('import.csv', "name,phone\nA,1\n")));
    }

    public function test_a_real_image_and_a_pdf_pass(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $this->assertFalse($this->fails(UploadedFile::fake()->createWithContent('xray.png', $png)));
        $this->assertFalse($this->fails(UploadedFile::fake()->createWithContent('consent.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF")));
    }
}
