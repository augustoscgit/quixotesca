<?php
declare(strict_types=1);

if (!function_exists('platform_markdown_escape')) {
function platform_markdown_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
}

if (!function_exists('platform_markdown_render_inline')) {
function platform_markdown_render_inline(string $text): string
{
    $escaped = platform_markdown_escape($text);
    $codeTokens = [];

    $escaped = preg_replace_callback('/`([^`]+)`/', static function (array $match) use (&$codeTokens): string {
        $token = "\x1A" . count($codeTokens) . "\x1A";
        $codeTokens[$token] = '<code>' . $match[1] . '</code>';
        return $token;
    }, $escaped) ?? $escaped;

    $escaped = preg_replace(
        '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/i',
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
        $escaped
    ) ?? $escaped;
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;

    return strtr($escaped, $codeTokens);
}
}

if (!function_exists('platform_markdown_split_table_row')) {
function platform_markdown_split_table_row(string $line): array
{
    $line = trim($line);
    if (str_starts_with($line, '|')) {
        $line = substr($line, 1);
    }
    if (str_ends_with($line, '|')) {
        $line = substr($line, 0, -1);
    }

    $cells = [];
    $cell = '';
    $escaped = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($escaped) {
            $cell .= $char;
            $escaped = false;
            continue;
        }
        if ($char === '\\') {
            $escaped = true;
            continue;
        }
        if ($char === '|') {
            $cells[] = trim($cell);
            $cell = '';
            continue;
        }
        $cell .= $char;
    }
    $cells[] = trim($cell);

    return $cells;
}
}

if (!function_exists('platform_markdown_parse_table_separator')) {
function platform_markdown_parse_table_separator(string $line, int $expectedCells): ?array
{
    $cells = platform_markdown_split_table_row($line);
    if (count($cells) < $expectedCells) {
        return null;
    }

    $alignments = [];
    foreach (array_slice($cells, 0, $expectedCells) as $cell) {
        if (!preg_match('/^:?-{3,}:?$/', trim($cell))) {
            return null;
        }
        $starts = str_starts_with(trim($cell), ':');
        $ends = str_ends_with(trim($cell), ':');
        $alignments[] = $starts && $ends ? 'center' : ($ends ? 'right' : ($starts ? 'left' : ''));
    }

    return $alignments;
}
}

if (!function_exists('platform_markdown_render')) {
function platform_markdown_render(?string $markdown, array $options = []): string
{
    $markdown = str_replace(["\r\n", "\r"], "\n", trim((string) $markdown));
    if ($markdown === '') {
        return '';
    }

    $headingOffset = max(0, min(4, (int) ($options['heading_offset'] ?? 2)));
    $headingClasses = is_array($options['heading_classes'] ?? null) ? $options['heading_classes'] : [];
    $paragraphLineBreaks = (bool) ($options['paragraph_line_breaks'] ?? true);

    $lines = explode("\n", $markdown);
    $html = '';
    $paragraph = [];
    $listStack = [];
    $inCode = false;
    $codeBuffer = [];

    $flushParagraph = static function () use (&$html, &$paragraph, $paragraphLineBreaks): void {
        if ($paragraph === []) {
            return;
        }
        $content = platform_markdown_render_inline(implode("\n", $paragraph));
        if ($paragraphLineBreaks) {
            $content = str_replace("\n", '<br>', $content);
        } else {
            $content = str_replace("\n", ' ', $content);
        }
        $html .= '<p>' . $content . '</p>';
        $paragraph = [];
    };

    $closeListTo = static function (int $depth = 0) use (&$html, &$listStack): void {
        while (count($listStack) > $depth) {
            $last = array_pop($listStack);
            if (($last['li_open'] ?? false) === true) {
                $html .= '</li>';
            }
            $html .= '</' . $last['type'] . '>';
        }
    };

    $appendListItem = static function (int $level, string $type, string $content) use (&$html, &$listStack, $closeListTo): void {
        $level = max(0, min(6, $level));
        $targetDepth = $level + 1;

        $closeListTo($targetDepth);

        while (count($listStack) < $targetDepth) {
            $html .= '<' . $type . '>';
            $listStack[] = ['type' => $type, 'li_open' => false];
        }

        if ($listStack[$level]['type'] !== $type) {
            $closeListTo($level);
            $html .= '<' . $type . '>';
            $listStack[] = ['type' => $type, 'li_open' => false];
        }

        if (($listStack[$level]['li_open'] ?? false) === true) {
            $html .= '</li>';
            $listStack[$level]['li_open'] = false;
        }

        $html .= '<li>' . platform_markdown_render_inline($content);
        $listStack[$level]['li_open'] = true;
    };

    $flushCode = static function () use (&$html, &$codeBuffer): void {
        $html .= '<pre><code>' . platform_markdown_escape(implode("\n", $codeBuffer)) . '</code></pre>';
        $codeBuffer = [];
    };

    $lineCount = count($lines);
    for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
        $line = $lines[$lineIndex];
        $trim = trim($line);

        if (str_starts_with($trim, '```')) {
            if ($inCode) {
                $flushCode();
                $inCode = false;
            } else {
                $flushParagraph();
                $closeListTo();
                $inCode = true;
                $codeBuffer = [];
            }
            continue;
        }

        if ($inCode) {
            $codeBuffer[] = rtrim($line);
            continue;
        }

        if ($trim === '') {
            $flushParagraph();
            $closeListTo();
            continue;
        }

        if (str_contains($line, '|') && isset($lines[$lineIndex + 1])) {
            $headers = platform_markdown_split_table_row($line);
            $alignments = platform_markdown_parse_table_separator($lines[$lineIndex + 1], count($headers));
            if ($headers !== [] && $alignments !== null) {
                $flushParagraph();
                $closeListTo();

                $html .= '<div class="table-responsive"><table class="table table-sm table-bordered align-middle fichario-markdown-table"><thead><tr>';
                foreach ($headers as $index => $header) {
                    $align = $alignments[$index] ?? '';
                    $style = $align !== '' ? ' style="text-align: ' . platform_markdown_escape($align) . '"' : '';
                    $html .= '<th' . $style . '>' . platform_markdown_render_inline($header) . '</th>';
                }
                $html .= '</tr></thead><tbody>';

                $lineIndex += 2;
                while ($lineIndex < $lineCount && trim($lines[$lineIndex]) !== '' && str_contains($lines[$lineIndex], '|')) {
                    $rowCells = platform_markdown_split_table_row($lines[$lineIndex]);
                    $html .= '<tr>';
                    foreach ($headers as $index => $_header) {
                        $align = $alignments[$index] ?? '';
                        $style = $align !== '' ? ' style="text-align: ' . platform_markdown_escape($align) . '"' : '';
                        $html .= '<td' . $style . '>' . platform_markdown_render_inline($rowCells[$index] ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                    $lineIndex++;
                }
                $lineIndex--;
                $html .= '</tbody></table></div>';
                continue;
            }
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $match)) {
            $flushParagraph();
            $closeListTo();
            $level = min(strlen($match[1]) + $headingOffset, 6);
            $class = isset($headingClasses[$level]) ? ' class="' . platform_markdown_escape((string) $headingClasses[$level]) . '"' : '';
            $html .= '<h' . $level . $class . '>' . platform_markdown_render_inline($match[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^(\s*)[-*+]\s+(.+)$/', $line, $match)) {
            $flushParagraph();
            $indent = strlen(str_replace("\t", '    ', $match[1]));
            $appendListItem(intdiv($indent, 2), 'ul', $match[2]);
            continue;
        }

        if (preg_match('/^(\s*)\d+[.)]\s+(.+)$/', $line, $match)) {
            $flushParagraph();
            $indent = strlen(str_replace("\t", '    ', $match[1]));
            $appendListItem(intdiv($indent, 2), 'ol', $match[2]);
            continue;
        }

        if (preg_match('/^>\s?(.+)$/', $trim, $match)) {
            $flushParagraph();
            $closeListTo();
            $html .= '<blockquote>' . platform_markdown_render_inline($match[1]) . '</blockquote>';
            continue;
        }

        if (preg_match('/^-{3,}$/', $trim)) {
            $flushParagraph();
            $closeListTo();
            $html .= '<hr>';
            continue;
        }

        $paragraph[] = $trim;
    }

    if ($inCode) {
        $flushCode();
    }

    $flushParagraph();
    $closeListTo();

    return $html;
}
}
