<template>
  <EntShell titre="Espace étudiant" :menu="[
    { libelle: 'Aujourd\'hui', to: '/ent/etudiant', icone: 'accueil' },
    { libelle: 'Planning', to: '/ent/etudiant/planning', icone: 'planning' },
    { libelle: 'Notes', to: '/ent/etudiant/notes', icone: 'notes' },
    { libelle: 'Documents', to: '/ent/etudiant/documents', icone: 'documents' },
  ]"><slot /></EntShell>
</template>
