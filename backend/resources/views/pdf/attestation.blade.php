<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Attestation d'assiduité</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  .bandeau { background: #F1F1F1; padding: 14px 18px; border-left: 6px solid #009BDE; }
  h1 { font-size: 18px; margin: 0 0 4px; color: #0D5A8A; }
  .meta { color: #555; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { background: #E6F5FC; text-align: left; padding: 6px; font-size: 10px; }
  td { padding: 5px 6px; border-bottom: 1px solid #E5E5E5; font-size: 10px; }
  .hash { font-family: DejaVu Sans Mono, monospace; font-size: 7px; color: #666; }
  .pied { margin-top: 24px; font-size: 9px; color: #666; }
</style>
</head>
<body>
  <div class="bandeau">
    <h1>Attestation d'assiduité</h1>
    <div class="meta">Expernet Campus - Parc 2000, 97420 Le Port</div>
  </div>

  <p>Nous attestons que <strong>{{ $etudiant->nomComplet() }}</strong>, inscrit(e) en
     <strong>{{ $promotion->formation->intitule }}</strong> (promotion {{ $promotion->libelle }}),
     a été présent(e) à <strong>{{ $presents }}</strong> {{ $presents > 1 ? 'demi-journées' : 'demi-journée' }} sur {{ $lignes->count() }} {{ $lignes->count() > 1 ? 'séances enregistrées' : 'séance enregistrée' }}.</p>

  <table>
    <thead><tr><th>Date</th><th>Créneau</th><th>Module</th><th>Statut</th><th>Signé le</th><th>Empreinte SHA-256</th></tr></thead>
    <tbody>
    @foreach ($lignes as $l)
      <tr>
        <td>{{ $l->seance->date->format('d/m/Y') }}</td>
        <td>{{ $l->seance->creneau === 'matin' ? 'Matin' : 'Après-midi' }}</td>
        <td>{{ $l->seance->module->intitule }}</td>
        <td>{{ ucfirst($l->statut) }}</td>
        <td>{{ $l->signe_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
        <td class="hash">{{ $l->empreinte ?? '-' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <p class="pied">Document généré le {{ $genere_le->format('d/m/Y à H:i') }}. Chaque signature est horodatée et scellée
     par une empreinte SHA-256 vérifiable via l'API (signature électronique simple, règlement eIDAS).</p>
</body>
</html>
