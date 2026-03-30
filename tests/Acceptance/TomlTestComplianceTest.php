<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Acceptance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs the official toml-test compliance suite against the decoder and encoder.
 *
 * @see https://github.com/toml-lang/toml-test
 */
#[Group('acceptance')]
final class TomlTestComplianceTest extends TestCase
{
    private const TOML_TEST_BINARY = __DIR__ . '/../../runtime/toml-test.exe';
    private const DECODER = __DIR__ . '/toml-test-decoder.php';
    private const ENCODER = __DIR__ . '/toml-test-encoder.php';

    /** Decoder parses TOML 1.1, encoder outputs TOML 1.0. */
    private const DECODER_TOML_VERSION = '1.1';
    private const ENCODER_TOML_VERSION = '1.0';

    /**
     * Known failures from toml-test suite. Supports fnmatch patterns.
     *
     * Each entry should be removed as the corresponding issue is fixed.
     */
    private const KNOWN_FAILURES = [
        // --- valid tests: decoder produces wrong result ---

        // Numeric/special bare keys: lexer treats digits, nan, inf as value tokens
        'valid/key/alphanum',
        'valid/key/escapes',
        'valid/key/like-date',
        'valid/key/numeric-01',
        'valid/key/numeric-02',
        'valid/key/numeric-03',
        'valid/key/numeric-04',
        'valid/key/numeric-05',
        'valid/key/numeric-06',
        'valid/key/numeric-07',
        'valid/key/numeric-08',
        'valid/key/special-word',
        'valid/key/start',
        'valid/key/zero',
        'valid/float/inf-and-nan',

        // Empty document: PHP empty array JSON-encodes as [] not {}
        'valid/empty-crlf',
        'valid/empty-lf',
        'valid/empty-nothing',
        'valid/empty-space',
        'valid/empty-tab',
        'valid/comment/noeol',
        'valid/comment/nonascii',

        // Datetime format normalization
        'valid/datetime/datetime',
        'valid/datetime/leap-year',
        'valid/datetime/milliseconds',
        'valid/datetime/no-seconds',
        'valid/datetime/timezone',

        // Comment handling edge cases
        'valid/comment/after-literal-no-ws',
        'valid/comment/everywhere',
        'valid/comment/tricky',

        // Table: implicit creation, super-tables, dotted key interactions
        'valid/table/array-empty',
        'valid/table/array-table-array',
        'valid/table/empty',
        'valid/table/keyword',
        'valid/table/keyword-with-values',
        'valid/table/names',
        'valid/table/names-with-values',
        'valid/table/no-eol',
        'valid/table/sub-empty',
        'valid/table/whitespace',
        'valid/table/without-super',
        'valid/array/array-subtables',
        'valid/array/nested-inline-table',
        'valid/array/open-parent-table',

        // Inline table edge cases
        'valid/inline-table/empty',
        'valid/inline-table/nest',
        'valid/inline-table/newline',
        'valid/inline-table/newline-comment',

        // String edge cases
        'valid/string/ends-in-whitespace-escape',
        'valid/string/multiline',
        'valid/string/multiline-empty',
        'valid/string/multiline-escaped-crlf',
        'valid/string/multiline-quotes',
        'valid/string/raw-multiline',

        // Float precision
        'valid/float/long',
        'valid/float/max-int',

        // Spec examples
        'valid/spec-1.1.0/common-11',
        'valid/spec-1.1.0/common-16',
        'valid/spec-1.1.0/common-19',
        'valid/spec-1.1.0/common-24',
        'valid/spec-1.1.0/common-27',
        'valid/spec-1.1.0/common-29',
        'valid/spec-1.1.0/common-3',
        'valid/spec-1.1.0/common-31',
        'valid/spec-1.1.0/common-34',
        'valid/spec-1.1.0/common-37',
        'valid/spec-1.1.0/common-40',
        'valid/spec-1.1.0/common-41',
        'valid/spec-1.1.0/common-42',
        'valid/spec-1.1.0/common-43',
        'valid/spec-1.1.0/common-51',
        'valid/spec-1.1.0/common-52',
        'valid/spec-example-1',
        'valid/spec-example-1-compact',

        // --- invalid tests: parser does not reject invalid input ---

        // Control character validation
        'invalid/control/bare-cr',
        'invalid/control/comment-cr',
        'invalid/control/comment-del',
        'invalid/control/comment-ff',
        'invalid/control/comment-lf',
        'invalid/control/comment-null',
        'invalid/control/comment-us',
        'invalid/control/multi-cr',
        'invalid/control/multi-del',
        'invalid/control/multi-lf',
        'invalid/control/multi-null',
        'invalid/control/multi-us',
        'invalid/control/rawmulti-cr',
        'invalid/control/rawmulti-del',
        'invalid/control/rawmulti-lf',
        'invalid/control/rawmulti-null',
        'invalid/control/rawmulti-us',
        'invalid/control/rawstring-del',
        'invalid/control/rawstring-lf',
        'invalid/control/rawstring-null',
        'invalid/control/rawstring-us',
        'invalid/control/string-bs',
        'invalid/control/string-del',
        'invalid/control/string-lf',
        'invalid/control/string-null',
        'invalid/control/string-us',

        // Number validation
        'invalid/integer/double-us',
        'invalid/integer/incomplete-bin',
        'invalid/integer/incomplete-hex',
        'invalid/integer/incomplete-oct',
        'invalid/integer/invalid-bin',
        'invalid/integer/invalid-oct',
        'invalid/integer/leading-zero-01',
        'invalid/integer/leading-zero-02',
        'invalid/integer/leading-zero-03',
        'invalid/integer/leading-zero-sign-01',
        'invalid/integer/leading-zero-sign-02',
        'invalid/integer/leading-zero-sign-03',
        'invalid/integer/negative-bin',
        'invalid/integer/negative-hex',
        'invalid/integer/negative-oct',
        'invalid/integer/positive-bin',
        'invalid/integer/positive-hex',
        'invalid/integer/positive-oct',
        'invalid/integer/trailing-us',
        'invalid/integer/trailing-us-bin',
        'invalid/integer/trailing-us-hex',
        'invalid/integer/trailing-us-oct',
        'invalid/integer/us-after-bin',
        'invalid/integer/us-after-hex',
        'invalid/integer/us-after-oct',
        'invalid/float/double-dot-02',
        'invalid/float/exp-dot-01',
        'invalid/float/exp-double-e-01',
        'invalid/float/exp-double-e-02',
        'invalid/float/exp-double-us',
        'invalid/float/exp-leading-us',
        'invalid/float/exp-trailing-us',
        'invalid/float/exp-trailing-us-01',
        'invalid/float/exp-trailing-us-02',
        'invalid/float/leading-dot-neg',
        'invalid/float/leading-dot-plus',
        'invalid/float/leading-zero',
        'invalid/float/leading-zero-neg',
        'invalid/float/leading-zero-plus',
        'invalid/float/trailing-exp',
        'invalid/float/trailing-exp-minus',
        'invalid/float/trailing-exp-plus',
        'invalid/float/trailing-us',
        'invalid/float/trailing-us-exp-01',
        'invalid/float/trailing-us-exp-02',
        'invalid/float/us-before-dot',

        // Table/key redefinition rules
        'invalid/table/append-with-dotted-keys-01',
        'invalid/table/append-with-dotted-keys-02',
        'invalid/table/append-with-dotted-keys-03',
        'invalid/table/append-with-dotted-keys-05',
        'invalid/table/append-with-dotted-keys-06',
        'invalid/table/append-with-dotted-keys-07',
        'invalid/table/duplicate-key-02',
        'invalid/table/duplicate-key-03',
        'invalid/table/duplicate-key-04',
        'invalid/table/duplicate-key-05',
        'invalid/table/duplicate-key-06',
        'invalid/table/duplicate-key-07',
        'invalid/table/duplicate-key-08',
        'invalid/table/duplicate-key-10',
        'invalid/table/llbrace',
        'invalid/table/overwrite-array-in-parent',
        'invalid/table/overwrite-bool-with-array',
        'invalid/table/overwrite-with-deep-table',
        'invalid/table/redefine-01',
        'invalid/table/redefine-02',
        'invalid/table/redefine-03',
        'invalid/table/rrbrace',

        // Inline table overwrite/duplicate rules
        'invalid/inline-table/duplicate-key-01',
        'invalid/inline-table/duplicate-key-02',
        'invalid/inline-table/duplicate-key-03',
        'invalid/inline-table/duplicate-key-04',
        'invalid/inline-table/overwrite-01',
        'invalid/inline-table/overwrite-02',
        'invalid/inline-table/overwrite-03',
        'invalid/inline-table/overwrite-04',
        'invalid/inline-table/overwrite-05',
        'invalid/inline-table/overwrite-06',
        'invalid/inline-table/overwrite-07',
        'invalid/inline-table/overwrite-08',
        'invalid/inline-table/overwrite-09',
        'invalid/inline-table/overwrite-10',

        // Datetime validation
        'invalid/datetime/day-zero',
        'invalid/datetime/feb-29',
        'invalid/datetime/feb-30',
        'invalid/datetime/hour-over',
        'invalid/datetime/mday-over',
        'invalid/datetime/mday-under',
        'invalid/datetime/minute-over',
        'invalid/datetime/month-over',
        'invalid/datetime/month-under',
        'invalid/datetime/offset-minus-no-hour-minute-sep',
        'invalid/datetime/offset-overflow-hour',
        'invalid/datetime/offset-overflow-minute',
        'invalid/datetime/offset-plus-no-hour-minute-sep',
        'invalid/datetime/second-over',
        'invalid/datetime/second-trailing-dot',
        'invalid/datetime/second-trailing-dotz',
        'invalid/local-datetime/feb-29',
        'invalid/local-datetime/feb-30',
        'invalid/local-datetime/hour-over',
        'invalid/local-datetime/mday-over',
        'invalid/local-datetime/mday-under',
        'invalid/local-datetime/minute-over',
        'invalid/local-datetime/month-over',
        'invalid/local-datetime/month-under',
        'invalid/local-datetime/second-over',
        'invalid/local-time/hour-over',
        'invalid/local-time/minute-over',
        'invalid/local-time/second-over',
        'invalid/local-time/trailing-dot',

        // String validation
        'invalid/string/multiline-bad-escape-04',
        'invalid/string/multiline-lit-no-close-01',
        'invalid/string/multiline-lit-no-close-02',
        'invalid/string/multiline-lit-no-close-03',
        'invalid/string/multiline-lit-no-close-04',
        'invalid/string/multiline-no-close-01',
        'invalid/string/multiline-no-close-02',
        'invalid/string/multiline-no-close-03',
        'invalid/string/multiline-no-close-04',

        // Encoding validation
        'invalid/encoding/bad-codepoint',
        'invalid/encoding/bad-utf8-in-comment',
        'invalid/encoding/bad-utf8-in-multiline',
        'invalid/encoding/bad-utf8-in-multiline-literal',
        'invalid/encoding/bad-utf8-in-string',
        'invalid/encoding/bad-utf8-in-string-literal',

        // Key validation
        'invalid/key/after-array',
        'invalid/key/after-table',
        'invalid/key/after-value',
        'invalid/key/dotted-redefine-table-01',
        'invalid/key/dotted-redefine-table-02',
        'invalid/key/no-eol-01',

        // Array of tables
        'invalid/array/extend-defined-aot',
        'invalid/array/extending-table',
        'invalid/array/tables-01',
        'invalid/array/tables-02',

        // Spec examples
        'invalid/spec-1.1.0/common-46-0',
        'invalid/spec-1.1.0/common-46-1',
        'invalid/spec-1.1.0/common-49-0',
        'invalid/spec-1.1.0/common-50-0',
    ];

    /**
     * Provides all test case names from toml-test list.
     *
     * @return \Generator<string, array{string}>
     */
    public static function provideDecoderTestCases(): \Generator
    {
        $binary = \str_replace('/', DIRECTORY_SEPARATOR, self::TOML_TEST_BINARY);

        if (!\file_exists($binary)) {
            return;
        }

        $process = \proc_open(
            [$binary, 'list', '-toml', self::DECODER_TOML_VERSION],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!\is_resource($process)) {
            return;
        }

        $stdout = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        $seen = [];
        foreach (\explode("\n", \trim($stdout)) as $line) {
            $name = \preg_replace('/\.(toml|json)$/', '', \trim($line));
            if ($name === '' || \str_starts_with($name, 'encoder/') || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            yield $name => [$name];
        }
    }

    #[DataProvider('provideDecoderTestCases')]
    public function testDecoderCase(string $testName): void
    {
        $result = $this->runSingleTest($testName);

        if ($result === null) {
            if (self::isKnownFailure($testName)) {
                self::fail("Known failure '{$testName}' now passes — remove it from KNOWN_FAILURES.");
            }

            $this->addToAssertionCount(1);

            return;
        }

        // Test failed
        if (self::isKnownFailure($testName)) {
            self::markTestIncomplete($result);
        }

        self::fail($result);
    }

    public function testEncoderCompliance(): void
    {
        $result = $this->runTomlTestSuite(self::ENCODER_TOML_VERSION);

        self::assertSame(0, $result['exit_code'], "Encoder compliance failed:\n" . $result['output']);
    }

    protected function setUp(): void
    {
        if (!\file_exists(self::TOML_TEST_BINARY)) {
            self::markTestSkipped('toml-test binary not found at ' . self::TOML_TEST_BINARY);
        }
    }

    /**
     * Runs a single toml-test case via -run and -json.
     *
     * @return string|null Failure details or null if passed.
     */
    private function runSingleTest(string $testName): ?string
    {
        $binary = \str_replace('/', DIRECTORY_SEPARATOR, self::TOML_TEST_BINARY);

        $process = \proc_open(
            [
                $binary, 'test',
                '-toml', self::DECODER_TOML_VERSION,
                '-color', 'never',
                '-json',
                '-run', $testName,
                '-decoder', PHP_BINARY . ' ' . self::DECODER,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $stdout = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        $json = \json_decode($stdout, true);

        foreach ($json['tests'] ?? [] as $test) {
            if (($test['failure'] ?? '') === '') {
                continue;
            }

            // Build readable output with full context
            $lines = ["FAIL {$test['path']}"];
            $lines[] = '';
            $lines[] = $test['failure'];

            if (($test['input'] ?? '') !== '') {
                $lines[] = '';
                $lines[] = '--- input:';
                $lines[] = $test['input'];
            }

            if (($test['output'] ?? '') !== '') {
                $lines[] = '--- output:';
                $lines[] = $test['output'];
            }

            if (($test['want'] ?? '') !== '') {
                $lines[] = '--- want:';
                $lines[] = $test['want'];
            }

            return \implode("\n", $lines);
        }

        return null;
    }

    private static function isKnownFailure(string $path): bool
    {
        foreach (self::KNOWN_FAILURES as $pattern) {
            if (\fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Runs the full toml-test suite with encoder, skipping known failures.
     *
     * @return array{exit_code: int, output: string}
     */
    private function runTomlTestSuite(string $tomlVersion): array
    {
        $binary = \str_replace('/', DIRECTORY_SEPARATOR, self::TOML_TEST_BINARY);

        $args = [
            $binary, 'test',
            '-toml', $tomlVersion,
            '-color', 'never',
            '-decoder', PHP_BINARY . ' ' . self::DECODER,
            '-encoder', PHP_BINARY . ' ' . self::ENCODER,
        ];

        foreach (self::KNOWN_FAILURES as $skip) {
            $args[] = '-skip';
            $args[] = $skip;
        }

        $process = \proc_open(
            $args,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        return ['exit_code' => $exitCode, 'output' => $stdout . $stderr];
    }
}
