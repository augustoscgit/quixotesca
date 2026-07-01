<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/markdown.php';

function platform_docs_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function platform_docs_is_excluded_dir(string $name): bool
{
    return in_array($name, [
        '.agents',
        '.codex',
        '.git',
        '_archive',
        'data',
        'documents',
        'node_modules',
        'private',
        'public',
        'public_html',
        'scratch',
        'secrets',
        'vendor',
    ], true);
}

function platform_docs_pretty_title(string $path): string
{
    $name = pathinfo($path, PATHINFO_FILENAME);
    $name = str_replace(['_', '-'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;

    return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
}

function platform_docs_extract_summary(string $path): array
{
    $title = '';
    $description = '';
    $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;

    if ($lines === false) {
        return [platform_docs_pretty_title($path), 'Documento Markdown interno.'];
    }

    foreach ($lines as $line) {
        $trim = trim((string) $line);
        if ($trim === '' || str_starts_with($trim, '```')) {
            continue;
        }

        if ($title === '' && preg_match('/^#\s+(.+)$/', $trim, $m)) {
            $title = trim($m[1]);
            continue;
        }

        if ($description === '' && !str_starts_with($trim, '#') && !str_starts_with($trim, '|') && !preg_match('/^-{3,}$/', $trim)) {
            $description = trim(preg_replace('/^[-*]\s+/', '', $trim) ?? $trim);
        }

        if ($title !== '' && $description !== '') {
            break;
        }
    }

    return [
        $title !== '' ? $title : platform_docs_pretty_title($path),
        $description !== '' ? mb_substr($description, 0, 180, 'UTF-8') : 'Documento Markdown interno.',
    ];
}

function platform_docs_infer_category(string $relativePath): string
{
    $text = mb_strtolower($relativePath, 'UTF-8');

    if (preg_match('/(ux|visual|tema|css|bootstrap|identidade|saneamento|diretrizes)/u', $text)) {
        return 'UX';
    }

    if (preg_match('/(metodologia|metodologico|criterio|conciliacao|matriz|trabalho|visao-geral|especialistas)/u', $text)) {
        return 'Metodologia';
    }

    if (preg_match('/(admin|administrativo|permiss|usuario|acesso)/u', $text)) {
        return 'Administracao';
    }

    if (preg_match('/(desenvolvimento|developer|arquitetura|api|banco|database|seguranca|security|git|migracao|producao|mapa|guia|readme|agente|opencnpj)/u', $text)) {
        return 'Desenvolvimento';
    }

    return 'Geral';
}

function platform_docs_infer_module(string $projectRelativePath, string $fallback): string
{
    $normalized = platform_docs_normalize_path($projectRelativePath);
    $firstSegment = strtok($normalized, '/');

    return match ($firstSegment) {
        'acesso' => 'Acesso',
        'carex' => 'CAREX',
        'cat' => 'CAT',
        'docs' => 'Plataforma',
        'fichario' => 'Fichario',
        'investigacao' => 'Investigacao',
        'ldrt' => 'LDRT',
        default => $fallback,
    };
}

function platform_docs_scan(string $projectRoot, array $roots): array
{
    $docs = [];
    $projectRootReal = realpath($projectRoot);
    if ($projectRootReal === false) {
        return [];
    }

    $projectRootNorm = rtrim(platform_docs_normalize_path($projectRootReal), '/') . '/';

    foreach ($roots as $rootConfig) {
        $rootPath = realpath((string) ($rootConfig['path'] ?? ''));
        if ($rootPath === false || !is_dir($rootPath)) {
            continue;
        }

        $rootNorm = rtrim(platform_docs_normalize_path($rootPath), '/') . '/';
        $prefix = trim((string) ($rootConfig['prefix'] ?? ''), '/');
        $module = (string) ($rootConfig['module'] ?? ($rootConfig['label'] ?? 'Projeto'));

        $directory = new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return !platform_docs_is_excluded_dir($current->getFilename());
                }

                return mb_strtolower($current->getExtension(), 'UTF-8') === 'md';
            }
        );

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $normalized = platform_docs_normalize_path($realPath);
            if (str_contains($normalized, '/public_html/')) {
                continue;
            }

            $rootRelative = str_starts_with($normalized, $rootNorm) ? substr($normalized, strlen($rootNorm)) : $file->getFilename();
            $projectRelative = str_starts_with($normalized, $projectRootNorm) ? substr($normalized, strlen($projectRootNorm)) : $rootRelative;
            $displayPath = $prefix !== '' ? $prefix . '/' . $rootRelative : $rootRelative;
            [$title, $description] = platform_docs_extract_summary($realPath);
            $docModule = platform_docs_infer_module($projectRelative, $module);

            $docs[sha1($normalized)] = [
                'key' => sha1($normalized),
                'title' => $title,
                'category' => platform_docs_infer_category($displayPath),
                'module' => $docModule,
                'path' => $realPath,
                'relative' => $displayPath,
                'project_relative' => $projectRelative,
                'description' => $description,
                'writable' => is_writable($realPath),
            ];
        }
    }

    uasort($docs, static function (array $a, array $b): int {
        return [$a['module'], $a['category'], $a['relative']] <=> [$b['module'], $b['category'], $b['relative']];
    });

    return $docs;
}

function platform_docs_render_inline(string $text): string
{
    return platform_markdown_render_inline($text);
}

function platform_docs_render_markdown(string $markdown): string
{
    return platform_markdown_render($markdown, [
        'heading_offset' => 1,
        'heading_classes' => [
            2 => 'h4 mt-0',
            3 => 'h5 mt-4',
            4 => 'h6 mt-3 text-body-secondary',
            5 => 'h6 mt-3 text-body-secondary',
            6 => 'h6 mt-3 text-body-secondary',
        ],
        'paragraph_line_breaks' => false,
    ]);

    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $paragraph = [];
    $inList = false;
    $inCode = false;
    $codeBuffer = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph === []) {
            return;
        }
        $html .= '<p>' . platform_docs_render_inline(implode(' ', $paragraph)) . '</p>';
        $paragraph = [];
    };

    $closeList = static function () use (&$html, &$inList): void {
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
    };

    $flushCode = static function () use (&$html, &$codeBuffer): void {
        $html .= '<pre><code>' . platform_markdown_escape(implode("\n", $codeBuffer)) . '</code></pre>';
        $codeBuffer = [];
    };

    foreach ($lines as $line) {
        $trim = trim($line);

        if (str_starts_with($trim, '```')) {
            if ($inCode) {
                $flushCode();
                $inCode = false;
            } else {
                $flushParagraph();
                $closeList();
                $inCode = true;
                $codeBuffer = [];
            }
            continue;
        }

        if ($inCode) {
            $codeBuffer[] = rtrim($line, "\r\n");
            continue;
        }

        if ($trim === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $m)) {
            $flushParagraph();
            $closeList();
            $level = min(strlen($m[1]) + 1, 6);
            $class = $level <= 2 ? 'h4 mt-0' : ($level === 3 ? 'h5 mt-4' : 'h6 mt-3 text-body-secondary');
            $html .= '<h' . $level . ' class="' . $class . '">' . platform_docs_render_inline($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
            $flushParagraph();
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . platform_docs_render_inline($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^>\s?(.+)$/', $trim, $m)) {
            $flushParagraph();
            $closeList();
            $html .= '<blockquote>' . platform_docs_render_inline($m[1]) . '</blockquote>';
            continue;
        }

        if (preg_match('/^-{3,}$/', $trim)) {
            $flushParagraph();
            $closeList();
            $html .= '<hr>';
            continue;
        }

        $paragraph[] = $trim;
    }

    if ($inCode) {
        $flushCode();
    }

    $flushParagraph();
    $closeList();

    return $html;
}
