<?php
require_once __DIR__ . '/src/hierarchical_flow_page.php';

renderHierarchicalFlowPage([
    'slug' => 'cnaes',
    'title' => 'Fluxo CNAE',
    'subtitle' => 'Navegação hierárquica por atividade econômica, com sumarização das CATs por nível CNAE.',
    'levels' => ['Seção', 'Divisão', 'Grupo', 'Classe', 'Subclasse'],
    'metrics' => ['CATs', 'Óbitos', 'CNPJs distintos', 'Municípios distintos', 'Período', 'Tipos de acidente'],
    'pages' => ['cnaes.php', 'cnae.php?codigo={codigo}&nivel={nivel}', 'api_etl.php?action=cnae_aggregates'],
]);
