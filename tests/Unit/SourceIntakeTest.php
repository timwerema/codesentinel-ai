<?php

namespace Tests\Unit;

use App\Analysis\RejectedSource;
use App\Analysis\SourceFile;
use App\Analysis\SourceIntake;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceIntakeTest extends TestCase
{
    #[DataProvider('names')]
    public function test_file_names_are_reduced_to_a_safe_base_name(string $given, string $expected): void
    {
        $this->assertSame($expected, (new SourceIntake)->safeName($given));
    }

    public static function names(): array
    {
        return [
            'plain' => ['app.py', 'app.py'],
            'path traversal' => ['../../etc/passwd.py', 'passwd.py'],
            'windows path' => ['C:\\Users\\me\\evil.php', 'evil.php'],
            'hidden file' => ['.env.php', 'env.php'],
            'looks like an option' => ['--config=x.js', 'config_x.js'],
            'shell characters' => ['a;rm -rf $(x)`y`.c', 'a_rm_-rf_x_y_.c'],
            'null byte' => ["a\0.php", 'a_.php'],
            'unicode' => ['наме.ts', 'file.ts'],
            'extension case' => ['Main.JAVA', 'Main.java'],
        ];
    }

    public function test_supported_text_is_accepted_and_line_endings_are_normalised(): void
    {
        $file = (new SourceIntake)->accept('a.py', "x = 1\r\ny = 2\r\n");

        $this->assertSame('python', $file->language);
        $this->assertSame("x = 1\ny = 2\n", $file->contents);
        $this->assertSame(2, $file->lines());
    }

    #[DataProvider('rejected')]
    public function test_unsafe_or_unsupported_input_is_refused(string $name, string $contents, string $expected): void
    {
        $this->expectException(RejectedSource::class);
        $this->expectExceptionMessage($expected);

        (new SourceIntake)->accept($name, $contents);
    }

    public static function rejected(): array
    {
        return [
            'unsupported type' => ['run.sh', 'echo hi', 'not a supported file type'],
            'no extension' => ['Makefile', 'all:', 'not a supported file type'],
            'double extension' => ['shell.py.exe', 'MZ', 'not a supported file type'],
            'binary with a source extension' => ['a.py', "\x7fELF\x00\x01\x02", 'does not look like a text source file'],
            'invalid utf-8' => ['a.js', "var a = '\xff\xfe';", 'does not look like a text source file'],
            'empty' => ['a.c', "  \n ", 'is empty'],
        ];
    }

    public function test_a_file_over_the_size_limit_is_refused(): void
    {
        config(['codesentinel.limits.max_file_bytes' => 100]);
        $this->expectException(RejectedSource::class);
        $this->expectExceptionMessage('larger than');

        (new SourceIntake)->accept('a.py', str_repeat('a', 101));
    }

    public function test_batches_are_limited_in_count_and_total_size(): void
    {
        config(['codesentinel.limits.max_files' => 2, 'codesentinel.limits.max_total_bytes' => 10]);
        $intake = new SourceIntake;
        $f = fn (string $c) => new SourceFile('a.py', 'python', $c);

        $intake->checkBatch([$f('12345'), $f('12345')]);

        foreach ([[], [$f('1'), $f('1'), $f('1')], [$f('123456'), $f('12345')]] as $batch) {
            try {
                $intake->checkBatch($batch);
                $this->fail('Expected the batch to be refused.');
            } catch (RejectedSource) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
