<!-- Bandeau d'accueil illustré des tableaux de bord (photo du campus + message). -->
<script setup lang="ts">
defineProps<{ titre: string; texte?: string; image?: string; ton?: 'bleu' | 'banane' }>()
</script>
<template>
  <section class="banniere" :class="ton || 'bleu'">
    <img :src="image || '/images/campus/hero-informatique.webp'" alt="" width="1920" height="547">
    <div class="contenu">
      <h1>{{ titre }}</h1>
      <p v-if="texte">{{ texte }}</p>
      <slot />
    </div>
    <img src="/images/brand/motif-carres.svg" alt="" class="motif" width="95" height="87">
  </section>
</template>
<style scoped>
.banniere { position: relative; overflow: hidden; border-radius: 18px; color: #fff; margin-bottom: 24px; min-height: 150px; display: flex; align-items: center; background: var(--marine); }
.banniere > img:first-child { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.banniere::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.95) 0%, rgba(13,90,138,.85) 50%, rgba(0,155,221,.35) 100%); }
.banniere.banane::after { background: linear-gradient(100deg, rgba(8,32,52,.95) 0%, rgba(13,90,138,.82) 55%, rgba(255,195,41,.45) 100%); }
.contenu { position: relative; z-index: 1; padding: 28px 32px; }
h1 { color: #fff; font-size: clamp(1.5rem, 3vw, 2.1rem); margin-bottom: 6px; }
p { margin: 0; color: #DCEAF4; }
.motif { position: absolute; z-index: 1; right: 28px; top: 50%; transform: translateY(-50%); width: 84px; opacity: .9; }
@media (max-width: 700px) { .motif { display: none; } .contenu { padding: 22px 20px; } }
</style>
