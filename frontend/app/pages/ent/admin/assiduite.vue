<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Assiduité et justificatifs' })
</script>
<template>
  <SuiviAssiduite />
</template>
