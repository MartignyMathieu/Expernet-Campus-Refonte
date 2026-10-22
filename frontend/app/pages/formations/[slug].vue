<script setup lang="ts">
const route = useRoute()
const api = useApi()
const config = useRuntimeConfig()

const { data, error } = await useAsyncData(`formation-${route.params.slug}`, () => api<{ data: any }>(`/api/formations/${route.params.slug}`))
if (error.value || !data.value) throw createError({ statusCode: 404, statusMessage: 'Formation introuvable', fatal: true })
const f = computed(() => data.value!.data)

useSeoMeta({
  title: () => `${f.value.intitule} (${f.value.niveau_libelle}) en alternance`,
  description: () => f.value.accroche,
  ogTitle: () => f.value.intitule,
})
useHead({
  link: [{ rel: 'canonical', href: `${config.public.siteUrl}/formations/${f.value.slug}` }],
  script: [{ type: 'application/ld+json', innerHTML: JSON.stringify({
    '@context': 'https://schema.org', '@type': 'Course', name: f.value.intitule, description: f.value.accroche,
    provider: { '@type': 'EducationalOrganization', name: 'Expernet Campus' },
    educationalCredentialAwarded: f.value.code_rncp,
  }) }],
})

// Formulaire de candidature
const form = reactive({ prenom: '', nom: '', email: '', telephone: '', message: '', consentement_rgpd: false, site_web: '' })
const erreurs = ref<Record<string, string>>({})
const etat = ref<'idle' | 'envoi' | 'ok' | 'ko'>('idle')
const retour = ref('')

function validerLocal() {
  const e: Record<string, string> = {}
  if (form.prenom.trim().length < 2) e.prenom = 'Indiquez votre prénom.'
  if (form.nom.trim().length < 2) e.nom = 'Indiquez votre nom.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = 'Adresse e-mail invalide.'
  if (form.telephone && !/^(\+262|\+33|0)[1-9](\s?\d{2}){4}$/.test(form.telephone)) e.telephone = 'Format attendu : 0262 12 34 56 ou +262 692 12 34 56.'
  if (!form.consentement_rgpd) e.consentement_rgpd = 'Votre accord est nécessaire pour traiter la candidature.'
  erreurs.value = e
  return Object.keys(e).length === 0
}

async function envoyer() {
  if (!validerLocal()) {
    nextTick(() => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus())
    return
  }
  etat.value = 'envoi'
  try {
    await csrf()
    const r: any = await api('/api/candidatures', { method: 'POST', body: { ...form, formation: f.value.slug } })
    etat.value = 'ok'; retour.value = r.message
  } catch (e: any) {
    etat.value = 'ko'
    erreurs.value = erreursValidation(e)
    retour.value = e?.status === 429 ? 'Trop de tentatives, réessayez dans une minute.' : 'Merci de corriger les champs signalés.'
  }
}
const sections = [['objectifs', 'Objectifs pédagogiques'], ['prerequis', 'Prérequis'], ['modalites', 'Modalités d\'accès'], ['debouches', 'Débouchés']] as const
</script>

<template>
  <div>
    <section class="bandeau" :class="f.filiere.code">
      <img :src="`/images/formations/${f.slug}.webp`" alt="" width="1439" height="693" fetchpriority="high">
      <div class="conteneur bandeau-contenu">
        <nav aria-label="Fil d'Ariane" class="ariane"><NuxtLink to="/">Accueil</NuxtLink> › <NuxtLink to="/formations">Formations</NuxtLink> › <span aria-current="page">{{ f.intitule }}</span></nav>
        <div class="pastilles">
          <span class="pastille niveau">{{ f.niveau_libelle }} · niveau {{ f.niveau_rncp }}</span>
          <span class="pastille">{{ f.filiere.libelle }}</span>
          <span v-if="f.eligible_cpf" class="pastille pastille-jaune">Éligible CPF*</span>
        </div>
        <h1>{{ f.intitule }}</h1>
        <p class="accroche">{{ f.accroche }}</p>
        <div class="cta"><a href="#candidater" class="btn btn-primaire">Je candidate</a><NuxtLink to="/contact" class="btn btn-contour-blanc">Demander un renseignement</NuxtLink></div>
      </div>
    </section>

  <div class="conteneur page">
    <div class="disposition">
      <div class="corps">
        <section v-for="[cle, titre] in sections" :key="cle" class="section">
          <h2>{{ titre }}</h2>
          <ul><li v-for="(item, i) in f[cle]" :key="i">{{ item }}</li></ul>
        </section>
        <p class="aide">* Éligibilité CPF à confirmer auprès du campus selon votre situation.</p>
      </div>

      <aside class="infos carte">
        <dl>
          <dt><Icone nom="certification" :taille="16" />Certification</dt><dd>{{ f.code_rncp }}<br><span class="aide">{{ f.certificateur }}</span></dd>
          <dt><Icone nom="alternance" :taille="16" />Rythme</dt><dd>{{ f.rythme }}</dd>
          <dt><Icone nom="planning" :taille="16" />Prochaine rentrée</dt><dd>{{ f.prochaine_rentree ? new Date(f.prochaine_rentree).toLocaleDateString('fr-FR', { dateStyle: 'long' }) : 'À venir' }}</dd>
          <dt><Icone nom="adresse" :taille="16" />Campus</dt><dd>{{ f.campus.join(' · ') }}</dd>
        </dl>
        <a href="#candidater" class="btn btn-primaire">Candidater</a>
      </aside>
    </div>

    <section id="candidater" class="carte candidature" aria-labelledby="titre-cand">
      <h2 id="titre-cand">Candidater à cette formation</h2>
      <div v-if="etat === 'ok'" class="message message-succes" role="status">{{ retour }}</div>
      <form v-else novalidate @submit.prevent="envoyer">
        <div v-if="etat === 'ko'" class="message message-erreur" role="alert">{{ retour }}</div>
        <p class="aide">Les champs marqués d'un astérisque (*) sont obligatoires.</p>
        <div class="deux-col">
          <div class="champ">
            <label for="prenom">Prénom *</label>
            <input id="prenom" v-model="form.prenom" autocomplete="given-name" required :aria-invalid="!!erreurs.prenom" :aria-describedby="erreurs.prenom ? 'e-prenom' : undefined">
            <span v-if="erreurs.prenom" id="e-prenom" class="erreur">{{ erreurs.prenom }}</span>
          </div>
          <div class="champ">
            <label for="nom">Nom *</label>
            <input id="nom" v-model="form.nom" autocomplete="family-name" required :aria-invalid="!!erreurs.nom" :aria-describedby="erreurs.nom ? 'e-nom' : undefined">
            <span v-if="erreurs.nom" id="e-nom" class="erreur">{{ erreurs.nom }}</span>
          </div>
          <div class="champ">
            <label for="email">E-mail *</label>
            <input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="!!erreurs.email" :aria-describedby="erreurs.email ? 'e-email' : undefined">
            <span v-if="erreurs.email" id="e-email" class="erreur">{{ erreurs.email }}</span>
          </div>
          <div class="champ">
            <label for="tel">Téléphone</label>
            <input id="tel" v-model="form.telephone" type="tel" autocomplete="tel" :aria-invalid="!!erreurs.telephone" aria-describedby="a-tel">
            <span id="a-tel" :class="erreurs.telephone ? 'erreur' : 'aide'">{{ erreurs.telephone || 'Ex. 0692 12 34 56' }}</span>
          </div>
        </div>
        <div class="champ">
          <label for="msg">Votre projet (facultatif)</label>
          <textarea id="msg" v-model="form.message" rows="4" maxlength="2000" />
        </div>
        <div class="pot" aria-hidden="true"><label for="site_web">Ne pas remplir</label><input id="site_web" v-model="form.site_web" tabindex="-1" autocomplete="off"></div>
        <div class="champ">
          <label class="case"><input v-model="form.consentement_rgpd" type="checkbox" :aria-invalid="!!erreurs.consentement_rgpd">
            <span>J'accepte qu'Expernet Campus utilise ces informations pour traiter ma candidature (conservées 2 ans maximum). *</span></label>
          <span v-if="erreurs.consentement_rgpd" class="erreur">{{ erreurs.consentement_rgpd }}</span>
        </div>
        <button class="btn btn-primaire" :disabled="etat === 'envoi'">{{ etat === 'envoi' ? 'Envoi…' : 'Envoyer ma candidature' }}</button>
      </form>
    </section>
  </div>
  </div>
</template>

<style scoped>
.bandeau { position: relative; color: #fff; overflow: hidden; background: var(--marine); }
.bandeau img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.bandeau::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,52,.94) 0%, rgba(8,32,52,.78) 45%, rgba(0,155,221,.25) 100%); }
.bandeau.marketing::after { background: linear-gradient(100deg, rgba(40,22,0,.94) 0%, rgba(70,40,0,.78) 45%, rgba(237,143,0,.25) 100%); }
.bandeau-contenu { position: relative; z-index: 1; padding: 36px 0 56px; }
.bandeau h1 { color: #fff; font-size: clamp(1.8rem, 4vw, 3rem); max-width: 22ch; }
.ariane { font-size: .88rem; color: #CFE3F1; margin-bottom: 28px; }
.ariane a { color: #fff; }
.pastilles { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
.pastilles .pastille { background: rgba(255,255,255,.16); color: #fff; backdrop-filter: blur(4px); }
.pastilles .niveau { background: var(--primaire); }
.marketing .pastilles .niveau { background: var(--orange); color: var(--encre); }
.pastilles .pastille-jaune { background: var(--jaune); color: var(--encre); }
.accroche { font-size: 1.15rem; color: #E3EEF6; max-width: 62ch; }
.cta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
.page { padding-top: var(--esp-5); }
.disposition { display: grid; grid-template-columns: 1fr 340px; gap: var(--esp-5); align-items: start; }
.section { margin-bottom: var(--esp-4); }
.section h2 { font-size: 1.35rem; display: flex; align-items: center; gap: 10px; }
.section h2::before { content: ''; width: 12px; height: 12px; border-radius: 3px; background: var(--primaire); box-shadow: 4px 4px 0 -1px var(--jaune); }
.section ul { list-style: none; padding: 0; display: grid; gap: 10px; }
.section li { background: var(--gris-fond); border-radius: var(--rayon-s); padding: 12px 16px; border-left: 3px solid var(--primaire); }
.infos { position: sticky; top: 90px; display: grid; gap: var(--esp-3); }
dl { margin: 0; display: grid; gap: 4px; }
dt { display: flex; align-items: center; gap: 6px; font-weight: 500; color: var(--texte-doux); font-size: .85rem; margin-top: 8px; }
dt :deep(.icone) { color: var(--primaire); }
dd { margin: 0; color: var(--encre); }
.candidature { margin-top: var(--esp-5); }
.deux-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0 var(--esp-3); }
.pot { position: absolute; left: -9999px; }
@media (max-width: 860px) { .disposition, .deux-col { grid-template-columns: 1fr; } .infos { position: static; } }
</style>
