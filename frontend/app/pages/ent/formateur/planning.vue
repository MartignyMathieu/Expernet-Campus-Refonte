<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Mes séances' })
const api = useApi()
const { data } = await useAsyncData('planning-form', () => api<any>('/api/planning'))
</script>
<template><div><h1>Mes séances</h1><PlanningSemaine :seances="data?.seances || []" role="formateur" /></div></template>
