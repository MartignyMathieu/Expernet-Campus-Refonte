<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Messages' })
</script>
<template><Messagerie /></template>
