<script setup lang="ts">
// Enrôlement à la double authentification (TOTP, RFC 6238) via Laravel Fortify.
definePageMeta({ layout: false, middleware: 'auth' })
useSeoMeta({ title: 'Sécurité du compte' })
const { user, charger, accueilRole } = useAuth()
const api = useApi()
const layout = computed(() => ({ admin: 'ent-admin', formateur: 'ent-formateur', etudiant: 'ent-etudiant' }[user.value?.role || 'etudiant']))

const qr = ref(''); const secret = ref(''); const code = ref(''); const codesSecours = ref<string[]>([])
const erreur = ref(''); const etape = ref<'depart' | 'scan' | 'fini'>(user.value?.mfa.active ? 'fini' : 'depart')

async function activer() {
  erreur.value = ''
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
</script>

<template>
  <NuxtLayout :name="layout">
    <h1>Sécurité du compte</h1>
    <div class="carte bloc">
      <p v-if="user?.mfa.obligatoire && !user?.mfa.active" class="message message-erreur" role="alert">
        Votre rôle donne accès à des données sensibles : la double authentification est <strong>obligatoire</strong> avant de continuer.
      </p>
      <template v-if="etape === 'depart'">
        <h2>Double authentification</h2>
        <p>À chaque connexion, un code à 6 chiffres généré par votre téléphone vous sera demandé en plus du mot de passe.</p>
        <button class="btn btn-bleu" @click="activer">Activer la double authentification</button>
      </template>
      <template v-else-if="etape === 'scan'">
        <h2>1. Scannez ce QR code</h2>
        <p>Avec Google Authenticator, Microsoft Authenticator ou toute application TOTP.</p>
        <div class="qr" v-html="qr" role="img" aria-label="QR code de configuration de l'application d'authentification" />
        <p v-if="secret" class="aide">Saisie manuelle : <code>{{ secret }}</code></p>
        <h2>2. Saisissez le code affiché</h2>
        <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
        <form class="ligne" @submit.prevent="confirmer">
          <div class="champ"><label for="code">Code à 6 chiffres</label><input id="code" v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></div>
          <button class="btn btn-bleu">Confirmer</button>
        </form>
      </template>
      <template v-else>
        <h2 class="titre-icone"><Icone nom="valide" :taille="28" poids="fill" class="ok" /> Double authentification active</h2>
        <div v-if="codesSecours.length">
          <p><strong>Conservez ces codes de secours</strong> en lieu sûr : chacun permet une connexion si vous perdez votre téléphone. Ils ne seront plus affichés.</p>
          <ul class="codes"><li v-for="c in codesSecours" :key="c"><code>{{ c }}</code></li></ul>
        </div>
        <NuxtLink :to="accueilRole()" class="btn btn-primaire">Continuer vers mon espace</NuxtLink>
      </template>
    </div>
  </NuxtLayout>
</template>

<style scoped>
.bloc { max-width: 640px; }
.titre-icone { display: flex; align-items: center; gap: 10px; }
.ok { color: var(--succes); }
.qr { background: #fff; padding: 12px; display: inline-block; border: 1px solid var(--gris-bord); border-radius: 8px; }
.ligne { display: flex; gap: 12px; align-items: flex-end; }
.ligne .champ { margin: 0; }
.codes { columns: 2; padding-left: 20px; }
</style>
