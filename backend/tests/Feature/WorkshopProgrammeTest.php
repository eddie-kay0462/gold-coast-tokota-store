<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use Database\Seeders\WorkshopTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workshop programme from §15 of the brand document — the six experiences
 * a session is scheduled against.
 */
class WorkshopProgrammeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_programme_lists_the_six_published_experiences(): void
    {
        $this->seed(WorkshopTypeSeeder::class);

        $response = $this->getJson('/api/v1/workshop-types');

        $response->assertOk();
        $this->assertCount(6, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'Sandal Sip & Paint');
        $response->assertJsonPath('data.0.days_label', 'Every Saturday');
        $response->assertJsonPath('data.0.capacity', 20);
    }

    /**
     * Three of the six run only by appointment, so they never appear in the
     * session list. A client offering seats for them would be offering seats
     * that do not exist.
     */
    public function test_by_appointment_experiences_are_flagged_as_such(): void
    {
        $this->seed(WorkshopTypeSeeder::class);

        $types = collect($this->getJson('/api/v1/workshop-types')->json('data'));

        $this->assertSame(3, $types->where('requires_appointment', true)->count());
        $this->assertSame(
            'Corporate Team Building Workshop',
            $types->firstWhere('requires_appointment', true)['name'],
        );
    }

    public function test_an_inactive_experience_is_not_advertised(): void
    {
        WorkshopType::factory()->create(['name' => 'Retired', 'is_active' => false]);
        WorkshopType::factory()->create(['name' => 'Running']);

        $response = $this->getJson('/api/v1/workshop-types');

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'Running');
    }

    /** Without this the booking page cannot say which experience it is offering. */
    public function test_a_session_carries_the_experience_it_runs(): void
    {
        $type = WorkshopType::factory()->create(['name' => 'Sandal Sip & Paint']);
        WorkshopSession::factory()->create([
            'workshop_type_id' => $type->id,
            'scheduled_date' => today()->addWeek(),
        ]);

        $this->getJson('/api/v1/workshop-sessions')
            ->assertOk()
            ->assertJsonPath('data.0.workshop_type.name', 'Sandal Sip & Paint');
    }

    public function test_the_admin_programme_counts_what_is_scheduled(): void
    {
        $type = WorkshopType::factory()->create();
        WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => today()->addWeek()]);
        WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => today()->subWeek()]);

        $staff = AdminUser::factory()->create(['role' => 'staff']);

        $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/workshop-types')
            ->assertOk()
            // Upcoming only — a count that includes last month's sessions is
            // not an answer to "what is scheduled".
            ->assertJsonPath('data.0.upcoming_sessions_count', 1);
    }

    public function test_the_programme_is_closed_to_guests_on_the_admin_side(): void
    {
        $this->getJson('/api/v1/admin/workshop-types')->assertUnauthorized();
    }

    /** Re-seeding must not duplicate the programme or undo a brand edit. */
    public function test_seeding_twice_leaves_six_experiences(): void
    {
        $this->seed(WorkshopTypeSeeder::class);
        WorkshopType::query()->where('slug', 'sandal-sip-and-paint')->update(['capacity' => 18]);
        $this->seed(WorkshopTypeSeeder::class);

        $this->assertSame(6, WorkshopType::query()->count());
        $this->assertSame(18, WorkshopType::query()->where('slug', 'sandal-sip-and-paint')->value('capacity'));
    }
}
