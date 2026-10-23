<!-- Photo de profil, ou initiales si l'utilisateur n'en a pas (ou si l'image ne se charge pas). -->
<script setup lang="ts">
const props = withDefaults(defineProps<{ url?: string | null; nom?: string; taille?: number }>(), { url: null, nom: '', taille: 40 })
const erreur = ref(false)
watch(() => props.url, () => { erreur.value = false })
const initiales = computed(() => props.nom.split(/\s+/).filter(Boolean).map((p) => p[0]).join('').slice(0, 2).toUpperCase())
</script>

<template>
  <span class="avatar-photo" :style="{ width: taille + 'px', height: taille + 'px', fontSize: Math.round(taille * .36) + 'px' }" aria-hidden="true">
    <img v-if="url && !erreur" :src="url" alt="" loading="lazy" @error="erreur = true">
    <template v-else>{{ initiales }}</template>
  </span>
</template>

<style scoped>
.avatar-photo { border-radius: 50%; display: inline-grid; place-items: center; flex-shrink: 0; overflow: hidden; background: var(--primaire-fonce); color: #fff; font-weight: 700; line-height: 1; }
.avatar-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
