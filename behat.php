<?php

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Formatter\Formatter;
use Behat\Config\Formatter\PrettyFormatter;
use Behat\Config\Profile;
use emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension;

return (new Config())
    ->withProfile((new Profile('default'))
        ->withFormatter((new Formatter('html'))->withOutputPath(__DIR__.'/report'))
        ->withFormatter(new PrettyFormatter())
        ->withExtension(new Extension(BehatHTMLFormatterExtension::class, [
            'name' => 'html',
            'renderer' => 'Twig',
            'file_name' => 'index',
            'print_args' => true,
            'print_outp' => true,
            'loop_break' => true,
        ])));
