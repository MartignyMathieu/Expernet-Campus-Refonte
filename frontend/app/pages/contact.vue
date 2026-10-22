<script setup lang="ts">
useSeoMeta({ title: 'Contact', description: 'Contactez Expernet Campus au Port (La Réunion) : informations sur les formations en alternance.' })
const api = useApi()
const form = reactive({ nom: '', email: '', sujet: '', message: '', consentement_rgpd: false, site_web: '' })
const erreurs = ref<Record<string, string>>({})
const etat = ref<'idle' | 'envoi' | 'ok' | 'ko'>('idle')
async function envoyer() {
  etat.value = 'envoi'; erreurs.value = {}
  try { await csrf(); await api('/api/contact', { method: 'POST', body: form }); etat.value = 'ok' }
  catch (e) { etat.value = 'ko'; erreurs.value = erreursValidation(e) }
}
</script>

<template>
  <div class="conteneur page">
    <div class="grille-contact">
    <div class="infos">
      <p class="sur-titre">Contact</p>
      <h1>Parlons de votre projet</h1>
      <p>Une question sur une formation, l'alternance ou le financement ? L'équipe du campus vous répond.</p>
      <ul class="coord">
        <li><Icone nom="telephone" :taille="22" poids="duotone" /><strong>Téléphone</strong><a href="tel:+262262219045">02 62 21 90 45</a></li>
        <li><Icone nom="email" :taille="22" poids="duotone" /><strong>E-mail</strong><a href="mailto:campus@expernet.re">campus@expernet.re</a></li>
        <li><Icone nom="adresse" :taille="22" poids="duotone" /><strong>Adresse</strong>Local 528, Parc 2000, 3 avenue Théodore Drouhet, 97420 Le Port</li>
      </ul>
      <img src="/images/campus/hero-informatique.webp" alt="Salle de cours du campus Expernet au Port" class="photo" loading="lazy">
    </div>
    <div class="carte formulaire">
      <p v-if="etat === 'ok'" class="message message-succes" role="status">Merci, votre message a bien été envoyé.</p>
      <form v-else novalidate @submit.prevent="envoyer">
        <p v-if="etat === 'ko'" class="message message-erreur" role="alert">Merci de corriger les champs signalés.</p>
        <div v-for="c in [{ k: 'nom', l: 'Nom complet', t: 'text', a: 'name' }, { k: 'email', l: 'E-mail', t: 'email', a: 'email' }, { k: 'sujet', l: 'Sujet', t: 'text', a: 'off' }]" :key="c.k" class="champ">
          <label :for="c.k">{{ c.l }} *</label>
          <input :id="c.k" v-model="(form as any)[c.k]" :type="c.t" :autocomplete="c.a" required :aria-invalid="!!erreurs[c.k]">
          <span v-if="erreurs[c.k]" class="erreur">{{ erreurs[c.k] }}</span>
        </div>
        <div class="champ">
          <label for="message">Message *</label>
          <textarea id="message" v-model="form.message" rows="5" required :aria-invalid="!!erreurs.message" />
          <span v-if="erreurs.message" class="erreur">{{ erreurs.message }}</span>
        </div>
        <div class="champ">
          <label class="case"><input v-model="form.consentement_rgpd" type="checkbox"> <span>J'accepte que mes données soient utilisées pour me répondre. *</span></label>
          <span v-if="erreurs.consentement_rgpd" class="erreur">{{ erreurs.consentement_rgpd }}</span>
        </div>
        <button class="btn btn-primaire" :disabled="etat === 'envoi'">Envoyer</button>
      </form>
    </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding-top: var(--esp-5); }
.grille-contact { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: var(--esp-5); align-items: start; }
.coord { list-style: none; padding: 0; display: grid; gap: 14px; margin: 24px 0; }
.coord li { display: grid; grid-template-columns: 34px 1fr; column-gap: 10px; }
.coord li > :deep(.icone) { grid-row: span 2; color: var(--primaire); width: 34px; height: 34px; padding: 6px; background: #E1F3FB; border-radius: 10px; }
.photo { border-radius: var(--rayon); aspect-ratio: 16 / 9; object-fit: cover; width: 100%; }
@media (max-width: 860px) { .grille-contact { grid-template-columns: minmax(0, 1fr); } }
.grille-contact > * { min-width: 0; }
</style>
