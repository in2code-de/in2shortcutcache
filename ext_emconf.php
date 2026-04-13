<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'in2shortcutcache',
    'description' => 'Fixes cache lifetime for pages with shortcut (Insert Records) content elements in TYPO3',
    'category' => 'misc',
    'author' => 'in2code GmbH',
    'author_email' => 'service@in2code.de',
    'state' => 'stable',
    'author_company' => 'in2code GmbH',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
