<?php
/**
 * Very simple FileOutputPrinter for BehatHTMLFormatter.
 *
 * @author David Raison <david@tentwentyfour.lu>
 */

namespace emuse\BehatHTMLFormatter\Printer;

use Behat\Testwork\Output\Exception\BadOutputPathException;
use Behat\Testwork\Output\Printer\OutputPrinter as PrinterInterface;

class FileOutputPrinter implements PrinterInterface
{
    /**
     * @param  $outputPath where to save the generated report file
     */
    private $outputPath;

    /**
     * @param  $base_path Behat base path
     */
    private $base_path;

    /**
     * @param  $rendererFiles List of the filenames for the renderers
     */
    private $rendererFiles;

    /**
     * @param $rendererList
     * @param $filename
     * @param $base_path
     */
    public function __construct($rendererList, $filename, $base_path)
    {
        //let's generate the filenames for the renderers
        $this->rendererFiles = array();
        foreach ($rendererList as $renderer) {
            if ('generated' == $filename) {
                $date = date('YmdHis');
                $this->rendererFiles[$renderer] = $renderer.'_'.$date;
            } else {
                $this->rendererFiles[$renderer] = $filename;
            }
        }

        $this->base_path = $base_path;
    }

    /**
     * Verify that the specified output path exists or can be created,
     * then sets the output path.
     *
     * @param string $path Output path relative to %paths.base%
     */
    public function setOutputPath(string $path): void
    {
        $outpath = $path;
        if (!file_exists($outpath)) {
            if (!mkdir($outpath, 0755, true)) {
                throw new BadOutputPathException(
          sprintf(
            'Output path %s does not exist and could not be created!',
            $outpath
          ),
          $outpath
        );
            }
        } else {
            if (!is_dir(realpath($outpath))) {
                throw new BadOutputPathException(
          sprintf(
            'The argument to `output` is expected to the a directory, but got %s!',
            $outpath
          ),
          $outpath
        );
            }
        }
        $this->outputPath = $outpath;
    }

    /**
     * Returns output path.
     *
     * @return string|null output path
     */
    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    /**
     * Sets output styles.
     *
     * @param array $styles
     */
    public function setOutputStyles(array $styles): void
    {
    }

    /**
     * Forces output to be decorated.
     *
     * @param bool $decorated
     */
    public function setOutputDecorated(bool $decorated): void
    {
    }

    /**
     * Sets output verbosity level.
     *
     * @param int $level
     */
    public function setOutputVerbosity(int $level): void
    {
    }

    /**
     * Writes message(s) to output console.
     *
     * @param string|array $messages message, or array of messages keyed by renderer name
     */
    public function write(string|array $messages): void
    {
        //Write it for each message = each renderer
        foreach ($this->messagesByRenderer($messages) as $key => $message) {
            $file = $this->outputPath.DIRECTORY_SEPARATOR.$this->rendererFiles[$key].'.html';
            file_put_contents($file, $message);
            $this->copyAssets($key);
        }
    }

    /**
     * Writes newlined message(s) to output console.
     *
     * @param string|array $messages message, or array of messages keyed by renderer name
     */
    public function writeln(string|array $messages = ''): void
    {
        //Write it for each message = each renderer
        foreach ($this->messagesByRenderer($messages) as $key => $message) {
            $file = $this->outputPath.DIRECTORY_SEPARATOR.$this->rendererFiles[$key].'.html';
            file_put_contents($file, $message, FILE_APPEND);
        }
    }

    /**
     * Writes  message(s) at start of the output console.
     *
     * @param string|array $messages message, or array of messages keyed by renderer name
     */
    public function writeBeginning(string|array $messages = ''): void
    {
        //Write it for each message = each renderer
        foreach ($this->messagesByRenderer($messages) as $key => $message) {
            $file = $this->outputPath.DIRECTORY_SEPARATOR.$this->rendererFiles[$key].'.html';
            $fileContents = file_get_contents($file);
            file_put_contents($file, $message.$fileContents);
        }
    }

    /**
     * Normalizes messages to an array keyed by renderer name.
     *
     * A string message is written to the file of every renderer.
     *
     * @param string|array $messages
     *
     * @return array<string, string>
     */
    private function messagesByRenderer(string|array $messages): array
    {
        if (is_array($messages)) {
            return $messages;
        }

        return array_fill_keys(array_keys($this->rendererFiles), $messages);
    }

    /**
     * Copies the assets folder to the report destination.
     *
     * @param string : the renderer
     */
    public function copyAssets(string $renderer): void
    {
        // If the assets folder doesn' exist in the output path for this renderer, copy it
        $source = realpath(dirname(__FILE__));
        $assets_source = realpath($source.'/../../assets/'.$renderer);
        if (false === $assets_source) {
            //There is no assets to copy for this renderer
            return;
        }

        //first create the assets dir
        $destination = $this->outputPath.DIRECTORY_SEPARATOR.'assets';
        @mkdir($destination);

        $this->recurse_copy($assets_source, $destination.DIRECTORY_SEPARATOR.$renderer);
    }

    /**
     * Recursivly copy a path.
     *
     * @param $src
     * @param $dst
     */
    private function recurse_copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (('.' != $file) && ('..' != $file)) {
                if (is_dir($src.'/'.$file)) {
                    $this->recurse_copy($src.'/'.$file, $dst.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Clear output console, so on next write formatter will need to init (create) it again.
     */
    public function flush(): void
    {
    }
}
