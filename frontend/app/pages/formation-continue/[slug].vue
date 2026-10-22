<script setup lang="ts">
// Fiche d'un stage de formation continue (contenu repris de expernet.re).
const route = useRoute()
const api = useApi()
const { data, error } = await useAsyncData(`fc-${route.params.slug}`, () => api<any>(`/api/formation-continue/${route.params.slug}`))
if (error.value || !data.value) throw createError({ statusCode: 404, statusMessage: 'Formation introuvable', fatal: true })

const s = computed(() => data.value!.stage)
useSeoMeta({
  title: () => `${s.value.intitule} — formation continue`,
  description: () => `${s.value.intitule} (${s.value.duree}) : ${(s.value.objectifs[0] || 'formation professionnelle Expernet')}. À La Réunion et à Mayotte.`,
})

// Le programme arrive en lignes ; « ## » ouvre un module, les lignes suivantes en sont les points.
const modules = computed(() => {
  const m: { titre: string; points: string[] }[] = []
  for (const l of s.value.programme as string[]) {
    if (l.startsWith('## ')) m.push({ titre: l.slice(3), points: [] })
    else (m.at(-1) || m[m.push({ titre: 'Contenu', points: [] }) - 1]).points.push(l)
  }
  return m
})
const ouverts = ref<Set<number>>(new Set([0]))
const toutOuvert = computed(() => ouverts.value.size === modules.value.length)
function basculer(i: number) { const o = new Set(ouverts.value); o.has(i) ? o.delete(i) : o.add(i); ouverts.value = o }
function toutBasculer() { ouverts.value = toutOuvert.value ? new Set() : new Set(modules.value.map((_, i) => i)) }

// Une ligne commençant par « ## » dans une modalité est un intertitre.
const intertitre = (l: string) => l.startsWith('## ')
const devis = computed(() => `/contact?sujet=${encodeURIComponent(`Devis — ${s.value.intitule}`.slice(0, 150))}`)
</script>

<template>
  <div v-if="data">
    <section class="bandeau">
      <img src="/images/campus/filiere-informatique.webp" alt="" width="1920" height="547">
      <div class="conteneur bandeau-contenu">
        <nav class="ariane" aria-label="Fil d'Ariane"><NuxtLink to="/">Accueil</NuxtLink> / <NuxtLink to="/formation-continue">Formation continue</NuxtLink> / <NuxtLink :to="`/formation-continue?domaine=${s.domaine.slug}#catalogue`">{{ s.domaine.libelle.split(' — ')[0] }}</NuxtLink></nav>
        <p class="pastilles">
          <span class="pastille niveau"><Icone :nom="s.domaine.icone" :taille="14" />{{ s.domaine.libelle.split(' — ')[0] }}</span>
          <span v-if="s.sous_theme" class="pastille">{{ s.sous_theme }}</span>
          <span class="pastille"><Icone nom="horloge" :taille="14" />{{ s.duree }}</span>
          <span v-if="sessionAVenir(s.prochaine_session)" class="pastille pastille-jaune">Session le {{ dateCourte(s.prochaine_session) }}</span>
        </p>
        <h1>{{ s.intitule }}</h1>
        <p v-if="s.public.length" class="public"><strong>Public :</strong> {{ s.public.join(' · ') }}</p>
      </div>
    </section>

    <div class="conteneur disposition">
      <div class="principal">
        <section v-if="s.objectifs.length" class="section" aria-labelledby="t-obj">
          <h2 id="t-obj"><Icone nom="objectif" :taille="22" />Objectifs</h2>
          <ul class="liste-cochee"><li v-for="o in s.objectifs" :key="o">{{ o }}</li></ul>
        </section>

        <section v-if="s.prerequis.length" class="section" aria-labelledby="t-pre">
          <h2 id="t-pre"><Icone nom="prerequis" :taille="22" />Prérequis</h2>
          <ul class="liste-simple"><li v-for="p in s.prerequis" :key="p">{{ p }}</li></ul>
        </section>

        <section v-if="modules.length" class="section" aria-labelledby="t-prog">
          <div class="titre-prog">
            <h2 id="t-prog"><Icone nom="programme" :taille="22" />Programme</h2>
            <button v-if="modules.length > 1" class="lien" @click="toutBasculer">{{ toutOuvert ? 'Tout replier' : 'Tout déplier' }}</button>
          </div>
          <ol class="modules">
            <li v-for="(m, i) in modules" :key="i" class="module" :class="{ ouvert: ouverts.has(i) }">
              <h3><button :aria-expanded="ouverts.has(i)" :aria-controls="`mod-${i}`" @click="basculer(i)">
                <span class="num">{{ String(i + 1).padStart(2, '0') }}</span><span class="module-titre">{{ m.titre }}</span><Icone nom="deplier" :taille="18" class="chevron" />
              </button></h3>
              <div :id="`mod-${i}`" class="module-corps" :class="{ visible: ouverts.has(i) }">
                <div><ul v-if="m.points.length"><li v-for="(p, j) in m.points" :key="j">{{ p }}</li></ul></div>
              </div>
            </li>
          </ol>
        </section>

        <div class="modalites">
          <section v-for="[cle, titre] in [['methodes', 'Méthodes pédagogiques'], ['moyens', 'Moyens techniques et humains'], ['evaluation', 'Modalités d\'évaluation']]" :key="cle" class="carte modalite" :aria-label="titre">
            <h2>{{ titre }}</h2>
            <template v-for="(l, i) in s[cle]" :key="i">
              <p v-if="intertitre(l)" class="intertitre">{{ l.slice(3) }}</p>
              <p v-else>{{ l }}</p>
            </template>
          </section>
        </div>
      </div>

      <aside class="infos">
        <div class="carte resume">
          <dl>
            <div><dt><Icone nom="horloge" :taille="18" />Durée</dt><dd>{{ s.duree }}</dd></div>
            <div v-for="t in lignesTarifs(s.tarifs)" :key="t.libelle"><dt><Icone nom="euro" :taille="18" />{{ t.libelle }}</dt><dd>{{ t.valeur }} <small v-if="t.valeur.endsWith('€')">HT</small></dd></div>
            <div v-if="s.delai_acces"><dt><Icone nom="minuteur" :taille="18" />Délai d'accès moyen</dt><dd>{{ s.delai_acces }}</dd></div>
            <div><dt><Icone nom="session" :taille="18" />Prochaine session</dt><dd>{{ sessionAVenir(s.prochaine_session) ? dateLongue(s.prochaine_session) : 'Sur demande' }}</dd></div>
          </dl>
          <NuxtLink :to="devis" class="btn btn-primaire plein">Demander un devis</NuxtLink>
          <a v-if="s.programme_pdf" :href="s.programme_pdf" class="btn btn-secondaire plein" target="_blank" rel="noopener"><Icone nom="pdf" :taille="18" />Programme (PDF)</a>
          <p class="contact-fc">Une question ? <a :href="`mailto:${data.contact.email}`">{{ data.contact.email }}</a> · <a href="tel:+262262219045">{{ data.contact.telephone }}</a></p>
        </div>
        <p class="source"><a :href="s.url_source" target="_blank" rel="noopener">Voir la fiche sur expernet.re <Icone nom="externe" :taille="14" /></a></p>
      </aside>
    </div>

    <section v-if="data.similaires.length" class="conteneur similaires" aria-labelledby="t-sim">
      <h2 id="t-sim">Dans le même domaine</h2>
      <ul>
        <li v-for="x in data.similaires" :key="x.slug">
          <NuxtLink :to="`/formation-continue/${x.slug}`" class="similaire">
            <strong>{{ x.intitule }}</strong>
            <span>{{ x.duree }} · {{ resumeTarif(x.tarifs) }}</span>
          </NuxtLink>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.bandeau { position: relative; color: #fff; overflow: hidden; background: var(--marine); }
.bandeau > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.bandeau::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.95) 0%, rgba(8,32,52,.82) 50%, rgba(13,90,138,.55) 100%); }
.bandeau-contenu { position: relative; z-index: 1; padding: 32px 0 52px; }
.ariane { font-size: .88rem; color: #CFE3F1; margin-bottom: 24px; }
.ariane a { color: #fff; }
.pastilles { display: flex; gap: 6px; flex-wrap: wrap; margin: 0 0 14px; }
.pastilles .pastille { background: rgba(255,255,255,.16); color: #fff; }
.pastilles .niveau { background: var(--primaire); }
.pastilles .pastille-jaune { background: var(--jaune); color: var(--encre); }
.bandeau h1 { color: #fff; font-size: clamp(1.8rem, 4vw, 2.8rem); max-width: 26ch; }
.public { margin: 0; color: #DCEAF4; max-width: 75ch; }
.public strong { color: #fff; }

.disposition { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: var(--esp-5); align-items: start; padding-top: var(--esp-5); }
.section { margin-bottom: var(--esp-5); }
.section h2 { font-size: 1.35rem; display: flex; align-items: center; gap: 10px; }
.section h2 :deep(.icone) { color: var(--primaire); }
.liste-cochee, .liste-simple { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.liste-cochee li { position: relative; padding: 12px 16px 12px 44px; background: var(--gris-fond); border-radius: var(--rayon-s); }
.liste-cochee li::before { content: ''; position: absolute; left: 16px; top: 17px; width: 14px; height: 8px; border-left: 2.5px solid var(--succes); border-bottom: 2.5px solid var(--succes); transform: rotate(-45deg); }
.liste-simple li { padding-left: 18px; position: relative; }
.liste-simple li::before { content: ''; position: absolute; left: 0; top: .65em; width: 7px; height: 7px; border-radius: 2px; background: var(--orange); }
.titre-prog { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
.lien { background: none; border: 0; color: var(--primaire-texte); text-decoration: underline; cursor: pointer; font: inherit; padding: 0; }
.modules { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.module { border: 1px solid var(--gris-bord); border-radius: var(--rayon); background: #fff; overflow: hidden; transition: border-color .2s; }
.module.ouvert { border-color: #B9E3F5; }
.module h3 { margin: 0; font-size: 1rem; }
.module h3 button { width: 100%; display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center; padding: 14px 16px; background: none; border: 0; font: inherit; font-weight: 700; color: var(--encre); text-align: left; cursor: pointer; }
.num { font-family: var(--police-titre); color: var(--primaire); font-size: .9rem; }
.chevron { color: var(--primaire); transition: transform .25s; }
.ouvert .chevron { transform: rotate(180deg); }
/* Ouverture animée sans JavaScript : grille 0fr → 1fr */
.module-corps { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s ease; }
.module-corps.visible { grid-template-rows: 1fr; }
.module-corps > div { overflow: hidden; }
.module-corps ul { margin: 0; padding: 0 20px 16px 58px; display: grid; gap: 6px; color: var(--texte); }
.modalites { display: grid; gap: var(--esp-3); }
.modalite h2 { font-size: 1.1rem; margin-bottom: 10px; }
.modalite p { margin: 0 0 6px; color: var(--texte); font-size: .95rem; }
.modalite .intertitre { font-weight: 700; color: var(--encre); margin-top: 10px; }
.infos { position: sticky; top: 100px; display: grid; gap: 10px; }
.resume { display: grid; gap: 12px; }
dl { margin: 0 0 6px; display: grid; gap: 12px; }
dl > div { display: grid; gap: 2px; padding-bottom: 12px; border-bottom: 1px solid var(--gris-bord); }
dt { display: flex; align-items: center; gap: 8px; color: var(--texte-doux); font-size: .85rem; font-weight: 500; }
dt :deep(.icone) { color: var(--primaire); }
dd { margin: 0 0 0 26px; color: var(--encre); font-weight: 700; font-size: 1.05rem; }
dd small { font-weight: 400; color: var(--texte-doux); font-size: .78rem; }
.plein { width: 100%; }
.contact-fc { margin: 4px 0 0; font-size: .86rem; color: var(--texte-doux); text-align: center; }
.source { margin: 0; text-align: center; font-size: .86rem; }
.source a { display: inline-flex; align-items: center; gap: 4px; }
.similaires { margin-top: var(--esp-5); }
.similaires h2 { font-size: 1.35rem; }
.similaires ul { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 12px; }
.similaire { display: grid; gap: 4px; height: 100%; padding: 16px 18px; border: 1px solid var(--gris-bord); border-radius: var(--rayon); color: var(--texte); text-decoration: none; background: #fff; transition: border-color .15s, transform .15s; }
.similaire:hover { border-color: var(--primaire); transform: translateY(-2px); }
.similaire strong { color: var(--encre); }
.similaire span { color: var(--texte-doux); font-size: .88rem; }
@media (max-width: 960px) { .disposition { grid-template-columns: minmax(0, 1fr); } .infos { position: static; order: -1; } }
@media (max-width: 600px) { .module-corps ul { padding-left: 20px; } }
</style>
