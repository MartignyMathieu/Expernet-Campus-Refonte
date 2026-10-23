<script setup lang="ts">
// Écran projeté en salle : code d'émargement + suivi des signatures en temps réel (rafraîchi toutes les 5 s).
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Émargement de la séance' })
const route = useRoute()
const api = useApi()
const { data: feuille, refresh } = await useAsyncData('feuille', () => api<any>(`/api/seances/${route.params.id}/emargement`))
const code = ref(''); const erreur = ref(''); const info = ref('')
let minuteur: any
onMounted(() => { minuteur = setInterval(refresh, 5000) })
onBeforeUnmount(() => clearInterval(minuteur))

const signes = computed(() => feuille.value?.lignes.filter((l: any) => l.statut === 'present').length || 0)
async function ouvrir() {
  erreur.value = ''
  try { await csrf(); const r: any = await api(`/api/seances/${route.params.id}/emargement/ouvrir`, { method: 'POST' }); code.value = r.code; await refresh() }
  catch (e: any) { erreur.value = e?.data?.message || 'Ouverture impossible.' }
}
async function cloturer() {
  if (!confirmerCloture.value) { confirmerCloture.value = true; return }
  try { const r: any = await api(`/api/seances/${route.params.id}/emargement/cloturer`, { method: 'POST' }); info.value = `Séance clôturée : ${nombre(r.absents, 'absent enregistré', 'absents enregistrés')}.`; code.value = ''; await refresh() }
  catch (e: any) { erreur.value = e?.data?.message || 'Clôture impossible.' } finally { confirmerCloture.value = false }
}
const confirmerCloture = ref(false)
</script>

<template>
  <div>
    <NuxtLink to="/ent/formateur" class="lien-retour"><Icone nom="retour" :taille="16" /> Tableau de bord</NuxtLink>
    <h1>Émargement</h1>
    <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
    <p v-if="info" class="message message-succes" role="status">{{ info }}</p>

    <div class="grille deux">
      <section class="carte panneau-code">
        <template v-if="feuille?.seance.cloture"><h2>Séance clôturée</h2><p>La feuille est figée.</p></template>
        <template v-else-if="code">
          <p class="aide">Code à communiquer aux étudiants</p>
          <p class="code" aria-live="polite">{{ code.slice(0, 3) }} {{ code.slice(3) }}</p>
          <p class="aide">Valable jusqu'à {{ new Date(feuille?.seance.expire_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</p>
        </template>
        <template v-else>
          <h2>{{ feuille?.seance.ouvert ? 'Émargement en cours' : 'Ouvrir l\'émargement' }}</h2>
          <p class="aide">Un code à 6 chiffres sera généré, valable 15 minutes. Rouvrir génère un nouveau code.</p>
        </template>
        <div class="boutons" v-if="!feuille?.seance.cloture">
          <button class="btn btn-primaire" @click="ouvrir">{{ feuille?.seance.ouvert ? 'Nouveau code' : 'Ouvrir l\'émargement' }}</button>
          <button class="btn" :class="confirmerCloture ? 'btn-danger' : 'btn-secondaire'" @click="cloturer">{{ confirmerCloture ? 'Confirmer la clôture' : 'Clôturer la séance' }}</button>
        </div>
      </section>
      <section class="carte">
        <h2>{{ signes }} / {{ feuille?.lignes.length || 0 }} signatures</h2>
        <ul class="lignes" aria-live="polite">
          <li v-for="l in feuille?.lignes" :key="l.etudiant_id">
            <span>{{ l.nom }}</span>
            <span v-if="l.statut === 'present'" class="pastille pastille-verte"><Icone nom="valide" :taille="14" poids="fill" /> {{ new Date(l.signe_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</span>
            <span v-else-if="l.statut === 'absent'" class="pastille pastille-rouge">Absent</span>
            <span v-else class="pastille">En attente</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
.deux { grid-template-columns: 1fr 1fr; }
.lien-retour { display: inline-flex; align-items: center; gap: 6px; }
.panneau-code { text-align: center; background: #FFFBEF; border-color: #F5DC8E; }
.code { font-size: clamp(3rem, 8vw, 5rem); font-weight: 700; letter-spacing: .1em; color: var(--encre); margin: 8px 0; font-variant-numeric: tabular-nums; }
.boutons { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: var(--esp-3); }
.lignes { list-style: none; padding: 0; display: grid; gap: 6px; max-height: 60vh; overflow: auto; }
.lignes li { display: flex; justify-content: space-between; padding: 8px 4px; border-bottom: 1px solid var(--gris-bord); }
@media (max-width: 900px) { .deux { grid-template-columns: 1fr; } }
</style>
