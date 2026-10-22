<script setup lang="ts">
const props = defineProps<{ error: { statusCode: number; statusMessage?: string } }>()
useSeoMeta({ title: props.error.statusCode === 404 ? 'Page introuvable' : 'Erreur' })
</script>
<template>
  <NuxtLayout>
    <div class="conteneur" style="padding: 64px 0">
      <h1>{{ error.statusCode === 404 ? 'Page introuvable' : 'Une erreur est survenue' }}</h1>
      <p>{{ error.statusCode === 404 ? 'Cette page n\'existe pas ou a été déplacée.' : 'Réessayez dans un instant.' }}</p>
      <button class="btn btn-primaire" @click="clearError({ redirect: '/' })">Retour à l'accueil</button>
    </div>
  </NuxtLayout>
</template>
