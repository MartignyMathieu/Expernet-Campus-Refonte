<script setup lang="ts">
const api = useApi()
const { data } = await useAsyncData('faq', () => api<any[]>('/api/faq'))
const theme = ref('')
const themes: Record<string, string> = { admission: 'Admission', alternance: 'Alternance', financement: 'Financement', campus: 'Campus' }
const liste = computed(() => (data.value || []).filter((q) => !theme.value || q.theme === theme.value))

useSeoMeta({ title: 'Questions fréquentes', description: 'Alternance, candidature, rémunération, âge limite, coût de la formation : les réponses aux questions les plus fréquentes sur Expernet Campus.' })
useHead({ script: [{ type: 'application/ld+json', innerHTML: computed(() => JSON.stringify({
  '@context': 'https://schema.org', '@type': 'FAQPage',
  mainEntity: (data.value || []).map((q) => ({ '@type': 'Question', name: q.question, acceptedAnswer: { '@type': 'Answer', text: q.reponse } })),
})) }] })
</script>

<template>
  <div>
    <EntetePage titre="Questions fréquentes" sur-titre="FAQ" texte="Les réponses aux questions que l'on nous pose le plus souvent sur l'alternance, la candidature et la vie au campus." image="/images/campus/hero-blog.webp" />
    <div class="conteneur page">
      <div class="filtres" role="group" aria-label="Filtrer par thème">
        <button :class="{ actif: !theme }" :aria-pressed="!theme" @click="theme = ''">Toutes</button>
        <button v-for="(lib, cle) in themes" :key="cle" :class="{ actif: theme === cle }" :aria-pressed="theme === cle" @click="theme = cle">{{ lib }}</button>
      </div>
      <div class="disposition">
        <div class="questions">
          <details v-for="q in liste" :key="q.id" class="question">
            <summary><span class="pastille pastille-bleue">{{ themes[q.theme] }}</span><span class="intitule">{{ q.question }}</span><Icone nom="deplier" :taille="20" class="chevron" /></summary>
            <p>{{ q.reponse }}</p>
          </details>
        </div>
        <aside class="carte aide">
          <Icone nom="faq" :taille="40" poids="duotone" class="aide-icone" />
          <h2>Vous ne trouvez pas votre réponse ?</h2>
          <p>L'équipe du campus vous répond du lundi au vendredi.</p>
          <a href="tel:+262262219045" class="ligne"><Icone nom="telephone" :taille="18" />02 62 21 90 45</a>
          <a href="mailto:campus@expernet.re" class="ligne"><Icone nom="email" :taille="18" />campus@expernet.re</a>
          <NuxtLink to="/contact" class="btn btn-primaire">Nous écrire</NuxtLink>
        </aside>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding-top: var(--esp-5); }
.filtres { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: var(--esp-4); }
.filtres button { font: inherit; font-weight: 500; padding: 8px 16px; border-radius: 999px; border: 1.5px solid var(--gris-bord); background: #fff; cursor: pointer; color: var(--texte); }
.filtres button.actif { background: var(--primaire); border-color: var(--primaire); color: #fff; }
.disposition { display: grid; grid-template-columns: 1fr 320px; gap: var(--esp-5); align-items: start; }
.questions { display: grid; gap: 12px; }
.question { background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); box-shadow: var(--ombre); overflow: hidden; }
.question[open] { border-color: #B9E3F5; }
summary { list-style: none; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 14px; padding: 18px 20px; cursor: pointer; }
summary::-webkit-details-marker { display: none; }
.intitule { font-family: var(--police-titre); font-weight: 700; color: var(--encre); }
.chevron { transition: transform .2s; color: var(--primaire); }
.question[open] .chevron { transform: rotate(180deg); }
.question p { margin: 0; padding: 0 20px 20px; color: var(--texte); }
.aide { position: sticky; top: 110px; display: grid; gap: 10px; }
.aide h2 { font-size: 1.2rem; margin: 0; }
.aide p { margin: 0; color: var(--texte-doux); }
.aide-icone { color: var(--primaire); }
.ligne { display: flex; align-items: center; gap: 8px; font-weight: 500; text-decoration: none; }
@media (max-width: 900px) {
  .disposition { grid-template-columns: 1fr; }
  .aide { position: static; }
  summary { grid-template-columns: 1fr auto; }
  summary .pastille { grid-column: 1 / -1; justify-self: start; }
}
</style>
