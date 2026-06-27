<?php
declare(strict_types=1);

namespace App\Parsers;

class ParserManager
{
    /** @var ParserInterface[] */
    protected array $parsers = [];
    protected ParserInterface $fallbackParser;

    public function __construct()
    {
        // Register default parsers
        $this->registerParser(new ScieloParser());
        $this->registerParser(new SpringerParser());
        
        $this->fallbackParser = new GenericParser();
    }

    public function registerParser(ParserInterface $parser): void
    {
        $this->parsers[] = $parser;
    }

    public function getParserForUrl(string $url): ParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($url)) {
                return $parser;
            }
        }

        return $this->fallbackParser;
    }

    public function parse(string $html, string $url): array
    {
        $parser = $this->getParserForUrl($url);
        return $parser->parse($html, $url);
    }
}
