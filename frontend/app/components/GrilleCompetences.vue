<!-- Saisie d'une grille de compétences par un membre du jury. -->
<script setup lang="ts">
const props = defineProps<{ sessionId: number }>()
const api = useApi()
const { data, refresh } = await useAsyncData(`grille-${props.sessionId}`, () => api<any>(`/api/examens/${props.sessionId}/grille`))
const libelles: Record<string, string> = { non_acquis: 'Non acquis', en_cours: 'En cours', acquis: 'Acquis', maitrise: 'Maîtrisé' }
const etat = ref('')
const niveau = (e: number, c: number) => data.value?.evaluations.find((x: any) => x.etudiant_id === e && x.competence_id === c)?.niveau || ''
async function saisir(e: number, c: number, n: string) {
  if (!n) return
  try { await csrf(); await api(`/api/examens/${props.sessionId}/evaluations`, { method: 'POST', body: { etudiant_id: e, competence_id: c, niveau: n } }); etat.value = 'Enregistré ✓'; await refresh() }
  catch { etat.value = 'Erreur d\'enregistrement' }
}
</script>
<template>
  <div class="tableau-scroll">
    <p class="aide" aria-live="polite">{{ etat }}</p>
    <table class="tableau">
      <caption class="sr-only">Grille de compétences</caption>
      <thead><tr><th scope="col">Étudiant</th><th v-for="c in data?.competences" :key="c.id" scope="col" :title="c.libelle">{{ c.code }}<br><small>{{ c.libelle }}</small></th></tr></thead>
      <tbody>
        <tr v-for="e in data?.etudiants" :key="e.id">
          <th scope="row">{{ e.nom }}</th>
          <td v-for="c in data?.competences" :key="c.id">
            <label class="sr-only" :for="`n-${e.id}-${c.id}`">{{ c.code }} - {{ e.nom }}</label>
            <select :id="`n-${e.id}-${c.id}`" :value="niveau(e.id, c.id)" :class="'n-' + niveau(e.id, c.id)" @change="saisir(e.id, c.id, ($event.target as HTMLSelectElement).value)">
              <option value="">-</option>
              <option v-for="n in data?.niveaux" :key="n" :value="n">{{ libelles[n] }}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
<style scoped>
th small { font-weight: 400; display: block; max-width: 22ch; }
select { font: inherit; padding: 6px 8px; border-radius: 6px; border: 1.5px solid #C9CED4; min-height: 40px; }
.n-acquis, .n-maitrise { background: #E2F3E8; }
.n-en_cours { background: #FFF3D1; }
.n-non_acquis { background: #FBE4E5; }
</style>
