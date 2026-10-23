<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Documents des promotions' })
const { user } = useAuth()
const promo = ref(user.value?.promotions[0]?.id)
</script>
<template>
  <div>
    <div class="titre-page">
      <div>
        <h1>Documents des promotions</h1>
        <p class="aide">Publiez les documents administratifs (règlement, calendrier, conventions) et suivez les dépôts des équipes et des apprenants.</p>
      </div>
      <div v-if="user?.promotions.length" class="champ selecteur">
        <label for="promo">Promotion</label>
        <select id="promo" v-model="promo"><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select>
      </div>
    </div>
    <EspacesDocuments v-if="promo" :key="promo" :promotion-id="promo" />
    <p v-else class="vide">Aucune promotion n'est encore créée.</p>
  </div>
</template>
<style scoped>
.titre-page { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0 0 4px; }
.titre-page .aide { margin: 0; max-width: 60ch; }
.selecteur { max-width: 320px; margin: 0; }
</style>
