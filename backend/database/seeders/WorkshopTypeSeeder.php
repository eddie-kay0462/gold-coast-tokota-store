<?php

namespace Database\Seeders;

use App\Models\WorkshopType;
use Illuminate\Database\Seeder;

/**
 * The workshop programme, verbatim from §15 of `GOLD_COAST_TOKOTA.md`.
 *
 * Seeded rather than hard-coded because the brand edits it: a capacity ceiling
 * or a start time changing should be a settings edit, not a deploy. §22.3 is
 * explicit that none of these may be changed without instruction, so the
 * values below are transcribed, not adapted — including the two daily slots on
 * the school tour and the three experiences that run only by appointment.
 *
 * `firstOrCreate` on the slug: re-seeding must not duplicate the programme or
 * overwrite a capacity the brand has since edited from admin.
 */
class WorkshopTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Sandal Sip & Paint',
                'slug' => 'sandal-sip-and-paint',
                'days_label' => 'Every Saturday',
                'slot_label' => '10:00 AM – 1:00 PM',
                'duration_label' => '3 hours',
                'capacity' => 20,
                'requires_appointment' => false,
                'description' => 'Paint and finish your own pair over drinks — a three-hour weekend session.',
            ],
            [
                'name' => 'Be a Shoemaker for a Day',
                'slug' => 'be-a-shoemaker-for-a-day',
                'days_label' => 'Every Friday',
                'slot_label' => '9:00 AM – 4:00 PM',
                'duration_label' => 'Full day',
                'capacity' => 10,
                'requires_appointment' => false,
                'description' => 'A full day at the bench with the production team, cutting, lasting and finishing a pair end to end.',
            ],
            [
                'name' => 'School Sustainability Tours',
                'slug' => 'school-sustainability-tours',
                'days_label' => 'Monday – Friday',
                'slot_label' => '9:00 AM – 12:00 PM / 1:00 PM – 3:00 PM',
                'duration_label' => '2–3 hours',
                'capacity' => 40,
                'requires_appointment' => false,
                'description' => 'A guided workshop tour for school groups, covering circular manufacturing and how discarded tyres and textiles become footwear.',
            ],
            [
                'name' => 'Corporate Team Building Workshop',
                'slug' => 'corporate-team-building-workshop',
                'days_label' => 'By Appointment',
                'slot_label' => 'Flexible',
                'duration_label' => 'Half day / Full day',
                'capacity' => 30,
                'requires_appointment' => true,
                'description' => 'A hands-on making session for teams, scheduled around your calendar.',
            ],
            [
                'name' => 'Cultural Craft Experience',
                'slug' => 'cultural-craft-experience',
                'days_label' => 'By Appointment',
                'slot_label' => 'Flexible',
                'duration_label' => '2 hours',
                'capacity' => 15,
                'requires_appointment' => true,
                'description' => 'Two hours with our artisans on the craft and heritage behind the ahenema.',
            ],
            [
                'name' => 'International Visitor Experience',
                'slug' => 'international-visitor-experience',
                'days_label' => 'By Appointment',
                'slot_label' => 'Flexible',
                'duration_label' => '2–4 hours',
                'capacity' => 20,
                'requires_appointment' => true,
                'description' => 'A visit built for travellers — the workshop, the makers and the making, in as much depth as your schedule allows.',
            ],
        ];

        foreach ($types as $index => $type) {
            WorkshopType::query()->firstOrCreate(
                ['slug' => $type['slug']],
                $type + ['sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
