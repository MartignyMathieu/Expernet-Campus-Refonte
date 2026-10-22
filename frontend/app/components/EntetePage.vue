<!-- En-tête illustré des pages publiques (photo officielle + voile aux couleurs de la marque). -->
<script setup lang="ts">
defineProps<{ titre: string; surTitre?: string; texte?: string; image: string }>()
</script>
<template>
  <section class="entete-page">
    <img :src="image" alt="" width="1920" height="547" fetchpriority="high">
    <div class="conteneur contenu">
      <p v-if="surTitre" class="sur-titre clair">{{ surTitre }}</p>
      <h1>{{ titre }}</h1>
      <p v-if="texte" class="texte">{{ texte }}</p>
      <slot />
    </div>
  </section>
</template>
<style scoped>
.entete-page { position: relative; color: #fff; padding: 64px 0; overflow: hidden; background: var(--marine); }
.entete-page > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.entete-page::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.95), rgba(13,90,138,.82) 60%, rgba(0,155,221,.45)); }
.contenu { position: relative; z-index: 1; }
h1 { color: #fff; margin-bottom: 10px; max-width: 24ch; }
.texte { margin: 0; color: #DCEAF4; max-width: 62ch; font-size: 1.08rem; }
.sur-titre.clair { color: #9FDCF5; }
@media (max-width: 600px) { .entete-page { padding: 44px 0; } }
</style>
