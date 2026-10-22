<script setup lang="ts">
const route = useRoute()
const api = useApi()
const config = useRuntimeConfig()
const { data, error } = await useAsyncData(`article-${route.params.slug}`, () => api<any>(`/api/articles/${route.params.slug}`))
if (error.value || !data.value) throw createError({ statusCode: 404, statusMessage: 'Article introuvable', fatal: true })
const a = computed(() => data.value!.article)
const date = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
useSeoMeta({ title: () => a.value.titre, description: () => a.value.resume, ogImage: () => a.value.image, ogType: 'article' })
useHead({ link: [{ rel: 'canonical', href: `${config.public.siteUrl}/blog/${a.value.slug}` }],
  script: [{ type: 'application/ld+json', innerHTML: JSON.stringify({ '@context': 'https://schema.org', '@type': 'BlogPosting', headline: a.value.titre, datePublished: a.value.publie_le, image: a.value.image, publisher: { '@type': 'Organization', name: 'Expernet Campus' } }) }] })
</script>

<template>
  <article class="conteneur page">
    <nav aria-label="Fil d'Ariane" class="ariane"><NuxtLink to="/">Accueil</NuxtLink><Icone nom="chevron" :taille="14" /><NuxtLink to="/blog">Blog</NuxtLink><Icone nom="chevron" :taille="14" /><span aria-current="page">{{ a.titre }}</span></nav>
    <header class="entete">
      <span class="pastille pastille-bleue">{{ data!.categorie_libelle }}</span>
      <h1>{{ a.titre }}</h1>
      <p class="meta"><Icone nom="planning" :taille="16" />{{ date(a.publie_le) }}<Icone nom="minuteur" :taille="16" />{{ a.temps_lecture }} min de lecture</p>
    </header>
    <img :src="a.image" alt="" class="couverture" width="1200" height="848">
    <div class="corps">
      <p class="chapo">{{ a.resume }}</p>
      <!-- Contenu importé depuis WordPress : HTML assaini côté serveur (App\Support\HtmlAssainisseur) -->
      <div v-if="a.contenu" class="contenu" v-html="a.contenu" />
      <div v-else-if="a.url_source" class="carte source">
        <Icone nom="lecture_article" :taille="32" poids="duotone" class="source-icone" />
        <div>
          <p><strong>Article en cours de migration</strong></p>
          <p class="aide">Le texte intégral sera importé depuis l'ancien site avec la commande <code>blog:import-wordpress</code>.</p>
        </div>
        <a :href="a.url_source" class="btn btn-secondaire" target="_blank" rel="noopener">Lire l'article original <Icone nom="externe" :taille="16" /></a>
      </div>
    </div>
    <section v-if="data!.suggestions.length" class="suggestions" aria-labelledby="t-sugg">
      <h2 id="t-sugg">À lire aussi</h2>
      <div class="grille grille-3">
        <NuxtLink v-for="s in data!.suggestions" :key="s.slug" :to="`/blog/${s.slug}`" class="sugg">
          <img :src="s.image" alt="" loading="lazy"><span>{{ s.titre }}</span>
        </NuxtLink>
      </div>
    </section>
  </article>
</template>

<style scoped>
.page { padding-top: var(--esp-4); max-width: 900px; }
.ariane { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: .88rem; color: var(--texte-doux); margin-bottom: var(--esp-4); }
.entete h1 { margin: 12px 0; font-size: clamp(1.7rem, 4vw, 2.6rem); }
.meta { display: flex; align-items: center; gap: 6px; color: var(--texte-doux); font-size: .9rem; flex-wrap: wrap; }
.meta :deep(.icone:not(:first-child)) { margin-left: 10px; }
.couverture { width: 100%; max-height: 460px; object-fit: cover; border-radius: var(--rayon); margin: var(--esp-4) 0; box-shadow: var(--ombre); }
.chapo { font-size: 1.2rem; color: var(--encre); font-weight: 500; }
.contenu :deep(h2) { font-size: 1.4rem; margin-top: 32px; }
.contenu :deep(p), .contenu :deep(li) { font-size: 1.05rem; }
.source { display: grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items: center; margin-top: var(--esp-4); }
.source p { margin: 0; }
.source-icone { color: var(--primaire); }
.suggestions { margin-top: var(--esp-6); }
.suggestions .grille { grid-template-columns: repeat(auto-fill, minmax(min(100%, 220px), 1fr)); }
.sugg { display: grid; gap: 10px; text-decoration: none; color: var(--encre); font-weight: 700; font-family: var(--police-titre); }
.sugg img { aspect-ratio: 16 / 10; object-fit: cover; border-radius: var(--rayon-s); width: 100%; }
@media (max-width: 700px) { .source { grid-template-columns: 1fr; } }
</style>
