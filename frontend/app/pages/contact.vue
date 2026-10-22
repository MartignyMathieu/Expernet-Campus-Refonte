<script setup lang="ts">
useSeoMeta({ title: 'Contact', description: 'Contactez Expernet Campus au Port (La Réunion) : admissions, alternance, financement et formation continue.' })
const api = useApi()
const route = useRoute()

// Motifs proposés : le sujet reste un texte libre côté API (3 à 150 caractères).
const motifs = ['Admission et candidature', 'Alternance et entreprises', 'Financement', 'Formation continue', 'Autre demande']
const sujetInitial = typeof route.query.sujet === 'string' ? route.query.sujet.slice(0, 150) : ''
const form = reactive({ nom: '', email: '', sujet: sujetInitial, message: '', consentement_rgpd: false, site_web: '' })
const motif = ref(sujetInitial ? 'Formation continue' : '')
watch(motif, (m) => { if (m && m !== 'Autre demande' && !sujetInitial) form.sujet = m })

const erreurs = ref<Record<string, string>>({})
const etat = ref<'idle' | 'envoi' | 'ok' | 'ko'>('idle')
async function envoyer() {
  etat.value = 'envoi'; erreurs.value = {}
  try { await csrf(); await api('/api/contact', { method: 'POST', body: form }); etat.value = 'ok' }
  catch (e) { etat.value = 'ko'; erreurs.value = erreursValidation(e) }
}
function recommencer() {
  Object.assign(form, { nom: '', email: '', sujet: '', message: '', consentement_rgpd: false })
  motif.value = ''; etat.value = 'idle'
}
</script>

<template>
  <div>
    <EntetePage titre="Parlons de votre projet" sur-titre="Contact" texte="Une question sur une formation, l'alternance ou le financement ? L'équipe du campus vous répond." image="/images/campus/campus-reunion.webp" />

    <div class="conteneur disposition">
      <!-- Formulaire -->
      <section class="carte formulaire" aria-labelledby="t-form">
        <template v-if="etat === 'ok'">
          <div class="confirmation" role="status">
            <span class="confirmation-icone"><Icone nom="valide" :taille="34" poids="fill" /></span>
            <h2>Message envoyé</h2>
            <p>Merci {{ form.nom.split(' ')[0] }}, votre demande a bien été transmise à l'équipe du campus. Une réponse vous sera adressée à <strong>{{ form.email }}</strong>.</p>
            <button class="btn btn-secondaire" @click="recommencer">Envoyer une autre demande</button>
          </div>
        </template>
        <form v-else novalidate @submit.prevent="envoyer">
          <h2 id="t-form">Écrire à l'équipe</h2>
          <p class="aide">Les champs marqués d'un astérisque (*) sont obligatoires.</p>
          <p v-if="etat === 'ko'" class="message message-erreur" role="alert">Merci de corriger les champs signalés.</p>

          <fieldset class="motifs">
            <legend>Votre demande concerne</legend>
            <label v-for="m in motifs" :key="m" class="motif" :class="{ choisi: motif === m }">
              <input v-model="motif" type="radio" name="motif" :value="m" class="sr-only">{{ m }}
            </label>
          </fieldset>

          <div class="deux-col">
            <div class="champ">
              <label for="nom">Nom complet *</label>
              <input id="nom" v-model="form.nom" autocomplete="name" required :aria-invalid="!!erreurs.nom" :aria-describedby="erreurs.nom ? 'e-nom' : undefined">
              <span v-if="erreurs.nom" id="e-nom" class="erreur">{{ erreurs.nom }}</span>
            </div>
            <div class="champ">
              <label for="email">E-mail *</label>
              <input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="!!erreurs.email" :aria-describedby="erreurs.email ? 'e-email' : undefined">
              <span v-if="erreurs.email" id="e-email" class="erreur">{{ erreurs.email }}</span>
            </div>
          </div>
          <div class="champ">
            <label for="sujet">Sujet *</label>
            <input id="sujet" v-model="form.sujet" maxlength="150" required :aria-invalid="!!erreurs.sujet" :aria-describedby="erreurs.sujet ? 'e-sujet' : undefined">
            <span v-if="erreurs.sujet" id="e-sujet" class="erreur">{{ erreurs.sujet }}</span>
          </div>
          <div class="champ">
            <label for="message">Message *</label>
            <textarea id="message" v-model="form.message" rows="6" maxlength="3000" required :aria-invalid="!!erreurs.message" :aria-describedby="erreurs.message ? 'e-message' : undefined" />
            <span v-if="erreurs.message" id="e-message" class="erreur">{{ erreurs.message }}</span>
          </div>
          <div class="pot" aria-hidden="true"><label for="site_web">Ne pas remplir</label><input id="site_web" v-model="form.site_web" tabindex="-1" autocomplete="off"></div>
          <div class="champ">
            <label class="case"><input v-model="form.consentement_rgpd" type="checkbox" :aria-invalid="!!erreurs.consentement_rgpd">
              <span>J'accepte que mes données soient utilisées pour répondre à ma demande. *</span></label>
            <span v-if="erreurs.consentement_rgpd" class="erreur">{{ erreurs.consentement_rgpd }}</span>
          </div>
          <div class="envoi">
            <button class="btn btn-primaire" :disabled="etat === 'envoi'"><Icone nom="envoyer" :taille="18" />{{ etat === 'envoi' ? 'Envoi…' : 'Envoyer le message' }}</button>
            <p class="aide">Vos données servent uniquement à vous répondre.</p>
          </div>
        </form>
      </section>

      <!-- Coordonnées -->
      <aside class="coordonnees" aria-label="Coordonnées du campus">
        <a href="tel:+262262219045" class="tuile">
          <span class="tuile-icone"><Icone nom="telephone" :taille="22" /></span>
          <span><small>Téléphone</small><strong>02 62 21 90 45</strong></span>
        </a>
        <a href="mailto:campus@expernet.re" class="tuile">
          <span class="tuile-icone"><Icone nom="email" :taille="22" /></span>
          <span><small>E-mail</small><strong>campus@expernet.re</strong></span>
        </a>
        <div class="tuile">
          <span class="tuile-icone"><Icone nom="adresse" :taille="22" /></span>
          <span><small>Adresse</small><strong>Local 528, Parc 2000</strong>3 avenue Théodore Drouhet, 97420 Le Port</span>
        </div>
        <div class="tuile">
          <span class="tuile-icone"><Icone nom="accessibilite" :taille="22" /></span>
          <span><small>Accessibilité</small>Locaux accessibles aux PMR et PSH, places de parking dédiées. Référente handicap au 02 62 21 90 45.</span>
        </div>
        <img src="/images/campus/hero-informatique.webp" alt="Salle de cours du campus Expernet au Port" class="photo" loading="lazy" width="1920" height="547">
        <div class="raccourcis">
          <p><strong>Une réponse immédiate ?</strong> Consultez la <NuxtLink to="/faq">foire aux questions</NuxtLink>.</p>
          <p><strong>Salarié ou entreprise ?</strong> Découvrez la <NuxtLink to="/formation-continue">formation continue</NuxtLink>.</p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.disposition { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: var(--esp-5); align-items: start; margin-top: calc(-1 * var(--esp-5)); position: relative; z-index: 1; }
.formulaire { padding: 32px; }
.formulaire h2 { font-size: 1.45rem; margin-bottom: 4px; }
.formulaire > form > .aide { margin: 0 0 20px; }
.motifs { border: 0; padding: 0; margin: 0 0 20px; display: flex; flex-wrap: wrap; gap: 8px; }
.motifs legend { font-weight: 500; color: var(--encre); margin-bottom: 8px; padding: 0; }
.motif { padding: 8px 14px; border-radius: 999px; border: 1.5px solid var(--gris-bord); cursor: pointer; font-size: .9rem; transition: border-color .15s, background .15s, color .15s; }
.motif:hover { border-color: var(--primaire); }
.motif.choisi { background: var(--primaire-fonce); border-color: var(--primaire-fonce); color: #fff; }
.motif:has(input:focus-visible) { outline: 3px solid var(--orange); outline-offset: 2px; }
.deux-col { display: grid; grid-template-columns: 1fr 1fr; align-items: start; gap: 0 var(--esp-3); }
.envoi { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.envoi .aide { margin: 0; }
.pot { position: absolute; left: -9999px; }
.confirmation { text-align: center; padding: 32px 8px; display: grid; justify-items: center; gap: 8px; }
.confirmation h2 { margin: 0; }
.confirmation p { max-width: 46ch; color: var(--texte-doux); }
.confirmation-icone { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; background: #E2F3E8; color: var(--succes); }
.coordonnees { display: grid; gap: 12px; }
.tuile { display: grid; grid-template-columns: 44px 1fr; gap: 14px; align-items: start; padding: 16px 18px; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); color: var(--texte); text-decoration: none; font-size: .92rem; line-height: 1.45; }
a.tuile { transition: border-color .15s, transform .15s; }
a.tuile:hover { border-color: var(--primaire); transform: translateY(-2px); }
.tuile small { display: block; color: var(--texte-doux); font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
.tuile strong { display: block; color: var(--encre); font-size: 1.02rem; }
.tuile-icone { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.photo { border-radius: var(--rayon); aspect-ratio: 16 / 9; object-fit: cover; width: 100%; height: auto; margin-top: 4px; }
.raccourcis { background: var(--gris-fond); border-radius: var(--rayon); padding: 16px 18px; font-size: .92rem; }
.raccourcis p { margin: 0; }
.raccourcis p + p { margin-top: 8px; }
@media (max-width: 960px) { .disposition { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 600px) { .deux-col { grid-template-columns: 1fr; } .formulaire { padding: 20px; } .disposition { margin-top: var(--esp-4); } }
</style>
