<template>
  <EntShell titre="Administration" :menu="[
    { libelle: 'Vue d\'ensemble', to: '/ent/admin', icone: 'accueil' },
    { libelle: 'Messages', to: '/ent/admin/messages', icone: 'messages' },
    { libelle: 'Utilisateurs', to: '/ent/admin/utilisateurs', icone: 'utilisateurs' },
    { libelle: 'Candidatures', to: '/ent/admin/candidatures', icone: 'candidatures' },
    { libelle: 'Assiduité', to: '/ent/admin/assiduite', icone: 'assiduite' },
    { libelle: 'Examens & jurys', to: '/ent/admin/examens', icone: 'examens' },
    { libelle: 'Documents', to: '/ent/admin/documents', icone: 'documents' },
    { libelle: 'Journal d\'audit', to: '/ent/admin/journal', icone: 'programme' },
  ]"><slot /></EntShell>
</template>
