<script setup lang="ts">
definePageMeta({ layout: false, middleware: 'auth' })
const { accueilRole } = useAuth()
await navigateTo(accueilRole(), { replace: true })
</script>
<template><p>Redirection…</p></template>
