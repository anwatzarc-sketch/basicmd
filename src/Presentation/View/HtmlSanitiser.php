<?php

declare(strict_types=1);

namespace Aster\Presentation\View;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Allow-list HTML sanitiser for CMS content.
 *
 * Article bodies are authored by clinic staff in a rich-text field and stored
 * as HTML. Echoing that unescaped would make a single compromised editor
 * account into stored XSS across the whole public site.
 *
 * Built on an allow-list, never a blocklist: anything not explicitly
 * permitted is dropped. Blocklists lose to encoding tricks and to whatever
 * element gets added to HTML next; an allow-list fails closed.
 *
 * Parsing is done with DOM rather than regex, because regex cannot reliably
 * distinguish `<script>` from `<scr<script>ipt>` or from an attribute value
 * that merely looks like a tag.
 */
final class HtmlSanitiser
{
    /** Tags kept, mapped to the attributes each may carry. */
    private const array ALLOWED = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        'h2'         => ['id'],
        'h3'         => ['id'],
        'h4'         => ['id'],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'blockquote' => [],
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'width', 'height', 'loading'],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => ['colspan', 'rowspan'],
        'td'         => ['colspan', 'rowspan'],
        'hr'         => [],
        'figure'     => [],
        'figcaption' => [],
        'sup'        => [],
        'sub'        => [],
        'code'       => [],
        'pre'        => [],
    ];

    /** Schemes permitted in href and src. */
    private const array SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        // libxml chatters on any real-world HTML; the sanitiser's job is to
        // produce safe output, not to validate the author's markup.
        $previous = libxml_use_internal_errors(true);

        // The XML declaration forces UTF-8 interpretation. Without it
        // DOMDocument assumes ISO-8859-1 and mangles every Ge'ez character.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="aster-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            // Unparseable input is stripped to text rather than passed on.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $xpath = new DOMXPath($document);
        $root  = $document->getElementById('aster-root');

        if (!$root instanceof DOMElement) {
            $found = $xpath->query('//div[@id="aster-root"]');
            $root  = $found !== false && $found->length > 0 ? $found->item(0) : null;
        }

        if (!$root instanceof DOMElement) {
            return '';
        }

        self::sanitiseNode($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /** Depth-first walk, removing or unwrapping anything not allowed. */
    private static function sanitiseNode(DOMNode $node): void
    {
        // Snapshot first: removing children while iterating a live NodeList
        // skips elements.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (!array_key_exists($tag, self::ALLOWED)) {
                    // script/style carry executable or layout-breaking
                    // payloads in their text, so they are deleted whole.
                    // Any other unknown tag is unwrapped, preserving the
                    // author's words while discarding the element.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
                        $node->removeChild($child);
                    } else {
                        self::unwrap($child);
                    }

                    continue;
                }

                self::sanitiseAttributes($child, self::ALLOWED[$tag]);
                self::sanitiseNode($child);
            } elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                // Conditional comments are an execution vector in old IE and
                // carry nothing of value regardless.
                $node->removeChild($child);
            }
        }
    }

    /** Replace an element with its children. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /** @param list<string> $allowedAttributes */
    private static function sanitiseAttributes(DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (!$attribute instanceof DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->nodeName);

            // Drops every on* handler in one rule, including ones that do
            // not exist yet.
            if (!in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && !self::isSafeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // Any link that opens a new tab gets noopener, or the opened page can
        // reach back through window.opener and redirect the original tab.
        if (strtolower($element->nodeName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        // Images below the fold should not block first paint.
        if (strtolower($element->nodeName) === 'img') {
            $element->setAttribute('loading', 'lazy');

            if ($element->getAttribute('alt') === '') {
                $element->setAttribute('alt', '');
            }
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Relative and anchor links carry no scheme and are safe.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        // Strip whitespace and control characters before testing the scheme:
        // "java\tscript:" is treated as javascript: by several browsers.
        $normalised = strtolower(preg_replace('/[\s\x00-\x20]+/', '', $url) ?? $url);

        $colon = strpos($normalised, ':');

        if ($colon === false) {
            return true; // Scheme-relative path.
        }

        $scheme = substr($normalised, 0, $colon);

        return in_array($scheme, self::SAFE_SCHEMES, true);
    }

    /**
     * Plain text from HTML, for meta descriptions and email previews.
     */
    public static function toText(string $html, int $maxLength = 0): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength - 1) . "\u{2026}";
        }

        return $text;
    }
}
