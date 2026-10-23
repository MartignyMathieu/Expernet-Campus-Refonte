<script setup lang="ts">
// Actualités de l'organisme de formation continue, reprises de expernet.re (lien vers l'article d'origine).
const api = useApi()
const { data } = await useAsyncData('fc-actualites', () => api<any[]>('/api/formation-continue/actualites'))
useSeoMeta({
  title: 'Actualités - formation continue',
  description: 'Les actualités d\'Expernet, organisme de formation continue à La Réunion et à Mayotte : cybersécurité, intelligence artificielle, bureautique, vie de l\'organisme.',
})
const PAS = 9
const affiches = ref(PAS)
const visibles = computed(() => (data.value || []).slice(0, affiches.value))
</script>

<template>
  <div v-if="data">
    <EntetePage titre="Actualités" sur-titre="Formation continue · Expernet" texte="Cybersécurité, intelligence artificielle, bureautique et vie de l'organisme : les dernières nouvelles d'Expernet." image="/images/campus/hero-blog.webp" />
    <NavFormationContinue />

    <section class="conteneur bloc" aria-label="Articles">
      <ul class="grille grille-3 articles">
        <li v-for="(a, i) in visibles" :key="a.slug" v-apparition="i % 3" class="carte article">
          <span class="pastille" :class="a.categorie === 'Évènements' ? 'pastille-orange' : 'pastille-bleue'">{{ a.categorie }}</span>
          <h2><a :href="a.url_source" target="_blank" rel="noopener">{{ a.titre }}</a></h2>
          <p>{{ a.extrait }}</p>
          <a :href="a.url_source" target="_blank" rel="noopener" class="lire" :aria-label="`Lire l'article ${a.titre} sur expernet.re`">Lire l'article <Icone nom="externe" :taille="16" /></a>
        </li>
      </ul>
      <div v-if="affiches < data.length" class="plus">
        <button class="btn btn-secondaire" @click="affiches += PAS">Afficher plus d'actualités</button>
        <p class="aide">{{ visibles.length }} sur {{ data.length }}</p>
      </div>
      <p class="source">Articles publiés sur <a href="https://expernet.re/nos-actualites/" target="_blank" rel="noopener">expernet.re</a> : chaque carte ouvre l'article d'origine.</p>
    </section>
  </div>
</template>

<style scoped>
.bloc { margin-top: var(--esp-5); }
.articles { list-style: none; padding: 0; margin: 0; }
.article { display: flex; flex-direction: column; gap: 10px; transition: transform .15s, box-shadow .15s; }
.article:hover { transform: translateY(-3px); box-shadow: var(--ombre-forte); }
.article .pastille { align-self: flex-start; }
.article h2 { font-size: 1.1rem; margin: 0; line-height: 1.35; }
.article h2 a { color: var(--encre); text-decoration: none; }
.article h2 a:hover { color: var(--primaire-texte); text-decoration: underline; }
.article p { margin: 0; color: var(--texte); flex: 1; }
.lire { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.plus { display: grid; justify-items: center; gap: 6px; margin-top: var(--esp-4); }
.source { margin-top: var(--esp-4); color: var(--texte-doux); font-size: .9rem; text-align: center; }
</style>
