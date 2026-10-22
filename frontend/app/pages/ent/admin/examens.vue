<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Examens & jurys' })
const api = useApi()
const { user } = useAuth()
const { data, refresh } = await useAsyncData('examens-admin', () => api<any[]>('/api/examens'))
const { data: users } = await useAsyncData('users-jury', () => api<any>('/api/admin/utilisateurs'))
const jurables = computed(() => (users.value?.data || []).filter((u: any) => u.role !== 'etudiant'))
const form = reactive({ promotion_id: user.value?.promotions[0]?.id, intitule: '', debut: '', fin: '', lieu: '' })
const jury = reactive<Record<number, { president: number | null; membres: number[] }>>({})
const msg = ref(''); const err = ref('')
async function planifier() {
  msg.value = ''; err.value = ''
  try { await csrf(); await api('/api/examens', { method: 'POST', body: form }); msg.value = 'Session planifiée.'; await refresh() }
  catch (e) { err.value = Object.values(erreursValidation(e))[0] || 'Erreur' }
}
function etat(s: any) {
  return jury[s.id] ||= { president: s.jury.find((j: any) => j.pivot.fonction === 'president')?.id || null, membres: s.jury.filter((j: any) => j.pivot.fonction === 'membre').map((j: any) => j.id) }
}
async function enregistrerJury(s: any) {
  const j = etat(s); msg.value = ''; err.value = ''
  const membres = [{ user_id: j.president, fonction: 'president' }, ...j.membres.filter((m) => m !== j.president).map((m) => ({ user_id: m, fonction: 'membre' }))]
  try { await csrf(); await api(`/api/examens/${s.id}/jury`, { method: 'PUT', body: { membres } }); msg.value = 'Jury enregistré.'; await refresh() }
  catch (e: any) { err.value = e?.data?.message || 'Jury invalide.' }
}
</script>
<template>
  <div>
    <h1>Examens & composition des jurys</h1>
    <p v-if="msg" class="message message-succes" role="status">{{ msg }}</p>
    <p v-if="err" class="message message-erreur" role="alert">{{ err }}</p>
    <div class="grille deux">
      <div class="grille">
        <article v-for="s in data" :key="s.id" class="carte">
          <h2 class="h3">{{ s.intitule }}</h2>
          <p class="aide">{{ s.promotion.libelle }} · {{ new Date(s.debut).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' }) }} · {{ s.lieu }}</p>
          <div class="champ"><label :for="`p-${s.id}`">Président du jury</label>
            <select :id="`p-${s.id}`" v-model="etat(s).president"><option v-for="u in jurables" :key="u.id" :value="u.id">{{ u.prenom }} {{ u.nom }}</option></select></div>
          <fieldset><legend>Membres</legend>
            <label v-for="u in jurables" :key="u.id" class="case"><input v-model="etat(s).membres" type="checkbox" :value="u.id"> {{ u.prenom }} {{ u.nom }}</label></fieldset>
          <button class="btn btn-bleu" @click="enregistrerJury(s)">Enregistrer le jury</button>
          <details><summary>Grille de compétences</summary><GrilleCompetences :session-id="s.id" /></details>
        </article>
      </div>
      <form class="carte" @submit.prevent="planifier">
        <h2>Planifier une session</h2>
        <div class="champ"><label for="e-promo">Promotion</label><select id="e-promo" v-model="form.promotion_id"><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select></div>
        <div class="champ"><label for="e-int">Intitulé</label><input id="e-int" v-model="form.intitule" required></div>
        <div class="champ"><label for="e-deb">Début</label><input id="e-deb" v-model="form.debut" type="datetime-local" required></div>
        <div class="champ"><label for="e-fin">Fin</label><input id="e-fin" v-model="form.fin" type="datetime-local" required></div>
        <div class="champ"><label for="e-lieu">Lieu</label><input id="e-lieu" v-model="form.lieu"></div>
        <button class="btn btn-primaire">Planifier</button>
      </form>
    </div>
  </div>
</template>
<style scoped>
.deux { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: start; }
.h3 { font-size: 1.1rem; }
fieldset { border: 0; padding: 0; margin: 0 0 12px; display: grid; gap: 4px; }
legend { font-weight: 500; margin-bottom: 4px; }
details { margin-top: 12px; }
@media (max-width: 1240px) { .deux { grid-template-columns: minmax(0, 1fr); } }
</style>
