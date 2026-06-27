<?php
declare(strict_types=1);

namespace App\Parsers;

class ScieloParser extends AbstractParser implements ParserInterface
{
    public function supports(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        return $host === 'scielo.br' || str_ends_with($host, '.scielo.br');
    }

    public function parse(string $html, string $url): array
    {
        $meta = $this->extract_meta_tags($html);
        $jsonLd = $this->extract_json_ld($html);

        $firstPage = $this->first_meta($meta, ['citation_firstpage', 'dc.identifier.pageNumber']);
        $lastPage = $this->first_meta($meta, ['citation_lastpage']);
        $pages = $this->first_meta($meta, ['citation_pages']);

        if ($pages === '' && $firstPage !== '') {
            $pages = $lastPage !== '' ? $firstPage . '-' . $lastPage : $firstPage;
        }

        $authors = $this->meta_values($meta, ['citation_author', 'dc.creator', 'author']);
        $keywords = $this->keyword_values($meta, ['citation_keywords', 'dc.subject', 'keywords']);
        $year = $this->year_from_metadata($meta);
        $doi = $this->first_meta($meta, ['citation_doi', 'dc.identifier', 'doi']);

        if ($doi === '') {
            $doi = $this->doi_from_json_ld($jsonLd);
        }

        if ($doi === '') {
            $doi = $this->doi_from_text($html);
        }

        $references = '';
        $fullText = '';

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);

        // References Extraction
        $refNodes = $xpath->query("//div[contains(@class, 'articleSection--referências-bibliográficas')] | //div[contains(@class, 'ref-list')] | //div[contains(@class, 'ref')]");
        if ($refNodes->length > 0) {
            $references = $this->scielo_html_to_clean_text($refNodes->item(0));
        } else {
            $legacyRefNodes = $xpath->query("//a[@name='back']/following-sibling::* | //h1[contains(text(), 'Referências') or contains(text(), 'References')]/following-sibling::* | //h2[contains(text(), 'Referências') or contains(text(), 'References')]/following-sibling::*");
            if ($legacyRefNodes->length > 0) {
                $parts = [];
                foreach ($legacyRefNodes as $node) {
                    if (in_array(strtolower($node->nodeName), ['footer', 'hr'], true)) {
                        break;
                    }
                    $parts[] = $this->scielo_html_to_clean_text($node);
                }
                $references = implode("\n\n", array_filter($parts));
            }
        }

        // Full Text Extraction
        $sectionNodes = $xpath->query("//div[contains(@class, 'articleSection')]");
        if ($sectionNodes->length > 0) {
            $blocks = [];
            foreach ($sectionNodes as $node) {
                $class = (string) $node->getAttribute('class');
                if (preg_match('/--(resumo|abstract|referências|datas|histórico)/u', $class)) {
                    continue;
                }
                $blocks = array_merge($blocks, $this->scielo_html_to_blocks($node));
            }
            $fullText = $this->get_clean_body($blocks);
        }

        if ($fullText === '') {
            $articleNodes = $xpath->query("//article");
            if ($articleNodes->length > 0) {
                $blocks = $this->scielo_html_to_blocks($articleNodes->item(0));
                $fullText = $this->get_clean_body($blocks);
            } else {
                $bodyNodes = $xpath->query("//body");
                if ($bodyNodes->length > 0) {
                    $blocks = $this->scielo_html_to_blocks($bodyNodes->item(0));
                    $fullText = $this->get_clean_body($blocks);
                }
            }
        }

        return [
            'title' => $this->first_non_empty([
                $this->first_meta($meta, ['citation_title', 'dc.title', 'og:title', 'twitter:title']),
                $this->value_from_json_ld($jsonLd, 'headline'),
                $this->value_from_json_ld($jsonLd, 'name'),
            ]),
            'authors' => implode('; ', array_unique(array_filter($authors))),
            'year' => $year,
            'journal' => $this->first_meta($meta, ['citation_journal_title', 'dc.source', 'prism.publicationName']),
            'volume' => $this->first_meta($meta, ['citation_volume', 'prism.volume']),
            'issue' => $this->first_meta($meta, ['citation_issue', 'prism.number']),
            'pages' => $pages,
            'publisher' => $this->first_meta($meta, ['citation_publisher', 'dc.publisher']),
            'doi' => $this->clean_doi($doi),
            'url' => $url,
            'pdf_url' => $this->first_meta($meta, ['citation_pdf_url']),
            'abstract' => $this->first_meta($meta, ['citation_abstract', 'dc.description', 'description']),
            'full_text' => $fullText,
            'references_text' => $references,
            'keywords' => implode('; ', array_unique(array_filter($keywords))),
        ];
    }
}
