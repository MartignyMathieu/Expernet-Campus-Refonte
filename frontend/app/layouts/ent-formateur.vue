<!-- Tableau de bord formateur : navigation à motifs de bananes (contrainte du cahier des charges). -->
<template>
  <EntShell titre="Espace formateur" theme="banane" :menu="[
    { libelle: 'Tableau de bord', to: '/ent/formateur', icone: 'accueil' },
    { libelle: 'Mes séances', to: '/ent/formateur/planning', icone: 'planning' },
    { libelle: 'Assiduité', to: '/ent/formateur/assiduite', icone: 'assiduite' },
    { libelle: 'Jurys & compétences', to: '/ent/formateur/examens', icone: 'examens' },
    { libelle: 'Documents', to: '/ent/formateur/documents', icone: 'documents' },
  ]"><slot /></EntShell>
</template>
