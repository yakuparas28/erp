<?php

namespace App\Support;

/**
 * Basit HTML temizleyici. Zengin metin alanları (Filo Kullanım Kuralları vb.)
 * için write-time sanitization sağlar. HTMLPurifier bağımlılığı eklemeden
 * en yaygın stored-XSS vektörlerini kapatır:
 *  - `<script>`, `<style>`, `<iframe>`, `<object>`, `<embed>`, `<link>`, `<meta>`,
 *    `<form>`, `<svg>` blokları içerikleriyle beraber silinir.
 *  - `on*` event handler'ları (onclick, onerror, ...) tüm etiketlerden çıkarılır.
 *  - `href`/`src` içindeki `javascript:` ve `data:` şemaları düşürülür.
 *
 * Not: Bu, tam bir HTMLPurifier ikamesi değildir; yalnızca güvenilir editör
 * (Fleet Manager, Tenant Admin) tarafından üretilen içerik için makul bir
 * hijyen katmanıdır. Halka açık form girdilerinde kullanılmaz.
 */
final class HtmlSanitizer
{
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'svg', 'math',
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        foreach (self::DANGEROUS_TAGS as $tag) {
            $html = preg_replace('#<\s*'.$tag.'\b[^>]*>.*?<\s*/\s*'.$tag.'\s*>#is', '', (string) $html);
            $html = preg_replace('#<\s*'.$tag.'\b[^>]*/?>#is', '', (string) $html);
        }

        $html = preg_replace('#\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', (string) $html);
        $html = preg_replace('#(href|src|action|formaction)\s*=\s*(["\'])\s*(?:javascript|data|vbscript)\s*:[^"\']*\2#i', '$1=$2#$2', (string) $html);

        return (string) $html;
    }
}
