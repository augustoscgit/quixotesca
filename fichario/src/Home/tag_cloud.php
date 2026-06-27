<?php
declare(strict_types=1);

function prepare_home_tag_cloud(array $tags): array
{
    $excludedTags = ['Objetivo'];
    $cloudTags = array_values(array_filter($tags, static function (array $tag) use ($excludedTags): bool {
        return !in_array($tag['name'], $excludedTags, true);
    }));

    usort($cloudTags, static function (array $a, array $b): int {
        return (int) ($b['article_count'] ?? 0) <=> (int) ($a['article_count'] ?? 0);
    });

    $radialTags = [];
    $toggle = true;
    foreach ($cloudTags as $tag) {
        if ($toggle) {
            array_unshift($radialTags, $tag);
        } else {
            $radialTags[] = $tag;
        }
        $toggle = !$toggle;
    }

    $maxCount = 0;
    $wordList = [];
    foreach ($radialTags as $tag) {
        $count = (int) ($tag['article_count'] ?? 0);
        $maxCount = max($maxCount, $count);
        $wordList[] = [
            $tag['name'],
            $count,
            (int) $tag['id'],
        ];
    }

    return [
        'cloudTags' => $radialTags,
        'maxCount' => $maxCount,
        'wordList' => $wordList,
    ];
}
