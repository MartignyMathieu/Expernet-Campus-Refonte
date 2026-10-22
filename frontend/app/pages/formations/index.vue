<script setup lang="ts">
const route = useRoute()
const router = useRouter()
const api = useApi()

const filtres = reactive({
  q: (route.query.q as string) || '',
  filiere: (route.query.filiere as string) || '',
  niveau: (route.query.niveau as string) || '',
  cpf: route.query.cpf === '1',
})

const requete = computed(() => {
  const p: Record<string, string> = {}
  if (filtres.q) p.q = filtres.q
  if (filtres.filiere) p.filiere = filtres.filiere
  if (filtres.niveau) p.niveau = filtres.niveau
  if (filtres.cpf) p.cpf = '1'
  return p
})

const { data: facettes } = await useAsyncData('facettes', () => api<any>('/api/formations/facettes'))
const { data, pending } = await useAsyncData('catalogue', () => api<{ data: any[] }>('/api/formations', { query: requete.value }), { watch: [requete] })

// L'URL reflète les filtres : résultats partageables et indexables.
watch(requete, (q) => router.replace({ query: q }))

useSeoMeta({
  title: 'Catalogue des formations en alternance',
  description: 'Filtrez les formations Expernet par filière, niveau RNCP et éligibilité CPF : informatique, cybersécurité, développement, marketing digital.',
})

function reinitialiser() { Object.assign(filtres, { q: '', filiere: '', niveau: '', cpf: false }) }
</script>

<template>
  <div>
  <section class="entete-page">
    <img src="/images/campus/filiere-marketing.webp" alt="" width="1440" height="441">
    <div class="conteneur">
      <p class="sur-titre clair">Catalogue 2026-2027</p>
      <h1>Nos formations diplômantes</h1>
      <p>Informatique et marketing digital, du BAC+2 au BAC+5, en alternance.</p>
    </div>
  </section>
  <div class="conteneur page">
    <div class="disposition">
      <aside class="filtres carte" aria-labelledby="titre-filtres">
        <h2 id="titre-filtres" class="h3">Affiner</h2>
        <div class="champ">
          <label for="q">Mot-clé</label>
          <input id="q" v-model.lazy="filtres.q" type="search" placeholder="ex. réseau">
        </div>
        <fieldset>
          <legend>Filière</legend>
          <label class="case"><input v-model="filtres.filiere" type="radio" value=""> Toutes</label>
          <label v-for="f in facettes?.filieres" :key="f.code" class="case"><input v-model="filtres.filiere" type="radio" :value="f.code"> {{ f.libelle }}</label>
        </fieldset>
        <fieldset>
          <legend>Niveau RNCP</legend>
          <label class="case"><input v-model="filtres.niveau" type="radio" value=""> Tous</label>
          <label v-for="n in facettes?.niveaux" :key="n.valeur" class="case"><input v-model="filtres.niveau" type="radio" :value="String(n.valeur)"> {{ n.libelle }}</label>
        </fieldset>
        <fieldset>
          <legend>Financement</legend>
          <label class="case"><input v-model="filtres.cpf" type="checkbox"> Éligible au CPF</label>
        </fieldset>
        <button class="btn btn-secondaire" type="button" @click="reinitialiser">Réinitialiser</button>
      </aside>
      <section aria-labelledby="titre-resultats">
        <h2 id="titre-resultats" class="compteur" aria-live="polite">
          {{ pending ? 'Recherche…' : `${data?.data.length || 0} formation${(data?.data.length || 0) > 1 ? 's' : ''}` }}
        </h2>
        <div v-if="data?.data.length" class="grille grille-3">
          <CarteFormation v-for="f in data.data" :key="f.slug" :f="f" />
        </div>
        <p v-else-if="!pending" class="vide">Aucune formation ne correspond à ces critères. <button class="lien" @click="reinitialiser">Effacer les filtres</button></p>
      </section>
    </div>
  </div>
  </div>
</template>

<style scoped>
.entete-page { position: relative; color: #fff; padding: 56px 0 60px; overflow: hidden; background: var(--marine); }
.entete-page img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.entete-page::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.95), rgba(13,90,138,.8) 60%, rgba(0,155,221,.45)); }
.entete-page .conteneur { position: relative; z-index: 1; }
.entete-page h1 { color: #fff; margin-bottom: 8px; }
.entete-page p { margin: 0; color: #DCEAF4; }
.sur-titre.clair { color: #9FDCF5; }
.page { padding-top: var(--esp-5); }
.disposition { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: var(--esp-4); align-items: start; }
.filtres { position: sticky; top: 90px; }
.h3 { font-size: 1.15rem; }
fieldset { border: 0; padding: 0; margin: 0 0 var(--esp-3); display: grid; gap: 6px; }
legend { font-weight: 500; color: var(--encre); margin-bottom: 6px; }
.compteur { font-size: 1rem; color: var(--texte-doux); font-weight: 500; }
.lien { background: none; border: 0; color: var(--primaire-texte); text-decoration: underline; cursor: pointer; font: inherit; }
@media (max-width: 860px) { .disposition { grid-template-columns: minmax(0, 1fr); } .filtres { position: static; } }
</style>
