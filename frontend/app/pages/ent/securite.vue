<script setup lang="ts">
// Ancienne adresse : la sécurité du compte est désormais dans « Mon profil ».
definePageMeta({ layout: false, middleware: 'auth' })
await navigateTo('/ent/profil#securite', { replace: true })
</script>
<template><p>Redirection…</p></template>
