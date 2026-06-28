<?php
/**
 * Mockup funcional em PHP + HTML + CSS + JavaScript
 * Investigação epidemiológica de óbitos relacionados ao trabalho
 *
 * Arquivos esperados na mesma pasta deste PHP:
 * - dict_cbo_oc.txt
 * - dict_cid_scat.txt
 * - dict_municipio.txt
 *
 * Formato esperado dos TXT:
 * {'010101':'Descrição', '010102':'Outra descrição', ...}
 * Também aceita JSON simples e linhas no formato codigo;descricao, codigo|descricao ou codigo\tdescricao.
 *
 * Este mockup não grava em banco de dados. Exporta JSON localmente no navegador.
 */

header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('America/Sao_Paulo');

const DICT_CBO = 'dict_cbo_oc.txt';
const DICT_CID = 'dict_cid_scat.txt';
const DICT_MUN = 'dict_municipio.txt';

function app_utf8(string $s): string {
    if ($s === '') return $s;
    if (function_exists('mb_check_encoding') && mb_check_encoding($s, 'UTF-8')) return $s;
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
    }
    $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
    return $converted !== false ? $converted : $s;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gerar_id_caso(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function normalize_search(string $s): string {
    $s = app_utf8($s);
    $s = mb_strtolower($s, 'UTF-8');
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n'
    ];
    return strtr($s, $map);
}

function parse_dict_file(string $filename): array {
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'investigacao' . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) return [];

    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $raw = app_utf8(trim($raw));
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

    $json = json_decode($raw, true);
    if (is_array($json)) return normalize_dict_array($json);

    $converted = preg_replace('/([\{,]\s*)\'([^\']*)\'\s*:/u', '$1"$2":', $raw);
    $converted = preg_replace('/:\s*\'((?:[^\'\\]|\\.)*)\'\s*([,\}])/u', ':"$1"$2', $converted);
    $json = json_decode($converted, true);
    if (is_array($json)) return normalize_dict_array($json);

    $dict = [];
    $lines = preg_split('/\R/u', $raw) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $line = trim($line, " \t\n\r\0\x0B,{}");
        $line = trim($line, "'\"");

        $parts = null;
        foreach (["\t", ';', '|'] as $sep) {
            if (str_contains($line, $sep)) {
                $parts = explode($sep, $line, 2);
                break;
            }
        }
        if ($parts === null && preg_match('/^\s*[\'\"]?([^\'\"\s:]+)[\'\"]?\s*:\s*[\'\"]?(.*?)[\'\"]?\s*$/u', $line, $m)) {
            $parts = [$m[1], $m[2]];
        }
        if ($parts !== null && count($parts) === 2) {
            $code = trim($parts[0], " \t\n\r\0\x0B'\"");
            $desc = trim($parts[1], " \t\n\r\0\x0B'\",");
            if ($code !== '' && $desc !== '') $dict[$code] = app_utf8($desc);
        }
    }
    return normalize_dict_array($dict);
}

function normalize_dict_array(array $arr): array {
    $out = [];
    foreach ($arr as $code => $desc) {
        $code = trim((string)$code);
        $desc = trim(app_utf8((string)$desc));
        if ($code !== '' && $desc !== '') $out[$code] = $desc;
    }
    ksort($out, SORT_NATURAL);
    return $out;
}

function dict_file_status(string $filename): array {
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'investigacao' . DIRECTORY_SEPARATOR . $filename;
    return [
        'file' => $filename,
        'exists' => is_file($path),
        'size' => is_file($path) ? filesize($path) : null,
    ];
}

function get_dict_by_kind(string $kind): array {
    return match ($kind) {
        'cbo' => parse_dict_file(DICT_CBO),
        'cid' => parse_dict_file(DICT_CID),
        'mun' => parse_dict_file(DICT_MUN),
        default => [],
    };
}

function ajax_search(): void {
    $kind = $_GET['kind'] ?? '';
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 50)));
    $dict = get_dict_by_kind($kind);
    $needle = normalize_search($q);
    $out = [];

    foreach ($dict as $code => $desc) {
        if ($needle === '' || str_contains(normalize_search($code . ' ' . $desc), $needle)) {
            $out[] = ['code' => $code, 'label' => $code . ' - ' . $desc, 'description' => $desc];
            if (count($out) >= $limit) break;
        }
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'kind' => $kind, 'q' => $q, 'count' => count($out), 'results' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ajax_status(): void {
    $status = [
        'cbo' => dict_file_status(DICT_CBO),
        'cid' => dict_file_status(DICT_CID),
        'mun' => dict_file_status(DICT_MUN),
    ];
    foreach ($status as $k => $s) {
        $status[$k]['records'] = $s['exists'] ? count(get_dict_by_kind($k)) : 0;
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'status' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'search') ajax_search();
    if ($_GET['ajax'] === 'status') ajax_status();
}

$idCaso = gerar_id_caso();
$dataHoje = date('d/m/Y');
$usuarioAtual = [
    'id' => 'USR001',
    'nome' => 'Usuário demonstrativo',
    'instituicoes' => [
        ['id' => 'INST001', 'nome' => 'Instituição demonstrativa']
    ]
];
$instituicaoPadrao = count($usuarioAtual['instituicoes']) === 1 ? $usuarioAtual['instituicoes'][0]['id'] : '';
$statusArquivos = [
    'CBO ocupação' => dict_file_status(DICT_CBO),
    'CID-10 subcategoria' => dict_file_status(DICT_CID),
    'Municípios' => dict_file_status(DICT_MUN),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Investigação epidemiológica de óbitos - Mockup</title>
<style>
:root{--bg:#f4f6f8;--panel:#fff;--text:#1f2937;--muted:#64748b;--border:#d7dee8;--primary:#135d66;--primary-dark:#0f4950;--soft:#eef7f8;--warn:#fff7ed;--warn-border:#fdba74;--danger:#991b1b;--ok:#166534;--shadow:0 8px 24px rgba(15,23,42,.08);--radius:14px}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;line-height:1.45}header{background:linear-gradient(135deg,var(--primary),#0b3b40);color:#fff;padding:22px 28px}header h1{margin:0 0 6px 0;font-size:1.35rem}header p{margin:0;opacity:.92;font-size:.94rem}main{max-width:1260px;margin:22px auto 70px;padding:0 18px}.app-shell{display:grid;grid-template-columns:285px minmax(0,1fr);gap:18px;align-items:start}nav{position:sticky;top:14px;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px}.step-btn{width:100%;border:1px solid transparent;background:transparent;color:var(--text);text-align:left;padding:12px 10px;border-radius:10px;cursor:pointer;display:flex;gap:10px;align-items:center;font-size:.95rem;margin-bottom:6px}.step-btn:hover{background:#f1f5f9}.step-btn.active{background:var(--soft);border-color:#b7dadd;color:var(--primary-dark);font-weight:700}.step-index{width:26px;height:26px;flex:0 0 26px;border-radius:999px;background:#e5e7eb;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700}.step-btn.active .step-index{background:var(--primary);color:#fff}.toolbar{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}.content{min-width:0}.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:18px}.step-panel{display:none}.step-panel.active{display:block}h2{margin:0 0 12px;font-size:1.22rem;color:#0f3f45}h3{margin:18px 0 10px;font-size:1rem;color:#334155;border-left:4px solid var(--primary);padding-left:9px}.hint{color:var(--muted);font-size:.9rem;margin:4px 0 12px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}label{display:block;font-size:.86rem;font-weight:700;color:#374151;margin-bottom:5px}input,select,textarea{width:100%;border:1px solid var(--border);border-radius:10px;padding:9px 10px;font-size:.95rem;background:#fff;color:var(--text)}textarea{min-height:92px;resize:vertical}input:focus,select:focus,textarea:focus{outline:3px solid rgba(19,93,102,.15);border-color:var(--primary)}input[readonly]{background:#f8fafc;color:#475569}button,.button{border:1px solid transparent;background:var(--primary);color:#fff;border-radius:10px;padding:9px 12px;font-size:.92rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}button:hover,.button:hover{background:var(--primary-dark)}button.secondary{background:#fff;color:var(--primary-dark);border-color:#bad8dc}button.secondary:hover{background:var(--soft)}button.warning{background:#9a3412}button.warning:hover{background:#7c2d12}button:disabled{opacity:.45;cursor:not-allowed}.inline-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px}.autocomplete{position:relative}.suggestions{position:absolute;z-index:50;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow);max-height:260px;overflow:auto;display:none}.suggestions.open{display:block}.suggestion-item{padding:9px 10px;border-bottom:1px solid #eef2f7;cursor:pointer;font-size:.9rem}.suggestion-item:hover,.suggestion-item.active{background:var(--soft)}.suggestion-item small{display:block;color:var(--muted);font-size:.78rem;margin-top:2px}.tagbox{min-height:42px;border:1px solid var(--border);border-radius:10px;padding:5px;display:flex;flex-wrap:wrap;gap:5px;background:#fff;position:relative}.tagbox input[type=text]{border:none;outline:none;flex:1;min-width:160px;padding:5px}.tagbox input[type=text]:focus{outline:none;border:none}.tag{background:var(--soft);border:1px solid #b7dadd;color:var(--primary-dark);padding:5px 8px;border-radius:999px;font-size:.86rem;display:inline-flex;gap:6px;align-items:center}.tag button{background:transparent;color:var(--primary-dark);border:none;padding:0;font-size:.9rem;line-height:1}.diff{background:var(--warn)!important;border-color:var(--warn-border)!important}.diff-label{display:none;margin-top:4px;color:#9a3412;font-size:.78rem;font-weight:700}.field-wrap.changed .diff-label{display:block}.summary-box{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.summary-item{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:10px}.summary-item strong{display:block;font-size:.78rem;color:var(--muted);margin-bottom:3px}.status-pill{display:inline-block;border-radius:999px;padding:4px 8px;font-size:.8rem;font-weight:700;background:#e5e7eb}.status-open{background:#dbeafe;color:#1e40af}.status-running{background:#fef3c7;color:#92400e}.status-closed{background:#dcfce7;color:#166534}.file-status{margin-top:12px;border-top:1px solid var(--border);padding-top:12px;font-size:.82rem;color:var(--muted)}.file-status div{margin:4px 0}.ok{color:var(--ok);font-weight:700}.bad{color:var(--danger);font-weight:700}.footer-note{color:var(--muted);font-size:.84rem;margin-top:14px}.json-preview{white-space:pre-wrap;max-height:360px;overflow:auto;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:12px;font-size:.82rem}.alert{border:1px solid var(--warn-border);background:var(--warn);border-radius:12px;padding:10px;color:#7c2d12;font-size:.9rem;margin:10px 0}@media(max-width:920px){.app-shell{grid-template-columns:1fr}nav{position:static}.grid{grid-template-columns:repeat(6,1fr)}.col-3,.col-4,.col-6,.col-8,.col-12{grid-column:span 6}.summary-box{grid-template-columns:1fr}}@media print{nav,.toolbar,.inline-actions,button{display:none!important}body{background:#fff}main{margin:0;padding:0}.app-shell{display:block}.step-panel{display:block!important;page-break-inside:avoid}.card{box-shadow:none;border:1px solid #ccc}.suggestions{display:none!important}}
</style>
</head>
<body>
<header>
    <h1>Investigação epidemiológica de óbitos relacionados ao trabalho</h1>
    <p>Mockup funcional para abertura, declaração original, investigação, classificação automática, feedback e encerramento.</p>
</header>
<main>
<div class="app-shell">
<nav aria-label="Fluxo do caso">
    <button type="button" class="step-btn active" data-step="1"><span class="step-index">1</span> Abertura do Caso</button>
    <button type="button" class="step-btn" data-step="2"><span class="step-index">2</span> Declaração original</button>
    <button type="button" class="step-btn" data-step="3"><span class="step-index">3</span> Investigação</button>
    <button type="button" class="step-btn" data-step="4"><span class="step-index">4</span> Classificação automática</button>
    <button type="button" class="step-btn" data-step="5"><span class="step-index">5</span> Feedback</button>
    <div class="toolbar">
        <button type="button" class="secondary" id="btnPrev">← Anterior</button>
        <button type="button" class="secondary" id="btnNext">Próximo →</button>
        <button type="button" class="secondary" onclick="window.print()">Imprimir</button>
        <button type="button" id="btnExport">Exportar JSON</button>
    </div>
    <div class="file-status" id="fileStatus">
        <strong>Dicionários:</strong>
        <?php foreach ($statusArquivos as $label => $st): ?>
            <div><?php echo h($label); ?>: <span class="<?php echo $st['exists'] ? 'ok' : 'bad'; ?>"><?php echo $st['exists'] ? 'encontrado' : 'não encontrado'; ?></span> <small><?php echo h($st['file']); ?></small></div>
        <?php endforeach; ?>
        <button type="button" class="secondary" id="btnCheckDicts" style="margin-top:8px">Verificar registros</button>
    </div>
    <p class="footer-note">Suba este PHP e os três arquivos TXT na mesma pasta. O mockup não grava em banco.</p>
</nav>
<section class="content">
<form id="caseForm" autocomplete="off">
    <div class="summary-box">
        <div class="summary-item"><strong>ID do caso</strong><span id="summaryId"><?php echo h($idCaso); ?></span></div>
        <div class="summary-item"><strong>Responsável</strong><span><?php echo h($usuarioAtual['id']); ?></span></div>
        <div class="summary-item"><strong>Instituição</strong><span id="summaryInst"><?php echo h($instituicaoPadrao ?: 'Não definida'); ?></span></div>
        <div class="summary-item"><strong>Situação</strong><span id="summaryStatus" class="status-pill status-open">Aberto</span></div>
    </div>

    <section class="card step-panel active" data-panel="1">
        <h2>1) Abertura do Caso</h2>
        <p class="hint">Campos administrativos iniciais do caso. Em produção, responsável e instituição viriam da sessão de login.</p>
        <div class="grid">
            <div class="col-3 field-wrap"><label for="case_id">Id do caso</label><input id="case_id" name="case_id" value="<?php echo h($idCaso); ?>" readonly></div>
            <div class="col-3 field-wrap"><label for="responsavel_id">Responsável</label><input id="responsavel_id" name="responsavel_id" value="<?php echo h($usuarioAtual['id']); ?>" readonly></div>
            <div class="col-3 field-wrap"><label for="instituicao_id">Instituição</label><select id="instituicao_id" name="instituicao_id"><?php foreach ($usuarioAtual['instituicoes'] as $inst): ?><option value="<?php echo h($inst['id']); ?>" <?php echo $instituicaoPadrao === $inst['id'] ? 'selected' : ''; ?>><?php echo h($inst['id'] . ' - ' . $inst['nome']); ?></option><?php endforeach; ?></select></div>
            <div class="col-3 field-wrap"><label for="tipo_caso">Tipo de caso</label><select id="tipo_caso" name="tipo_caso"><option value="1">1. Real</option><option value="0">0. Teste</option></select></div>
            <div class="col-3 field-wrap"><label for="situacao_caso_abertura">Situação do caso</label><select id="situacao_caso_abertura" name="situacao_caso_abertura"><option value="1">1. Aberto</option><option value="2">2. Em execução</option><option value="3">3. Encerrado</option></select></div>
            <div class="col-3 field-wrap"><label for="data_abertura">Data de abertura</label><input id="data_abertura" name="data_abertura" class="date-mask" value="<?php echo h($dataHoje); ?>" placeholder="dd/mm/aaaa"></div>
        </div>
    </section>

    <section class="card step-panel" data-panel="2">
        <h2>2) Declaração de Óbito original</h2>
        <p class="hint">Transcrição estruturada da Declaração de Óbito original recebida.</p>
        <div id="doOriginalFields"></div>
    </section>

    <section class="card step-panel" data-panel="3">
        <h2>3) Investigação</h2>
        <p class="hint">Formulário da investigação. Campos alterados em relação à DO original são destacados.</p>
        <div class="inline-actions">
            <button type="button" id="btnCopyOriginal">Copiar valores da DO original</button>
            <button type="button" class="secondary" id="btnCompare">Atualizar diferenças</button>
        </div>
        <div id="investigationFields"></div>
    </section>

    <section class="card step-panel" data-panel="4">
        <h2>4) Classificação automática</h2>
        <p class="hint">Área reservada para retorno do classificador. Neste mockup, a predição é simulada no navegador.</p>
        <div class="grid">
            <div class="col-4 field-wrap"><label for="class_at">Acidente de trabalho</label><select id="class_at" name="class_at"><option value="">Selecione</option><option value="1">1. Sim</option><option value="0">0. Não</option></select></div>
            <div class="col-4 field-wrap"><label for="class_prob">Probabilidade de predição</label><input id="class_prob" name="class_prob" inputmode="decimal" placeholder="0.000 a 1.000"></div>
            <div class="col-4 field-wrap"><label>&nbsp;</label><button type="button" id="btnMockPredict">Gerar predição simulada</button></div>
            <div class="col-12"><div class="card" style="box-shadow:none;background:#f8fafc"><strong>Interpretação operacional provisória:</strong><p id="classInterpretation" class="hint" style="margin-bottom:0">Aguardando predição. Em produção, este bloco pode exibir versão do modelo, data/hora da inferência e variáveis de maior contribuição.</p></div></div>
        </div>
    </section>

    <section class="card step-panel" data-panel="5">
        <h2>5) Feedback e encerramento</h2>
        <h3>Feedback</h3>
        <div class="grid"><div class="col-6 field-wrap"><label for="feedback_concordancia">Você concorda com a classificação automática?</label><select id="feedback_concordancia" name="feedback_concordancia"><option value="">Selecione</option><option value="1">1. Definitivamente não</option><option value="2">2. Talvez não</option><option value="3">3. Incerto</option><option value="4">4. Talvez sim</option><option value="5">5. Definitivamente sim</option></select></div></div>
        <h3>Encerramento do caso</h3>
        <div class="grid">
            <div class="col-12 field-wrap"><label for="encerramento_obs">Observações</label><textarea id="encerramento_obs" name="encerramento_obs"></textarea></div>
            <div class="col-4 field-wrap"><label for="situacao_caso_encerramento">Situação do caso</label><select id="situacao_caso_encerramento" name="situacao_caso_encerramento"><option value="1">1. Aberto</option><option value="2">2. Em execução</option><option value="3">3. Encerrado</option></select></div>
            <div class="col-4 field-wrap"><label for="data_encerramento">Data de encerramento</label><input id="data_encerramento" name="data_encerramento" class="date-mask" placeholder="dd/mm/aaaa"></div>
        </div>
        <h3>Pré-visualização dos dados</h3>
        <div class="inline-actions"><button type="button" class="secondary" id="btnPreview">Atualizar pré-visualização</button></div>
        <pre id="jsonPreview" class="json-preview">{}</pre>
    </section>
</form>
</section>
</div>
</main>
<script>
(function(){
'use strict';
const today = <?php echo json_encode($dataHoje, JSON_UNESCAPED_UNICODE); ?>;
const selectOptions = {
sexo:[['','Selecione'],['1','1. Masculino'],['2','2. Feminino'],['0','0. Ignorado'],['8','8. Não preenchido']],
raca_cor:[['','Selecione'],['1','1. Branco'],['2','2. Preta'],['3','3. Amarela'],['4','4. Parda'],['5','5. Indígena'],['8','8. Não preenchido']],
estado_civil:[['','Selecione'],['1','1. Solteiro'],['2','2. Casado'],['3','3. Viúvo'],['4','4. Separado judicialmente/Divorciado'],['9','9. Ignorado'],['8','8. Não preenchido']],
escolaridade:[['','Selecione'],['1','1. Nenhuma'],['2','2. De 1 a 3'],['3','3. De 4 a 7'],['4','4. De 8 a 11'],['5','5. 12 ou mais'],['9','9. Ignorado'],['8','8. Não preenchido']],
local_ocor:[['','Selecione'],['1','1. Hospital'],['2','2. Outros estabelecimento de saúde'],['3','3. Domicílio'],['4','4. Via pública'],['5','5. Outros'],['9','9. Ignorado'],['8','8. Não preenchido']],
medico_atendeu:[['','Selecione'],['1','1. Sim'],['2','2. Substituto'],['3','3. IML'],['4','4. SVO'],['5','5. Outros'],['8','8. Não preenchido']],
tipo_morte_nao_natural:[['','Selecione'],['1','1. Acidente'],['2','2. Suicídio'],['3','3. Homicídio'],['4','4. Outros'],['9','9. Ignorado'],['8','8. Não preenchido']],
acidente_trabalho:[['','Selecione'],['1','1. Sim'],['0','0. Não'],['9','9. Ignorado'],['8','8. Não preenchido']],
fonte_info:[['','Selecione'],['1','1. Boletim de Ocorrência'],['2','2. Hospital'],['3','3. Família'],['4','4. Outra'],['9','9. Ignorada'],['8','8. Não preenchido']],
investigacao:[['','Selecione'],['1','1. Sim'],['0','0. Não'],['9','9. Ignorado'],['8','8. Não preenchido']]
};
const fieldSchema = [
{type:'heading',label:'Identificação'},
{key:'data_obito',label:'(8) Data do óbito',type:'date',col:'col-3'},
{key:'hora_obito',label:'(8.1) Hora do óbito',type:'time_select',col:'col-3'},
{key:'data_nasc',label:'(14) Data de nascimento',type:'date',col:'col-3'},
{key:'idade_anos',label:'(15) Idade (anos)',type:'age',col:'col-3'},
{key:'sexo',label:'16. Sexo',type:'select',options:'sexo',col:'col-3'},
{key:'raca_cor',label:'17. Raça/Cor',type:'select',options:'raca_cor',col:'col-3'},
{key:'estado_civil',label:'18. Estado civil',type:'select',options:'estado_civil',col:'col-3'},
{key:'escolaridade',label:'19. Escolaridade',type:'select',options:'escolaridade',col:'col-3'},
{key:'ocup_texto',label:'20.1. Ocupação habitual - texto curto',type:'text',col:'col-6'},
{key:'ocup_cbo',label:'20.2. CBO',type:'autocomplete_single',kind:'cbo',placeholder:'Digite código ou ocupação',col:'col-6'},
{type:'heading',label:'Ocorrência'},
{key:'local_ocor',label:'26. Local de ocorrência do óbito',type:'select',options:'local_ocor',col:'col-4'},
{key:'cep_ocor',label:'29. CEP de ocorrência',type:'cep',col:'col-4'},
{key:'mun_ocor',label:'31. Município de ocorrência',type:'autocomplete_single',kind:'mun',placeholder:'Digite código, município ou UF',col:'col-4'},
{type:'heading',label:'Condições e causas do óbito'},
{key:'causa_basica',label:'49.1. Causa Básica',type:'tag_single',kind:'cid',col:'col-12'},
{key:'linha_a',label:'49.1.1. Linha A, incluindo causa básica',type:'tag_multi',kind:'cid',col:'col-12'},
{key:'linha_b',label:'49.1.2. Linha B',type:'tag_multi',kind:'cid',col:'col-12'},
{key:'linha_c',label:'49.1.3. Linha C',type:'tag_multi',kind:'cid',col:'col-12'},
{key:'linha_d',label:'49.1.4. Linha D',type:'tag_multi',kind:'cid',col:'col-12'},
{key:'parte_ii',label:'49.2. Parte II',type:'tag_multi',kind:'cid',col:'col-12'},
{type:'heading',label:'Médico'},
{key:'medico_atendeu',label:'52. O médico que assina atendeu ao falecido?',type:'select',options:'medico_atendeu',col:'col-6'},
{type:'heading',label:'Causas externas'},
{key:'tipo_morte_nao_natural',label:'56. Tipo',type:'select',options:'tipo_morte_nao_natural',col:'col-4'},
{key:'acidente_trabalho',label:'57. Acidente de trabalho',type:'select',options:'acidente_trabalho',col:'col-4'},
{key:'fonte_info',label:'58. Fonte de Informação',type:'select',options:'fonte_info',col:'col-4'},
{key:'descricao_evento',label:'59. Descrição sumária do evento incluindo tipo e local de ocorrência',type:'textarea',col:'col-12'},
{key:'investigacao',label:'Investigação',type:'select',options:'investigacao',col:'col-4'},
{key:'obs_documentos',label:'Observações sobre DO e outros documentos recebidos',type:'textarea',col:'col-12'}
];
function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}
function selectHtml(name,id,optionsKey){return '<select id="'+id+'" name="'+name+'">'+selectOptions[optionsKey].map(o=>'<option value="'+escapeHtml(o[0])+'">'+escapeHtml(o[1])+'</option>').join('')+'</select>';}
function timeOptions(){let s='';for(let h=0;h<24;h++){for(let m=0;m<60;m+=30){const v=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');s+='<option value="'+v+'">'+v+'</option>';}}return s;}
function autocompleteHtml(id,name,kind,placeholder){return '<div class="autocomplete" data-kind="'+kind+'"><input id="'+id+'" name="'+name+'" type="text" placeholder="'+escapeHtml(placeholder||'')+'"><div class="suggestions"></div></div>';}
function tagboxHtml(id,name,kind,single){return '<div class="tagbox" data-kind="'+kind+'" data-single="'+(single?'1':'0')+'"><input id="'+id+'" type="text" placeholder="Digite código ou termo e selecione"><input type="hidden" name="'+name+'" value="[]"><div class="suggestions"></div></div>';}
function renderFields(containerId,prefix,includeInvestigationObs){const container=document.getElementById(containerId);let html='<div class="grid">';fieldSchema.forEach(f=>{if(f.type==='heading'){html+='<div class="col-12"><h3>'+escapeHtml(f.label)+'</h3></div>';return;}const id=prefix+'_'+f.key;const name=prefix+'['+f.key+']';html+='<div class="'+f.col+' field-wrap" data-field="'+f.key+'"><label for="'+id+'">'+escapeHtml(f.label)+'</label>';if(f.type==='select')html+=selectHtml(name,id,f.options);else if(f.type==='date')html+='<input id="'+id+'" name="'+name+'" class="date-mask" placeholder="dd/mm/aaaa">';else if(f.type==='time_select')html+='<select id="'+id+'" name="'+name+'"><option value="">Selecione</option>'+timeOptions()+'</select>';else if(f.type==='age'){html+='<input id="'+id+'" name="'+name+'" inputmode="numeric" placeholder="Idade em anos"><div class="inline-actions"><button type="button" class="secondary age-calc" data-prefix="'+prefix+'">Calcular</button></div>';}else if(f.type==='cep')html+='<input id="'+id+'" name="'+name+'" class="cep-mask" placeholder="00000-000">';else if(f.type==='textarea')html+='<textarea id="'+id+'" name="'+name+'"></textarea>';else if(f.type==='autocomplete_single')html+=autocompleteHtml(id,name,f.kind,f.placeholder);else if(f.type==='tag_multi'||f.type==='tag_single')html+=tagboxHtml(id,name,f.kind,f.type==='tag_single');else html+='<input id="'+id+'" name="'+name+'">';if(prefix==='inv')html+='<div class="diff-label">Valor diferente da DO original</div>';html+='</div>';});if(includeInvestigationObs){html+='<div class="col-12"><h3>Observações da investigação</h3></div><div class="col-12 field-wrap"><label for="inv_observacoes_investigacao">Observações da investigação</label><textarea id="inv_observacoes_investigacao" name="inv[observacoes_investigacao]"></textarea></div>';}html+='</div>';container.innerHTML=html;}
renderFields('doOriginalFields','orig',false);renderFields('investigationFields','inv',true);
let currentStep=1;document.querySelectorAll('.step-btn').forEach(btn=>btn.addEventListener('click',()=>showStep(Number(btn.dataset.step))));document.getElementById('btnPrev').addEventListener('click',()=>showStep(Math.max(1,currentStep-1)));document.getElementById('btnNext').addEventListener('click',()=>showStep(Math.min(5,currentStep+1)));
function showStep(step){currentStep=step;document.querySelectorAll('.step-btn').forEach(b=>b.classList.toggle('active',Number(b.dataset.step)===step));document.querySelectorAll('.step-panel').forEach(p=>p.classList.toggle('active',Number(p.dataset.panel)===step));document.getElementById('btnPrev').disabled=step===1;document.getElementById('btnNext').disabled=step===5;if(step===3)compareOriginalInvestigation();if(step===5)updatePreview();window.scrollTo({top:0,behavior:'smooth'});}
function onlyDigits(v){return String(v||'').replace(/\D+/g,'');}
function bindMasks(){document.querySelectorAll('.date-mask').forEach(el=>el.addEventListener('input',()=>{let v=onlyDigits(el.value).slice(0,8);if(v.length>=5)v=v.replace(/^(\d{2})(\d{2})(\d{1,4}).*/,'$1/$2/$3');else if(v.length>=3)v=v.replace(/^(\d{2})(\d{1,2}).*/,'$1/$2');el.value=v;}));document.querySelectorAll('.cep-mask').forEach(el=>el.addEventListener('input',()=>{let v=onlyDigits(el.value).slice(0,8);if(v.length>=6)v=v.replace(/^(\d{5})(\d{1,3}).*/,'$1-$2');el.value=v;}));document.querySelectorAll('.age-calc').forEach(btn=>btn.addEventListener('click',()=>calculateAge(btn.dataset.prefix)));}
function parseDateBR(v){const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v||'');if(!m)return null;const d=new Date(Number(m[3]),Number(m[2])-1,Number(m[1]));if(d.getFullYear()!==Number(m[3])||d.getMonth()!==Number(m[2])-1||d.getDate()!==Number(m[1]))return null;return d;}
function calculateAge(prefix){const dob=parseDateBR(document.getElementById(prefix+'_data_nasc').value);const dod=parseDateBR(document.getElementById(prefix+'_data_obito').value);const ageInput=document.getElementById(prefix+'_idade_anos');if(!dob||!dod||dod<dob){alert('Informe datas válidas de nascimento e óbito.');return;}let age=dod.getFullYear()-dob.getFullYear();const before=(dod.getMonth()<dob.getMonth())||(dod.getMonth()===dob.getMonth()&&dod.getDate()<dob.getDate());if(before)age--;ageInput.value=age;compareOriginalInvestigation();}
function debounce(fn,delay){let t;return(...args)=>{clearTimeout(t);t=setTimeout(()=>fn(...args),delay);};}
async function searchDict(kind,q){const url=new URL(window.location.href);url.search='';url.searchParams.set('ajax','search');url.searchParams.set('kind',kind);url.searchParams.set('q',q);url.searchParams.set('limit','50');const r=await fetch(url.toString(),{headers:{'Accept':'application/json'}});return await r.json();}
function bindAutocomplete(){document.querySelectorAll('.autocomplete').forEach(box=>{const input=box.querySelector('input');const sug=box.querySelector('.suggestions');const kind=box.dataset.kind;const run=debounce(async()=>{const q=input.value.trim();if(q.length<2){sug.classList.remove('open');sug.innerHTML='';return;}try{const data=await searchDict(kind,q);renderSuggestions(sug,data.results||[],item=>{input.value=item.label;sug.classList.remove('open');compareOriginalInvestigation();});}catch(e){sug.innerHTML='<div class="suggestion-item">Erro ao consultar dicionário.</div>';sug.classList.add('open');}},250);input.addEventListener('input',run);input.addEventListener('focus',run);document.addEventListener('click',ev=>{if(!box.contains(ev.target))sug.classList.remove('open');});});document.querySelectorAll('.tagbox').forEach(box=>{const input=box.querySelector('input[type=text]');const sug=box.querySelector('.suggestions');const kind=box.dataset.kind;const run=debounce(async()=>{const q=input.value.trim();if(q.length<1){sug.classList.remove('open');sug.innerHTML='';return;}try{const data=await searchDict(kind,q);renderSuggestions(sug,data.results||[],item=>{addTag(box,item.label);input.value='';sug.classList.remove('open');compareOriginalInvestigation();});}catch(e){sug.innerHTML='<div class="suggestion-item">Erro ao consultar dicionário.</div>';sug.classList.add('open');}},250);input.addEventListener('input',run);input.addEventListener('focus',run);input.addEventListener('keydown',ev=>{if(ev.key==='Enter'){ev.preventDefault();const first=sug.querySelector('.suggestion-item');if(first)first.click();else if(input.value.trim()){addTag(box,input.value.trim());input.value='';compareOriginalInvestigation();}}});document.addEventListener('click',ev=>{if(!box.contains(ev.target))sug.classList.remove('open');});});}
function renderSuggestions(container,items,onPick){if(!items.length){container.innerHTML='<div class="suggestion-item"><em>Nenhum resultado encontrado</em></div>';container.classList.add('open');return;}container.innerHTML='';items.forEach(item=>{const div=document.createElement('div');div.className='suggestion-item';div.innerHTML=escapeHtml(item.label)+'<small>'+escapeHtml(item.description||'')+'</small>';div.addEventListener('click',()=>onPick(item));container.appendChild(div);});container.classList.add('open');}
function addTag(box,value){if(!value)return;const single=box.dataset.single==='1';const hidden=box.querySelector('input[type="hidden"]');let values=JSON.parse(hidden.value||'[]');if(single)values=[];if(!values.includes(value))values.push(value);hidden.value=JSON.stringify(values);renderTags(box,values);}
function renderTags(box,values){box.querySelectorAll('.tag').forEach(t=>t.remove());const input=box.querySelector('input[type=text]');values.forEach(value=>{const tag=document.createElement('span');tag.className='tag';tag.innerHTML=escapeHtml(value)+' <button type="button" title="Remover">×</button>';tag.querySelector('button').addEventListener('click',()=>{const hidden=box.querySelector('input[type="hidden"]');const current=JSON.parse(hidden.value||'[]').filter(v=>v!==value);hidden.value=JSON.stringify(current);renderTags(box,current);compareOriginalInvestigation();});box.insertBefore(tag,input);});}
function getFieldValue(prefix,key){const el=document.getElementById(prefix+'_'+key);if(el)return el.value||'';const hidden=document.querySelector('.tagbox input[type="hidden"][name="'+prefix+'['+key+']'+'"]');if(hidden)return hidden.value||'[]';return '';}
function setFieldValue(prefix,key,value){const el=document.getElementById(prefix+'_'+key);if(el){el.value=value||'';return;}const hidden=document.querySelector('.tagbox input[type="hidden"][name="'+prefix+'['+key+']'+'"]');if(hidden){hidden.value=value||'[]';renderTags(hidden.closest('.tagbox'),JSON.parse(hidden.value||'[]'));}}
function copyOriginalToInvestigation(){fieldSchema.forEach(f=>{if(f.key)setFieldValue('inv',f.key,getFieldValue('orig',f.key));});compareOriginalInvestigation();alert('Valores da DO original copiados para a investigação.');}
function normalizeValue(v){return String(v||'').trim();}
function compareOriginalInvestigation(){fieldSchema.forEach(f=>{if(!f.key)return;const original=normalizeValue(getFieldValue('orig',f.key));const investigated=normalizeValue(getFieldValue('inv',f.key));const wrap=document.querySelector('#investigationFields .field-wrap[data-field="'+f.key+'"]');if(!wrap)return;const changed=investigated!==''&&original!==investigated;wrap.classList.toggle('changed',changed);wrap.querySelectorAll('input,select,textarea,.tagbox').forEach(el=>el.classList.toggle('diff',changed));});}
function syncStatus(value){const status=document.getElementById('summaryStatus');status.className='status-pill';if(value==='1'){status.textContent='Aberto';status.classList.add('status-open');}else if(value==='2'){status.textContent='Em execução';status.classList.add('status-running');}else if(value==='3'){status.textContent='Encerrado';status.classList.add('status-closed');if(!document.getElementById('data_encerramento').value)document.getElementById('data_encerramento').value=today;}}
function mockPredict(){const tipo=getFieldValue('inv','tipo_morte_nao_natural')||getFieldValue('orig','tipo_morte_nao_natural');const at=getFieldValue('inv','acidente_trabalho')||getFieldValue('orig','acidente_trabalho');const ocup=getFieldValue('inv','ocup_cbo')||getFieldValue('orig','ocup_cbo');const y96=((getFieldValue('inv','parte_ii')||getFieldValue('orig','parte_ii')).toUpperCase().includes('Y96'));let p=0.18+Math.random()*0.18;if(tipo==='1')p+=0.12;if(at==='1')p+=0.35;if(y96)p+=0.18;if(ocup.trim()!=='')p+=0.07;p=Math.min(0.98,Math.max(0.02,p));document.getElementById('class_prob').value=p.toFixed(3);document.getElementById('class_at').value=p>=0.50?'1':'0';updateClassInterpretation();}
function updateClassInterpretation(){const p=Number(String(document.getElementById('class_prob').value||'').replace(',','.'));const at=document.getElementById('class_at').value;const out=document.getElementById('classInterpretation');if(Number.isNaN(p)){out.textContent='Probabilidade inválida. Utilize valor entre 0 e 1.';return;}if(at===''){out.textContent='Aguardando classificação binária.';return;}const label=at==='1'?'provável acidente de trabalho':'provável não acidente de trabalho';let faixa='baixa confiança';if(p>=0.70||p<=0.30)faixa='maior confiança';if(p>0.40&&p<0.60)faixa='zona de incerteza operacional';out.textContent='Classificação: '+label+'. Probabilidade: '+p.toFixed(3)+' ('+faixa+').';}
function parseMaybeJson(v){try{if(String(v).trim().startsWith('['))return JSON.parse(v);}catch(e){}return v;}
function collectData(){const data={abertura:{case_id:document.getElementById('case_id').value,responsavel_id:document.getElementById('responsavel_id').value,instituicao_id:document.getElementById('instituicao_id').value,tipo_caso:document.getElementById('tipo_caso').value,situacao_caso_abertura:document.getElementById('situacao_caso_abertura').value,data_abertura:document.getElementById('data_abertura').value},declaracao_original:{},investigacao:{},classificacao:{acidente_trabalho:document.getElementById('class_at').value,probabilidade_predicao:document.getElementById('class_prob').value},feedback:{concordancia:document.getElementById('feedback_concordancia').value,observacoes:document.getElementById('encerramento_obs').value,situacao_caso_encerramento:document.getElementById('situacao_caso_encerramento').value,data_encerramento:document.getElementById('data_encerramento').value}};fieldSchema.forEach(f=>{if(!f.key)return;data.declaracao_original[f.key]=parseMaybeJson(getFieldValue('orig',f.key));data.investigacao[f.key]=parseMaybeJson(getFieldValue('inv',f.key));});data.investigacao.observacoes_investigacao=document.getElementById('inv_observacoes_investigacao').value;return data;}
function updatePreview(){document.getElementById('jsonPreview').textContent=JSON.stringify(collectData(),null,2);}
function exportJson(){const data=JSON.stringify(collectData(),null,2);const blob=new Blob([data],{type:'application/json;charset=utf-8'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='caso_'+document.getElementById('case_id').value+'.json';document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(a.href);}
async function checkDicts(){const url=new URL(window.location.href);url.search='';url.searchParams.set('ajax','status');const r=await fetch(url.toString());const data=await r.json();let msg='';Object.entries(data.status||{}).forEach(([k,v])=>{msg+=k+': '+(v.exists?'encontrado':'não encontrado')+'; registros: '+(v.records||0)+'\n';});alert(msg||'Não foi possível verificar os dicionários.');}
bindMasks();bindAutocomplete();document.getElementById('btnCopyOriginal').addEventListener('click',copyOriginalToInvestigation);document.getElementById('btnCompare').addEventListener('click',compareOriginalInvestigation);document.getElementById('btnPreview').addEventListener('click',updatePreview);document.getElementById('btnExport').addEventListener('click',exportJson);document.getElementById('btnMockPredict').addEventListener('click',mockPredict);document.getElementById('btnCheckDicts').addEventListener('click',checkDicts);document.getElementById('caseForm').addEventListener('input',ev=>{if(ev.target.id==='instituicao_id')document.getElementById('summaryInst').textContent=ev.target.value||'Não definida';if(ev.target.id==='situacao_caso_abertura'||ev.target.id==='situacao_caso_encerramento')syncStatus(ev.target.value);if(ev.target.closest('#investigationFields'))compareOriginalInvestigation();if(ev.target.id==='class_prob'||ev.target.id==='class_at')updateClassInterpretation();});showStep(1);
})();
</script>
</body>
</html>
