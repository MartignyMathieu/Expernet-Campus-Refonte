<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Mon planning' })
const api = useApi()
const { data } = await useAsyncData('planning-etu', () => api<any>('/api/planning'))
</script>
<template>
  <div><h1>Mon planning</h1><PlanningSemaine :seances="data?.seances || []" role="etudiant" /></div>
</template>
