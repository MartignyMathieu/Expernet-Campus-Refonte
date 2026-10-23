<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Documents pédagogiques' })
const { user } = useAuth()
const promo = ref(user.value?.promotions[0]?.id)
</script>
<template>
  <div>
    <div class="titre-page">
      <h1>Documents pédagogiques</h1>
      <div v-if="(user?.promotions.length || 0) > 1" class="champ selecteur">
        <label for="promo">Promotion</label>
        <select id="promo" v-model="promo"><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select>
      </div>
    </div>
    <EspacesDocuments v-if="promo" :key="promo" :promotion-id="promo" />
    <p v-else class="vide">Aucune promotion ne vous est encore attribuée.</p>
  </div>
</template>
<style scoped>
.titre-page { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0; }
.selecteur { max-width: 320px; margin: 0; }
</style>
