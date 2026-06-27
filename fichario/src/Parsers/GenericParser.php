<?php
declare(strict_types=1);

namespace App\Parsers;

class GenericParser extends AbstractParser implements ParserInterface
{
    public function supports(string $url): bool
    {
        return true;
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

        // Standard references fallback
        $refNodes = $xpath->query("//div[contains(@class, 'references')] | //ol[contains(@class, 'references')] | //section[contains(@id, 'reference')] | //div[contains(@class, 'ref-list')] | //div[contains(@class, 'ref')]");
        if ($refNodes->length > 0) {
            $references = $this->scielo_html_to_clean_text($refNodes->item(0));
        }

        // Standard body text fallback
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
