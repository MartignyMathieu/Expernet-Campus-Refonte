<!-- Carrousel de photos du campus. RGAA 13.8 : bouton pause, arrêt au survol/focus, pas d'animation si mouvement réduit. -->
<script setup lang="ts">
const diapos = [
  { src: '/images/campus/hero-informatique.webp', alt: 'Apprenants en cours dans une salle informatique du campus Expernet' },
  { src: '/images/campus/hero-marketing.webp', alt: 'Promotion d\'apprenants Expernet réunie devant le campus' },
  { src: '/images/campus/campus-expernet.webp', alt: 'Les apprenants Expernet lors d\'une sortie sur la plage' },
]
const actif = ref(0)
const enPause = ref(false)
const survol = ref(false)
let minuteur: any
onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) enPause.value = true
  minuteur = setInterval(() => { if (!enPause.value && !survol.value) actif.value = (actif.value + 1) % diapos.length }, 6000)
})
onBeforeUnmount(() => clearInterval(minuteur))
</script>

<template>
  <div class="carrousel" @mouseenter="survol = true" @mouseleave="survol = false" @focusin="survol = true" @focusout="survol = false">
    <img v-for="(d, i) in diapos" :key="d.src" :src="d.src" :alt="i === actif ? d.alt : ''" :aria-hidden="i !== actif"
         :class="{ visible: i === actif }" :fetchpriority="i === 0 ? 'high' : 'low'" width="1920" height="547">
    <div class="voile" />
    <div class="controles">
      <button v-for="(d, i) in diapos" :key="i" class="point" :class="{ actif: i === actif }" :aria-label="`Photo ${i + 1} sur ${diapos.length}`" :aria-current="i === actif" @click="actif = i" />
      <button class="pause" :aria-label="enPause ? 'Relancer le défilement des photos' : 'Mettre en pause le défilement des photos'" @click="enPause = !enPause"><Icone :nom="enPause ? 'lecture' : 'pause'" :taille="14" poids="fill" /></button>
    </div>
  </div>
</template>

<style scoped>
.carrousel { position: absolute; inset: 0; overflow: hidden; background: var(--marine); }
img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.04); transition: opacity 1.2s ease, transform 7s ease; }
img.visible { opacity: 1; transform: scale(1); }
.voile { position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.92) 0%, rgba(8,32,52,.75) 38%, rgba(0,155,221,.25) 75%, rgba(0,0,0,.1) 100%); }
.controles { position: absolute; right: 24px; bottom: 24px; display: flex; gap: 8px; align-items: center; z-index: 2; }
.point { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; background: transparent; cursor: pointer; padding: 0; }
.point.actif { background: var(--orange); border-color: var(--orange); }
.pause { margin-left: 6px; width: 34px; height: 34px; border-radius: 50%; border: 0; background: rgba(255,255,255,.18); color: #fff; cursor: pointer; display: grid; place-items: center; }
.pause:hover { background: rgba(255,255,255,.3); }
@media (max-width: 760px) { .voile { background: linear-gradient(180deg, rgba(8,32,52,.85), rgba(8,32,52,.92)); } .controles { right: 50%; transform: translateX(50%); bottom: 14px; } }
</style>
