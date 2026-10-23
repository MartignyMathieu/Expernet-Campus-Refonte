<!-- Emploi du temps groupé par jour (liste lisible sur mobile, plus accessible qu'une grille horaire). -->
<script setup lang="ts">
const props = defineProps<{ seances: any[]; role: 'etudiant' | 'formateur' }>()
const jours = computed(() => {
  const g: Record<string, any[]> = {}
  for (const s of props.seances) (g[s.date] ||= []).push(s)
  return Object.entries(g)
})
const aujourdHui = new Date().toISOString().slice(0, 10)
const libelleJour = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const documentsDu = (s: any) => `/ent/${props.role}/documents?module=${s.module_id}`
const statut: Record<string, [string, string]> = { present: ['Présent', 'pastille-verte'], absent: ['Absent', 'pastille-rouge'], excuse: ['Excusé', 'pastille-jaune'] }
</script>

<template>
  <div class="planning">
    <p v-if="!jours.length" class="vide">Aucune séance sur cette période.</p>
    <section v-for="[jour, liste] in jours" :key="jour" class="jour" :class="{ aujourdhui: jour === aujourdHui }">
      <h3>{{ libelleJour(jour) }} <span v-if="jour === aujourdHui" class="pastille pastille-bleue">Aujourd'hui</span></h3>
      <ul>
        <li v-for="s in liste" :key="s.id" class="seance">
          <span class="heure">{{ s.heure_debut }}<br><small>{{ s.heure_fin }}</small></span>
          <span class="detail"><NuxtLink v-if="s.module_id" :to="documentsDu(s)" class="lien-module" :title="role === 'formateur' ? 'Déposer ou consulter les cours et TP de ce module' : 'Cours et TP de ce module'"><strong>{{ s.module }}</strong><Icone nom="documents" :taille="15" /></NuxtLink><strong v-else>{{ s.module }}</strong><br><small>{{ role === 'etudiant' ? s.formateur : s.promotion }} · {{ s.lieu }}</small></span>
          <span class="action">
            <template v-if="role === 'etudiant'">
              <span v-if="s.emargement.mon_statut" class="pastille" :class="statut[s.emargement.mon_statut][1]">{{ statut[s.emargement.mon_statut][0] }}</span>
              <NuxtLink v-else-if="s.emargement.ouvert" :to="`/ent/etudiant/emargement/${s.id}`" class="btn btn-primaire">Signer</NuxtLink>
            </template>
            <template v-else>
              <span v-if="s.emargement.cloture" class="pastille pastille-verte">Clôturée</span>
              <NuxtLink v-else-if="jour === aujourdHui" :to="`/ent/formateur/seance/${s.id}`" class="btn btn-primaire">{{ s.emargement.ouvert ? 'Suivre' : 'Émargement' }}</NuxtLink>
            </template>
          </span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.jour { margin-bottom: var(--esp-4); }
.jour h3 { text-transform: capitalize; font-size: 1rem; display: flex; gap: 8px; align-items: center; }
ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
.seance { display: grid; grid-template-columns: 64px 1fr auto; gap: 16px; align-items: center; background: #fff; border: 1px solid var(--gris-bord); border-left: 4px solid var(--primaire); border-radius: 10px; padding: 12px 16px; }
.aujourdhui .seance { border-left-color: var(--jaune); }
.lien-module { display: inline-flex; align-items: center; gap: 6px; color: var(--encre); text-decoration: none; }
.lien-module:hover { color: var(--primaire-texte); text-decoration: underline; }
.lien-module :deep(.icone) { color: var(--primaire); }
.heure { font-weight: 700; color: var(--encre); line-height: 1.2; }
.heure small, .detail small { color: var(--texte-doux); font-weight: 400; }
@media (max-width: 600px) { .seance { grid-template-columns: 52px 1fr; } .action { grid-column: 1 / -1; } }
</style>
