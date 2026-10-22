<!--
  Chiffre clé animé de 0 à sa valeur lorsqu'il devient visible.
  Le rendu serveur affiche directement la valeur finale (référencement, lecteurs d'écran).
-->
<script setup lang="ts">
const props = withDefaults(defineProps<{ valeur: number; suffixe?: string; duree?: number }>(), { suffixe: '', duree: 1400 })
const affiche = ref(props.valeur)
const el = ref<HTMLElement | null>(null)
const texte = computed(() => Math.round(affiche.value).toLocaleString('fr-FR') + props.suffixe)

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window) || !el.value) return
  const obs = new IntersectionObserver(([e]) => {
    if (!e?.isIntersecting) return
    obs.disconnect()
    const debut = performance.now()
    const etape = (t: number) => {
      const p = Math.min(1, (t - debut) / props.duree)
      affiche.value = props.valeur * (1 - Math.pow(1 - p, 3)) // décélération douce
      if (p < 1) requestAnimationFrame(etape)
    }
    affiche.value = 0
    requestAnimationFrame(etape)
  }, { threshold: .6 })
  obs.observe(el.value)
})
</script>

<template>
  <span ref="el" class="chiffre" :aria-label="valeur.toLocaleString('fr-FR') + suffixe"><span aria-hidden="true">{{ texte }}</span></span>
</template>
