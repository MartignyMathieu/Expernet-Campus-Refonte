<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Mes notes' })
const api = useApi()
const { data } = await useAsyncData('notes', () => api<any>('/api/me/notes'))
</script>
<template>
  <div>
    <h1>Mes notes</h1>
    <p><span class="chiffre">{{ data?.moyenne ?? '—' }}</span> <span class="libelle-chiffre">/ 20 de moyenne générale pondérée</span></p>
    <div class="carte tableau-scroll">
      <table class="tableau">
        <caption class="sr-only">Liste de mes évaluations</caption>
        <thead><tr><th scope="col">Date</th><th scope="col">Module</th><th scope="col">Évaluation</th><th scope="col">Coef.</th><th scope="col">Note</th></tr></thead>
        <tbody>
          <tr v-for="n in data?.notes" :key="n.id">
            <td>{{ new Date(n.date_evaluation).toLocaleDateString('fr-FR') }}</td><td>{{ n.module.intitule }}</td><td>{{ n.intitule }}</td>
            <td>{{ Number(n.coefficient) }}</td><td><strong :class="Number(n.valeur) < 10 ? 'erreur' : ''">{{ Number(n.valeur) }}/20</strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
