<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Assiduité des promotions' })
const api = useApi()
const { data } = await useAsyncData('assiduite', () => api<any[]>('/api/formateur/promotions'))
</script>
<template>
  <div>
    <h1>Assiduité</h1>
    <section v-for="p in data" :key="p.promotion.id" class="carte bloc">
      <h2>{{ p.promotion.libelle }} <span class="aide">— {{ p.formation }}</span></h2>
      <p>Taux moyen : <strong>{{ p.taux_moyen ?? '—' }} %</strong> · seuil d'alerte : {{ p.seuil }} %</p>
      <div class="tableau-scroll">
        <table class="tableau">
          <caption class="sr-only">Assiduité par étudiant</caption>
          <thead><tr><th scope="col">Étudiant</th><th scope="col">Taux de présence</th><th scope="col">Absences</th><th scope="col">Statut</th></tr></thead>
          <tbody>
            <tr v-for="e in p.etudiants" :key="e.id">
              <td>{{ e.nom }}</td>
              <td style="min-width: 220px"><JaugeAssiduite :taux="e.taux" :seuil="p.seuil" /></td>
              <td>{{ e.absences }}</td>
              <td><span v-if="e.alerte" class="pastille pastille-rouge">Alerte</span><span v-else class="pastille pastille-verte">OK</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
<style scoped>.bloc { margin-bottom: var(--esp-4); }</style>
