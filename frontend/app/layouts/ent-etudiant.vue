<template>
  <EntShell titre="Espace étudiant" :menu="[
    { libelle: 'Aujourd\'hui', to: '/ent/etudiant', icone: 'accueil' },
    { libelle: 'Messages', to: '/ent/etudiant/messages', icone: 'messages' },
    { libelle: 'Planning', to: '/ent/etudiant/planning', icone: 'planning' },
    { libelle: 'Devoirs', to: '/ent/etudiant/devoirs', icone: 'devoir' },
    { libelle: 'Notes', to: '/ent/etudiant/notes', icone: 'notes' },
    { libelle: 'Assiduité', to: '/ent/etudiant/assiduite', icone: 'assiduite' },
    { libelle: 'Documents', to: '/ent/etudiant/documents', icone: 'documents' },
  ]"><slot /></EntShell>
</template>
