<?php
require_once __DIR__ . '/../../cat/src/hierarchical_flow_page.php';

renderHierarchicalFlowPage([
    'slug' => 'cbos',
    'title' => 'Fluxo CBO',
    'subtitle' => 'Navegação hierárquica por ocupação, com sumarização das CATs por nível CBO.',
    'levels' => ['Grande grupo', 'Subgrupo principal', 'Subgrupo', 'Família', 'Ocupação'],
    'metrics' => ['CATs', 'Óbitos', 'CNPJs distintos', 'CNAEs distintos', 'Municípios distintos', 'Tipos de acidente'],
    'pages' => ['cbos.php', 'cbo.php?codigo={codigo}&nivel={nivel}', 'api_etl.php?action=cbo_aggregates'],
]);
