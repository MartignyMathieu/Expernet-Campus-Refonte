<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Utilisateurs' })
const api = useApi()
const { user: moi } = useAuth()
const { data, refresh } = await useAsyncData('users', () => api<any>('/api/admin/utilisateurs'))
const form = reactive({ prenom: '', nom: '', email: '', role: 'etudiant', password: '' })
const erreurs = ref<Record<string, string>>({}); const ok = ref('')
async function creer() {
  erreurs.value = {}; ok.value = ''
  try { await csrf(); await api('/api/admin/utilisateurs', { method: 'POST', body: form }); ok.value = 'Compte créé.'; Object.assign(form, { prenom: '', nom: '', email: '', password: '' }); await refresh() }
  catch (e) { erreurs.value = erreursValidation(e) }
}
async function basculer(u: any) { await csrf(); await api(`/api/admin/utilisateurs/${u.id}`, { method: 'PATCH', body: { actif: !u.actif } }); await refresh() }
async function changerRole(u: any, role: string) { await csrf(); await api(`/api/admin/utilisateurs/${u.id}`, { method: 'PATCH', body: { role } }); await refresh() }
</script>
<template>
  <div>
    <h1>Utilisateurs & rôles</h1>
    <div class="grille deux">
      <div class="carte tableau-scroll">
        <table class="tableau">
          <caption class="sr-only">Comptes utilisateurs</caption>
          <thead><tr><th scope="col">Nom</th><th scope="col">E-mail</th><th scope="col">Rôle</th><th scope="col">MFA</th><th scope="col">État</th></tr></thead>
          <tbody>
            <tr v-for="u in data?.data" :key="u.id">
              <td>{{ u.prenom }} {{ u.nom }}</td><td>{{ u.email }}</td>
              <td>
                <label class="sr-only" :for="`r-${u.id}`">Rôle de {{ u.prenom }}</label>
                <select :id="`r-${u.id}`" :value="u.role" :disabled="u.id === moi?.id" @change="changerRole(u, ($event.target as HTMLSelectElement).value)">
                  <option value="etudiant">Étudiant</option><option value="formateur">Formateur</option><option value="admin">Admin</option>
                </select>
              </td>
              <td><span class="pastille" :class="u.mfa ? 'pastille-verte' : ''">{{ u.mfa ? 'Active' : 'Inactive' }}</span></td>
              <td><button class="btn btn-secondaire petit" :disabled="u.id === moi?.id" @click="basculer(u)">{{ u.actif ? 'Désactiver' : 'Réactiver' }}</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <form class="carte" @submit.prevent="creer">
        <h2>Nouveau compte</h2>
        <p v-if="ok" class="message message-succes" role="status">{{ ok }}</p>
        <div v-for="c in ['prenom', 'nom', 'email']" :key="c" class="champ">
          <label :for="'n-' + c">{{ { prenom: 'Prénom', nom: 'Nom', email: 'E-mail' }[c] }}</label>
          <input :id="'n-' + c" v-model="(form as any)[c]" :type="c === 'email' ? 'email' : 'text'" required :aria-invalid="!!erreurs[c]">
          <span v-if="erreurs[c]" class="erreur">{{ erreurs[c] }}</span>
        </div>
        <div class="champ"><label for="n-role">Rôle</label><select id="n-role" v-model="form.role"><option value="etudiant">Étudiant</option><option value="formateur">Formateur</option><option value="admin">Administrateur</option></select></div>
        <div class="champ">
          <label for="n-mdp">Mot de passe provisoire</label>
          <input id="n-mdp" v-model="form.password" type="password" autocomplete="new-password" required aria-describedby="a-mdp" :aria-invalid="!!erreurs.password">
          <span id="a-mdp" :class="erreurs.password ? 'erreur' : 'aide'">{{ erreurs.password || '12 caractères min., majuscule, minuscule et chiffre.' }}</span>
        </div>
        <button class="btn btn-primaire">Créer le compte</button>
      </form>
    </div>
  </div>
</template>
<style scoped>
.deux { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: start; }
.petit { min-height: 36px; padding: 6px 12px; }
select { font: inherit; padding: 6px; border-radius: 6px; }
@media (max-width: 1240px) { .deux { grid-template-columns: minmax(0, 1fr); } }
</style>
