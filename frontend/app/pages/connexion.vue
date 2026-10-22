<script setup lang="ts">
definePageMeta({ layout: false })
useSeoMeta({ title: 'Connexion à l\'ENT', robots: 'noindex' })

const { connexion, codeMfa, accueilRole } = useAuth()
const route = useRoute()
const etape = ref<'identifiants' | 'mfa'>('identifiants')
const email = ref(''); const motDePasse = ref(''); const code = ref(''); const secours = ref(false)
const erreur = ref(''); const enCours = ref(false)

async function soumettre() {
  erreur.value = ''; enCours.value = true
  try {
    if (etape.value === 'identifiants') {
      const mfa = await connexion(email.value, motDePasse.value)
      if (mfa) { etape.value = 'mfa'; nextTick(() => document.getElementById('code')?.focus()); return }
    } else {
      await codeMfa(code.value.trim(), secours.value)
    }
    await navigateTo((route.query.retour as string) || accueilRole())
  } catch (e: any) {
    erreur.value = e?.status === 429 ? 'Trop de tentatives. Patientez une minute.'
      : e?.status === 409 && e?.data?.message ? e.data.message   // MFA illisible : l'administration doit la réinitialiser
      : etape.value === 'mfa' ? 'Code invalide ou expiré (un même code ne sert qu\'une fois : attendez le suivant).' : 'Identifiants incorrects.'
  } finally { enCours.value = false }
}
</script>

<template>
  <div class="connexion">
    <section class="visuel" aria-hidden="true">
      <img src="/images/campus/hero-informatique.webp" alt="" class="photo">
      <div class="voile" />
      <div class="visuel-contenu">
        <NuxtLink to="/" tabindex="-1"><span class="cartouche"><img src="/images/brand/logo-expernet.png" alt="" class="logo" width="900" height="241"></span></NuxtLink>
        <div>
          <p class="slogan">Votre campus,<br><strong>dans un seul espace.</strong></p>
          <ul class="atouts">
            <li><Icone nom="planning" :taille="22" poids="duotone" />Planning et séances du jour</li>
            <li><Icone nom="signature" :taille="22" poids="duotone" />Émargement signé en 10 secondes</li>
            <li><Icone nom="notes" :taille="22" poids="duotone" />Notes, compétences et assiduité</li>
            <li><Icone nom="messages" :taille="22" poids="duotone" />Messagerie avec formateurs et promotion</li>
          </ul>
        </div>
        <p class="pied-visuel">Expernet Campus · Le Port, La Réunion</p>
      </div>
    </section>
    <main class="panneau">
      <form class="formulaire" @submit.prevent="soumettre">
        <NuxtLink to="/" class="logo-mobile"><img src="/images/brand/logo-expernet.png" alt="Expernet Campus — retour au site" width="900" height="241"></NuxtLink>
        <Transition name="etape" mode="out-in">
        <div :key="etape" class="etape">
        <p class="sur-titre">{{ etape === 'identifiants' ? 'Espace numérique de travail' : 'Sécurité renforcée' }}</p>
        <h1>{{ etape === 'identifiants' ? 'Connexion' : 'Vérification en deux étapes' }}</h1>
        <p class="aide intro">{{ etape === 'identifiants' ? 'Apprenants, formateurs et équipe administrative.' : 'Une étape de plus pour protéger vos données.' }}</p>
        <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
        <template v-if="etape === 'identifiants'">
          <div class="champ"><label for="email">Adresse e-mail</label><input id="email" v-model="email" type="email" autocomplete="username" placeholder="prenom.nom@exemple.re" required></div>
          <div class="champ"><label for="mdp">Mot de passe</label><input id="mdp" v-model="motDePasse" type="password" autocomplete="current-password" required></div>
        </template>
        <template v-else>
          <p class="aide">{{ secours ? 'Saisissez l\'un de vos codes de secours.' : 'Ouvrez votre application d\'authentification (Google Authenticator, Microsoft Authenticator…) et saisissez le code à 6 chiffres.' }}</p>
          <div class="champ">
            <label for="code">{{ secours ? 'Code de secours' : 'Code à 6 chiffres' }}</label>
            <input id="code" v-model="code" class="code" :inputmode="secours ? 'text' : 'numeric'" autocomplete="one-time-code" :maxlength="secours ? 21 : 6" required>
          </div>
          <button type="button" class="lien" @click="secours = !secours; code = ''">{{ secours ? 'Utiliser l\'application' : 'Utiliser un code de secours' }}</button>
        </template>
        <button class="btn btn-bleu plein" :disabled="enCours"><span v-if="enCours" class="chargement" aria-hidden="true" />{{ enCours ? 'Vérification…' : etape === 'mfa' ? 'Valider le code' : 'Se connecter' }}</button>
        <p class="securite"><Icone nom="cadenas" :taille="15" /> Connexion chiffrée · double authentification pour les comptes formateur et administrateur</p>
        <NuxtLink to="/" class="retour"><Icone nom="retour" :taille="15" /> Retour au site Expernet</NuxtLink>
        </div>
        </Transition>
      </form>
    </main>
  </div>
</template>

<style scoped>
.connexion { display: grid; grid-template-columns: 1.1fr 1fr; min-height: 100vh; }
.visuel { position: relative; overflow: hidden; color: #fff; }
.photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; animation: lent-zoom 14s ease-out both; }
@keyframes lent-zoom { from { transform: scale(1.1); } to { transform: scale(1); } }
@keyframes glisser { from { opacity: 0; transform: translateX(-24px); } to { opacity: 1; transform: none; } }
.visuel-contenu > * { animation: glisser .7s cubic-bezier(.2,.7,.2,1) both; }
.visuel-contenu > :nth-child(2) { animation-delay: .12s; }
.visuel-contenu > :nth-child(3) { animation-delay: .5s; }
.atouts li { animation: glisser .6s cubic-bezier(.2,.7,.2,1) both; }
.atouts li:nth-child(1) { animation-delay: .25s; } .atouts li:nth-child(2) { animation-delay: .33s; }
.atouts li:nth-child(3) { animation-delay: .41s; } .atouts li:nth-child(4) { animation-delay: .49s; }
/* Formulaire : apparition échelonnée, puis transition entre identifiants et code MFA */
.etape > * { animation: monter .5s cubic-bezier(.2,.7,.2,1) both; }
.etape > :nth-child(2) { animation-delay: .05s; } .etape > :nth-child(3) { animation-delay: .1s; }
.etape > :nth-child(4) { animation-delay: .15s; } .etape > :nth-child(5) { animation-delay: .2s; }
.etape > :nth-child(6) { animation-delay: .25s; } .etape > :nth-child(n+7) { animation-delay: .3s; }
.etape-leave-active { transition: opacity .18s ease, transform .18s ease; }
.etape-leave-to { opacity: 0; transform: translateX(-16px); }
.plein .chargement { margin-right: 4px; }
.etape > .message-erreur { animation: secouer .4s ease; }
.voile { position: absolute; inset: 0; background: linear-gradient(160deg, rgba(0,155,221,.88) 0%, rgba(13,90,138,.9) 45%, rgba(8,32,52,.96) 100%); }
.visuel-contenu { position: relative; z-index: 1; height: 100%; padding: 48px; display: flex; flex-direction: column; justify-content: space-between; }
.cartouche { display: inline-block; background: #fff; padding: 12px 18px; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
.logo { width: 220px; height: auto; display: block; }
.slogan { font-family: var(--police-titre); font-size: clamp(1.8rem, 3vw, 2.6rem); line-height: 1.2; margin: 0 0 28px; }
.slogan strong { color: var(--jaune); }
.atouts { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
.atouts li { display: flex; gap: 14px; align-items: center; padding: 6px 0; max-width: 380px; }
.atouts :deep(.icone) { color: var(--jaune); }
.pied-visuel { font-size: .85rem; opacity: .8; margin: 0; }
.panneau { display: grid; place-items: center; padding: var(--esp-5) var(--esp-3); background: #fff; min-width: 0; }
.formulaire { width: 100%; max-width: 420px; min-width: 0; }
.logo-mobile { display: none; }
.logo-mobile img { width: 200px; height: auto; margin-bottom: 28px; }
h1 { font-size: 2.2rem; margin-bottom: 6px; }
.intro { margin: 0 0 28px; }
.code { font-size: 1.6rem; letter-spacing: .4em; text-align: center; font-weight: 700; }
.plein { width: 100%; margin-top: 8px; border-radius: var(--rayon-s); min-height: 50px; }
.lien { background: none; border: 0; color: var(--primaire-texte); text-decoration: underline; cursor: pointer; font: inherit; padding: 0; margin-bottom: 12px; }
.securite { font-size: .82rem; color: var(--texte-doux); margin: 18px 0 0; text-align: center; }
.retour { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 16px; font-size: .92rem; }
@media (max-width: 900px) { .connexion { grid-template-columns: 1fr; } .visuel { display: none; } .logo-mobile { display: block; } }
</style>
