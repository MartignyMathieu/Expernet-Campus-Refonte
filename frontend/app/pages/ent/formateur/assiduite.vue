<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Assiduité des promotions' })
</script>
<template>
  <SuiviAssiduite />
</template>
