<!-- Sécurité du compte (dans « Mon profil ») : double authentification TOTP via Laravel Fortify. -->
<script setup lang="ts">
const { user, charger, accueilRole } = useAuth()
const api = useApi()

const qr = ref(''); const secret = ref(''); const code = ref(''); const codesSecours = ref<string[]>([])
const erreur = ref(''); const etape = ref<'depart' | 'scan' | 'fini'>(user.value?.mfa.active ? 'fini' : 'depart')
const etaitBloque = ref(!!(user.value?.mfa.obligatoire && !user.value?.mfa.active))

async function activer() {
  erreur.value = ''; info.value = ''
  await csrf()
  await api('/api/auth/user/two-factor-authentication', { method: 'POST' })
  const q: any = await api('/api/auth/user/two-factor-qr-code')
  qr.value = q.svg
  const s: any = await api('/api/auth/user/two-factor-secret-key').catch(() => ({}))
  secret.value = s.secretKey || ''
  etape.value = 'scan'
}
async function confirmer() {
  erreur.value = ''
  try {
    await api('/api/auth/user/confirmed-two-factor-authentication', { method: 'POST', body: { code: code.value.trim() } })
    codesSecours.value = await api<string[]>('/api/auth/user/two-factor-recovery-codes')
    await charger(); etape.value = 'fini'
  } catch { erreur.value = 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.' }
}

// Désactivation par l'utilisateur lui-même (mot de passe exigé)
const desactivation = ref(false); const mdp = ref(''); const erreurDesactivation = ref(''); const info = ref(''); const enCours = ref(false)
function ouvrirDesactivation() { desactivation.value = true; nextTick(() => document.getElementById('mdp-desactivation')?.focus()) }
async function desactiver() {
  erreurDesactivation.value = ''; enCours.value = true
  try {
    await csrf()
    const r: any = await api('/api/me/mfa/desactiver', { method: 'POST', body: { password: mdp.value } })
    await charger()
    info.value = r.message; mdp.value = ''; desactivation.value = false; codesSecours.value = []; code.value = ''
    etape.value = 'depart'; etaitBloque.value = !!user.value?.mfa.obligatoire
  } catch (e: any) {
    erreurDesactivation.value = e?.status === 429 ? 'Trop de tentatives. Patientez une minute.' : erreursValidation(e).password || e?.data?.message || 'Désactivation impossible.'
  } finally { enCours.value = false }
}
</script>

<template>
  <section id="securite" class="carte" aria-labelledby="t-securite">
    <h2 id="t-securite" class="titre-icone"><Icone nom="securite" :taille="22" /> Sécurité du compte</h2>
    <p v-if="user?.mfa.obligatoire && !user?.mfa.active" class="message message-erreur" role="alert">
      Votre rôle donne accès à des données sensibles : la double authentification est <strong>obligatoire</strong> avant de continuer.
    </p>
    <p v-if="info" class="message message-succes" role="status">{{ info }}</p>

    <template v-if="etape === 'depart'">
      <h3>Double authentification</h3>
      <p>À chaque connexion, un code à 6 chiffres généré par votre téléphone vous sera demandé en plus du mot de passe.</p>
      <button class="btn btn-bleu" @click="activer">Activer la double authentification</button>
    </template>

    <template v-else-if="etape === 'scan'">
      <h3>1. Scannez ce QR code</h3>
      <p>Avec Google Authenticator, Microsoft Authenticator ou toute application TOTP.</p>
      <div class="qr" v-html="qr" role="img" aria-label="QR code de configuration de l'application d'authentification" />
      <p v-if="secret" class="aide">Saisie manuelle : <code>{{ secret }}</code></p>
      <h3>2. Saisissez le code affiché</h3>
      <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
      <form class="ligne" @submit.prevent="confirmer">
        <div class="champ"><label for="code">Code à 6 chiffres</label><input id="code" v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></div>
        <button class="btn btn-bleu">Confirmer</button>
      </form>
    </template>

    <template v-else>
      <p class="etat"><Icone nom="valide" :taille="24" poids="fill" class="ok" /> <strong>Double authentification active</strong></p>
      <div v-if="codesSecours.length">
        <p><strong>Conservez ces codes de secours</strong> en lieu sûr : chacun permet une connexion si vous perdez votre téléphone. Ils ne seront plus affichés.</p>
        <ul class="codes"><li v-for="c in codesSecours" :key="c"><code>{{ c }}</code></li></ul>
        <NuxtLink v-if="etaitBloque" :to="accueilRole()" class="btn btn-primaire">Continuer vers mon espace</NuxtLink>
      </div>

      <div class="desactiver">
        <h3>Changer de téléphone ou désactiver</h3>
        <p class="aide">La double authentification sera retirée de votre compte.
          <template v-if="user?.mfa.obligatoire"><strong>Elle est obligatoire pour votre rôle</strong> : vous devrez la réactiver (nouveau QR code) pour accéder à votre espace.</template>
          <template v-else>Vous pourrez la réactiver à tout moment.</template></p>
        <button v-if="!desactivation" type="button" class="btn btn-secondaire" @click="ouvrirDesactivation">Désactiver ma double authentification</button>
        <form v-else class="ligne" @submit.prevent="desactiver">
          <div class="champ">
            <label for="mdp-desactivation">Confirmez avec votre mot de passe</label>
            <input id="mdp-desactivation" v-model="mdp" type="password" autocomplete="current-password" required :aria-invalid="!!erreurDesactivation" aria-describedby="err-desactivation">
          </div>
          <button class="btn btn-danger" :disabled="enCours">{{ enCours ? 'Désactivation…' : 'Désactiver' }}</button>
          <button type="button" class="btn btn-secondaire" @click="desactivation = false; mdp = ''; erreurDesactivation = ''">Annuler</button>
        </form>
        <p v-if="erreurDesactivation" id="err-desactivation" class="erreur" role="alert">{{ erreurDesactivation }}</p>
      </div>
    </template>
  </section>
</template>

<style scoped>
#securite { scroll-margin-top: 90px; }
.titre-icone { display: flex; align-items: center; gap: 8px; }
h3 { margin: var(--esp-3) 0 6px; font-size: 1.05rem; }
.etat { display: flex; align-items: center; gap: 8px; margin: 0 0 var(--esp-3); }
.ok { color: var(--succes); }
.qr { background: #fff; padding: 12px; display: inline-block; border: 1px solid var(--gris-bord); border-radius: 8px; }
.ligne { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.ligne .champ { margin: 0; }
.codes { columns: 2; padding-left: 20px; }
.desactiver { margin-top: var(--esp-4); padding-top: var(--esp-3); border-top: 1px solid var(--gris-bord); }
.desactiver h3 { margin-top: 0; }
.btn-danger { background: var(--alerte); color: #fff; border-color: var(--alerte); }
.erreur { color: var(--alerte); margin: 8px 0 0; }
</style>
