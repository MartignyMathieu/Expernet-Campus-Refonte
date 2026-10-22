<script setup lang="ts">
const props = defineProps<{ taux: number | null; seuil: number }>()
const couleur = computed(() => props.taux === null ? '#C9CED4' : props.taux < props.seuil ? 'var(--alerte)' : props.taux < 90 ? 'var(--jaune)' : 'var(--succes)')
</script>
<template>
  <div class="jauge" role="meter" :aria-valuenow="taux ?? 0" aria-valuemin="0" aria-valuemax="100" :aria-label="`Taux d'assiduité ${taux ?? 'non calculé'} %`">
    <div class="barre"><div class="rempli" :style="{ width: (taux ?? 0) + '%', background: couleur }" /><div class="seuil" :style="{ left: seuil + '%' }" /></div>
    <span class="valeur">{{ taux === null ? '—' : taux + ' %' }}</span>
  </div>
</template>
<style scoped>
.jauge { display: flex; align-items: center; gap: 10px; }
.barre { position: relative; flex: 1; height: 10px; background: var(--gris-fond); border-radius: 99px; overflow: visible; min-width: 80px; }
.rempli { height: 100%; border-radius: 99px; }
.seuil { position: absolute; top: -3px; bottom: -3px; width: 2px; background: var(--encre); opacity: .4; }
.valeur { font-weight: 700; min-width: 56px; text-align: right; }
</style>
