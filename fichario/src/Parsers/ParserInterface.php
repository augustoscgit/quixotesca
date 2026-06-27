<?php
declare(strict_types=1);

namespace App\Parsers;

interface ParserInterface
{
    public function supports(string $url): bool;
    
    /**
     * Parse article details from HTML content.
     * 
     * @param string $html Raw HTML content of the page
     * @param string $url Target URL of the article
     * @return array Mapped article fields
     */
    public function parse(string $html, string $url): array;
}
