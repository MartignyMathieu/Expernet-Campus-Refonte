<!--
  Icône « banane » de la navigation du tableau de bord formateur (contrainte du sujet).
  Une banane stylisée + un pictogramme métier dans une pastille, pour que chaque entrée reste identifiable.
  Décorative : aria-hidden, le libellé texte du menu porte le sens (RGAA 1.2).
-->
<script setup lang="ts">
defineProps<{ variante?: 'accueil' | 'planning' | 'emargement' | 'assiduite' | 'notes' | 'documents' | 'examens' | 'securite' | 'messages'; taille?: number }>()
</script>

<template>
  <svg :width="taille || 28" :height="taille || 28" viewBox="0 0 32 32" aria-hidden="true" focusable="false" class="banane">
    <!-- banane -->
    <path d="M4.5 18.5c5.5 6.2 16.3 5.4 20.6-5.6.4-1 1.1-1.8 2-2.2l-.9-2.1c-1.4.5-2.4 1.6-2.9 3C19.8 19.8 11.6 21.2 6.4 16.6z"
          fill="#FFC329" stroke="#6B4E00" stroke-width="1.1" stroke-linejoin="round" />
    <path d="M25.6 8.6l1.3-1.4" stroke="#6B4E00" stroke-width="1.6" stroke-linecap="round" />
    <path d="M7.5 18.2c4.2 2.6 10.6 1.6 14-4.6" fill="none" stroke="#E0A300" stroke-width="1" stroke-linecap="round" />
    <!-- pastille métier -->
    <g v-if="variante && variante !== 'accueil'" transform="translate(18 18)">
      <circle cx="6" cy="6" r="6" fill="#0D5A8A" />
      <g stroke="#fff" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <template v-if="variante === 'planning'"><rect x="3" y="3.5" width="6" height="5.5" rx="1" /><path d="M3 5.5h6" /></template>
        <path v-else-if="variante === 'emargement'" d="M3 7.5c1-2 2-2 2.5 0s1.5 2 3.5-2" />
        <path v-else-if="variante === 'assiduite'" d="M3 9V7M6 9V4.5M9 9V3" />
        <path v-else-if="variante === 'notes'" d="M3.5 9l1.8-5.5h1.4L8.5 9M4.2 7.2h3.6" />
        <path v-else-if="variante === 'documents'" d="M4 3h3l2 2v4H4z" />
        <path v-else-if="variante === 'examens'" d="M3.5 6.2l1.8 1.8 3.3-3.6" />
        <path v-else-if="variante === 'messages'" d="M3 3.5h6v4H5.5L3 9.2z" />
        <path v-else-if="variante === 'securite'" d="M6 3l2.5 1v2c0 1.6-1 2.6-2.5 3-1.5-.4-2.5-1.4-2.5-3V4z" />
      </g>
    </g>
  </svg>
</template>

<style scoped>
.banane { flex-shrink: 0; transition: transform .2s; }
</style>
