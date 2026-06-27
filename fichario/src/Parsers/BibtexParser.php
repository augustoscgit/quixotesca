<?php
declare(strict_types=1);

namespace App\Parsers;

class BibtexParser
{
    public function parse(string $bibtex): array
    {
        return $this->article_from_bibtex($bibtex);
    }

    public function parse_raw(string $input): array
    {
        $result = [
            'entry_type' => '',
            'bibtex_key' => '',
            'fields' => [],
        ];

        $input = trim($input);
        if ($input === '') {
            return $result;
        }

        if (!preg_match('/@([a-zA-Z]+)\s*\{\s*([^,]+)\s*,(.*)\}\s*$/s', $input, $matches)) {
            return $result;
        }

        $result['entry_type'] = strtolower($matches[1]);
        $result['bibtex_key'] = trim($matches[2]);
        $body = trim($matches[3]);
        $length = strlen($body);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && preg_match('/[\s,]/', $body[$offset])) {
                $offset++;
            }

            if ($offset >= $length || !preg_match('/\G([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*/A', $body, $fieldMatch, 0, $offset)) {
                break;
            }

            $field = strtolower($fieldMatch[1]);
            $offset += strlen($fieldMatch[0]);

            if ($offset >= $length) {
                break;
            }

            $value = '';
            $starter = $body[$offset];

            if ($starter === '{') {
                [$value, $offset] = $this->read_braced_value($body, $offset);
            } elseif ($starter === '"') {
                [$value, $offset] = $this->read_quoted_value($body, $offset);
            } else {
                $start = $offset;
                while ($offset < $length && $body[$offset] !== ',') {
                    $offset++;
                }
                $value = substr($body, $start, $offset - $start);
            }

            $result['fields'][$field] = $this->normalize_bibtex_value($value);
        }

        return $result;
    }

    protected function read_braced_value(string $text, int $offset): array
    {
        $depth = 0;
        $start = $offset + 1;
        $length = strlen($text);

        for ($i = $offset; $i < $length; $i++) {
            if ($text[$i] === '{') {
                $depth++;
                continue;
            }

            if ($text[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return [substr($text, $start, $i - $start), $i + 1];
                }
            }
        }

        return [substr($text, $start), $length];
    }

    protected function read_quoted_value(string $text, int $offset): array
    {
        $depth = 0;
        $start = $offset + 1;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            } elseif ($char === '"' && $depth === 0) {
                if ($i > $start && $text[$i - 1] === '\\') {
                    continue;
                }
                return [substr($text, $start, $i - $start), $i + 1];
            }
        }

        return [substr($text, $start), $length];
    }

    protected function normalize_bibtex_value(string $value): string
    {
        $replacements = [
            // Cedilla
            '/\\\\c\s*\{c\}/u' => 'ç',
            '/\\\\c\s*\{C\}/u' => 'Ç',
            '/\{\\\\cc\}/u' => 'ç',
            '/\{\\\\cC\}/u' => 'Ç',
            '/\\\\cc/u' => 'ç',
            '/\\\\cC/u' => 'Ç',
            '/\\\\c\s+c/u' => 'ç',
            '/\\\\c\s+C/u' => 'Ç',
            '/\{\\\\c\s+c\}/u' => 'ç',
            '/\{\\\\c\s+C\}/u' => 'Ç',

            // Acute accents
            '/\\\\\'\s*\{?i\}?/u' => 'í',
            '/\\\\\'\s*\{?\\\\i\}?/u' => 'í',
            '/\\\\\'\s*\{?a\}?/u' => 'á',
            '/\\\\\'\s*\{?e\}?/u' => 'é',
            '/\\\\\'\s*\{?o\}?/u' => 'ó',
            '/\\\\\'\s*\{?u\}?/u' => 'ú',
            '/\\\\\'\s*\{?A\}?/u' => 'Á',
            '/\\\\\'\s*\{?E\}?/u' => 'É',
            '/\\\\\'\s*\{?I\}?/u' => 'Í',
            '/\\\\\'\s*\{?O\}?/u' => 'Ó',
            '/\\\\\'\s*\{?U\}?/u' => 'Ú',

            // Tilde accents
            '/\\\\\~\s*\{?a\}?/u' => 'ã',
            '/\\\\\~\s*\{?o\}?/u' => 'õ',
            '/\\\\\~\s*\{?n\}?/u' => 'ñ',
            '/\\\\\~\s*\{?A\}?/u' => 'Ã',
            '/\\\\\~\s*\{?O\}?/u' => 'Õ',
            '/\\\\\~\s*\{?N\}?/u' => 'Ñ',

            // Circumflex accents
            '/\\\\\^\s*\{?a\}?/u' => 'â',
            '/\\\\\^\s*\{?e\}?/u' => 'ê',
            '/\\\\\^\s*\{?i\}?/u' => 'î',
            '/\\\\\^\s*\{?o\}?/u' => 'ô',
            '/\\\\\^\s*\{?u\}?/u' => 'û',
            '/\\\\\^\s*\{?A\}?/u' => 'Â',
            '/\\\\\^\s*\{?E\}?/u' => 'Ê',
            '/\\\\\^\s*\{?I\}?/u' => 'Î',
            '/\\\\\^\s*\{?O\}?/u' => 'Ô',
            '/\\\\\^\s*\{?U\}?/u' => 'Û',

            // Grave accents
            '/\\\\`\s*\{?a\}?/u' => 'à',
            '/\\\\`\s*\{?e\}?/u' => 'è',
            '/\\\\`\s*\{?i\}?/u' => 'ì',
            '/\\\\`\s*\{?o\}?/u' => 'ò',
            '/\\\\`\s*\{?u\}?/u' => 'ù',
            '/\\\\`\s*\{?A\}?/u' => 'À',
            '/\\\\`\s*\{?E\}?/u' => 'È',
            '/\\\\`\s*\{?I\}?/u' => 'Ì',
            '/\\\\`\s*\{?O\}?/u' => 'Ò',
            '/\\\\`\s*\{?U\}?/u' => 'Ù',

            // Umlaut accents
            '/\\\\"\s*\{?a\}?/u' => 'ä',
            '/\\\\"\s*\{?e\}?/u' => 'ë',
            '/\\\\"\s*\{?i\}?/u' => 'ï',
            '/\\\\"\s*\{?o\}?/u' => 'ö',
            '/\\\\"\s*\{?u\}?/u' => 'ü',
            '/\\\\"\s*\{?A\}?/u' => 'Ä',
            '/\\\\"\s*\{?E\}?/u' => 'Ë',
            '/\\\\"\s*\{?I\}?/u' => 'Ï',
            '/\\\\"\s*\{?O\}?/u' => 'Ö',
            '/\\\\"\s*\{?U\}?/u' => 'Ü',
        ];

        $replacementsStr = [
            "{\\'a}" => 'á', "{\\'e}" => 'é', "{\\'i}" => 'í', "{\\'o}" => 'ó', "{\\'u}" => 'ú',
            "{\\'A}" => 'Á', "{\\'E}" => 'É', "{\\'I}" => 'Í', "{\\'O}" => 'Ó', "{\\'U}" => 'Ú',
            "{\\`a}" => 'à', "{\\`e}" => 'è', "{\\`i}" => 'ì', "{\\`o}" => 'ò', "{\\`u}" => 'ù',
            "{\\`A}" => 'À', "{\\`E}" => 'È', "{\\`I}" => 'Ì', "{\\`O}" => 'Ò', "{\\`U}" => 'Ù',
            "{\\^a}" => 'â', "{\\^e}" => 'ê', "{\\^i}" => 'î', "{\\^o}" => 'ô', "{\\^u}" => 'û',
            "{\\^A}" => 'Â', "{\\^E}" => 'Ê', "{\\^I}" => 'Î', "{\\^O}" => 'Ô', "{\\^U}" => 'Û',
            "{\\~a}" => 'ã', "{\\~o}" => 'õ', "{\\~n}" => 'ñ',
            "{\\~A}" => 'Ã', "{\\~O}" => 'Õ', "{\\~N}" => 'Ñ',
            "{\\c c}" => 'ç', "{\\c C}" => 'Ç',
            '{\\"a}' => 'ä', '{\\"e}' => 'ë', '{\\"i}' => 'ï', '{\\"o}' => 'ö', '{\\"u}' => 'ü',
            '{\\"A}' => 'Ä', '{\\"E}' => 'Ë', '{\\"I}' => 'Ï', '{\\"O}' => 'Ö', '{\\"U}' => 'Ü',
        ];

        $value = strtr($value, $replacementsStr);

        foreach ($replacements as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        $value = preg_replace('/\{([^{}]*)\}/u', '$1', $value) ?? $value;
        $value = preg_replace('/\{([^{}]*)\}/u', '$1', $value) ?? $value;
        $value = str_replace(['--', "\r\n", "\r"], ['-', "\n", "\n"], $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function article_from_bibtex(string $bibtex): array
    {
        $parsed = $this->parse_raw($bibtex);
        $fields = $parsed['fields'];

        return [
            'title' => $fields['title'] ?? '',
            'authors' => isset($fields['author']) ? str_replace(' and ', '; ', $fields['author']) : '',
            'year' => $fields['year'] ?? '',
            'journal' => $fields['journal'] ?? ($fields['booktitle'] ?? ''),
            'volume' => $fields['volume'] ?? '',
            'issue' => $fields['number'] ?? '',
            'pages' => $fields['pages'] ?? '',
            'publisher' => $fields['publisher'] ?? '',
            'doi' => $fields['doi'] ?? '',
            'url' => $fields['url'] ?? '',
            'pdf_url' => $fields['pdf'] ?? ($fields['file'] ?? ''),
            'abstract' => $fields['abstract'] ?? '',
            'full_text' => '',
            'references_text' => '',
            'keywords' => $fields['keywords'] ?? '',
            'bibtex_key' => $parsed['bibtex_key'],
            'bibtex_raw' => trim($bibtex),
        ];
    }
}
