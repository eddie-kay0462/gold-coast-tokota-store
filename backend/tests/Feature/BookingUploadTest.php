<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\BookingUploadController;
use App\Jobs\PruneOrphanedBookingUploads;
use App\Models\AdminUser;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The DIY reference-photo upload (Feature 7).
 *
 * Most of what matters here is that this is the **only unauthenticated
 * write-to-disk endpoint on the API** — guest bookings are supported, so it
 * cannot sit behind a login, and the file rules plus the prune job are what
 * stand in for that.
 */
class BookingUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_guest_can_upload_a_reference_photo(): void
    {
        $response = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('my-sandal-idea.jpg', 800, 600),
        ]);

        $response->assertCreated();
        $path = $response->json('data.path');

        $this->assertStringStartsWith(BookingUploadController::DIRECTORY.'/', $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.filename', 'my-sandal-idea.jpg');
        $this->assertNotNull($response->json('data.url'));
    }

    public function test_the_stored_name_is_not_the_customers_filename(): void
    {
        $response = $this->postJson('/api/v1/booking-uploads', [
            // A traversal attempt that still satisfies the mime allowlist, so
            // it reaches the storage call rather than being caught earlier.
            'file' => UploadedFile::fake()->image('../../etc/passwd.jpg', 800, 600),
        ]);

        $response->assertCreated();
        $path = $response->json('data.path');

        // Laravel generates the stored name, so a hostile original never
        // reaches the filesystem — and the URL is not guessable from the
        // customer's name or the date either.
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('passwd', $path);
        Storage::disk('public')->assertExists($path);

        // The original survives only as a display label.
        $this->assertStringContainsString('passwd.jpg', $response->json('data.filename'));
    }

    public function test_a_file_with_a_disallowed_extension_never_reaches_storage(): void
    {
        $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('config.env', 800, 600),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles(BookingUploadController::DIRECTORY));
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->create('payload.php', 40, 'application/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles(BookingUploadController::DIRECTORY));
    }

    public function test_a_script_renamed_as_an_image_is_refused(): void
    {
        // Passes a naive extension check; fails the decoder that `dimensions`
        // forces the file through.
        $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "hi";'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles(BookingUploadController::DIRECTORY));
    }

    public function test_an_oversized_photo_is_refused(): void
    {
        $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('huge.jpg', 800, 600)->size(6_000),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_a_tiny_image_is_refused(): void
    {
        // A 1x1 pixel is not a reference photo; it is someone probing.
        $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('speck.jpg', 8, 8),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_a_missing_file_is_refused(): void
    {
        $this->postJson('/api/v1/booking-uploads', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    // ----------------------------------------------------- end to end

    public function test_an_uploaded_path_travels_onto_the_booking_and_reaches_admin(): void
    {
        $path = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('idea.jpg', 800, 600),
        ])->json('data.path');

        $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'details' => [
                'name' => 'Kofi Mensah',
                'email' => 'kofi@example.com',
                'phone' => '0209876543',
                'size' => '42',
                'foot_length' => 27.5,
                'fulfilment' => 'pickup',
                'reference_image' => $path,
            ],
        ])->assertCreated();

        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/bookings');

        $response->assertOk();
        // The workshop has to be able to open the photo, or the upload was
        // pointless.
        $this->assertNotNull($response->json('data.0.reference_image_url'));
        $this->assertStringContainsString($path, $response->json('data.0.reference_image_url'));
    }

    public function test_a_legacy_filename_does_not_become_a_broken_image_url(): void
    {
        // Bookings taken before the upload endpoint existed recorded the
        // customer's filename and asked for the photo over WhatsApp.
        Booking::factory()->create([
            'type' => 'diy_order',
            'details' => ['name' => 'Kofi', 'reference_image' => 'my-photo.jpg'],
        ]);

        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/bookings');

        $response->assertOk();
        $response->assertJsonPath('data.0.reference_image_url', null);
    }

    public function test_an_arbitrary_string_is_never_rendered_as_a_url(): void
    {
        Booking::factory()->create([
            'type' => 'diy_order',
            'details' => ['reference_image' => 'https://evil.test/tracker.gif'],
        ]);

        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/bookings')
            ->assertJsonPath('data.0.reference_image_url', null);
    }

    // ---------------------------------------------------------- pruning

    public function test_an_abandoned_upload_is_pruned(): void
    {
        $path = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('abandoned.jpg', 800, 600),
        ])->json('data.path');

        // Nobody ever submitted the booking.
        $this->travel(PruneOrphanedBookingUploads::GRACE_HOURS + 1)->hours();
        (new PruneOrphanedBookingUploads)->handle();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_upload_still_inside_the_grace_window_is_kept(): void
    {
        $path = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('in-progress.jpg', 800, 600),
        ])->json('data.path');

        // The customer is still filling in the rest of the form.
        (new PruneOrphanedBookingUploads)->handle();

        Storage::disk('public')->assertExists($path);
    }

    public function test_an_attached_upload_is_never_pruned(): void
    {
        $path = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('claimed.jpg', 800, 600),
        ])->json('data.path');

        Booking::factory()->create([
            'type' => 'diy_order',
            'details' => ['reference_image' => $path],
        ]);

        $this->travel(PruneOrphanedBookingUploads::GRACE_HOURS + 100)->hours();
        (new PruneOrphanedBookingUploads)->handle();

        // Age is irrelevant once a booking claims it — the workshop may not
        // make the sandal for weeks.
        Storage::disk('public')->assertExists($path);
    }

    public function test_pruning_an_empty_directory_is_harmless(): void
    {
        (new PruneOrphanedBookingUploads)->handle();

        $this->assertSame([], Storage::disk('public')->allFiles(BookingUploadController::DIRECTORY));
    }
}
