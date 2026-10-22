<template>
  <EntShell titre="Espace étudiant" :menu="[
    { libelle: 'Aujourd\'hui', to: '/ent/etudiant', icone: 'accueil' },
    { libelle: 'Planning', to: '/ent/etudiant/planning', icone: 'planning' },
  ]"><slot /></EntShell>
</template>
