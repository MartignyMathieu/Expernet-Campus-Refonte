<script setup lang="ts">
const api = useApi()
const route = useRoute()
const router = useRouter()
const categorie = ref((route.query.categorie as string) || '')
const { data } = await useAsyncData('blog', () => api<any>('/api/articles', { query: categorie.value ? { categorie: categorie.value } : {} }), { watch: [categorie] })
watch(categorie, (c) => router.replace({ query: c ? { categorie: c } : {} }))
const date = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
const une = computed(() => (categorie.value ? null : data.value?.data[0]))
const reste = computed(() => (une.value ? data.value?.data.slice(1) : data.value?.data) || [])
useSeoMeta({ title: 'Blog', description: 'Conseils pour trouver une alternance, vie du campus, événements et métiers du numérique à La Réunion.' })
</script>

<template>
  <div>
    <EntetePage titre="Le blog du campus" sur-titre="Actualités & conseils" texte="Orientation, alternance, métiers du numérique et vie du campus." image="/images/campus/le-numerique-se-forme.webp" />
    <div class="conteneur page">
      <div class="filtres" role="group" aria-label="Filtrer par catégorie">
        <button :class="{ actif: !categorie }" :aria-pressed="!categorie" @click="categorie = ''">Tous les articles</button>
        <button v-for="(lib, cle) in data?.categories" :key="cle" :class="{ actif: categorie === cle }" :aria-pressed="categorie === cle" @click="categorie = cle as string">{{ lib }}</button>
      </div>

      <NuxtLink v-if="une" :to="`/blog/${une.slug}`" class="une">
        <img :src="une.image" alt="" width="1200" height="848">
        <div class="une-texte">
          <span class="pastille pastille-orange">À la une · {{ data.categories[une.categorie] }}</span>
          <h2>{{ une.titre }}</h2>
          <p>{{ une.resume }}</p>
          <p class="meta"><Icone nom="planning" :taille="16" />{{ date(une.publie_le) }}<Icone nom="minuteur" :taille="16" />{{ une.temps_lecture }} min de lecture</p>
        </div>
      </NuxtLink>

      <div class="grille grille-3">
        <article v-for="a in reste" :key="a.slug" class="article">
          <div class="visuel"><img :src="a.image" alt="" loading="lazy" width="1200" height="848"></div>
          <div class="corps">
            <span class="pastille pastille-bleue">{{ data.categories[a.categorie] }}</span>
            <h3><NuxtLink :to="`/blog/${a.slug}`" class="lien-etire">{{ a.titre }}</NuxtLink></h3>
            <p>{{ a.resume }}</p>
            <p class="meta"><Icone nom="planning" :taille="15" />{{ date(a.publie_le) }}<Icone nom="minuteur" :taille="15" />{{ a.temps_lecture }} min</p>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding-top: var(--esp-5); }
.filtres { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: var(--esp-4); }
.filtres button { font: inherit; font-weight: 500; padding: 8px 16px; border-radius: 999px; border: 1.5px solid var(--gris-bord); background: #fff; cursor: pointer; color: var(--texte); }
.filtres button.actif { background: var(--primaire); border-color: var(--primaire); color: #fff; }
.une { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); overflow: hidden; box-shadow: var(--ombre); text-decoration: none; color: inherit; margin-bottom: var(--esp-5); transition: box-shadow .2s; }
.une:hover { box-shadow: var(--ombre-forte); }
.une img { width: 100%; height: 100%; object-fit: cover; aspect-ratio: 16 / 10; }
.une-texte { padding: 32px; display: flex; flex-direction: column; gap: 12px; justify-content: center; }
.une-texte h2 { margin: 0; }
.une-texte p { margin: 0; color: var(--texte-doux); }
.article { position: relative; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); overflow: hidden; box-shadow: var(--ombre); display: flex; flex-direction: column; transition: transform .2s, box-shadow .2s; }
.article:hover { transform: translateY(-4px); box-shadow: var(--ombre-forte); }
.article:focus-within { outline: 3px solid var(--orange); outline-offset: 2px; }
.visuel { aspect-ratio: 16 / 10; overflow: hidden; background: var(--gris-fond); }
.visuel img { width: 100%; height: 100%; object-fit: cover; }
.corps { padding: 18px 20px; display: flex; flex-direction: column; gap: 8px; flex: 1; align-items: flex-start; }
.corps h3 { margin: 0; font-size: 1.05rem; }
.corps p { margin: 0; color: var(--texte-doux); font-size: .92rem; }
.lien-etire { color: var(--encre); text-decoration: none; }
.lien-etire::after { content: ''; position: absolute; inset: 0; }
.meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: .84rem; color: var(--texte-doux); margin-top: auto !important; }
.meta :deep(.icone:not(:first-child)) { margin-left: 8px; }
@media (max-width: 860px) { .une { grid-template-columns: 1fr; } .une-texte { padding: 22px; } }
</style>
