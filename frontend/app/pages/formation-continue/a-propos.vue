<script setup lang="ts">
// À propos de l'organisme de formation continue : histoire, équipe, certification, locaux (repris de expernet.re).
const api = useApi()
const { data } = await useAsyncData('fc-a-propos', () => api<any>('/api/formation-continue/a-propos'))
useSeoMeta({
  title: 'À propos - formation continue',
  description: 'Expernet, centre de formation en informatique et marketing digital depuis 2002 à La Réunion et à Mayotte : notre histoire, notre équipe, nos engagements.',
})
const initiales = (nom: string) => nom.split(/[\s-]+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase()
</script>

<template>
  <div v-if="data">
    <EntetePage titre="À propos d'Expernet" sur-titre="Formation continue · depuis 2002" texte="Un centre de formation en informatique et marketing digital, à La Réunion et à Mayotte, avec l'humain au centre du projet." image="/images/campus/filiere-informatique.webp" />
    <NavFormationContinue />

    <section class="conteneur bloc presentation" aria-labelledby="t-presentation">
      <div>
        <p class="sur-titre">Qui sommes-nous</p>
        <h2 id="t-presentation">Accompagner chacun dans le numérique</h2>
        <p class="intro">{{ data.presentation }}</p>
      </div>
      <div class="carte chiffre-cle">
        <span class="chiffre"><span>{{ data.chiffre_cle.prefixe }}</span><Compteur :valeur="data.chiffre_cle.valeur" /></span>
        <p class="libelle-chiffre">{{ data.chiffre_cle.libelle }}</p>
      </div>
    </section>

    <section class="conteneur bloc" aria-labelledby="t-histoire">
      <p class="sur-titre">Notre histoire</p>
      <h2 id="t-histoire">Plus de 20 ans au service du territoire</h2>
      <ol class="frise">
        <li v-for="(e, i) in data.histoire" :key="e.annee" v-apparition="i">
          <span class="annee">{{ e.annee }}</span>
          <div class="carte etape"><h3>{{ e.titre }}</h3><p>{{ e.texte }}</p></div>
        </li>
      </ol>
    </section>

    <section class="conteneur bloc" aria-labelledby="t-equipe">
      <p class="sur-titre">L'équipe</p>
      <h2 id="t-equipe">Toujours à votre écoute</h2>
      <ul class="equipe">
        <li v-for="(m, i) in data.equipe" :key="m.nom" v-apparition="i % 4" class="carte membre">
          <span class="avatar-membre" aria-hidden="true">{{ initiales(m.nom) }}</span>
          <strong>{{ m.nom }}</strong>
          <span class="aide">{{ m.fonction }}</span>
        </li>
      </ul>
    </section>

    <section class="conteneur bloc engagements" aria-label="Engagements et informations pratiques">
      <div class="carte">
        <h2 class="titre-icone"><Icone nom="certification" :taille="24" poids="duotone" /> Certifié {{ data.certification.nom }}</h2>
        <p>La certification qualité a été délivrée au titre des catégories d'actions suivantes :</p>
        <ul><li v-for="c in data.certification.categories" :key="c">{{ c }}</li></ul>
        <a :href="data.liens.certificat_qualiopi" target="_blank" rel="noopener" class="lien-fleche">Consulter le certificat <Icone nom="externe" :taille="16" /></a>
      </div>
      <div class="carte">
        <h2 class="titre-icone"><Icone nom="accessibilite" :taille="24" poids="duotone" /> Accessibilité</h2>
        <p>{{ data.accessibilite }}</p>
        <p>Référente handicap : <strong>{{ data.referente_handicap.nom }}</strong>, <a :href="`mailto:${data.referente_handicap.email}`">{{ data.referente_handicap.email }}</a></p>
        <a :href="data.liens.livret_accueil" target="_blank" rel="noopener" class="lien-fleche">Livret d'accueil (PDF) <Icone nom="externe" :taille="16" /></a>
      </div>
      <div class="carte">
        <h2 class="titre-icone"><Icone nom="adresse" :taille="24" poids="duotone" /> Nos locaux</h2>
        <ul class="locaux"><li v-for="l in data.locaux" :key="l.ville"><strong>{{ l.ville }}</strong><span class="aide">{{ l.adresse }}</span></li></ul>
        <p><a :href="`tel:${data.contact.telephone.replace(/\s/g, '')}`">{{ data.contact.telephone }}</a> · <a :href="`mailto:${data.contact.email}`">{{ data.contact.email }}</a></p>
      </div>
    </section>

    <p class="conteneur source">Informations reprises de <a href="https://expernet.re/notre-histoire" target="_blank" rel="noopener">expernet.re</a> (Notre histoire, L'équipe).</p>
  </div>
</template>

<style scoped>
.bloc { margin-top: var(--esp-6); }
.bloc:first-of-type { margin-top: var(--esp-5); }
.presentation { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: var(--esp-5); align-items: center; }
.intro { font-size: 1.1rem; line-height: 1.65; max-width: 65ch; }
.chiffre-cle { text-align: center; padding: var(--esp-5) var(--esp-4); background: linear-gradient(135deg, #E1F3FB, #fff); }
.chiffre-cle .chiffre { display: inline-flex; align-items: baseline; gap: 6px; font-size: 2.6rem; }
.chiffre-cle .chiffre > span:first-child { font-size: 1.3rem; }
.frise { list-style: none; padding: 0; margin: var(--esp-4) 0 0; display: grid; gap: var(--esp-3); position: relative; }
.frise::before { content: ''; position: absolute; left: 47px; top: 10px; bottom: 10px; width: 3px; background: linear-gradient(var(--primaire), var(--orange)); border-radius: 3px; }
.frise li { display: grid; grid-template-columns: 96px minmax(0, 1fr); gap: var(--esp-3); align-items: start; }
.annee { position: relative; z-index: 1; justify-self: center; background: var(--primaire-fonce); color: #fff; font-weight: 800; padding: 8px 12px; border-radius: 999px; font-size: 1rem; box-shadow: 0 0 0 5px var(--gris-fond, #fff); }
.etape h3 { margin: 0 0 6px; }
.etape p { margin: 0; }
.equipe { list-style: none; padding: 0; margin: var(--esp-4) 0 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--esp-3); }
.membre { display: grid; justify-items: center; text-align: center; gap: 6px; padding: var(--esp-4) var(--esp-3); }
.avatar-membre { width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; background: var(--primaire-fonce); color: #fff; font-weight: 800; font-size: 1.4rem; margin-bottom: 6px; }
.membre:nth-child(3n+2) .avatar-membre { background: var(--orange); color: var(--encre); }
.membre:nth-child(3n) .avatar-membre { background: var(--primaire); }
.engagements { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--esp-3); align-items: start; }
.engagements h2 { font-size: 1.2rem; }
.titre-icone { display: flex; align-items: center; gap: 8px; color: var(--primaire-fonce); }
.locaux { list-style: none; padding: 0; display: grid; gap: 10px; }
.locaux li { display: grid; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.source { margin-top: var(--esp-5); color: var(--texte-doux); font-size: .9rem; text-align: center; }
@media (max-width: 1000px) { .engagements { grid-template-columns: 1fr; } .presentation { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .frise li { grid-template-columns: 72px minmax(0, 1fr); } .frise::before { left: 35px; } }
</style>
