<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Documents pédagogiques' })
const { user } = useAuth()
const promo = ref(user.value?.promotions[0]?.id)
</script>
<template>
  <div>
    <h1>Documents pédagogiques</h1>
    <div class="champ selecteur" v-if="(user?.promotions.length || 0) > 1">
      <label for="promo">Promotion</label>
      <select id="promo" v-model="promo"><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select>
    </div>
    <ListeDocuments v-if="promo" :key="promo" :promotion-id="promo" peut-deposer />
  </div>
</template>
<style scoped>.selecteur { max-width: 320px; }</style>
