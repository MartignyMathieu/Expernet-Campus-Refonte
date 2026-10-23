<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Émargement' })
const route = useRoute()
const api = useApi()
const code = ref(''); const signature = ref<string | null>(null)
const erreur = ref(''); const resultat = ref<any>(null); const envoi = ref(false)

const auj = new Date().toISOString().slice(0, 10)
const { data } = await useAsyncData('seance-emarg', () => api<any>('/api/planning', { query: { du: auj, au: auj } }))
const seance = computed(() => data.value?.seances.find((s: any) => String(s.id) === route.params.id))

const messages: Record<string, string> = {
  BAD_CODE: 'Code incorrect : vérifiez le code affiché par votre formateur.',
  WINDOW_CLOSED: 'L\'émargement est fermé pour cette séance.',
  ALREADY_SIGNED: 'Vous avez déjà signé cette séance.',
  NOT_ENROLLED: 'Vous n\'êtes pas inscrit à cette séance.',
}
async function signer() {
  erreur.value = ''
  if (!/^\d{6}$/.test(code.value)) { erreur.value = 'Le code comporte 6 chiffres.'; return }
  if (!signature.value) { erreur.value = 'Merci de signer dans le cadre.'; return }
  envoi.value = true
  try {
    await csrf()
    resultat.value = await api(`/api/seances/${route.params.id}/emargement/signer`, { method: 'POST', body: { code: code.value, signature: signature.value } })
  } catch (e: any) {
    erreur.value = messages[e?.data?.code] || e?.data?.message || 'Signature impossible.'
  } finally { envoi.value = false }
}
</script>

<template>
  <div class="page">
    <NuxtLink to="/ent/etudiant" class="lien-retour"><Icone nom="retour" :taille="16" /> Aujourd'hui</NuxtLink>
    <h1>Émargement</h1>
    <p v-if="seance" class="aide">{{ seance.module }} · {{ seance.heure_debut }}-{{ seance.heure_fin }} · {{ seance.formateur }}</p>

    <div v-if="resultat" class="carte confirmation" role="status">
      <h2 class="titre-icone"><Icone nom="valide" :taille="28" poids="fill" class="ok" /> Présence enregistrée</h2>
      <p>Signé le {{ new Date(resultat.signe_at).toLocaleString('fr-FR') }}</p>
      <p class="aide">Empreinte de preuve (SHA-256) :<br><code class="hash">{{ resultat.empreinte }}</code></p>
    </div>

    <form v-else class="carte" @submit.prevent="signer">
      <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
      <div class="champ">
        <label for="code">Code affiché en salle</label>
        <input id="code" v-model="code" class="code" inputmode="numeric" autocomplete="off" maxlength="6" placeholder="••••••" required>
      </div>
      <div class="champ">
        <span id="lbl-sig" class="label">Votre signature</span>
        <PaveSignature aria-labelledby="lbl-sig" @change="signature = $event" />
      </div>
      <p class="aide">En signant, vous attestez sur l'honneur votre présence. L'horodatage, votre adresse IP et une empreinte numérique sont enregistrés.</p>
      <button class="btn btn-primaire plein" :disabled="envoi">{{ envoi ? 'Envoi…' : 'Je signe ma présence' }}</button>
    </form>
  </div>
</template>

<style scoped>
.page { max-width: 560px; }
.lien-retour { display: inline-flex; align-items: center; gap: 6px; }
.titre-icone { display: flex; align-items: center; gap: 10px; }
.ok { color: var(--succes); }
.label { font-weight: 500; color: var(--encre); }
.code { font-size: 1.8rem; letter-spacing: .5em; text-align: center; font-weight: 700; }
.plein { width: 100%; }
.confirmation { border-left: 6px solid var(--succes); }
.hash { word-break: break-all; font-size: .8rem; }
</style>
