<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Mes séances' })
</script>
<template><div><h1>Mes séances</h1><AgendaPlanning role="formateur" /></div></template>
