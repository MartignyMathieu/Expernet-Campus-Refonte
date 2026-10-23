<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Documents' })
const { user } = useAuth()
const promo = computed(() => user.value?.promotions[0])
</script>
<template>
  <div>
    <div class="titre-page">
      <h1>Documents & ressources</h1>
      <a v-if="promo" :href="`/api/promotions/${promo.id}/attestation`" class="btn btn-secondaire"><Icone nom="telecharger" :taille="18" /> Mon attestation d'assiduité (PDF)</a>
    </div>
    <EspacesDocuments v-if="promo" :promotion-id="promo.id" />
    <p v-else class="vide">Vous n'êtes inscrit dans aucune promotion.</p>
  </div>
</template>
<style scoped>
.titre-page { display: flex; justify-content: space-between; align-items: center; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0; }
.titre-page .btn { display: inline-flex; align-items: center; gap: 8px; }
</style>
