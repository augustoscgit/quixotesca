<?php
require_once __DIR__ . '/src/hierarchical_flow_page.php';

renderHierarchicalFlowPage([
    'slug' => 'territorios',
    'title' => 'Fluxo Territórios',
    'subtitle' => 'Navegação hierárquica por território, com sumarização das CATs por região, UF e município.',
    'levels' => ['Região', 'UF', 'Município'],
    'metrics' => ['CATs', 'Óbitos', 'CNPJs distintos', 'CNAEs distintos', 'CBOs distintos', 'Período'],
    'pages' => ['territorios.php', 'territorio.php?codigo={codigo}&nivel={nivel}', 'api_etl.php?action=territorio_aggregates'],
]);
