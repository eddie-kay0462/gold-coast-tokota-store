<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\MediaAsset;
use App\Models\MegaMenuPromo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront's mega menus: links from the catalogue, tiles from
 * Content › Menus with product photos in any empty slot.
 */
class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $type, array $departments, array $extra = []): Product
    {
        return Product::factory()->create($extra + [
            'product_type' => $type,
            'departments' => $departments,
            'compare_at_ghs' => null,
            'is_active' => true,
            'images' => ['products/'.$type.'.jpg'],
        ]);
    }

    private function menu(string $key): array
    {
        return collect($this->getJson('/api/v1/navigation/menus')->assertOk()->json('data'))->firstWhere('key', $key);
    }

    private function links(array $menu, string $heading): array
    {
        return collect(collect($menu['columns'])->firstWhere('heading', $heading)['links'] ?? [])->pluck('to', 'label')->all();
    }

    private function asset(): MediaAsset
    {
        return MediaAsset::query()->create([
            'path' => 'media/2026/10/tile.jpg', 'filename' => 'tile.jpg', 'mime_type' => 'image/jpeg',
            'size_bytes' => 1000, 'width' => 800, 'height' => 600, 'alt_text' => 'Red ahenema on sand',
        ]);
    }

    public function test_menus_link_only_to_styles_and_departments_that_have_products(): void
    {
        $this->product('slippers', ['mens']);
        $this->product('ahenema', ['womens']);
        $this->product('ahenema', ['mens'], ['is_active' => false, 'product_type' => 'closed-toe']);

        $sandals = $this->menu('sandals');
        $this->assertSame([
            'All Sandals' => '/shop',
            'Slippers' => '/shop?type=slippers',
            'Ahenema' => '/shop?type=ahenema',
        ], $this->links($sandals, 'Styles'));
        $this->assertSame(['Men’s' => '/shop?category=mens', 'Women’s' => '/shop?category=womens'], $this->links($sandals, 'Shop for'));
    }

    public function test_a_one_style_menu_narrows_within_itself(): void
    {
        $this->product('slippers', ['mens']);
        $this->product('ahenema', ['womens']);
        $this->product('ahenema', ['mens']);

        $ahenema = $this->menu('ahenema');
        $this->assertSame(['All Ahenema' => '/shop?type=ahenema'], $this->links($ahenema, 'Styles'));
        $this->assertSame([
            'Men’s' => '/shop?type=ahenema&category=mens',
            'Women’s' => '/shop?type=ahenema&category=womens',
        ], $this->links($ahenema, 'Shop for'));
        $this->assertSame('/shop?type=ahenema&sort=newest', $this->links($ahenema, 'Featured')['New Arrivals']);
    }

    public function test_on_sale_appears_only_when_something_is_and_never_in_the_sale_menu(): void
    {
        $this->product('slippers', ['mens']);
        $this->assertArrayNotHasKey('On Sale', $this->links($this->menu('sandals'), 'Featured'));

        $this->product('ahenema', ['mens'], ['base_price_ghs' => 40000, 'compare_at_ghs' => 50000]);
        $this->assertSame('/shop?sale=true', $this->links($this->menu('sandals'), 'Featured')['On Sale']);
        $this->assertArrayNotHasKey('On Sale', $this->links($this->menu('sale'), 'Featured'));
        $this->assertArrayNotHasKey('Top Rated', $this->links($this->menu('sandals'), 'Featured'));
    }

    public function test_empty_tiles_show_the_menus_own_products_of_different_styles(): void
    {
        $slipper = $this->product('slippers', ['mens'], ['name' => 'Ogya']);
        $ahenema = $this->product('ahenema', ['womens'], ['name' => 'Sikapa']);

        $tiles = $this->menu('sandals')['promos'];
        $this->assertEqualsCanonicalizing(['Ogya', 'Sikapa'], array_column($tiles, 'label'));
        $this->assertFalse($tiles[0]['chosen']);

        $this->assertSame(['Sikapa'], array_column($this->menu('ahenema')['promos'], 'label'));
        $this->assertSame('/shop/'.$ahenema->slug, $this->menu('ahenema')['promos'][0]['to']);
    }

    public function test_a_chosen_tile_replaces_the_product_photo_until_its_image_is_deleted(): void
    {
        $this->product('ahenema', ['womens'], ['name' => 'Sikapa']);
        $asset = $this->asset();

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->putJson('/api/v1/admin/menus/ahenema', ['slots' => [
                ['position' => 1, 'media_asset_id' => $asset->id, 'caption' => 'New season ahenema', 'link' => '/shop?type=ahenema&sort=newest'],
            ]])->assertOk();

        $tile = $this->menu('ahenema')['promos'][0];
        $this->assertSame(['New season ahenema', '/shop?type=ahenema&sort=newest', 'Red ahenema on sand', true], [$tile['label'], $tile['to'], $tile['alt'], $tile['chosen']]);
        $this->assertStringEndsWith('media/2026/10/tile.jpg', $tile['image']);

        $asset->delete();
        $this->assertSame('Sikapa', $this->menu('ahenema')['promos'][0]['label']);
    }

    public function test_clearing_a_slot_removes_the_choice(): void
    {
        $asset = $this->asset();
        MegaMenuPromo::query()->create(['menu' => 'sale', 'position' => 2, 'media_asset_id' => $asset->id, 'caption' => 'Sale', 'link' => '/shop?sale=true']);

        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->putJson('/api/v1/admin/menus/sale', ['slots' => [['position' => 2, 'media_asset_id' => null]]])
            ->assertOk()
            ->assertJsonPath('data.3.slots.1.media_asset', null);

        $this->assertDatabaseCount('mega_menu_promos', 0);
    }

    public function test_tiles_link_only_within_the_site_and_need_a_caption(): void
    {
        $asset = $this->asset();
        $staff = AdminUser::factory()->create(['role' => 'staff']);

        foreach (['https://evil.example', '//evil.example/shop', 'shop'] as $link) {
            $this->actingAs($staff, 'admin')
                ->putJson('/api/v1/admin/menus/sandals', ['slots' => [['position' => 1, 'media_asset_id' => $asset->id, 'caption' => 'x', 'link' => $link]]])
                ->assertUnprocessable()->assertJsonValidationErrors('slots.0.link');
        }

        $this->actingAs($staff, 'admin')
            ->putJson('/api/v1/admin/menus/sandals', ['slots' => [['position' => 1, 'media_asset_id' => $asset->id, 'caption' => '', 'link' => '/shop']]])
            ->assertUnprocessable()->assertJsonValidationErrors('slots.0.caption');
    }

    public function test_interns_can_see_the_menus_but_not_change_them(): void
    {
        $intern = AdminUser::factory()->create(['role' => 'intern']);

        $this->actingAs($intern, 'admin')->getJson('/api/v1/admin/menus')->assertOk()->assertJsonCount(4, 'data');
        $this->actingAs($intern, 'admin')
            ->putJson('/api/v1/admin/menus/sandals', ['slots' => []])
            ->assertForbidden();
    }

    public function test_an_unknown_menu_is_not_found(): void
    {
        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->putJson('/api/v1/admin/menus/kids', ['slots' => []])
            ->assertNotFound();
    }
}
