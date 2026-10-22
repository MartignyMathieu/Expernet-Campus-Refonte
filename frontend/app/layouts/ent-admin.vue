<template>
  <EntShell titre="Administration" :menu="[
    { libelle: 'Vue d\'ensemble', to: '/ent/admin', icone: 'accueil' },
    { libelle: 'Utilisateurs', to: '/ent/admin/utilisateurs', icone: 'securite' },
    { libelle: 'Candidatures', to: '/ent/admin/candidatures', icone: 'documents' },
    { libelle: 'Examens & jurys', to: '/ent/admin/examens', icone: 'examens' },
  ]"><slot /></EntShell>
</template>
