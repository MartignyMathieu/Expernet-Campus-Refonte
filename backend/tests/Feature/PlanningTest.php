<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireScenario();
    }

    public function test_la_vue_agenda_charge_une_periode_choisie(): void
    {
        $lundi = Carbon::today()->startOfWeek();
        $this->creerSeance($lundi->copy()->addWeeks(3), 'matin');
        $this->creerSeance($lundi->copy()->addWeeks(3), 'apres_midi');

        // Hors de la période par défaut (2 semaines), mais visible en vue mois
        $du = $lundi->copy()->addWeeks(3)->toDateString();
        $au = $lundi->copy()->addWeeks(3)->endOfWeek()->toDateString();
        $this->actingAs($this->etudiant)->getJson("/api/planning?du=$du&au=$au")->assertOk()
            ->assertJsonPath('du', $du)->assertJsonCount(2, 'seances');
    }

    public function test_une_periode_trop_longue_ou_inversee_est_refusee(): void
    {
        $this->actingAs($this->etudiant)->getJson('/api/planning?du=2026-01-01&au=2026-12-31')->assertStatus(422);
        $this->actingAs($this->etudiant)->getJson('/api/planning?du=2026-10-10&au=2026-10-01')->assertStatus(422);
    }

    public function test_export_icalendar_du_planning(): void
    {
        $r = $this->actingAs($this->etudiant)->get('/api/planning/ics')->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $ics = $r->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertMatchesRegularExpression('/DTSTART;TZID=Indian\/Reunion:\d{8}T\d{6}/', $ics);
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        foreach (explode("\r\n", $ics) as $ligne) {
            $this->assertLessThanOrEqual(75, strlen($ligne), 'Lignes pliées à 75 octets (RFC 5545)');
        }
    }

    public function test_l_export_est_cloisonne(): void
    {
        $this->assertSame(0, substr_count($this->actingAs($this->etudiantHorsPromo)->get('/api/planning/ics')->getContent(), 'BEGIN:VEVENT'));
        $this->assertSame(1, substr_count($this->actingAs($this->formateur)->get('/api/planning/ics')->getContent(), 'BEGIN:VEVENT'));
    }

    public function test_l_export_exige_une_session(): void
    {
        $this->get('/api/planning/ics')->assertUnauthorized();
    }
}
