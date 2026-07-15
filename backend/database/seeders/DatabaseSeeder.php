<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! AdminUser::query()->where('email', 'admin@goldcoasttokota.store')->exists()) {
            AdminUser::factory()->create([
                'name' => 'Test Admin',
                'email' => 'admin@goldcoasttokota.store',
                'role' => 'admin',
            ]);
        }

        SiteSetting::current()->update([
            'whatsapp_number' => '233200000000',
            'whatsapp_default_message' => 'Hi! I have a question about your sandals.',
            'contact_email' => 'hello@goldcoasttokota.store',
            'diy_turnaround_estimate' => '2-3 weeks',
        ]);

        Page::query()->updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About Gold Coast Tokota',
                'body' => '<p>Handmade in Ghana, one pair at a time.</p>',
            ],
        );
    }
}
