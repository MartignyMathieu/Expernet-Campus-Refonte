<!--
  Octobre rose : rappel discret dans la barre haute du site, affiché uniquement en octobre
  (dépistage du cancer du sein). Hors octobre, le slogan habituel du campus reprend sa place.
-->
<script setup lang="ts">
const enOctobre = new Date().getMonth() === 9
</script>

<template>
  <p v-if="enOctobre" class="octobre-rose">
    <svg class="ruban" viewBox="0 0 64 64" aria-hidden="true">
      <path d="M37 30 18 58M27 30l19 28" />
      <path d="M32 34c-6-6-9.5-12-9.5-17a9.5 9.5 0 0 1 19 0c0 5-3.5 11-9.5 17Z" />
    </svg>
    <span><strong>Octobre rose</strong> · dépistage du cancer du sein tous les 2 ans de 50 à 74 ans, pris en charge à 100 %</span>
    <a href="https://www.service-public.gouv.fr/particuliers/actualites/A15216" target="_blank" rel="noopener">S'informer<span class="sr-only"> sur le dépistage (nouvel onglet)</span></a>
  </p>
  <slot v-else />
</template>

<style scoped>
.octobre-rose { display: flex; align-items: center; gap: 8px; margin: 0; min-width: 0; }
.ruban { width: 18px; height: 18px; flex-shrink: 0; fill: none; stroke: #FF7EB3; stroke-width: 7; stroke-linecap: round; stroke-linejoin: round; }
strong { color: #FFB3D1; }
span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
a { color: #FFB3D1; font-weight: 700; white-space: nowrap; text-decoration: underline; text-underline-offset: 3px; }
a:hover { color: #fff; }
</style>
