<script setup lang="ts">
// Assiduité personnelle : taux, décompte et détail des demi-journées manquées, avec accès aux justificatifs.
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Mon assiduité' })
const api = useApi()
const { data, refresh } = await useAsyncData('mon-assiduite', () => api<any[]>('/api/me/assiduite'))
const date = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const creneau = (c: string) => c === 'matin' ? 'Matin' : 'Après-midi'
const envoi = ref<number | null>(null); const erreurs = reactive<Record<number, string>>({})
async function joindre(absence: any, e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]; if (!f) return
  envoi.value = absence.id; erreurs[absence.id] = ''
  try {
    const fd = new FormData(); fd.append('fichier', f)
    await csrf(); await api(`/api/emargements/${absence.id}/justificatif`, { method: 'POST', body: fd }); await refresh()
  } catch (err: any) { erreurs[absence.id] = erreursValidation(err).fichier || err?.data?.message || 'Envoi impossible.' } finally { envoi.value = null; (e.target as HTMLInputElement).value = '' }
}
</script>

<template>
  <div>
    <h1>Mon assiduité</h1>
    <p v-if="!data?.length" class="vide">Vous n'êtes inscrit dans aucune promotion.</p>
    <div v-for="a in data" :key="a.promotion.id" class="bloc">
      <div class="grille grille-4 indicateurs">
        <div class="carte large" :class="{ critique: a.taux !== null && a.taux < a.seuil }">
          <p class="libelle-chiffre">Taux de présence · {{ a.promotion.libelle }}</p>
          <JaugeAssiduite :taux="a.taux" :seuil="a.seuil" />
          <p class="aide">Seuil d'alerte : {{ a.seuil }} %. Les absences justifiées comptent comme des présences.</p>
        </div>
        <div class="carte"><Icone nom="valide" :taille="26" poids="duotone" class="ic ok" /><span class="chiffre">{{ a.presences }}</span><p class="libelle-chiffre">{{ pluriel(a.presences, 'demi-journée présente', 'demi-journées présentes') }}</p></div>
        <div class="carte"><Icone nom="alerte" :taille="26" poids="duotone" class="ic ko" /><span class="chiffre">{{ a.absences }}</span><p class="libelle-chiffre">{{ pluriel(a.absences, 'absence non justifiée', 'absences non justifiées') }}</p></div>
        <div class="carte"><Icone nom="documents" :taille="26" poids="duotone" class="ic" /><span class="chiffre">{{ a.excuses }}</span><p class="libelle-chiffre">{{ pluriel(a.excuses, 'absence justifiée', 'absences justifiées') }}</p></div>
      </div>

      <div class="grille deux">
        <section class="carte" aria-labelledby="t-detail">
          <h2 id="t-detail">Demi-journées manquées</h2>
          <p v-if="!a.detail_absences.length" class="vide">Aucune absence : bravo !</p>
          <ul class="absences">
            <li v-for="(x, i) in a.detail_absences" :key="i">
              <div><strong>{{ date(x.date) }}</strong> · {{ creneau(x.creneau) }}<br><span class="aide">{{ x.module || 'Module non renseigné' }}</span></div>
              <span class="cote-absence">
                <span class="pastille" :class="x.statut === 'excuse' ? 'pastille-verte' : x.justificatif ? 'pastille-orange' : 'pastille-rouge'">{{ x.statut === 'excuse' ? 'Justifiée' : x.justificatif ? 'Justificatif en attente' : 'Non justifiée' }}</span>
                <a v-if="x.justificatif" :href="x.justificatif.url" target="_blank" rel="noopener" class="lien-justif"><Icone nom="fichier" :taille="15" /> {{ x.justificatif.nom }}</a>
                <label v-if="x.statut !== 'excuse'" class="btn btn-secondaire petit" :class="{ desactive: envoi === x.id }">
                  <input type="file" accept=".pdf,.png,.jpg,.jpeg" class="sr-only" :disabled="envoi === x.id" @change="joindre(x, $event)">
                  <span v-if="envoi === x.id" class="chargement" aria-hidden="true" /><Icone v-else nom="deposer" :taille="16" /> {{ x.justificatif ? 'Remplacer' : 'Joindre un justificatif' }}
                </label>
                <small v-if="erreurs[x.id]" class="erreur" role="alert">{{ erreurs[x.id] }}</small>
              </span>
            </li>
          </ul>
        </section>
        <section class="carte" aria-labelledby="t-justif">
          <h2 id="t-justif">Justifier une absence</h2>
          <p>Joignez votre justificatif (certificat médical, convocation…) directement sur la ligne de l'absence, sous 48 heures. Votre formateur est prévenu et la valide : l'absence passe alors en « justifiée ».</p>
          <p class="aide">Formats acceptés : PDF, PNG ou JPEG, 5 Mo maximum. Le justificatif est aussi rangé dans <NuxtLink to="/ent/etudiant/documents?espace=rendus">Mes rendus</NuxtLink>.</p>
          <hr>
          <p>Votre attestation d'assiduité reprend chaque séance signée avec son empreinte de vérification.</p>
          <a :href="`/api/promotions/${a.promotion.id}/attestation`" class="btn btn-secondaire"><Icone nom="telecharger" :taille="18" /> Télécharger l'attestation (PDF)</a>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bloc { margin-bottom: var(--esp-5); }
.indicateurs { margin-bottom: var(--esp-4); }
.indicateurs .carte { padding: var(--esp-3) var(--esp-4); }
.indicateurs p { margin: 6px 0 0; }
.large .libelle-chiffre { margin: 0 0 12px; font-weight: 600; }
.critique { border-color: #F2B8BB; background: #FFF7F7; }
.ic { display: block; color: var(--primaire); margin-bottom: 10px; }
.ic.ok { color: var(--succes); }
.ic.ko { color: var(--alerte); }
.deux { grid-template-columns: 1.3fr 1fr; }
.absences { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
.absences li { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px; background: var(--gris-fond); }
.absences strong { text-transform: capitalize; }
.btn { display: inline-flex; align-items: center; gap: 8px; }
.cote-absence { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.lien-justif { display: inline-flex; align-items: center; gap: 4px; font-size: .88rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.petit { min-height: 36px; padding: 4px 12px; font-size: .86rem; cursor: pointer; }
.desactive { opacity: .6; pointer-events: none; }
.petit:focus-within { outline: 3px solid var(--primaire); outline-offset: 2px; }
.erreur { color: var(--alerte); width: 100%; text-align: right; }
hr { border: 0; border-top: 1px solid var(--gris-bord); margin: var(--esp-4) 0; }
@media (max-width: 1000px) { .deux { grid-template-columns: 1fr; } }
</style>
