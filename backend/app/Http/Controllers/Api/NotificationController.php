<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Notifications de l'ENT (cloche) : les 30 dernières, compteur de non lues, marquage comme lues. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'non_lues' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id, 'lue' => $n->read_at !== null, 'date' => $n->created_at->toIso8601String(),
                'categorie' => $n->data['categorie'] ?? 'info', 'titre' => $n->data['titre'] ?? '',
                'message' => $n->data['message'] ?? '', 'lien' => $n->data['lien'] ?? null,
            ]),
        ]);
    }

    public function lire(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->noContent();
    }

    public function toutLire(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
