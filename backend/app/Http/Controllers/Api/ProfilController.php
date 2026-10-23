<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Profil de l'utilisateur connecté : coordonnées, photo, mot de passe.
 * La photo est ré-encodée côté serveur (carré 400 px, JPEG) : métadonnées EXIF (position GPS…)
 * supprimées et fichiers piégés neutralisés.
 */
class ProfilController extends Controller
{
    public const TAILLE_AVATAR = 400;

    public function modifier(Request $request)
    {
        $donnees = $request->validate([
            'telephone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +().-]{6,20}$/'],
            'bio' => ['nullable', 'string', 'max:280'],
        ], ['telephone.regex' => 'Numéro de téléphone invalide.']);

        $user = $request->user();
        $user->fill([
            'telephone' => isset($donnees['telephone']) ? trim($donnees['telephone']) : null,
            'bio' => isset($donnees['bio']) ? trim(strip_tags($donnees['bio'])) : null,
        ])->save();

        return response()->json(['message' => 'Profil mis à jour.', 'telephone' => $user->telephone, 'bio' => $user->bio]);
    }

    public function deposerAvatar(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=96,min_height=96,max_width=6000,max_height=6000'],
        ], [
            'photo.max' => 'La photo ne doit pas dépasser 4 Mo.',
            'photo.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
            'photo.dimensions' => 'La photo doit mesurer au moins 96 × 96 pixels.',
        ]);

        $user = $request->user();
        $contenu = $this->normaliser($request->file('photo'));
        abort_if($contenu === null, 422, 'Image illisible : essayez avec une autre photo.');

        $chemin = 'avatars/'.$user->id.'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($chemin, $contenu);
        $this->supprimerFichier($user);
        $user->forceFill(['avatar_path' => $chemin])->save();

        return response()->json(['message' => 'Photo de profil mise à jour.', 'avatar_url' => $user->avatarUrl()], 201);
    }

    public function supprimerAvatar(Request $request)
    {
        $user = $request->user();
        $this->supprimerFichier($user);
        $user->forceFill(['avatar_path' => null])->save();

        return response()->json(['message' => 'Photo de profil supprimée.', 'avatar_url' => null]);
    }

    /** Photo d'un membre de l'ENT : visible des seuls utilisateurs connectés (trombinoscope, messagerie). */
    public function avatar(User $user)
    {
        $disque = Storage::disk('local');
        abort_unless($user->avatar_path && $disque->exists($user->avatar_path), 404);

        return $disque->response($user->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function changerMotDePasse(Request $request)
    {
        $request->validate([
            'mot_de_passe_actuel' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:mot_de_passe_actuel', Password::min(12)->mixedCase()->numbers()],
        ], [
            'mot_de_passe_actuel.current_password' => 'Mot de passe actuel incorrect.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l\'actuel.',
            'password.confirmed' => 'La confirmation ne correspond pas.',
        ]);

        $request->user()->forceFill(['password' => $request->input('password')])->save();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    /** Recadrage carré centré et ré-encodage JPEG ; null si l'image n'est pas décodable. */
    private function normaliser(UploadedFile $fichier): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));
        if (! $source) {
            return null;
        }

        $l = imagesx($source);
        $h = imagesy($source);
        $cote = min($l, $h);
        $cible = imagecreatetruecolor(self::TAILLE_AVATAR, self::TAILLE_AVATAR);
        imagefill($cible, 0, 0, imagecolorallocate($cible, 255, 255, 255));   // fond blanc pour les PNG transparents
        imagecopyresampled($cible, $source, 0, 0, intdiv($l - $cote, 2), intdiv($h - $cote, 2), self::TAILLE_AVATAR, self::TAILLE_AVATAR, $cote, $cote);

        ob_start();
        imagejpeg($cible, null, 86);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($cible);

        return $jpeg ?: null;
    }

    private function supprimerFichier(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
    }
}
