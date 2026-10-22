<script setup lang="ts">
const api = useApi()
const config = useRuntimeConfig()
const { data: formations } = await useAsyncData('formations-accueil', () => api<{ data: any[] }>('/api/formations'))
const recherche = ref('')
const onglet = ref<'informatique' | 'marketing'>('informatique')

useSeoMeta({
  title: 'École informatique et marketing digital en alternance à La Réunion',
  description: 'Expernet Campus forme en alternance du BAC+2 au BAC+5 aux métiers de l\'informatique, de la cybersécurité et du marketing digital, au Port (La Réunion) et à Mayotte.',
  ogTitle: 'Expernet Campus — Informatique & Marketing Digital en alternance',
  ogType: 'website',
})
useHead({
  link: [{ rel: 'canonical', href: config.public.siteUrl + '/' }, { rel: 'preload', as: 'image', href: '/images/campus/hero-informatique.webp' }],
  script: [{ type: 'application/ld+json', innerHTML: JSON.stringify({
    '@context': 'https://schema.org', '@type': 'EducationalOrganization', name: 'Expernet Campus', foundingDate: '2017',
    logo: config.public.siteUrl + '/images/brand/logo-expernet.png',
    address: { '@type': 'PostalAddress', streetAddress: 'Local 528, Parc 2000, 3 avenue Théodore Drouhet', postalCode: '97420', addressLocality: 'Le Port', addressRegion: 'La Réunion', addressCountry: 'FR' },
    telephone: '+262262219045', email: 'campus@expernet.re',
  }) }],
})

function chercher() { navigateTo({ path: '/formations', query: recherche.value ? { q: recherche.value } : {} }) }
const liste = computed(() => formations.value?.data.filter((f) => f.filiere?.code === onglet.value) || [])
const nb = (code: string) => formations.value?.data.filter((f) => f.filiere?.code === code).length || 0
const partenaires = [
  { src: '/images/partenaires/ipi.png', nom: 'IPI — École d\'informatique' },
  { src: '/images/partenaires/eni.png', nom: 'ENI École Informatique' },
  { src: '/images/partenaires/esd.png', nom: 'ESD Cybersecurity Academy' },
  { src: '/images/partenaires/digital-campus.png', nom: 'Digital Campus' },
]
</script>

<template>
  <div>
    <!-- HERO -->
    <section class="hero" aria-labelledby="titre-hero">
      <HeroCarrousel />
      <div class="conteneur hero-contenu">
        <p class="sur-titre clair">Campus informatique & marketing digital</p>
        <h1 id="titre-hero">Plongez au cœur<br>du <span class="accent">numérique</span>.</h1>
        <p class="intro">Du BAC+2 au BAC+5, des titres certifiés par l'État et financés en alternance, au Port et à Mayotte.</p>
        <form class="recherche" role="search" @submit.prevent="chercher">
          <label for="q" class="sr-only">Rechercher une formation</label>
          <Icone nom="recherche" :taille="20" />
          <input id="q" v-model="recherche" type="search" placeholder="Développeur, réseau, cybersécurité, digital…" autocomplete="off">
          <button class="btn btn-primaire" type="submit">Rechercher</button>
        </form>
        <p class="raccourcis">
          <NuxtLink to="/formations?niveau=5">BAC+2</NuxtLink>
          <NuxtLink to="/formations?niveau=6">BAC+3/4</NuxtLink>
          <NuxtLink to="/formations?niveau=7">BAC+5</NuxtLink>
          <NuxtLink to="/formations?cpf=1">Éligible CPF</NuxtLink>
        </p>
      </div>
    </section>

    <!-- CHIFFRES -->
    <section class="conteneur chiffres" aria-label="Expernet en chiffres">
      <div><Icone nom="entreprise" :taille="26" poids="duotone" class="ic" /><span class="chiffre">2017</span><span class="libelle-chiffre">création du campus</span></div>
      <div><Icone nom="diplome" :taille="26" poids="duotone" class="ic" /><span class="chiffre">{{ formations?.data.length || 8 }}</span><span class="libelle-chiffre">titres certifiés RNCP</span></div>
      <div><Icone nom="etudiant" :taille="26" poids="duotone" class="ic" /><span class="chiffre">~100</span><span class="libelle-chiffre">apprenants chaque année</span></div>
      <div><Icone nom="adresse" :taille="26" poids="duotone" class="ic" /><span class="chiffre">2</span><span class="libelle-chiffre">campus : Le Port & Mayotte</span></div>
      <div class="qualiopi"><Icone nom="certification" :taille="34" poids="duotone" /><span class="libelle-chiffre">Certifié Qualiopi</span></div>
    </section>

    <!-- FORMATIONS -->
    <section class="conteneur bloc" aria-labelledby="titre-formations">
      <div class="titre-bloc">
        <div>
          <p class="sur-titre">Nos formations diplômantes</p>
          <h2 id="titre-formations">Un cursus pour chaque ambition</h2>
        </div>
        <div class="onglets" role="tablist" aria-label="Filière">
          <button role="tab" :aria-selected="onglet === 'informatique'" :class="{ actif: onglet === 'informatique' }" @click="onglet = 'informatique'">Informatique <span>{{ nb('informatique') }}</span></button>
          <button role="tab" :aria-selected="onglet === 'marketing'" :class="{ actif: onglet === 'marketing', orange: true }" @click="onglet = 'marketing'">Marketing digital <span>{{ nb('marketing') }}</span></button>
        </div>
      </div>
      <div class="grille grille-3" role="tabpanel">
        <CarteFormation v-for="(f, i) in liste" :key="f.slug" v-apparition="i % 3" :f="f" />
      </div>
      <p class="voir-tout"><NuxtLink :to="`/formations?filiere=${onglet}`" class="btn btn-secondaire">Voir toutes les formations {{ onglet === 'informatique' ? 'informatique' : 'marketing digital' }} <Icone nom="suivant" :taille="18" /></NuxtLink></p>
    </section>

    <!-- PRÉSENTATION -->
    <section class="presentation">
      <div class="conteneur presentation-grille">
        <div v-apparition class="visuels">
          <img src="/images/campus/hero-blog.webp" alt="Apprenants et équipe pédagogique échangeant au campus Expernet" class="v1" loading="lazy" width="1920" height="547">
          <img src="/images/campus/presentation.webp" alt="" class="v2" loading="lazy" width="422" height="381">
          <img src="/images/brand/motif-carres.svg" alt="" class="motif" width="95" height="87">
        </div>
        <div>
          <p v-apparition="1" class="sur-titre">Le campus</p>
          <h2>Le spécialiste de l'alternance dans les métiers du numérique</h2>
          <p>Le Campus EXPERNET est créé en 2017. Après être devenu un acteur local reconnu de la formation continue des salariés, il s'est spécialisé dans les <strong>formations en alternance</strong> aux métiers du numérique.</p>
          <p>Avec {{ nb('informatique') }} cursus en informatique et {{ nb('marketing') }} cursus en marketing digital, du BAC+2 au BAC+5, le campus accompagne une centaine d'apprenants par an pour les préparer aux métiers de demain et garantir leur employabilité.</p>
          <ul class="atouts">
            <li><strong>Alternance</strong> : un rythme école / entreprise, une formation financée et rémunérée</li>
            <li><strong>Titres RNCP</strong> certifiés par l'État, avec nos écoles partenaires</li>
            <li><strong>Accompagnement</strong> personnalisé jusqu'à l'insertion professionnelle</li>
          </ul>
          <NuxtLink to="/contact" class="btn btn-bleu">Rencontrer l'équipe</NuxtLink>
        </div>
      </div>
    </section>

    <!-- FILIÈRES -->
    <section class="conteneur bloc filieres" aria-label="Filières">
      <NuxtLink v-apparition to="/formations?filiere=informatique" class="filiere">
        <img src="/images/campus/filiere-informatique.webp" alt="" loading="lazy" width="1920" height="1280">
        <span class="filiere-texte"><span class="sur-titre clair">{{ nb('informatique') }} cursus</span><strong>Formations informatique</strong><span>Réseaux, développement, cybersécurité, pilotage de projets IT</span></span>
      </NuxtLink>
      <NuxtLink v-apparition="1" to="/formations?filiere=marketing" class="filiere marketing">
        <img src="/images/formations/bac2-graphiste-multimedia-ia.webp" alt="" loading="lazy" width="1438" height="694">
        <span class="filiere-texte"><span class="sur-titre clair">{{ nb('marketing') }} cursus</span><strong>Formations marketing digital</strong><span>Design graphique, IA, webmarketing, stratégie digitale</span></span>
      </NuxtLink>
    </section>

    <!-- PARTENAIRES -->
    <section class="partenaires" aria-labelledby="titre-partenaires">
      <div class="conteneur">
        <p class="sur-titre centre">Nos partenaires</p>
        <h2 id="titre-partenaires" class="centre">Des titres délivrés avec des écoles reconnues</h2>
        <ul>
          <li v-for="(p, i) in partenaires" :key="p.nom" v-apparition="i"><img :src="p.src" :alt="p.nom" loading="lazy"></li>
        </ul>
      </div>
    </section>

    <!-- VIE DU CAMPUS -->
    <section class="conteneur bloc" aria-labelledby="titre-vie">
      <p class="sur-titre">La vie du campus</p>
      <h2 id="titre-vie">Apprendre ensemble, au Port</h2>
      <div v-apparition class="mosaique">
        <img src="/images/campus/hero-marketing.webp" alt="Une promotion Expernet devant le campus" class="m1" loading="lazy">
        <img src="/images/campus/campus-reunion.webp" alt="Apprenante travaillant sur ordinateur portable" class="m2" loading="lazy">
        <img src="/images/campus/le-numerique-se-forme.webp" alt="Interview d'un apprenant Expernet" class="m3" loading="lazy">
        <img src="/images/campus/campus-expernet.webp" alt="Sortie de cohésion des apprenants sur la plage" class="m4" loading="lazy">
      </div>
    </section>

    <!-- BANDEAU ENT -->
    <section class="conteneur bloc">
      <div v-apparition class="bandeau-ent">
        <img src="/images/brand/motif-carres.svg" alt="" class="motif-ent" width="95" height="87">
        <div>
          <p class="sur-titre clair">Espace numérique de travail</p>
          <h2>Déjà apprenant ou formateur ?</h2>
          <p>Planning, émargement, notes, documents et jurys réunis dans un seul espace sécurisé.</p>
        </div>
        <div class="boutons-ent">
          <NuxtLink to="/connexion" class="btn btn-primaire">Accéder à mon espace</NuxtLink>
          <NuxtLink to="/contact" class="btn btn-contour-blanc">Nous contacter</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.hero { position: relative; min-height: min(640px, 88vh); display: flex; align-items: center; color: #fff; overflow: hidden; }
.hero-contenu { position: relative; z-index: 1; padding: 72px 0 110px; }
/* Entrée du hero : éléments échelonnés */
.hero-contenu > * { animation: monter .7s cubic-bezier(.2,.7,.2,1) both; }
.hero-contenu > :nth-child(2) { animation-delay: .08s; }
.hero-contenu > :nth-child(3) { animation-delay: .16s; }
.hero-contenu > :nth-child(4) { animation-delay: .24s; }
.hero-contenu > :nth-child(5) { animation-delay: .32s; }
.chiffres { animation: monter .7s .35s cubic-bezier(.2,.7,.2,1) both; }
.sur-titre.clair { color: #9FDCF5; }
.hero h1 { color: #fff; max-width: 14ch; text-shadow: 0 2px 30px rgba(0,0,0,.25); }
.accent { color: var(--orange); }
.intro { font-size: 1.15rem; max-width: 52ch; color: #E3EEF6; margin-bottom: 28px; }
.recherche { display: flex; align-items: center; gap: 10px; background: #fff; padding: 6px 6px 6px 18px; border-radius: 999px; max-width: 620px; box-shadow: 0 20px 50px rgba(0,0,0,.25); color: var(--texte-doux); }
.recherche input { flex: 1; border: 0; font: inherit; padding: 10px 4px; min-width: 0; color: var(--encre); background: transparent; }
.recherche input:focus { outline: none; }
.raccourcis { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.raccourcis a { color: #fff; text-decoration: none; font-size: .88rem; font-weight: 500; padding: 6px 14px; border-radius: 999px; border: 1px solid rgba(255,255,255,.45); transition: background .15s; }
.raccourcis a:hover { background: rgba(255,255,255,.2); }

.chiffres { position: relative; z-index: 2; margin-top: -56px; display: grid; grid-template-columns: repeat(5, 1fr); background: #fff; border-radius: var(--rayon); box-shadow: var(--ombre-forte); overflow: hidden; }
.chiffres > div { padding: 24px 22px; display: grid; gap: 6px; border-right: 1px solid var(--gris-bord); }
.chiffres > div:last-child { border-right: 0; }
.chiffres .chiffre { color: var(--primaire-fonce); }
.chiffres .ic { color: var(--primaire); }
.chiffres .qualiopi { background: var(--primaire-fonce); }
.chiffres .qualiopi .libelle-chiffre, .chiffres .qualiopi :deep(.icone) { color: #fff; }

.bloc { margin-top: var(--esp-6); }
.titre-bloc { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-bloc h2 { margin: 0; }
.onglets { display: inline-flex; background: var(--gris-fond); padding: 5px; border-radius: 999px; gap: 4px; }
.onglets button { border: 0; background: transparent; font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 999px; cursor: pointer; color: var(--texte-doux); display: flex; gap: 8px; align-items: center; }
.onglets button span { background: #fff; border-radius: 999px; padding: 0 8px; font-size: .78rem; }
.onglets button.actif { background: var(--primaire); color: #fff; }
.onglets button.actif.orange { background: var(--orange); color: var(--encre); }
.onglets button.actif span { color: var(--encre); }
.voir-tout { text-align: center; margin-top: var(--esp-4); }

.presentation { background: var(--gris-fond); margin-top: var(--esp-6); padding: var(--esp-6) 0; }
.presentation-grille { display: grid; grid-template-columns: 1fr 1fr; gap: var(--esp-6); align-items: center; }
.visuels { position: relative; min-height: 420px; }
.visuels .v1 { width: 88%; height: 340px; object-fit: cover; border-radius: var(--rayon); box-shadow: var(--ombre-forte); }
.visuels .v2 { position: absolute; right: 0; bottom: 0; width: 44%; aspect-ratio: 1; object-fit: cover; border-radius: var(--rayon); border: 6px solid #fff; box-shadow: var(--ombre-forte); }
.visuels .motif { position: absolute; left: 6px; bottom: -18px; width: 90px; }
.atouts { list-style: none; padding: 0; margin: 20px 0 28px; display: grid; gap: 12px; }
.atouts li { padding-left: 30px; position: relative; }
.atouts li::before { content: ''; position: absolute; left: 0; top: 5px; width: 16px; height: 16px; border-radius: 4px; background: var(--primaire); box-shadow: 5px 5px 0 -1px var(--jaune); }

.filieres { display: grid; grid-template-columns: 1fr 1fr; gap: var(--esp-4); }
.filiere { position: relative; display: block; border-radius: var(--rayon); overflow: hidden; min-height: 300px; color: #fff; text-decoration: none; box-shadow: var(--ombre); }
.filiere img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .6s cubic-bezier(.2,.7,.2,1); }
.filiere::after { content: ''; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,70,110,.94) 0%, rgba(0,155,221,.35) 60%, rgba(0,155,221,.1) 100%); }
.filiere.marketing::after { background: linear-gradient(0deg, rgba(110,62,0,.94) 0%, rgba(237,143,0,.35) 60%, rgba(237,143,0,.1) 100%); }
.filiere:hover img { transform: scale(1.06); }
.filiere-texte { position: absolute; z-index: 1; left: 28px; right: 28px; bottom: 26px; display: grid; gap: 4px; }
.filiere-texte strong { font-family: var(--police-titre); font-size: 1.6rem; }
.filiere .sur-titre.clair { color: #fff; margin: 0; }
.filiere .sur-titre::before { background: #fff; }

.partenaires { margin-top: var(--esp-6); padding: var(--esp-5) 0; border-block: 1px solid var(--gris-bord); }
.centre { text-align: center; display: block; }
.sur-titre.centre { display: flex; justify-content: center; }
.partenaires ul { list-style: none; padding: 0; margin: 28px 0 0; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 48px; }
.partenaires img { height: 64px; width: auto; filter: grayscale(1); opacity: .75; transition: filter .2s, opacity .2s; }
.partenaires img:hover { filter: none; opacity: 1; }

.mosaique { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 220px 220px; gap: 14px; }
.mosaique img { width: 100%; height: 100%; object-fit: cover; border-radius: var(--rayon); }
.mosaique .m1 { grid-row: 1 / 3; }
.mosaique .m4 { grid-column: 2 / 4; }

.bandeau-ent { position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; gap: var(--esp-4); padding: 44px 48px; border-radius: 20px; color: #fff; background: var(--primaire-fonce); }
.bandeau-ent h2 { color: #fff; margin-bottom: 8px; }
.bandeau-ent p { margin: 0; color: #E3EEF6; }
.motif-ent { position: absolute; right: 30%; top: -20px; width: 110px; opacity: .14; }
.boutons-ent { display: flex; gap: 12px; flex-wrap: wrap; position: relative; }

@media (max-width: 960px) {
  .visuels .motif { display: none; }
  .chiffres { grid-template-columns: repeat(2, 1fr); }
  .chiffres > div { border-bottom: 1px solid var(--gris-bord); }
  .chiffres .qualiopi { grid-column: 1 / -1; }
  .presentation-grille, .filieres { grid-template-columns: 1fr; }
  .visuels { min-height: 340px; }
  .bandeau-ent { flex-direction: column; align-items: flex-start; padding: 32px 24px; }
  .mosaique { grid-template-columns: 1fr 1fr; grid-template-rows: 180px 180px 180px; }
  .mosaique .m1 { grid-row: auto; grid-column: 1 / 3; }
  .mosaique .m4 { grid-column: 1 / 3; }
}
@media (max-width: 600px) {
  .onglets { width: 100%; }
  .onglets button { flex: 1; justify-content: center; padding: 10px 8px; font-size: .88rem; }
  .titre-bloc { align-items: stretch; }
  .recherche { flex-direction: column; align-items: stretch; border-radius: 18px; padding: 10px; }
  .recherche :deep(.icone) { display: none; }
  .hero-contenu { padding: 48px 0 96px; }
}
</style>
