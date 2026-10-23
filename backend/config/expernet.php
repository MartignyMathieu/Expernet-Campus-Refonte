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
        'contact' => ['telephone' => '02 62 21 90 45', 'email' => 'formation@expernet.re'],
        'liens' => [
            'devis' => 'https://expernet.re/demande-de-devis/',
            'planning' => 'https://expernet.re/planning/',
            'livret_accueil' => 'https://expernet.re/wp-content/uploads/2026/02/Reunion-Livret-accueil-FC.pdf',
            'certificat_qualiopi' => 'https://expernet.re/wp-content/uploads/2025/06/23FOR01923.1-Certificat-de-Conformite.pdf',
        ],
        'locaux' => [
            ['ville' => 'Le Port', 'adresse' => '3 avenue Théodore Drouhet, 97420 Le Port'],
            ['ville' => 'Sainte-Clotilde', 'adresse' => '13 rue Maurice Tomi, 97490 Sainte-Clotilde (locaux de One to One)'],
            ['ville' => 'Mamoudzou', 'adresse' => 'ZI Kawéni, route de la mangrove, bâtiment SOMIBAT, 97600 Mamoudzou'],
        ],
        'formats' => [
            ['icone' => 'entreprise', 'titre' => 'Intra-entreprise', 'texte' => 'La formation intra-entreprise est destinée à un groupe spécifique de participants au sein de la même entreprise.'],
            ['icone' => 'groupe', 'titre' => 'Inter-entreprises', 'texte' => 'La formation inter-entreprise s\'adresse à un public varié de différentes entreprises.'],
            ['icone' => 'kanban', 'titre' => 'Sur mesure', 'texte' => 'La formation sur mesure vise à être intensive et ciblée sur une courte période de temps.'],
        ],
        // Modalités communes à la plupart des stages (une fiche peut les remplacer).
        'modalites' => [
            'moyens' => ['Intervenant : Formateur Professionnel', 'Mobilisation des outils et auxiliaires utiles : paperboard, vidéoprojecteur…', 'Possibilité de mettre à disposition des PC portables pour les stagiaires'],
            'methodes' => ['Alternance de méthodes affirmatives, interrogatives et actives', 'Ressources de type formatives, évaluatives, de synthèses, de soutien, de mémorisation…', 'Utilisation de Padlet'],
            'evaluation' => ['- Des acquis', 'Fiche de renseignements en amont et questions', 'Evaluations formatives réalisées à l’aide de grilles critériées ;', 'Attestation de fin de formation mentionnant l’atteinte des objectifs de la formation', 'Evaluation différée', '- Evaluation satisfaction', '- Rédaction d’un bilan d’action remis au commanditaire en cas de formation intra-entreprise'],
        ],
        'accessibilite' => 'Établissements accessibles aux PMR et PSH, places de parking dédiées. Référente handicap joignable au 02 62 21 90 45.',
        // Page « À propos » (expernet.re : Notre histoire, L'équipe), reprise le 23/10/2026.
        'a_propos' => [
            'presentation' => 'Expernet est un centre de formation en informatique et marketing digital implanté à La Réunion et à Mayotte. Créé pour accompagner les personnes à l\'ère d\'internet et au début de la transformation digitale, il forme les salariés et les aide à monter en compétences sur l\'informatique et ses solutions, avec l\'humain au centre du projet.',
            'chiffre_cle' => ['valeur' => 100000, 'prefixe' => '+ de ', 'libelle' => 'personnes formées depuis 2002'],
            'histoire' => [
                ['annee' => 2002, 'titre' => 'Création du centre de formation', 'texte' => 'Expernet ouvre ses portes : un projet visionnaire pour l\'époque, qui devient rapidement un acteur majeur de la formation sur le territoire.'],
                ['annee' => 2015, 'titre' => 'Rachat par le Groupe MDSI', 'texte' => 'Le Groupe MDSI, acteur majeur des services informatiques dans l\'océan Indien, rachète Expernet pour élargir ses activités et contribuer au développement du territoire.'],
                ['annee' => 2017, 'titre' => 'Création du campus en alternance', 'texte' => 'Sous l\'impulsion de Marc-Henri Ravaux, le campus Expernet s\'associe à des écoles de métropole pour proposer des diplômes adaptés aux entreprises et limiter la fuite des talents vers l\'Hexagone.'],
                ['annee' => 2022, 'titre' => 'Ouverture à Mayotte', 'texte' => 'Sur le modèle de La Réunion, Expernet devient à Mayotte le premier centre de formation à proposer un cursus Bac+4 en informatique.'],
            ],
            'equipe' => [
                ['nom' => 'Marc-Henri Ravaux', 'fonction' => 'Directeur Général'],
                ['nom' => 'Maxime Courbet', 'fonction' => 'Directeur Exploitation Réunion & Mayotte'],
                ['nom' => 'Emma Nerina', 'fonction' => 'Conseillère en formation continue'],
                ['nom' => 'Jérémy Oger', 'fonction' => 'Conseiller en formation continue'],
                ['nom' => 'Emeline Hubert', 'fonction' => 'Assistante commerciale en formation continue'],
                ['nom' => 'Laetitia Laurence-Nicosia', 'fonction' => 'Responsable administrative et financière'],
                ['nom' => 'Murielle Chamand', 'fonction' => 'Assistante administrative et comptable, référente handicap'],
            ],
            'certification' => [
                'nom' => 'Qualiopi',
                'categories' => ['Actions de formation', 'Actions de formation par apprentissage'],
            ],
            'referente_handicap' => ['nom' => 'Murielle Chamand', 'email' => 'm.chamand@expernet.re'],
        ],
        'planning_note' => 'Toutes nos formations sont disponibles en inter ou intra-entreprise, avec des dates flexibles, adaptables à vos besoins et sur demande.',
    ],

    'assiduite' => [
        'seuil_alerte' => (int) env('ASSIDUITE_SEUIL_ALERTE', 80),
    ],
];
