<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CASABLANCA Booking Engine',
    'description' => 'CASABLANCA Booking Engine integration for TYPO3. Synchronises availability, rates and inventory from the CASABLANCA IBE v2 API in the background and provides SSR-rendered, SEO-friendly booking widgets that redirect to the CASABLANCA SaaS platform for the actual booking and payment (PCI DSS safe).',
    'category' => 'plugin',
    'author' => 'Martin Hairer',
    'author_email' => 'martin.hairer@casablanca.at',
    'author_company' => 'CASABLANCA hotelsoftware GmbH',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'php' => '8.1.0-8.4.99',
            'scheduler' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Casablanca\\CasablancaBooking\\' => 'Classes/',
        ],
    ],
];
