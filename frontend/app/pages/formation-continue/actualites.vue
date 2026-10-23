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
const vignettes: Record<string, { icone: string; classe: string }> = {
  cybersecurite: { icone: 'shield', classe: 'cyber' }, ia: { icone: 'brain', classe: 'ia' },
  bureautique: { icone: 'table', classe: 'bureautique' }, ecole: { icone: 'diplome', classe: 'ecole' }, organisme: { icone: 'megaphone', classe: 'organisme' },
}
const dateFr = (d?: string | null) => d ? new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : ''
</script>

<template>
  <div v-if="data">
    <EntetePage titre="Actualités" sur-titre="Formation continue · Expernet" texte="Cybersécurité, intelligence artificielle, bureautique et vie de l'organisme : les dernières nouvelles d'Expernet." image="/images/campus/hero-blog.webp" />
    <NavFormationContinue />

    <section class="conteneur bloc" aria-label="Articles">
      <ul class="grille grille-3 articles">
        <li v-for="(a, i) in visibles" :key="a.slug" v-apparition="i % 3" class="carte article">
          <a :href="a.url_source" target="_blank" rel="noopener" class="illustration" tabindex="-1" aria-hidden="true">
            <img v-if="a.image" :src="a.image" alt="" width="640" height="360" loading="lazy">
            <span v-else class="repli" :class="(vignettes[a.theme] || vignettes.organisme).classe"><Icone :nom="(vignettes[a.theme] || vignettes.organisme).icone" :taille="44" poids="duotone" /></span>
          </a>
          <p class="meta-article"><span class="pastille" :class="a.categorie === 'Évènements' ? 'pastille-orange' : 'pastille-bleue'">{{ a.categorie }}</span><time v-if="a.publie_le" :datetime="a.publie_le">{{ dateFr(a.publie_le) }}</time></p>
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
.article { padding-top: 0; overflow: hidden; }
.illustration { display: block; margin: 0 calc(-1 * var(--esp-4)) 4px; aspect-ratio: 16 / 9; overflow: hidden; background: var(--gris-fond); }
.illustration img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
.article:hover .illustration img { transform: scale(1.04); }
.repli { width: 100%; height: 100%; display: grid; place-items: center; color: #fff; }
.repli.cyber { background: linear-gradient(135deg, #0D5A8A, #14202B); }
.repli.ia { background: linear-gradient(135deg, #6C3FC5, #0D5A8A); }
.repli.bureautique { background: linear-gradient(135deg, #1E7A43, #0D5A8A); }
.repli.ecole { background: linear-gradient(135deg, #ED8F00, #C25E00); }
.repli.organisme { background: linear-gradient(135deg, #009BDD, #0D5A8A); }
.meta-article { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0; color: var(--texte-doux); font-size: .88rem; }
.article h2 { font-size: 1.1rem; margin: 0; line-height: 1.35; }
.article h2 a { color: var(--encre); text-decoration: none; }
.article h2 a:hover { color: var(--primaire-texte); text-decoration: underline; }
.article p { margin: 0; color: var(--texte); flex: 1; }
.lire { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.plus { display: grid; justify-items: center; gap: 6px; margin-top: var(--esp-4); }
.source { margin-top: var(--esp-4); color: var(--texte-doux); font-size: .9rem; text-align: center; }
</style>
