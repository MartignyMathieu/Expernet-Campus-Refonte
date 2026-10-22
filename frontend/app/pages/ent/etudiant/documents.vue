<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Documents' })
const { user } = useAuth()
const promo = computed(() => user.value?.promotions[0])
</script>
<template>
  <div>
    <h1>Documents & ressources</h1>
    <div v-if="promo" class="actions">
      <a :href="`/api/promotions/${promo.id}/attestation`" class="btn btn-secondaire">Télécharger mon attestation d'assiduité (PDF)</a>
    </div>
    <ListeDocuments v-if="promo" :promotion-id="promo.id" />
    <p v-else class="vide">Vous n'êtes inscrit dans aucune promotion.</p>
  </div>
</template>
<style scoped>.actions { margin-bottom: var(--esp-4); }</style>
