<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Jurys & compétences' })
const api = useApi()
const { data } = await useAsyncData('examens-form', () => api<any[]>('/api/examens'))
const choisie = ref<number | null>(null)
</script>
<template>
  <div>
    <h1>Jurys & grilles de compétences</h1>
    <p v-if="!data?.length" class="vide">Vous n'êtes membre d'aucun jury.</p>
    <div class="grille grille-3">
      <article v-for="s in data" :key="s.id" class="carte">
        <h2 class="h3">{{ s.intitule }}</h2>
        <p class="aide">{{ new Date(s.debut).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' }) }} · {{ s.lieu }}</p>
        <p>Jury : {{ s.jury.map((j: any) => `${j.prenom} ${j.nom}${j.pivot.fonction === 'president' ? ' (président)' : ''}`).join(', ') }}</p>
        <button class="btn btn-primaire" @click="choisie = s.id">Saisir la grille</button>
      </article>
    </div>
    <section v-if="choisie" class="carte bloc"><h2>Grille de compétences</h2><GrilleCompetences :session-id="choisie" :key="choisie" /></section>
  </div>
</template>
<style scoped>.h3 { font-size: 1.1rem; } .bloc { margin-top: var(--esp-4); }</style>
