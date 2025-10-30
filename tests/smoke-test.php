#!/usr/bin/env php
<?php
/**
 * Smoke Tests für ExerciseStatusFile Plugin
 *
 * Führt grundlegende Checks aus OHNE ILIAS-Abhängigkeiten
 *
 * Verwendung:
 *   php tests/smoke-test.php
 *
 * Exit Codes:
 *   0 = Alle Tests bestanden
 *   1 = Tests fehlgeschlagen
 */

define('PLUGIN_DIR', dirname(__DIR__));

class SmokeTests
{
    private $passed = 0;
    private $failed = 0;
    private $warnings = 0;

    public function run(): int
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════\n";
        echo "  ExerciseStatusFile Plugin - Smoke Tests\n";
        echo "═══════════════════════════════════════════════════════\n\n";

        $this->testFileStructure();
        $this->testPhpSyntax();
        $this->testClassStructure();
        $this->testSecurityFunctions();
        $this->testAssignmentDetection();

        echo "\n";
        echo "───────────────────────────────────────────────────────\n";
        echo "Results:\n";
        echo "  ✅ Passed:   {$this->passed}\n";
        echo "  ❌ Failed:   {$this->failed}\n";
        echo "  ⚠️  Warnings: {$this->warnings}\n";
        echo "───────────────────────────────────────────────────────\n\n";

        return $this->failed > 0 ? 1 : 0;
    }

    private function test(string $name, callable $check, bool $critical = true): void
    {
        try {
            $result = $check();
            if ($result === true) {
                echo "✅ PASS: $name\n";
                $this->passed++;
            } else {
                if ($critical) {
                    echo "❌ FAIL: $name\n";
                    if (is_string($result)) {
                        echo "   → $result\n";
                    }
                    $this->failed++;
                } else {
                    echo "⚠️  WARN: $name\n";
                    if (is_string($result)) {
                        echo "   → $result\n";
                    }
                    $this->warnings++;
                }
            }
        } catch (Exception $e) {
            echo "❌ ERROR: $name\n";
            echo "   → Exception: {$e->getMessage()}\n";
            $this->failed++;
        }
    }

    private function testFileStructure(): void
    {
        echo "\n📁 File Structure Tests\n";
        echo "───────────────────────────────────────────────────────\n";

        $required_files = [
            'plugin.php',
            'README.md',
            'classes/class.ilExerciseStatusFileUIHookGUI.php',
            'classes/Processing/class.ilExFeedbackUploadHandler.php',
            'classes/Processing/class.ilExMultiFeedbackDownloadHandler.php',
            'classes/Processing/class.ilExIndividualMultiFeedbackDownloadHandler.php',
        ];

        foreach ($required_files as $file) {
            $this->test(
                "File exists: $file",
                fn() => file_exists(PLUGIN_DIR . '/' . $file)
            );
        }

        $this->test(
            ".gitignore excludes ki_infos/",
            function() {
                $gitignore = @file_get_contents(PLUGIN_DIR . '/.gitignore');
                return $gitignore && strpos($gitignore, 'ki_infos/') !== false;
            },
            false
        );
    }

    private function testPhpSyntax(): void
    {
        echo "\n🔍 PHP Syntax Tests\n";
        echo "───────────────────────────────────────────────────────\n";

        $php_files = $this->findPhpFiles(PLUGIN_DIR . '/classes');

        foreach ($php_files as $file) {
            $relative = str_replace(PLUGIN_DIR . '/', '', $file);
            $this->test(
                "PHP syntax: $relative",
                function() use ($file) {
                    $output = [];
                    $return_var = 0;
                    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
                    return $return_var === 0;
                }
            );
        }
    }

    private function testClassStructure(): void
    {
        echo "\n🏗️  Class Structure Tests\n";
        echo "───────────────────────────────────────────────────────\n";

        // Test dass wichtige Methoden existieren (ohne ILIAS zu laden)
        $upload_handler = file_get_contents(PLUGIN_DIR . '/classes/Processing/class.ilExFeedbackUploadHandler.php');

        $this->test(
            "handleFeedbackUpload method exists",
            fn() => strpos($upload_handler, 'function handleFeedbackUpload') !== false
        );

        $this->test(
            "extractZipContents method exists",
            fn() => strpos($upload_handler, 'function extractZipContents') !== false
        );

        $this->test(
            "Security: Path traversal prevention in extractZipContents",
            fn() => strpos($upload_handler, 'Path traversal') !== false &&
                    strpos($upload_handler, 'realpath') !== false
        );

        $this->test(
            "processTeamFeedbackFiles method exists",
            fn() => strpos($upload_handler, 'function processTeamFeedbackFiles') !== false
        );

        $this->test(
            "filterNewFeedbackFiles method exists",
            fn() => strpos($upload_handler, 'function filterNewFeedbackFiles') !== false
        );

        $this->test(
            "Dead method isUserMarkedForUpdate removed",
            fn() => strpos($upload_handler, 'function isUserMarkedForUpdate') === false,
            false
        );
    }

    private function testSecurityFunctions(): void
    {
        echo "\n🔒 Security Tests\n";
        echo "───────────────────────────────────────────────────────\n";

        $upload_handler = file_get_contents(PLUGIN_DIR . '/classes/Processing/class.ilExFeedbackUploadHandler.php');

        $this->test(
            "Path traversal prevention: ../ filtering",
            fn() => strpos($upload_handler, "str_replace(['../', '..\\\\']") !== false ||
                    strpos($upload_handler, "../") !== false
        );

        $this->test(
            "Path traversal prevention: realpath() check",
            fn() => strpos($upload_handler, 'realpath($extract_dir)') !== false &&
                    strpos($upload_handler, 'realpath($extracted_path)') !== false
        );

        $this->test(
            "Null-byte protection",
            fn() => strpos($upload_handler, '\\0') !== false ||
                    strpos($upload_handler, 'null byte') !== false
        );

        $this->test(
            "Security logging for suspicious files",
            fn() => strpos($upload_handler, 'Suspicious filename') !== false ||
                    strpos($upload_handler, 'Path traversal attempt') !== false
        );

        $this->test(
            "File deletion on security violation",
            fn() => strpos($upload_handler, '@unlink') !== false ||
                    strpos($upload_handler, 'unlink($extracted_path)') !== false
        );
    }

    private function testAssignmentDetection(): void
    {
        echo "\n🎯 Assignment Detection Tests\n";
        echo "───────────────────────────────────────────────────────\n";

        $detector_file = PLUGIN_DIR . '/classes/Detection/class.ilExAssignmentDetector.php';
        $detector_content = file_get_contents($detector_file);

        $this->test(
            "Assignment detection: saveToSession method exists",
            fn() => strpos($detector_content, 'private function saveToSession') !== false
        );

        $this->test(
            "Assignment detection: Session storage implementation",
            fn() => strpos($detector_content, "exc_status_file_last_assignment") !== false
        );

        $this->test(
            "Assignment detection: Session detection checks custom key",
            fn() => strpos($detector_content, "['exc_status_file_last_assignment']") !== false
        );

        $this->test(
            "Assignment detection: saveToSession called on direct params",
            fn() => strpos($detector_content, '$this->saveToSession($direct_result)') !== false
        );
    }

    private function findPhpFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->findPhpFiles($path));
            } elseif (substr($item, -4) === '.php') {
                $files[] = $path;
            }
        }

        return $files;
    }
}

// Run tests
$tests = new SmokeTests();
exit($tests->run());
