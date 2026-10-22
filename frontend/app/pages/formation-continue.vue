<script setup lang="ts">
const api = useApi()
const { data } = await useAsyncData('fc', () => api<any>('/api/formation-continue'))
useSeoMeta({ title: 'Formation continue', description: 'Bureautique, réseaux, cybersécurité, développement, IA : les formations courtes Expernet pour les salariés et les entreprises à La Réunion et à Mayotte.' })
const parcours = [
  { icone: 'entreprise', titre: 'Intra-entreprise', texte: 'Une session dédiée à un groupe de collaborateurs de votre entreprise, dans vos locaux ou au centre.' },
  { icone: 'groupe', titre: 'Inter-entreprises', texte: 'Des sessions planifiées qui réunissent des participants de différentes entreprises.' },
  { icone: 'kanban', titre: 'Sur mesure', texte: 'Un programme intensif et ciblé, construit à partir de vos besoins et sur une période courte.' },
]
const ouvert = ref<string | null>(null)
</script>

<template>
  <div>
    <EntetePage titre="Formation continue" sur-titre="Expernet · depuis 2002" texte="Salariés, demandeurs d'emploi, entreprises : des formations courtes en informatique et en marketing digital, à La Réunion et à Mayotte." image="/images/campus/filiere-informatique.webp">
      <a :href="`${data?.site}/demande-de-devis/`" class="btn btn-primaire devis" target="_blank" rel="noopener">Demander un devis <Icone nom="externe" :taille="18" /></a>
    </EntetePage>

    <section class="conteneur chiffres" aria-label="Expernet formation continue en chiffres">
      <div v-for="c in data?.chiffres" :key="c.libelle"><span class="chiffre">{{ c.valeur.toLocaleString('fr-FR') }}{{ c.suffixe || '' }}</span><span class="libelle-chiffre">{{ c.libelle }}</span></div>
    </section>

    <section class="conteneur bloc" aria-labelledby="t-parcours">
      <p class="sur-titre">Votre parcours de formation</p>
      <h2 id="t-parcours">Trois formats, selon votre besoin</h2>
      <div class="grille grille-3">
        <div v-for="p in parcours" :key="p.titre" class="carte format">
          <span class="format-icone"><Icone :nom="p.icone" :taille="28" poids="duotone" /></span>
          <h3>{{ p.titre }}</h3>
          <p>{{ p.texte }}</p>
        </div>
      </div>
    </section>

    <section class="conteneur bloc" aria-labelledby="t-domaines">
      <p class="sur-titre">Notre offre</p>
      <h2 id="t-domaines">{{ data?.domaines.length }} domaines de compétences</h2>
      <div class="domaines">
        <div v-for="d in data?.domaines" :key="d.slug" class="domaine" :class="{ ouvert: ouvert === d.slug }">
          <button class="domaine-tete" :aria-expanded="ouvert === d.slug" :aria-controls="`st-${d.slug}`" @click="ouvert = ouvert === d.slug ? null : d.slug">
            <span class="domaine-icone"><Icone :nom="d.icone" :taille="26" poids="duotone" /></span>
            <span class="domaine-nom">{{ d.libelle }}<small>{{ d.stages.length }} formations</small></span>
            <Icone nom="deplier" :taille="18" class="chevron" />
          </button>
          <ul :id="`st-${d.slug}`" v-show="ouvert === d.slug" class="stages">
            <li v-for="s in d.stages" :key="s.id">{{ s.intitule }}</li>
          </ul>
        </div>
      </div>
      <p class="renvoi">Programmes détaillés, dates et tarifs sur <a :href="data?.site" target="_blank" rel="noopener">expernet.re <Icone nom="externe" :taille="14" /></a></p>
    </section>
  </div>
</template>

<style scoped>
.devis { margin-top: 24px; }
.chiffres { position: relative; z-index: 2; margin-top: -36px; display: grid; grid-template-columns: repeat(4, 1fr); background: #fff; border-radius: var(--rayon); box-shadow: var(--ombre-forte); overflow: hidden; }
.chiffres > div { padding: 24px; display: grid; gap: 6px; border-right: 1px solid var(--gris-bord); }
.chiffres > div:last-child { border-right: 0; }
.chiffres .chiffre { color: var(--primaire-fonce); }
.bloc { margin-top: var(--esp-6); }
.format { display: grid; gap: 10px; align-content: start; }
.format h3, .format p { margin: 0; }
.format p { color: var(--texte-doux); }
.format-icone { width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.domaines { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); gap: 12px; align-items: start; }
.domaine { background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); box-shadow: var(--ombre); }
.domaine.ouvert { border-color: #B9E3F5; }
.domaine-tete { width: 100%; display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center; padding: 14px 16px; background: none; border: 0; font: inherit; text-align: left; cursor: pointer; color: var(--encre); }
.domaine-icone { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, #E1F3FB, #FDEFD9); color: var(--primaire-fonce); }
.domaine-nom { display: grid; font-weight: 700; font-family: var(--police-titre); font-size: .98rem; }
.domaine-nom small { font-family: Roboto, sans-serif; font-weight: 400; color: var(--texte-doux); font-size: .82rem; }
.chevron { color: var(--primaire); transition: transform .2s; }
.ouvert .chevron { transform: rotate(180deg); }
.stages { list-style: none; margin: 0; padding: 0 16px 16px 76px; display: flex; flex-wrap: wrap; gap: 6px; }
.stages li { background: var(--gris-fond); border-radius: 999px; padding: 4px 12px; font-size: .86rem; }
.renvoi { margin-top: var(--esp-4); color: var(--texte-doux); }
.renvoi a { display: inline-flex; align-items: center; gap: 4px; }
@media (max-width: 900px) { .chiffres { grid-template-columns: 1fr 1fr; } .chiffres > div { border-bottom: 1px solid var(--gris-bord); } .stages { padding-left: 16px; } }
</style>
