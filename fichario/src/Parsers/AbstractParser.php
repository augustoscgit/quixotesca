<?php
declare(strict_types=1);

namespace App\Parsers;

abstract class AbstractParser
{
    protected function extract_meta_tags(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $meta = [];
        foreach ($document->getElementsByTagName('meta') as $node) {
            $name = strtolower(trim($node->getAttribute('name') ?: $node->getAttribute('property')));
            $content = $this->normalize_text($node->getAttribute('content'));

            if ($name === '' || $content === '') {
                continue;
            }

            $meta[$name][] = $content;
        }

        return $meta;
    }

    protected function extract_json_ld(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $items = [];
        foreach ($document->getElementsByTagName('script') as $node) {
            if (strtolower(trim($node->getAttribute('type'))) !== 'application/ld+json') {
                continue;
            }

            $decoded = json_decode(trim($node->textContent), true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        return $items;
    }

    protected function first_meta(array $meta, array $names): string
    {
        foreach ($names as $name) {
            $key = strtolower($name);
            if (!empty($meta[$key][0])) {
                return $this->normalize_text((string) $meta[$key][0]);
            }
        }

        return '';
    }

    protected function meta_values(array $meta, array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            foreach ($meta[strtolower($name)] ?? [] as $value) {
                $value = $this->normalize_text((string) $value);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    protected function keyword_values(array $meta, array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            foreach ($meta[strtolower($name)] ?? [] as $value) {
                $parts = preg_split('/\s*;\s*|\s*,\s*/u', (string) $value) ?: [];
                foreach ($parts as $part) {
                    $part = $this->normalize_text($part);
                    if ($part !== '') {
                        $values[] = $part;
                    }
                }
            }
        }

        return $values;
    }

    protected function year_from_metadata(array $meta): string
    {
        $date = $this->first_meta($meta, [
            'citation_publication_date',
            'citation_online_date',
            'citation_date',
            'dc.date',
            'prism.publicationDate',
        ]);

        if (preg_match('/\b(18|19|20)\d{2}\b/', $date, $match)) {
            return $match[0];
        }

        return '';
    }

    protected function first_non_empty(array $values): string
    {
        foreach ($values as $value) {
            $value = $this->normalize_text((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function value_from_json_ld(array $items, string $field): string
    {
        foreach ($items as $item) {
            if (isset($item[$field]) && is_scalar($item[$field])) {
                return $this->normalize_text((string) $item[$field]);
            }
        }

        return '';
    }

    protected function doi_from_json_ld(array $items): string
    {
        foreach ($items as $item) {
            if (isset($item['identifier'])) {
                $identifier = is_array($item['identifier']) ? json_encode($item['identifier']) : (string) $item['identifier'];
                $doi = $this->doi_from_text((string) $identifier);
                if ($doi !== '') {
                    return $doi;
                }
            }

            if (isset($item['sameAs'])) {
                $sameAs = is_array($item['sameAs']) ? implode(' ', $item['sameAs']) : (string) $item['sameAs'];
                $doi = $this->doi_from_text($sameAs);
                if ($doi !== '') {
                    return $doi;
                }
            }
        }

        return '';
    }

    protected function doi_from_text(string $text): string
    {
        if (preg_match('/10\.\d{4,9}\/[-._;()\/:A-Z0-9]+/i', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $match)) {
            return $this->clean_doi($match[0]);
        }

        return '';
    }

    protected function clean_doi(string $doi): string
    {
        $doi = preg_replace('/^https?:\/\/(dx\.)?doi\.org\//i', '', trim($doi)) ?? $doi;
        $doi = preg_replace('/^doi:\s*/i', '', $doi) ?? $doi;

        return rtrim($doi, " \t\n\r\0\x0B.,;)");
    }

    protected function normalize_text(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function scielo_html_to_clean_text(\DOMNode $node): string
    {
        $blocks = $this->scielo_html_to_blocks($node);
        $lines = [];
        foreach ($blocks as $block) {
            $content = $block['content'];
            if ($block['type'] === 'heading') {
                $lines[] = "\n\n[" . $content . "]\n\n";
            } elseif ($block['type'] === 'li') {
                $lines[] = "\n- " . $content;
            } else {
                $lines[] = "\n\n" . $content . "\n\n";
            }
        }
        $text = implode('', $lines);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    protected function scielo_html_to_blocks(\DOMNode $node): array
    {
        $blocks = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $val = trim((string) $child->nodeValue);
                if ($val !== '') {
                    $blocks[] = ['type' => 'text', 'content' => $val];
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower((string) $child->nodeName);
                $trimmedText = trim((string) $child->textContent);

                $isHeading = false;
                if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                    $isHeading = true;
                } elseif (($tagName === 'b' || $tagName === 'strong') && strlen($trimmedText) < 100 && strlen($trimmedText) > 2) {
                    $pattern = '/^(?:(?:\d+[\.\-\s]*)?(?:introdução|introduction|método|method|methods|material|resultados|results|discussão|discussion|conclusão|conclusion|conclusões|conclusions|discussao|conclusao|conclusoes|agradecimentos|acknowledgements|acknowledgments|acknowledgment|fontes|discussão\s+e\s+conclusão|resultados\s+e\s+discussão|abstract|resumo|referências|references|colaboradores|apêndice|anexo)(?:\s+.*)?)$/ui';
                    if (preg_match($pattern, $trimmedText)) {
                        $isHeading = true;
                    }
                } elseif ($tagName === 'p') {
                    $boldElements = $child->getElementsByTagName('b');
                    $strongElements = $child->getElementsByTagName('strong');
                    $boldCount = $boldElements->length;
                    $strongCount = $strongElements->length;

                    if (($boldCount === 1 && $strongCount === 0 && trim((string) $child->textContent) === trim((string) $boldElements->item(0)->textContent))
                        || ($strongCount === 1 && $boldCount === 0 && trim((string) $child->textContent) === trim((string) $strongElements->item(0)->textContent))) {
                        $pattern = '/^(?:(?:\d+[\.\-\s]*)?(?:introdução|introduction|método|method|methods|material|resultados|results|discussão|discussion|conclusão|conclusion|conclusões|conclusions|discussao|conclusao|conclusoes|agradecimentos|acknowledgements|acknowledgments|acknowledgment|fontes|discussão\s+e\s+conclusão|resultados\s+e\s+discussão|abstract|resumo|referências|references|colaboradores|apêndice|anexo)(?:\s+.*)?)$/ui';
                        if (preg_match($pattern, $trimmedText)) {
                            $isHeading = true;
                        }
                    }
                }

                if ($isHeading && $trimmedText !== '') {
                    $blocks[] = ['type' => 'heading', 'content' => $trimmedText];
                } elseif ($tagName === 'p') {
                    $cleanText = $this->normalize_text((string) $child->textContent);
                    if ($cleanText !== '') {
                        $blocks[] = ['type' => 'text', 'content' => $cleanText];
                    }
                } elseif (in_array($tagName, ['div', 'section', 'article', 'body', 'ul', 'ol'], true)) {
                    $subBlocks = $this->scielo_html_to_blocks($child);
                    foreach ($subBlocks as $sb) {
                        $blocks[] = $sb;
                    }
                } elseif ($tagName === 'li') {
                    $cleanText = $this->normalize_text((string) $child->textContent);
                    if ($cleanText !== '') {
                        $blocks[] = ['type' => 'li', 'content' => $cleanText];
                    }
                } else {
                    $subBlocks = $this->scielo_html_to_blocks($child);
                    foreach ($subBlocks as $sb) {
                        $blocks[] = $sb;
                    }
                }
            }
        }
        return $blocks;
    }

    protected function get_clean_body(array $blocks): string
    {
        $hasBodyHeadings = false;
        foreach ($blocks as $block) {
            if ($block['type'] === 'heading') {
                $content = $block['content'];
                if (!preg_match('/^(resumo|abstract|article\s+summary)$/ui', $content)) {
                    $hasBodyHeadings = true;
                    break;
                }
            }
        }

        $lines = [];
        $keep = !$hasBodyHeadings;

        foreach ($blocks as $block) {
            $content = $block['content'];
            if ($block['type'] === 'heading') {
                if (preg_match('/^(resumo|abstract|article\s+summary)$/ui', $content)) {
                    continue;
                }

                if (preg_match('/^(referências|references|referências\s+bibliográficas|referencias\s+bibliograficas)$/ui', $content)) {
                    $keep = false;
                    break;
                }

                $keep = true;
                $lines[] = "\n\n[" . $content . "]\n\n";
            } else {
                if ($keep) {
                    if ($block['type'] === 'li') {
                        $lines[] = "\n- " . $content;
                    } else {
                        $lines[] = "\n\n" . $content . "\n\n";
                    }
                }
            }
        }

        $text = implode('', $lines);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}
