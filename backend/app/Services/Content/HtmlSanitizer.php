<?php

namespace App\Services\Content;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Server-side sanitisation for rich text submitted through the admin
 * `PageEditor` — README Feature 9 edge case: "must sanitize submitted HTML
 * server-side to prevent stored XSS".
 *
 * Server-side is the operative word. The editor may well strip scripts in the
 * browser, but the browser is not where the trust boundary is: anything that
 * can POST to the endpoint can post whatever it likes. This runs on the way
 * in, so what lands in the database is already safe to render.
 *
 * HTMLPurifier rather than a hand-rolled allowlist: stripping dangerous HTML
 * correctly means handling malformed markup, nested encodings, `javascript:`
 * URLs, CSS expressions and mutation-XSS. That is a library problem, and
 * hand-rolled sanitisers are a well-known source of bypasses.
 */
class HtmlSanitizer
{
    /**
     * What the About page and blog posts legitimately need: structure,
     * emphasis, links, lists, images and tables. No <script>, no <style>, no
     * <iframe>, no event handlers, no inline styles.
     *
     * No <figure>/<figcaption>: HTMLPurifier's doctype is HTML 4.01 and it
     * emits a warning for both rather than allowing them. Adding HTML5
     * elements means a custom definition, which is not worth it for a caption
     * — <img> plus a <p> reads the same.
     */
    private const ALLOWED_ELEMENTS =
        'p,br,strong,b,em,i,u,s,blockquote,pre,code,'
        .'h2,h3,h4,h5,h6,'
        .'ul,ol,li,'
        .'a[href|title|rel],'
        .'img[src|alt|title|width|height],'
        .'hr,'
        .'table,thead,tbody,tr,th[scope|colspan|rowspan],td[colspan|rowspan]';

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return $this->purifier()->purify($html);
    }

    private function purifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', self::ALLOWED_ELEMENTS);

        // h1 is the page's own title, rendered by the layout. Letting an editor
        // add more breaks the document outline and the page's SEO.
        $config->set('AutoFormat.RemoveEmpty', true);

        // Only schemes that cannot execute. This is what stops
        // `javascript:`, `data:` payloads and `vbscript:` in href/src.
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        // `target` is not in the allowlist at all, which sidesteps the whole
        // window.opener class of problem rather than mitigating it. Whether a
        // link opens a new tab is an editorial preference; it is not worth the
        // attack surface, and the storefront can style outbound links itself.
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer']);

        // HTMLPurifier compiles a definition cache; without a writable path it
        // falls back to serialising on every call, which is slow and noisy.
        $cache = storage_path('app/htmlpurifier');
        if (! is_dir($cache)) {
            mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);

        return new HTMLPurifier($config);
    }
}
