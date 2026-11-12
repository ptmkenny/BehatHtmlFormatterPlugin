<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs Behat with the HTML formatter in a temporary project and checks the report.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class FeatureContext implements Context
{
    private const BEHAT_BIN_PATH = __DIR__.'/../../vendor/bin/behat';

    private const BUILD_DIR = __DIR__.'/../../build';

    private string $phpBin;

    private string $workingDir;

    private string $reportDir;

    private ?Process $process = null;

    #[BeforeScenario]
    public function prepareTestFolders(): void
    {
        $this->workingDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'behat-html-formatter'.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8));
        mkdir($this->workingDir.'/features/bootstrap', 0777, true);
        $this->reportDir = $this->workingDir.'/build/';

        $php = (new PhpExecutableFinder())->find();
        if (false === $php) {
            throw new RuntimeException('Unable to find the PHP executable.');
        }
        $this->phpBin = $php;
    }

    /**
     * Copies the generated report to build/ in the project root for inspection,
     * then removes the temporary project.
     */
    #[AfterScenario]
    public function copyReportAndCleanUp(): void
    {
        self::removeDirectory(self::BUILD_DIR);
        if (is_dir($this->reportDir)) {
            self::copyDirectory($this->reportDir, self::BUILD_DIR);
        }
        self::removeDirectory($this->workingDir);
    }

    #[Given('a file named :filename with:')]
    public function aFileNamedWith(string $filename, PyStringNode $content): void
    {
        $path = $this->workingDir.'/'.$filename;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content->getRaw());
    }

    #[When('/^I run "behat(?: ((?:\"|[^"])*))?"$/')]
    public function iRunBehat(string $argumentsString = ''): void
    {
        $arguments = '' === $argumentsString ? [] : explode(' ', $argumentsString);
        $this->process = new Process(
            [$this->phpBin, self::BEHAT_BIN_PATH, ...$arguments],
            $this->workingDir,
            ['LANG' => 'en']
        );
        $this->process->run();
    }

    /**
     * Checks the exit code (0 to pass, 1 to fail), that nothing was written to
     * stderr, and that stdout is exactly the expected text.
     */
    #[Then('/^it should (fail|pass) with:$/')]
    public function itShouldFailOrPassWith(string $result, PyStringNode $expected): void
    {
        $expectedExitCode = 'fail' === $result ? 1 : 0;
        $exitCode = $this->process->getExitCode();
        $stdout = $this->process->getOutput();
        $stderr = $this->process->getErrorOutput();

        if ($expectedExitCode !== $exitCode) {
            throw new RuntimeException(sprintf(
                "Expected exit code %d, got %d. Output:\n\n%s",
                $expectedExitCode,
                $exitCode,
                $stderr.$stdout
            ));
        }
        if ('' !== $stderr) {
            throw new RuntimeException("Expected no error output, got:\n\n".$stderr);
        }
        if (trim($expected->getRaw()) !== trim($stdout)) {
            throw new RuntimeException(sprintf(
                "Expected output:\n\n%s\n\nActual output:\n\n%s",
                $expected->getRaw(),
                $stdout
            ));
        }
    }

    #[Then('report file should exist')]
    public function reportFileShouldExist(): void
    {
        $files = [
            $this->reportDir.'Index.html',
            $this->reportDir.'assets/Twig/css/style.css',
            $this->reportDir.'assets/Twig/css/style.less',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new RuntimeException(sprintf('File "%s" does not exist.', $file));
            }
        }
    }

    #[Then('report file should contain:')]
    public function reportFileShouldContain(PyStringNode $string): void
    {
        $report = file_get_contents($this->reportDir.'Index.html');
        if (!str_contains($report, $string->getRaw())) {
            throw new RuntimeException(sprintf('The report does not contain "%s".', $string->getRaw()));
        }
    }

    private static function copyDirectory(string $source, string $destination): void
    {
        mkdir($destination, 0777, true);
        foreach (scandir($source) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            if (is_dir($source.'/'.$file)) {
                self::copyDirectory($source.'/'.$file, $destination.'/'.$file);
            } else {
                copy($source.'/'.$file, $destination.'/'.$file);
            }
        }
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            if (is_dir($path.'/'.$file)) {
                self::removeDirectory($path.'/'.$file);
            } else {
                unlink($path.'/'.$file);
            }
        }
        rmdir($path);
    }
}
