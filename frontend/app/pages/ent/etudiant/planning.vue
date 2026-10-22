<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Mon planning' })
</script>
<template>
  <div><h1>Mon planning</h1><AgendaPlanning role="etudiant" /></div>
</template>
