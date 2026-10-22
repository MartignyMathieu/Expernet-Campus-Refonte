<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Candidatures' })
const api = useApi()
const { data } = await useAsyncData('candidatures', () => api<any>('/api/admin/candidatures'))
</script>
<template>
  <div>
    <h1>Candidatures reçues</h1>
    <div class="carte tableau-scroll">
      <p v-if="!data?.data.length" class="vide">Aucune candidature pour l'instant.</p>
      <table v-else class="tableau">
        <caption class="sr-only">Candidatures</caption>
        <thead><tr><th scope="col">Date</th><th scope="col">Candidat</th><th scope="col">Formation</th><th scope="col">Contact</th><th scope="col">Statut</th></tr></thead>
        <tbody>
          <tr v-for="c in data.data" :key="c.id">
            <td>{{ new Date(c.created_at).toLocaleDateString('fr-FR') }}</td><td>{{ c.prenom }} {{ c.nom }}</td>
            <td>{{ c.formation.intitule }}</td><td>{{ c.email }}<br><small>{{ c.telephone }}</small></td>
            <td><span class="pastille pastille-bleue">{{ c.statut }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
