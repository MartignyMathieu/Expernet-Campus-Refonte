<script setup lang="ts">
const ouvert = ref(false)
const defile = ref(false)
const route = useRoute()
watch(() => route.fullPath, () => (ouvert.value = false))
onMounted(() => {
  const maj = () => (defile.value = window.scrollY > 40)
  maj(); window.addEventListener('scroll', maj, { passive: true })
  onBeforeUnmount(() => window.removeEventListener('scroll', maj))
})
const informatique = [
  ['tsrs-bac2', 'Technicien Systèmes Réseaux et Sécurité BAC+2'],
  ['cda-bac4', 'Concepteur Développeur d\'Applications BAC+4'],
  ['asr-bac4', 'Administrateur Système et Réseau BAC+4'],
  ['hacking-cybersecurite-bac5', 'Hacking & Cybersécurité BAC+5'],
  ['directeur-des-projets-it-bac5', 'Directeur des Projets IT BAC+5'],
]
const marketing = [
  ['bac2-graphiste-multimedia-ia', 'Graphiste, Multimédia & IA BAC+2'],
  ['chef-de-projet-digital-bac3', 'Chef de Projet Digital BAC+3'],
  ['expert-en-strategie-digitale-bac5', 'Expert en Stratégie Digitale BAC+5'],
]
</script>

<template>
  <div class="site">
    <a href="#contenu" class="lien-evitement">Aller au contenu</a>
    <div class="barre-haute">
      <div class="conteneur ligne-haute">
        <OctobreRose><p>Campus informatique et marketing digital · BAC+2 à BAC+5 en alternance</p></OctobreRose>
        <p class="coord"><a href="tel:+262262219045"><Icone nom="telephone" :taille="15" />02 62 21 90 45</a><a href="mailto:campus@expernet.re"><Icone nom="email" :taille="15" />campus@expernet.re</a></p>
      </div>
    </div>
    <header class="entete" :class="{ defile }">
      <div class="conteneur ligne">
        <NuxtLink to="/" class="logo"><img src="/images/brand/logo-expernet.png" alt="Expernet Campus Informatique & Marketing Digital - accueil" width="900" height="241"></NuxtLink>
        <button class="burger" :aria-expanded="ouvert" aria-controls="menu-principal" @click="ouvert = !ouvert">
          <span class="sr-only">Menu</span><Icone :nom="ouvert ? 'fermer' : 'menu'" :taille="26" />
        </button>
        <nav id="menu-principal" :class="{ ouvert }" aria-label="Navigation principale">
          <NuxtLink to="/formations?filiere=informatique">Informatique</NuxtLink>
          <NuxtLink to="/formations?filiere=marketing">Marketing digital</NuxtLink>
          <NuxtLink to="/formation-continue">Formation continue</NuxtLink>
          <NuxtLink to="/blog">Blog</NuxtLink>
          <NuxtLink to="/faq">FAQ</NuxtLink>
          <NuxtLink to="/contact">Contact</NuxtLink>
          <span class="actions">
            <NuxtLink to="/connexion" class="btn btn-secondaire"><Icone nom="utilisateur" :taille="18" />Mon espace</NuxtLink>
            <NuxtLink to="/formations" class="btn btn-primaire">Candidater</NuxtLink>
          </span>
        </nav>
      </div>
    </header>

    <main id="contenu" tabindex="-1"><slot /></main>

    <footer class="pied">
      <div class="conteneur colonnes">
        <div class="col-marque">
          <img src="/images/brand/logo-expernet-blanc.png" alt="Expernet Campus" width="220" height="59" loading="lazy">
          <p>Spécialiste de l'alternance dans les métiers du numérique depuis 2017, du BAC+2 au BAC+5.</p>
          <p class="qualiopi"><strong>Certifié Qualiopi</strong> au titre des actions de formation et des actions de formation par apprentissage.</p>
        </div>
        <div>
          <h2>Informatique</h2>
          <ul><li v-for="[slug, nom] in informatique" :key="slug"><NuxtLink :to="`/formations/${slug}`">{{ nom }}</NuxtLink></li></ul>
        </div>
        <div>
          <h2>Marketing digital</h2>
          <ul><li v-for="[slug, nom] in marketing" :key="slug"><NuxtLink :to="`/formations/${slug}`">{{ nom }}</NuxtLink></li></ul>
        </div>
        <div>
          <h2>Nous trouver</h2>
          <ul class="contact-pied">
            <li><Icone nom="adresse" :taille="18" />Local 528, Parc 2000, 3 avenue Théodore Drouhet, 97420 Le Port</li>
            <li><Icone nom="telephone" :taille="18" /><a href="tel:+262262219045">02 62 21 90 45</a></li>
            <li><Icone nom="email" :taille="18" /><a href="mailto:campus@expernet.re">campus@expernet.re</a></li>
          </ul>
          <p class="pmr"><Icone nom="accessibilite" :taille="16" /> Établissement accessible aux PMR et PSH, places de parking dédiées. Référente handicap joignable au 02 62 21 90 45.</p>
        </div>
      </div>
      <div class="conteneur legal">
        <p>© Expernet Campus {{ new Date().getFullYear() }} · Prototype pédagogique réalisé dans le cadre du titre CDA</p>
        <p class="liens-legaux"><NuxtLink to="/formations">Formations</NuxtLink><NuxtLink to="/formation-continue">Formation continue</NuxtLink><NuxtLink to="/blog">Blog</NuxtLink><NuxtLink to="/faq">FAQ</NuxtLink><NuxtLink to="/contact">Contact</NuxtLink><NuxtLink to="/connexion">Mon espace</NuxtLink><a href="/api/docs">API</a></p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.site { min-height: 100vh; display: flex; flex-direction: column; }
.site > main { flex: 1; padding-bottom: var(--esp-6); }
.barre-haute { background: var(--marine); color: #CFE3F1; font-size: .82rem; }
.ligne-haute { display: flex; justify-content: space-between; gap: 16px; min-height: 36px; align-items: center; }
.ligne-haute p { margin: 0; letter-spacing: .02em; }
.coord { display: flex; gap: 20px; }
.coord a { color: #fff; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
.coord a:hover { text-decoration: underline; }
.entete { position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,.97); backdrop-filter: blur(8px); border-bottom: 1px solid transparent; transition: box-shadow .2s, border-color .2s; }
.entete.defile { box-shadow: 0 6px 24px rgba(11,46,74,.08); border-bottom-color: var(--gris-bord); }
.ligne { display: flex; align-items: center; justify-content: space-between; min-height: 82px; gap: 20px; }
.logo img { height: 52px; width: auto; }
nav { display: flex; align-items: center; gap: 22px; }
nav > a { color: var(--encre); text-decoration: none; font-weight: 500; position: relative; padding: 6px 0; }
nav > a::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; border-radius: 2px; background: var(--primaire); transform: scaleX(0); transition: transform .2s; }
nav > a:hover::after, nav > a.router-link-exact-active::after { transform: scaleX(1); }
.actions { display: flex; gap: 10px; margin-left: 8px; }
.burger { display: none; background: none; border: 0; font-size: 1.6rem; min-width: 44px; min-height: 44px; cursor: pointer; color: var(--encre); }
.pied { background: var(--marine); color: #C9D8E4; padding: var(--esp-6) 0 var(--esp-3); position: relative; overflow: hidden; }
.pied h2 { color: #fff; font-size: .95rem; text-transform: uppercase; letter-spacing: .08em; }
.pied a { color: #fff; text-decoration: none; }
.pied a:hover { text-decoration: underline; }
.pied ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; font-size: .92rem; }
.colonnes { display: grid; grid-template-columns: 1.3fr 1fr 1fr 1fr; gap: var(--esp-5); position: relative; }
.col-marque img { width: 220px; height: auto; margin-bottom: 16px; }
.qualiopi { font-size: .88rem; border-left: 3px solid var(--orange); padding-left: 12px; }
.pmr { font-size: .82rem; opacity: .85; margin-top: 14px; }
.contact-pied li { display: grid; grid-template-columns: 20px 1fr; gap: 10px; align-items: start; }
.contact-pied :deep(.icone) { color: var(--primaire-clair); margin-top: 2px; }
.legal { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: .8rem; color: #93A9BA; border-top: 1px solid rgba(255,255,255,.12); padding-top: var(--esp-3); margin-top: var(--esp-5); }
.legal p { margin: 0; }
.liens-legaux { display: flex; flex-wrap: wrap; gap: 6px 16px; }
@media (max-width: 1050px) { .colonnes { grid-template-columns: 1fr 1fr; } }
@media (max-width: 1240px) {
  .ligne-haute p:first-child { display: none; }
  .ligne-haute { justify-content: center; }
  .burger { display: block; }
  .logo img { height: 42px; }
  nav { display: none; position: absolute; top: 82px; left: 0; right: 0; background: #fff; flex-direction: column; align-items: stretch; padding: 16px; gap: 4px; border-bottom: 1px solid var(--gris-bord); box-shadow: var(--ombre); }
  nav.ouvert { display: flex; }
  nav > a { padding: 12px 4px; }
  .actions { margin: 8px 0 0; flex-direction: column; }
}
@media (max-width: 600px) { .colonnes { grid-template-columns: 1fr; } }
</style>
