<?php
declare(strict_types=1);

function prepare_home_tag_graph(PDO $pdo, array $cloudTags, int $nodeLimit = 60, int $edgeLimit = 130, int $minEdgesPerNode = 2): array
{
    $graphTags = array_values(array_filter($cloudTags, static function (array $tag): bool {
        return (int) ($tag['article_count'] ?? 0) > 0;
    }));
    usort($graphTags, static function (array $a, array $b): int {
        return (int) ($b['article_count'] ?? 0) <=> (int) ($a['article_count'] ?? 0);
    });

    $graphTags = array_slice($graphTags, 0, $nodeLimit);
    $graphTagIds = [];
    foreach ($graphTags as $tag) {
        $graphTagIds[(int) $tag['id']] = true;
    }

    $tagsById = [];
    foreach ($cloudTags as $tag) {
        $tagsById[(int) $tag['id']] = $tag;
    }

    $hierarchyRows = $pdo->query('SELECT parent_id, child_id FROM tag_hierarchy')->fetchAll();
    do {
        $addedParent = false;
        foreach ($hierarchyRows as $row) {
            $parentId = (int) $row['parent_id'];
            $childId = (int) $row['child_id'];
            if (isset($graphTagIds[$childId], $tagsById[$parentId]) && !isset($graphTagIds[$parentId])) {
                $graphTagIds[$parentId] = true;
                $addedParent = true;
            }
        }
    } while ($addedParent);

    $nodes = [];
    foreach ($cloudTags as $tag) {
        $tagId = (int) $tag['id'];
        if (!isset($graphTagIds[$tagId])) {
            continue;
        }

        $count = (int) ($tag['article_count'] ?? 0);
        $category = trim((string) ($tag['category'] ?? ''));
        $color = get_tag_colors($category);
        $nodes[] = [
            'id' => $tagId,
            'label' => $tag['name'],
            'value' => $count,
            'category' => $category !== '' ? $category : 'Sem tipo',
            'colorBg' => $color['bg'],
            'colorText' => $color['text'],
            'colorBorder' => $color['border'],
            'title' => ($category !== '' ? $category . ' - ' : '') . $tag['name'] . ' (' . $count . ' artigo(s))',
        ];
    }

    $edges = [];
    $mandatoryTagIds = [];
    $hierarchyEdges = [];
    foreach ($hierarchyRows as $row) {
        $parentId = (int) $row['parent_id'];
        $childId = (int) $row['child_id'];
        if (!isset($graphTagIds[$parentId], $graphTagIds[$childId])) {
            continue;
        }

        $parentName = (string) ($tagsById[$parentId]['name'] ?? 'tag pai');
        $childName = (string) ($tagsById[$childId]['name'] ?? 'tag filha');
        $hierarchyEdges[] = [
            'from' => $childId,
            'to' => $parentId,
            'value' => 1,
            'type' => 'hierarchy',
            'title' => $childName . ' -> ' . $parentName,
        ];
        $mandatoryTagIds[$parentId] = true;
        $mandatoryTagIds[$childId] = true;
    }

    $cooccurrences = $pdo->query('
        SELECT a.tag_id AS tag_a, b.tag_id AS tag_b, COUNT(*) AS weight
        FROM (SELECT DISTINCT article_id, tag_id FROM article_tags) a
        JOIN (SELECT DISTINCT article_id, tag_id FROM article_tags) b ON a.article_id = b.article_id AND a.tag_id < b.tag_id
        GROUP BY a.tag_id, b.tag_id
        ORDER BY weight DESC
        LIMIT 1200
    ')->fetchAll();

    $candidateEdges = [];
    foreach ($cooccurrences as $co) {
        $tagA = (int) $co['tag_a'];
        $tagB = (int) $co['tag_b'];
        if (isset($graphTagIds[$tagA], $graphTagIds[$tagB])) {
            $candidateEdges[] = [
                'from' => $tagA,
                'to' => $tagB,
                'value' => (int) $co['weight'],
                'type' => 'cooccurrence',
                'title' => 'Co-ocorrem em ' . $co['weight'] . ' artigo(s)',
            ];
        }
    }

    $edgeKeys = [];
    $degreeByTag = array_fill_keys(array_keys($graphTagIds), 0);
    $addEdge = static function (array $edge) use (&$edges, &$edgeKeys, &$degreeByTag): void {
        $from = (int) $edge['from'];
        $to = (int) $edge['to'];
        $type = (string) ($edge['type'] ?? 'cooccurrence');
        $key = $type . ':' . min($from, $to) . '-' . max($from, $to);
        if (isset($edgeKeys[$key])) {
            return;
        }

        $edges[] = $edge;
        $edgeKeys[$key] = true;
        $degreeByTag[$from] = ($degreeByTag[$from] ?? 0) + 1;
        $degreeByTag[$to] = ($degreeByTag[$to] ?? 0) + 1;
    };

    foreach ($hierarchyEdges as $edge) {
        $addEdge($edge);
    }

    $cooccurrenceEdgeCount = 0;
    foreach ($candidateEdges as $edge) {
        if ($cooccurrenceEdgeCount >= $edgeLimit) {
            break;
        }
        $addEdge($edge);
        $cooccurrenceEdgeCount++;
    }

    foreach (array_keys($graphTagIds) as $tagId) {
        if (($degreeByTag[$tagId] ?? 0) >= $minEdgesPerNode) {
            continue;
        }

        foreach ($candidateEdges as $edge) {
            if ((int) $edge['from'] === $tagId || (int) $edge['to'] === $tagId) {
                $addEdge($edge);
                if (($degreeByTag[$tagId] ?? 0) >= $minEdgesPerNode) {
                    break;
                }
            }
        }
    }

    do {
        $degreeByTag = array_fill_keys(array_keys($graphTagIds), 0);
        foreach ($edges as $edge) {
            $from = (int) $edge['from'];
            $to = (int) $edge['to'];
            if (isset($graphTagIds[$from], $graphTagIds[$to])) {
                $degreeByTag[$from] = ($degreeByTag[$from] ?? 0) + 1;
                $degreeByTag[$to] = ($degreeByTag[$to] ?? 0) + 1;
            }
        }

        $removedAny = false;
        foreach (array_keys($graphTagIds) as $tagId) {
            if (isset($mandatoryTagIds[$tagId])) {
                continue;
            }
            if (($degreeByTag[$tagId] ?? 0) < $minEdgesPerNode) {
                unset($graphTagIds[$tagId]);
                $removedAny = true;
            }
        }

        if ($removedAny) {
            $edges = array_values(array_filter($edges, static function (array $edge) use ($graphTagIds): bool {
                return isset($graphTagIds[(int) $edge['from']], $graphTagIds[(int) $edge['to']]);
            }));
        }
    } while ($removedAny);

    $nodes = array_values(array_filter($nodes, static function (array $node) use ($graphTagIds): bool {
        return isset($graphTagIds[(int) $node['id']]);
    }));

    $centralityByTag = array_fill_keys(array_keys($graphTagIds), 0.0);
    foreach ($edges as $edge) {
        $from = (int) $edge['from'];
        $to = (int) $edge['to'];
        if (!isset($graphTagIds[$from], $graphTagIds[$to])) {
            continue;
        }

        $weight = (float) ($edge['value'] ?? 1);
        $score = ($edge['type'] ?? 'cooccurrence') === 'hierarchy'
            ? max(1.0, $weight) * 0.75
            : max(1.0, $weight);
        $centralityByTag[$from] = ($centralityByTag[$from] ?? 0.0) + $score;
        $centralityByTag[$to] = ($centralityByTag[$to] ?? 0.0) + $score;
    }

    usort($nodes, static function (array $a, array $b) use ($centralityByTag): int {
        $centralityCompare = ($centralityByTag[(int) $b['id']] ?? 0.0) <=> ($centralityByTag[(int) $a['id']] ?? 0.0);
        if ($centralityCompare !== 0) {
            return $centralityCompare;
        }

        return ((int) ($b['value'] ?? 0)) <=> ((int) ($a['value'] ?? 0));
    });

    $nodeCount = max(1, count($nodes));
    $maxCentrality = max(1.0, ...array_values($centralityByTag));
    foreach ($nodes as $index => &$node) {
        $tagId = (int) $node['id'];
        $centrality = $centralityByTag[$tagId] ?? 0.0;
        $normalized = min(1.0, $centrality / $maxCentrality);
        $ringProgress = $index / max(1, $nodeCount - 1);
        $radius = 24 + (420 * sqrt($ringProgress)) * (1.08 - (0.36 * $normalized));
        $angle = $index * 2.399963229728653;

        $node['centrality'] = round($centrality, 2);
        $node['x'] = (int) round(cos($angle) * $radius);
        $node['y'] = (int) round(sin($angle) * $radius);
        $node['mass'] = round(1 + ($normalized * 2.6), 2);
    }
    unset($node);

    return [
        'nodes' => $nodes,
        'edges' => $edges,
    ];
}
