<script setup lang="ts">
// Assiduité personnelle : taux, décompte et détail des demi-journées manquées, avec accès aux justificatifs.
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Mon assiduité' })
const api = useApi()
const { data } = await useAsyncData('mon-assiduite', () => api<any[]>('/api/me/assiduite'))
const date = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const creneau = (c: string) => c === 'matin' ? 'Matin' : 'Après-midi'
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
              <span class="pastille" :class="x.statut === 'excuse' ? 'pastille-verte' : 'pastille-rouge'">{{ x.statut === 'excuse' ? 'Justifiée' : 'Non justifiée' }}</span>
            </li>
          </ul>
        </section>
        <section class="carte" aria-labelledby="t-justif">
          <h2 id="t-justif">Justifier une absence</h2>
          <p>Déposez votre justificatif (certificat médical, convocation…) dans l'espace <strong>Mes rendus</strong> sous 48 heures : la scolarité le valide et l'absence passe en « justifiée ».</p>
          <NuxtLink to="/ent/etudiant/documents?espace=rendus" class="btn btn-primaire"><Icone nom="deposer" :taille="18" /> Déposer un justificatif</NuxtLink>
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
hr { border: 0; border-top: 1px solid var(--gris-bord); margin: var(--esp-4) 0; }
@media (max-width: 1000px) { .deux { grid-template-columns: 1fr; } }
</style>
