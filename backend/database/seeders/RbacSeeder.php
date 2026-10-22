<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Matrice RBAC : la seule source de vérité des droits par rôle. */
class RbacSeeder extends Seeder
{
    public const PERMISSIONS = [
        'planning.view' => 'Consulter son emploi du temps',
        'emargement.sign' => 'Signer sa présence',
        'emargement.manage' => 'Ouvrir / clôturer l\'émargement de ses séances',
        'attestation.download' => 'Télécharger son attestation d\'assiduité',
        'assiduite.view' => 'Consulter l\'assiduité des promotions suivies',
        'notes.view_own' => 'Consulter ses notes',
        'notes.manage' => 'Saisir des notes',
        'documents.view' => 'Consulter les documents de sa promotion',
        'documents.upload' => 'Déposer des documents pédagogiques',
        'examens.view' => 'Consulter les sessions d\'examen',
        'examens.manage' => 'Planifier les examens et composer les jurys',
        'competences.evaluate' => 'Saisir les grilles de compétences (membre du jury)',
        'users.manage' => 'Administrer les comptes, séances et candidatures',
    ];

    public const MATRICE = [
        Role::ETUDIANT => ['planning.view', 'emargement.sign', 'attestation.download', 'notes.view_own', 'documents.view'],
        Role::FORMATEUR => ['planning.view', 'emargement.manage', 'assiduite.view', 'notes.manage', 'documents.view',
            'documents.upload', 'examens.view', 'competences.evaluate'],
        Role::ADMIN => ['planning.view', 'assiduite.view', 'documents.view', 'documents.upload', 'examens.view',
            'examens.manage', 'competences.evaluate', 'users.manage'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $code => $libelle) {
            Permission::updateOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        $libelles = [Role::ADMIN => 'Administrateur', Role::FORMATEUR => 'Formateur', Role::ETUDIANT => 'Étudiant'];
        foreach (self::MATRICE as $code => $permissions) {
            $role = Role::updateOrCreate(['code' => $code], ['libelle' => $libelles[$code]]);
            $role->permissions()->sync(Permission::whereIn('code', $permissions)->pluck('id'));
        }
    }
}
