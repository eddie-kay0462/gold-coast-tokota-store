<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the gap between what `ApiProduct` (frontend/utils/catalog.ts) reads
 * and what ProductResource emits. The storefront's shop listing and product
 * detail pages were built against these fields and have been falling back to
 * DESIGN_PRODUCTS fixtures for every one of them.
 *
 * Two fields from that type are deliberately NOT here:
 *   - `rating` / `reviews` — product reviews are unplanned scope (no README
 *     feature covers them) and need a stakeholder decision on authorship and
 *     moderation before they get a table. See FOR_THE_TEAM.md.
 *   - `sizes` / `size_availability` — already computed from InventoryItem
 *     (Product::getSizeAvailabilityAttribute), never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Was-price, minor units, same as base_price_ghs. Nullable because
            // "on sale" is exactly the state of having one. The storefront only
            // renders a discount when this exceeds base_price_ghs; that
            // relationship is validated at the Form Request layer rather than a
            // CHECK constraint, matching how merchandising_badge is handled.
            $table->unsignedBigInteger('compare_at_ghs')->nullable()->after('base_price_ghs');

            // What the listing sidebar's "Category" facet filters on (ahenema,
            // slippers, sandals, closed-toe) — distinct from category_id, which
            // is the top-level catalogue split, and from departments below.
            // Indexed: it is the most-used facet on the listing page.
            $table->string('product_type')->nullable()->after('collection_id');
            $table->index('product_type');

            // Merchandising groupings the header nav resolves to via
            // `?category=` (mens, womens, kids). A product can sit in more than
            // one, so this is a list rather than a column per department.
            $table->jsonb('departments')->default('[]');

            // Width fittings (s, m, l). Not on InventoryItem: width is a
            // catalogue-level claim about what the product is made in, and the
            // storefront renders the full range with unavailable ones struck
            // through — the same reason `sizes` is derived rather than filtered.
            $table->jsonb('widths')->default('[]');

            // Sustainability/fulfilment badges shown on the card, e.g.
            // "Custom Made", "Renewed Materials". Free text by design: the
            // brand adds new ones from admin without a deploy.
            $table->jsonb('tags')->default('[]');

            // The colourway pictured, plus every colourway the product ships
            // in (a list of {name, hex} for the card's swatch row).
            //
            // jsonb rather than a product_colors table on purpose: nothing in
            // the current design hangs stock, images or a price off a colour —
            // they are swatches. The moment a colourway needs its own photos or
            // its own inventory it has become a variant and belongs in a table
            // with inventory_items pointing at it. Cheap to promote later,
            // expensive to over-build now.
            $table->string('color')->nullable();
            $table->jsonb('colors')->default('[]');

            // Renders a "Pre-Order" badge and blocks immediate add-to-cart.
            $table->boolean('is_pre_order')->default(false);

            // Long-form detail copy: description_heading titles the existing
            // `description` body, model_note is the fit line under it.
            $table->string('description_heading')->nullable()->after('description');
            $table->string('model_note')->nullable();

            // The "Transparent Pricing" panel — an ordered list of
            // {label, amount_ghs, icon} cost lines. Ordered and free-form, so
            // jsonb rather than a table: the brand rewrites these lines as
            // editorial content, they are never queried or aggregated.
            $table->jsonb('cost_breakdown')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropColumn([
                'compare_at_ghs',
                'product_type',
                'departments',
                'widths',
                'tags',
                'color',
                'colors',
                'is_pre_order',
                'description_heading',
                'model_note',
                'cost_breakdown',
            ]);
        });
    }
};
