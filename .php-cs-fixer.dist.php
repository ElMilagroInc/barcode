<?php

/** SPDX-License-Identifier: AGPL-3.0-or-later */
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'single'],
        'no_extra_blank_lines' => true,
        'array_indentation' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__)->exclude(['vendor', '.glpi']));
