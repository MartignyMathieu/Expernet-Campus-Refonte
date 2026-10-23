<!-- Sous-navigation des pages Formation continue (catalogue, planning, actualités, à propos). -->
<script setup lang="ts">
const route = useRoute()
const liens = [
  { to: '/formation-continue', libelle: 'Catalogue', icone: 'programme' },
  { to: '/formation-continue/planning', libelle: 'Planning', icone: 'planning' },
  { to: '/formation-continue/actualites', libelle: 'Actualités', icone: 'blog' },
  { to: '/formation-continue/a-propos', libelle: 'À propos', icone: 'info' },
]
const actif = (to: string) => route.path === to
</script>

<template>
  <nav class="nav-fc" aria-label="Formation continue">
    <div class="conteneur">
      <ul>
        <li v-for="l in liens" :key="l.to">
          <NuxtLink :to="l.to" :class="{ actif: actif(l.to) }" :aria-current="actif(l.to) ? 'page' : undefined"><Icone :nom="l.icone" :taille="18" :poids="actif(l.to) ? 'fill' : 'regular'" />{{ l.libelle }}</NuxtLink>
        </li>
      </ul>
    </div>
  </nav>
</template>

<style scoped>
.nav-fc { background: #fff; border-bottom: 1px solid var(--gris-bord); position: relative; z-index: 3; }
ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; }
a { display: inline-flex; align-items: center; gap: 8px; padding: 14px 16px; color: var(--texte); text-decoration: none; font-weight: 600; white-space: nowrap; border-bottom: 3px solid transparent; transition: color .15s, border-color .15s; }
a:hover { color: var(--primaire-texte); }
a.actif { color: var(--primaire-fonce); border-bottom-color: var(--primaire); }
</style>
