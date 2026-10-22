<?php

return [
    // Rôles pour lesquels la double authentification (TOTP) est obligatoire.
    'mfa_obligatoire_pour' => array_filter(explode(',', env('MFA_OBLIGATOIRE_POUR', 'admin,formateur'))),

    'emargement' => [
        'duree_minutes' => (int) env('EMARGEMENT_DUREE_MINUTES', 15),
    ],

    // Offre de formation continue (site expernet.re, chiffres publiés pour 2024-2025).
    'formation_continue' => [
        'site' => 'https://expernet.re',
        'chiffres' => [
            ['valeur' => 3350, 'libelle' => 'personnes formées en 2024 et 2025'],
            ['valeur' => 1502, 'libelle' => 'sessions de formation en 2024 et 2025'],
            ['valeur' => 86, 'suffixe' => ' %', 'libelle' => 'de taux de satisfaction'],
            ['valeur' => 876, 'libelle' => 'clients nous font confiance'],
        ],
    ],

    'assiduite' => [
        'seuil_alerte' => (int) env('ASSIDUITE_SEUIL_ALERTE', 80),
    ],
];
