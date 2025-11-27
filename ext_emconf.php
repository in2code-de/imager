<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Imager - AI generated images in TYPO3',
    'description' => 'Adding images from Gemini with Nano Banana',
    'category' => 'plugin',
    'version' => '1.1.0',
    'author' => 'Alex Kellner',
    'author_email' => 'alexander.kellner@in2code.de',
    'author_company' => 'in2code.de',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
