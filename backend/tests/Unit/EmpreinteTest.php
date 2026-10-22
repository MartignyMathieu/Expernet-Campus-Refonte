<?php

namespace Tests\Unit;

use App\Models\Formation;
use App\Services\EmargementService;
use PHPUnit\Framework\TestCase;

class EmpreinteTest extends TestCase
{
    public function test_l_empreinte_est_un_sha256_deterministe(): void
    {
        $a = EmargementService::calculerEmpreinte(1, 2, '2026-10-22T08:35:00+04:00', hash('sha256', 'img'));
        $b = EmargementService::calculerEmpreinte(1, 2, '2026-10-22T08:35:00+04:00', hash('sha256', 'img'));

        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a);
    }

    public function test_toute_modification_change_l_empreinte(): void
    {
        $base = EmargementService::calculerEmpreinte(1, 2, '2026-10-22T08:35:00+04:00', hash('sha256', 'img'));

        $this->assertNotSame($base, EmargementService::calculerEmpreinte(1, 3, '2026-10-22T08:35:00+04:00', hash('sha256', 'img')));
        $this->assertNotSame($base, EmargementService::calculerEmpreinte(1, 2, '2026-10-22T08:36:00+04:00', hash('sha256', 'img')));
        $this->assertNotSame($base, EmargementService::calculerEmpreinte(1, 2, '2026-10-22T08:35:00+04:00', hash('sha256', 'img2')));
    }

    public function test_libelle_du_niveau_rncp(): void
    {
        $formation = new Formation;
        foreach ([5 => 'Bac+2', 6 => 'Bac+3/4', 7 => 'Bac+5'] as $niveau => $attendu) {
            $formation->niveau_rncp = $niveau;
            $this->assertSame($attendu, $formation->libelleNiveau());
        }
    }
}
