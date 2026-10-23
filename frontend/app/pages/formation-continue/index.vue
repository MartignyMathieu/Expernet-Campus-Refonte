<script setup lang="ts">
// Catalogue de formation continue : les 149 stages de expernet.re, filtrables par domaine et par mot-clé.
const api = useApi()
const route = useRoute()
const router = useRouter()
const { data } = await useAsyncData('fc', () => api<any>('/api/formation-continue'))
useSeoMeta({
  title: 'Formation continue',
  description: 'Bureautique, réseaux, cybersécurité, développement, IA : 149 formations courtes Expernet pour les salariés et les entreprises à La Réunion et à Mayotte.',
})

const domaines = computed<any[]>(() => data.value?.domaines || [])
const stages = computed(() => domaines.value.flatMap((d) => d.stages.map((s: any) => ({ ...s, domaine: d }))))
const total = computed(() => stages.value.length)

// Filtres reflétés dans l'URL (résultats partageables)
const filtres = reactive({ q: (route.query.q as string) || '', domaine: (route.query.domaine as string) || '' })
watch(filtres, (f) => router.replace({ query: { ...(f.q ? { q: f.q } : {}), ...(f.domaine ? { domaine: f.domaine } : {}) } }))

const normaliser = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const resultats = computed(() => {
  const q = normaliser(filtres.q.trim())
  return stages.value.filter((s) => (!filtres.domaine || s.domaine.slug === filtres.domaine)
    && (!q || normaliser(`${s.intitule} ${s.sous_theme || ''} ${s.domaine.libelle}`).includes(q)))
})

const PAS = 24
const affiches = ref(PAS)
watch(() => [filtres.q, filtres.domaine], () => (affiches.value = PAS))

const prochaines = computed(() => stages.value.filter((s) => sessionAVenir(s.prochaine_session))
  .sort((a, b) => a.prochaine_session.localeCompare(b.prochaine_session)).slice(0, 6))

function choisirDomaine(slug: string) {
  filtres.domaine = filtres.domaine === slug ? '' : slug
  document.getElementById('catalogue')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <div v-if="data">
    <EntetePage titre="Formation continue" sur-titre="Expernet · organisme de formation depuis 2002" :texte="`Salariés, demandeurs d'emploi, entreprises : ${total} formations courtes en informatique et en marketing digital, à La Réunion et à Mayotte.`" image="/images/campus/filiere-informatique.webp">
      <div class="cta-entete">
        <NuxtLink to="/contact?sujet=Demande de devis - formation continue" class="btn btn-primaire">Demander un devis</NuxtLink>
        <NuxtLink to="/formation-continue/planning" class="btn btn-contour-blanc">Planning des sessions</NuxtLink>
      </div>
    </EntetePage>
    <NavFormationContinue />

    <section class="conteneur chiffres" aria-label="Expernet formation continue en chiffres">
      <div v-for="c in data.chiffres" :key="c.libelle"><Compteur :valeur="c.valeur" :suffixe="c.suffixe || ''" /><span class="libelle-chiffre">{{ c.libelle }}</span></div>
    </section>

    <section class="conteneur bloc" aria-labelledby="t-parcours">
      <p class="sur-titre">Votre parcours de formation</p>
      <h2 id="t-parcours">Trois formats, selon votre besoin</h2>
      <div class="grille grille-3">
        <div v-for="(p, i) in data.formats" :key="p.titre" v-apparition="i" class="carte format">
          <span class="format-icone"><Icone :nom="p.icone" :taille="28" poids="duotone" /></span>
          <h3>{{ p.titre }}</h3>
          <p>{{ p.texte }}</p>
        </div>
      </div>
    </section>

    <section v-if="prochaines.length" class="conteneur bloc" aria-labelledby="t-sessions">
      <p class="sur-titre">Inter-entreprises</p>
      <div class="titre-lien"><h2 id="t-sessions">Prochaines sessions</h2><NuxtLink to="/formation-continue/planning" class="lien-planning">Tout le planning <Icone nom="suivant" :taille="16" /></NuxtLink></div>
      <ul class="sessions">
        <li v-for="(s, i) in prochaines" :key="s.slug" v-apparition="i % 3">
          <NuxtLink :to="`/formation-continue/${s.slug}`" class="session">
            <span class="date"><strong>{{ new Date(s.prochaine_session + 'T00:00:00').getDate() }}</strong>{{ new Date(s.prochaine_session + 'T00:00:00').toLocaleDateString('fr-FR', { month: 'short' }) }}</span>
            <span class="session-texte"><strong>{{ s.intitule }}</strong><small>{{ s.duree }} · {{ resumeTarif(s.tarifs) }}</small></span>
            <Icone nom="chevron" :taille="18" class="fleche" />
          </NuxtLink>
        </li>
      </ul>
    </section>

    <section id="catalogue" class="conteneur bloc" aria-labelledby="t-catalogue">
      <p class="sur-titre">Notre offre</p>
      <h2 id="t-catalogue">{{ total }} formations dans {{ domaines.length }} domaines</h2>

      <div class="outils">
        <label class="recherche"><Icone nom="recherche" :taille="20" /><span class="sr-only">Rechercher une formation</span>
          <input v-model="filtres.q" type="search" placeholder="Excel, Fortinet, WordPress, Python…"></label>
        <p class="compte" role="status">{{ resultats.length }} formation{{ resultats.length > 1 ? 's' : '' }}</p>
      </div>
      <div class="domaines" role="group" aria-label="Filtrer par domaine">
        <button class="puce" :class="{ actif: !filtres.domaine }" :aria-pressed="!filtres.domaine" @click="filtres.domaine = ''">Tous les domaines</button>
        <button v-for="d in domaines" :key="d.slug" class="puce" :class="{ actif: filtres.domaine === d.slug }" :aria-pressed="filtres.domaine === d.slug" @click="choisirDomaine(d.slug)">
          <Icone :nom="d.icone" :taille="16" />{{ d.libelle.split(' - ')[0] }}<small>{{ d.stages.length }}</small>
        </button>
      </div>

      <p v-if="!resultats.length" class="vide">Aucune formation ne correspond. <button class="lien" @click="filtres.q = ''; filtres.domaine = ''">Réinitialiser les filtres</button></p>
      <TransitionGroup tag="ul" name="liste" class="stages">
        <li v-for="s in resultats.slice(0, affiches)" :key="s.slug">
          <article class="stage">
            <p class="stage-domaine"><Icone :nom="s.domaine.icone" :taille="15" />{{ s.sous_theme || s.domaine.libelle.split(' - ')[0] }}</p>
            <h3><NuxtLink :to="`/formation-continue/${s.slug}`" class="lien-etire">{{ s.intitule }}</NuxtLink></h3>
            <p class="stage-bas">
              <span><Icone nom="horloge" :taille="15" />{{ s.duree }}</span>
              <span><Icone nom="tarif" :taille="15" />{{ resumeTarif(s.tarifs) }}</span>
              <span v-if="sessionAVenir(s.prochaine_session)" class="pastille pastille-verte">Session le {{ dateCourte(s.prochaine_session) }}</span>
            </p>
          </article>
        </li>
      </TransitionGroup>
      <div v-if="resultats.length > affiches" class="plus">
        <button class="btn btn-secondaire" @click="affiches += PAS">Afficher plus ({{ resultats.length - affiches }} restantes)</button>
      </div>
    </section>

    <section class="conteneur bloc infos" aria-label="Informations pratiques">
      <div v-apparition class="carte info">
        <span class="info-icone"><Icone nom="certification" :taille="26" /></span>
        <h3>Certifié Qualiopi</h3>
        <p>La certification qualité a été délivrée au titre des actions de formation et des actions de formation par apprentissage.</p>
        <p class="liens"><a :href="data.liens.certificat_qualiopi" target="_blank" rel="noopener">Certificat Qualiopi <Icone nom="externe" :taille="14" /></a>
          <a :href="data.liens.livret_accueil" target="_blank" rel="noopener">Livret d'accueil <Icone nom="externe" :taille="14" /></a></p>
      </div>
      <div v-apparition="1" class="carte info">
        <span class="info-icone"><Icone nom="adresse" :taille="26" /></span>
        <h3>Nos locaux</h3>
        <ul><li v-for="l in data.locaux" :key="l.ville"><strong>{{ l.ville }}</strong> - {{ l.adresse }}</li></ul>
      </div>
      <div v-apparition="2" class="carte info">
        <span class="info-icone"><Icone nom="accessibilite" :taille="26" /></span>
        <h3>Accessibilité et contact</h3>
        <p>{{ data.accessibilite }}</p>
        <p class="liens"><a :href="`tel:+262${data.contact.telephone.replace(/\s/g, '').slice(1)}`">{{ data.contact.telephone }}</a><a :href="`mailto:${data.contact.email}`">{{ data.contact.email }}</a></p>
      </div>
    </section>
    <p class="conteneur source">Catalogue repris de <a :href="data.site" target="_blank" rel="noopener">expernet.re</a> : programmes détaillés, dates et tarifs HT.</p>
  </div>
</template>

<style scoped>
.cta-entete { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
.chiffres { animation: monter .7s .2s cubic-bezier(.2,.7,.2,1) both; position: relative; z-index: 2; margin-top: -36px; display: grid; grid-template-columns: repeat(4, 1fr); background: #fff; border-radius: var(--rayon); box-shadow: var(--ombre-forte); overflow: hidden; }
.chiffres > div { padding: 24px; display: grid; gap: 6px; border-right: 1px solid var(--gris-bord); }
.chiffres > div:last-child { border-right: 0; }
.chiffres .chiffre { color: var(--primaire-fonce); }
.bloc { margin-top: var(--esp-6); }
.titre-lien { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.lien-planning { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.format { display: grid; gap: 10px; align-content: start; }
.format h3, .format p { margin: 0; }
.format p { color: var(--texte-doux); }
.format-icone { width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }

.sessions { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr)); gap: 12px; }
.session { display: grid; grid-template-columns: 58px 1fr auto; gap: 14px; align-items: center; padding: 12px 16px 12px 12px; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); color: var(--texte); text-decoration: none; transition: border-color .15s, transform .15s; }
.session:hover { border-color: var(--primaire); transform: translateY(-2px); }
.date { display: grid; justify-items: center; background: #E1F3FB; color: var(--primaire-fonce); border-radius: 12px; padding: 6px 0; font-size: .78rem; text-transform: uppercase; font-weight: 700; }
.date strong { font-family: var(--police-titre); font-size: 1.4rem; line-height: 1; }
.session-texte { display: grid; min-width: 0; }
.session-texte strong { color: var(--encre); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.session-texte small { color: var(--texte-doux); }
.fleche { color: var(--primaire); }

.outils { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin: 8px 0 14px; }
.recherche { flex: 1; min-width: min(100%, 280px); max-width: 560px; display: flex; align-items: center; gap: 10px; padding: 0 16px; background: #fff; border: 1.5px solid #C6CFD8; border-radius: 999px; color: var(--texte-doux); }
.recherche:focus-within { border-color: var(--primaire); }
.recherche input { flex: 1; min-width: 0; border: 0; font: inherit; padding: 12px 0; background: transparent; outline: none; }
.compte { margin: 0; color: var(--texte-doux); font-weight: 500; }
.domaines { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
.puce { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; border: 1.5px solid var(--gris-bord); background: #fff; font: inherit; font-size: .88rem; cursor: pointer; color: var(--texte); transition: border-color .15s, background .15s, color .15s; }
.puce small { color: var(--texte-doux); font-size: .76rem; }
.puce:hover { border-color: var(--primaire); }
.puce.actif { background: var(--primaire-fonce); border-color: var(--primaire-fonce); color: #fff; }
.puce.actif small { color: #CFE3F1; }
.stages { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr)); gap: 14px; }
.stage { position: relative; height: 100%; display: flex; flex-direction: column; gap: 8px; padding: 18px; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); transition: border-color .15s, transform .15s; }
.stage:hover { border-color: var(--primaire); transform: translateY(-3px); }
.stage-domaine { margin: 0; display: flex; align-items: center; gap: 6px; color: var(--primaire-texte); font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.stage h3 { margin: 0; font-size: 1.02rem; line-height: 1.3; }
.stage h3 a { color: var(--encre); text-decoration: none; }
.lien-etire::after { content: ''; position: absolute; inset: 0; border-radius: var(--rayon); }
.stage-bas { margin: auto 0 0; display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; color: var(--texte-doux); font-size: .86rem; }
.stage-bas > span:not(.pastille) { display: inline-flex; align-items: center; gap: 5px; }
.plus { text-align: center; margin-top: 24px; }
.lien { background: none; border: 0; color: var(--primaire-texte); text-decoration: underline; cursor: pointer; font: inherit; }
.liste-enter-active, .liste-leave-active { transition: opacity .25s, transform .25s; }
.liste-enter-from, .liste-leave-to { opacity: 0; transform: translateY(8px); }
.liste-leave-active { display: none; }

.infos { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--esp-4); }
.info { display: grid; gap: 8px; align-content: start; }
.info h3, .info p { margin: 0; }
.info p, .info li { color: var(--texte-doux); font-size: .92rem; }
.info ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 6px; }
.info strong { color: var(--encre); }
.info-icone { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.liens { display: flex; flex-wrap: wrap; gap: 6px 16px; }
.liens a { display: inline-flex; align-items: center; gap: 4px; font-weight: 500; }
.source { margin-top: var(--esp-4); color: var(--texte-doux); font-size: .86rem; }
@media (max-width: 900px) { .chiffres { grid-template-columns: 1fr 1fr; } .chiffres > div { border-bottom: 1px solid var(--gris-bord); } .infos { grid-template-columns: 1fr; } }
</style>
