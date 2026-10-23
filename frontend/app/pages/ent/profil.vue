<script setup lang="ts">
// Mon profil : photo, coordonnées, mot de passe, et un résumé propre à chaque rôle.
definePageMeta({ layout: false, middleware: 'auth' })
useSeoMeta({ title: 'Mon profil' })
const { user, charger, peut } = useAuth()
const api = useApi()
const layout = computed(() => ({ admin: 'ent-admin', formateur: 'ent-formateur', etudiant: 'ent-etudiant' }[user.value?.role || 'etudiant']))
const libelleRole = computed(() => ({ admin: 'Administrateur', formateur: 'Formateur', etudiant: 'Apprenant' } as any)[user.value?.role || ''] || '')
const nomComplet = computed(() => `${user.value?.prenom || ''} ${user.value?.nom || ''}`.trim())

// ---------- Photo ----------
const champPhoto = ref<HTMLInputElement | null>(null)
const apercuLocal = ref<string | null>(null)
const photo = ref<File | null>(null)
const erreurPhoto = ref(''); const infoPhoto = ref(''); const envoiPhoto = ref(false); const survol = ref(false)
const TYPES = ['image/jpeg', 'image/png', 'image/webp']

function choisir(f?: File | null) {
  erreurPhoto.value = ''; infoPhoto.value = ''
  if (!f) return
  if (!TYPES.includes(f.type)) { erreurPhoto.value = 'Formats acceptés : JPEG, PNG ou WebP.'; return }
  if (f.size > 4 * 1024 * 1024) { erreurPhoto.value = 'La photo ne doit pas dépasser 4 Mo.'; return }
  if (apercuLocal.value) URL.revokeObjectURL(apercuLocal.value)
  photo.value = f; apercuLocal.value = URL.createObjectURL(f)
}
function deposerFichier(e: DragEvent) { survol.value = false; choisir(e.dataTransfer?.files?.[0]) }
function annulerPhoto() {
  if (apercuLocal.value) URL.revokeObjectURL(apercuLocal.value)
  apercuLocal.value = null; photo.value = null
  if (champPhoto.value) champPhoto.value.value = ''
}
async function enregistrerPhoto() {
  if (!photo.value) return
  envoiPhoto.value = true; erreurPhoto.value = ''
  try {
    const fd = new FormData(); fd.append('photo', photo.value)
    await csrf(); const r: any = await api('/api/me/avatar', { method: 'POST', body: fd })
    await charger(); annulerPhoto(); infoPhoto.value = r.message
  } catch (e: any) { erreurPhoto.value = erreursValidation(e).photo || e?.data?.message || 'Envoi impossible.' } finally { envoiPhoto.value = false }
}
async function supprimerPhoto() {
  envoiPhoto.value = true; erreurPhoto.value = ''
  try { await csrf(); const r: any = await api('/api/me/avatar', { method: 'DELETE' }); await charger(); infoPhoto.value = r.message } catch { erreurPhoto.value = 'Suppression impossible.' } finally { envoiPhoto.value = false }
}
onBeforeUnmount(() => { if (apercuLocal.value) URL.revokeObjectURL(apercuLocal.value) })

// ---------- Coordonnées ----------
const telephone = ref(user.value?.telephone || ''); const bio = ref(user.value?.bio || '')
const erreursProfil = ref<Record<string, string>>({}); const infoProfil = ref(''); const envoiProfil = ref(false)
async function enregistrerProfil() {
  erreursProfil.value = {}; infoProfil.value = ''; envoiProfil.value = true
  try {
    await csrf(); const r: any = await api('/api/me/profil', { method: 'PATCH', body: { telephone: telephone.value || null, bio: bio.value || null } })
    await charger(); infoProfil.value = r.message
  } catch (e: any) { erreursProfil.value = erreursValidation(e) } finally { envoiProfil.value = false }
}

// ---------- Mot de passe ----------
const mdp = reactive({ actuel: '', nouveau: '', confirmation: '' })
const erreursMdp = ref<Record<string, string>>({}); const infoMdp = ref(''); const envoiMdp = ref(false)
const criteres = computed(() => [
  { ok: mdp.nouveau.length >= 12, libelle: '12 caractères minimum' },
  { ok: /[a-z]/.test(mdp.nouveau) && /[A-Z]/.test(mdp.nouveau), libelle: 'majuscules et minuscules' },
  { ok: /\d/.test(mdp.nouveau), libelle: 'au moins un chiffre' },
])
async function changerMdp() {
  erreursMdp.value = {}; infoMdp.value = ''; envoiMdp.value = true
  try {
    await csrf()
    const r: any = await api('/api/me/mot-de-passe', { method: 'PUT', body: { mot_de_passe_actuel: mdp.actuel, password: mdp.nouveau, password_confirmation: mdp.confirmation } })
    mdp.actuel = ''; mdp.nouveau = ''; mdp.confirmation = ''; infoMdp.value = r.message
  } catch (e: any) {
    erreursMdp.value = e?.status === 429 ? { password: 'Trop de tentatives. Patientez une minute.' } : erreursValidation(e)
  } finally { envoiMdp.value = false }
}

// ---------- Résumé par rôle ----------
const { data: assiduite } = await useAsyncData('profil-assiduite', () => peut('emargement.sign') ? api<any[]>('/api/me/assiduite') : Promise.resolve([]))
const { data: promosSuivies } = await useAsyncData('profil-promos', () => user.value?.role === 'formateur' ? api<any[]>('/api/formateur/promotions') : Promise.resolve([]))
const dateFr = (d?: string | null) => d ? new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : '—'
</script>

<template>
  <NuxtLayout :name="layout">
    <h1>Mon profil</h1>

    <div class="profil-grille">
      <!-- Carte d'identité -->
      <section class="carte identite" aria-labelledby="t-identite">
        <h2 id="t-identite" class="sr-only">Identité</h2>
        <div class="photo-zone" :class="{ survol }" @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposerFichier">
          <img v-if="apercuLocal" :src="apercuLocal" alt="Aperçu de la nouvelle photo" class="grande">
          <Avatar v-else class="grande" :url="user?.avatar_url" :nom="nomComplet" :taille="148" />
          <button type="button" class="bouton-photo" aria-label="Choisir une photo de profil" @click="champPhoto?.click()"><Icone nom="photo" :taille="20" /></button>
        </div>
        <input ref="champPhoto" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="Photo de profil" @change="choisir(($event.target as HTMLInputElement).files?.[0])">

        <p class="nom-profil">{{ nomComplet }}</p>
        <p class="role-profil"><span class="pastille">{{ libelleRole }}</span></p>

        <p v-if="erreurPhoto" class="message message-erreur" role="alert">{{ erreurPhoto }}</p>
        <p v-if="infoPhoto" class="message message-succes" role="status">{{ infoPhoto }}</p>
        <div v-if="photo" class="actions-photo">
          <button class="btn btn-primaire" :disabled="envoiPhoto" @click="enregistrerPhoto"><span v-if="envoiPhoto" class="chargement" aria-hidden="true" />Enregistrer la photo</button>
          <button class="btn btn-secondaire" :disabled="envoiPhoto" @click="annulerPhoto">Annuler</button>
        </div>
        <div v-else class="actions-photo">
          <button class="btn btn-secondaire" @click="champPhoto?.click()"><Icone nom="photo" :taille="18" /> {{ user?.avatar_url ? 'Changer la photo' : 'Ajouter une photo' }}</button>
          <button v-if="user?.avatar_url" class="btn btn-secondaire" :disabled="envoiPhoto" @click="supprimerPhoto"><Icone nom="supprimer" :taille="18" /> Retirer</button>
        </div>
        <p class="aide">JPEG, PNG ou WebP, 4 Mo maximum. Glissez une image sur la photo ou cliquez sur l'appareil. Elle est recadrée en carré et visible des seuls membres de l'ENT.</p>

        <dl class="infos">
          <div><dt><Icone nom="email" :taille="18" /> E-mail</dt><dd>{{ user?.email }}</dd></div>
          <div v-if="user?.promotions.length"><dt><Icone nom="groupe" :taille="18" /> {{ user?.role === 'etudiant' ? 'Promotion' : 'Promotions' }}</dt><dd>{{ user?.promotions.map((p) => p.libelle).join(', ') }}</dd></div>
          <div><dt><Icone nom="session" :taille="18" /> Membre depuis</dt><dd>{{ dateFr(user?.membre_depuis) }}</dd></div>
          <div><dt><Icone nom="securite" :taille="18" /> Double authentification</dt>
            <dd><span class="pastille" :class="user?.mfa.active ? 'pastille-verte' : 'pastille-rouge'">{{ user?.mfa.active ? 'Active' : 'Inactive' }}</span>
              <NuxtLink to="/ent/securite" class="lien-discret">Gérer</NuxtLink></dd></div>
        </dl>
      </section>

      <div class="colonne">
        <!-- Résumé selon le rôle -->
        <section v-if="assiduite?.length" class="carte" aria-labelledby="t-assiduite">
          <h2 id="t-assiduite">Mon assiduité</h2>
          <div v-for="a in assiduite" :key="a.promotion.id" class="resume">
            <JaugeAssiduite :taux="a.taux" :seuil="a.seuil" />
            <ul class="compteurs">
              <li><strong>{{ a.presences }}</strong> présence(s)</li>
              <li><strong>{{ a.absences }}</strong> absence(s)</li>
              <li><strong>{{ a.excuses }}</strong> excusée(s)</li>
            </ul>
          </div>
          <NuxtLink to="/ent/etudiant/assiduite" class="lien-fleche">Détail et justificatifs <Icone nom="suivant" :taille="16" /></NuxtLink>
        </section>
        <section v-if="promosSuivies?.length" class="carte" aria-labelledby="t-promos">
          <h2 id="t-promos">Promotions suivies</h2>
          <ul class="promos">
            <li v-for="p in promosSuivies" :key="p.promotion.id">
              <div><strong>{{ p.promotion.libelle }}</strong><br><span class="aide">{{ p.formation }} · {{ p.etudiants.length }} apprenant(s)</span></div>
              <JaugeAssiduite :taux="p.taux_moyen" :seuil="p.seuil" />
            </li>
          </ul>
        </section>

        <!-- Coordonnées -->
        <form class="carte" aria-labelledby="t-coordonnees" @submit.prevent="enregistrerProfil">
          <h2 id="t-coordonnees">Coordonnées et présentation</h2>
          <p v-if="infoProfil" class="message message-succes" role="status">{{ infoProfil }}</p>
          <div class="champ">
            <label for="telephone">Téléphone <span class="aide">(facultatif)</span></label>
            <input id="telephone" v-model="telephone" type="tel" autocomplete="tel" maxlength="20" placeholder="0692 00 00 00" :aria-invalid="!!erreursProfil.telephone" aria-describedby="err-telephone">
            <p v-if="erreursProfil.telephone" id="err-telephone" class="erreur" role="alert">{{ erreursProfil.telephone }}</p>
          </div>
          <div class="champ">
            <label for="bio">Présentation <span class="aide">(facultatif, visible de l'équipe pédagogique)</span></label>
            <textarea id="bio" v-model="bio" rows="3" maxlength="280" :placeholder="user?.role === 'formateur' ? 'Vos spécialités, vos disponibilités…' : 'Votre projet, votre entreprise d\'accueil…'" aria-describedby="compteur-bio" />
            <p id="compteur-bio" class="aide compteur">{{ bio.length }} / 280</p>
            <p v-if="erreursProfil.bio" class="erreur" role="alert">{{ erreursProfil.bio }}</p>
          </div>
          <button class="btn btn-primaire" :disabled="envoiProfil"><span v-if="envoiProfil" class="chargement" aria-hidden="true" />Enregistrer</button>
        </form>

        <!-- Mot de passe -->
        <form class="carte" aria-labelledby="t-mdp" @submit.prevent="changerMdp">
          <h2 id="t-mdp" class="titre-icone"><Icone nom="cle" :taille="22" /> Mot de passe</h2>
          <p v-if="infoMdp" class="message message-succes" role="status">{{ infoMdp }}</p>
          <div class="champ">
            <label for="mdp-actuel">Mot de passe actuel</label>
            <input id="mdp-actuel" v-model="mdp.actuel" type="password" autocomplete="current-password" required :aria-invalid="!!erreursMdp.mot_de_passe_actuel">
            <p v-if="erreursMdp.mot_de_passe_actuel" class="erreur" role="alert">{{ erreursMdp.mot_de_passe_actuel }}</p>
          </div>
          <div class="deux-champs">
            <div class="champ">
              <label for="mdp-nouveau">Nouveau mot de passe</label>
              <input id="mdp-nouveau" v-model="mdp.nouveau" type="password" autocomplete="new-password" required aria-describedby="criteres-mdp" :aria-invalid="!!erreursMdp.password">
            </div>
            <div class="champ">
              <label for="mdp-confirmation">Confirmation</label>
              <input id="mdp-confirmation" v-model="mdp.confirmation" type="password" autocomplete="new-password" required>
            </div>
          </div>
          <ul id="criteres-mdp" class="criteres">
            <li v-for="c in criteres" :key="c.libelle" :class="{ ok: c.ok }"><Icone :nom="c.ok ? 'valide' : 'chevron'" :taille="16" :poids="c.ok ? 'fill' : 'regular'" /> {{ c.libelle }}</li>
          </ul>
          <p v-if="erreursMdp.password" class="erreur" role="alert">{{ erreursMdp.password }}</p>
          <button class="btn btn-primaire" :disabled="envoiMdp"><span v-if="envoiMdp" class="chargement" aria-hidden="true" />Changer le mot de passe</button>
        </form>
      </div>
    </div>
  </NuxtLayout>
</template>

<style scoped>
.profil-grille { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: var(--esp-4); align-items: start; }
.colonne { display: grid; gap: var(--esp-4); min-width: 0; }
.identite { text-align: center; position: sticky; top: 90px; }
.photo-zone { position: relative; width: 148px; height: 148px; margin: 4px auto 14px; border-radius: 50%; transition: box-shadow .2s, transform .2s; }
.photo-zone.survol { box-shadow: 0 0 0 4px var(--primaire); transform: scale(1.03); }
.grande { width: 148px; height: 148px; border-radius: 50%; object-fit: cover; box-shadow: 0 0 0 4px #fff, 0 0 0 5px var(--gris-bord); }
.bouton-photo { position: absolute; right: 4px; bottom: 4px; width: 42px; height: 42px; border-radius: 50%; border: 3px solid #fff; background: var(--primaire-fonce); color: #fff; display: grid; place-items: center; cursor: pointer; transition: transform .15s; }
.bouton-photo:hover { transform: scale(1.08); }
.nom-profil { font-size: 1.3rem; font-weight: 700; margin: 0; color: var(--encre); }
.role-profil { margin: 6px 0 14px; }
.actions-photo { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 8px; }
.actions-photo .btn { display: inline-flex; align-items: center; gap: 6px; }
.infos { text-align: left; margin: var(--esp-3) 0 0; padding-top: var(--esp-3); border-top: 1px solid var(--gris-bord); display: grid; gap: 12px; }
.infos div { display: grid; gap: 2px; }
.infos dt { display: flex; align-items: center; gap: 6px; color: var(--texte-doux); font-size: .84rem; }
.infos dd { margin: 0; font-weight: 600; word-break: break-word; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.lien-discret { font-size: .88rem; font-weight: 500; }
.resume { display: grid; gap: 10px; margin-bottom: 12px; }
.compteurs { list-style: none; padding: 0; margin: 0; display: flex; gap: 18px; flex-wrap: wrap; color: var(--texte-doux); }
.compteurs strong { color: var(--encre); font-size: 1.1rem; }
.promos { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
.promos li { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 16px; align-items: center; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; }
.titre-icone { display: flex; align-items: center; gap: 8px; }
.deux-champs { display: grid; grid-template-columns: 1fr 1fr; gap: 0 var(--esp-3); }
.criteres { list-style: none; padding: 0; margin: 0 0 var(--esp-3); display: flex; gap: 16px; flex-wrap: wrap; color: var(--texte-doux); font-size: .88rem; }
.criteres li { display: inline-flex; align-items: center; gap: 4px; transition: color .2s; }
.criteres li.ok { color: var(--succes); font-weight: 600; }
.compteur { text-align: right; margin: 4px 0 0; }
.erreur { color: var(--alerte); margin: 6px 0 0; }
textarea { resize: vertical; }
@media (max-width: 1000px) {
  .profil-grille { grid-template-columns: minmax(0, 1fr); }
  .identite { position: static; }
}
@media (max-width: 600px) {
  .deux-champs { grid-template-columns: 1fr; }
  .promos li { grid-template-columns: 1fr; }
}
</style>
