<?php

/**
 * Builds public/load-test-report.html from a k6 run: summary stats + notes,
 * Prometheus SVG charts and the searchable Q&A transcript. php k6/build-report.php
 * Charts are inline SVG (no iframe/auth, works offline); if Prometheus is down
 * they show "no data" and the rest still builds.
 */

const ROOT = __DIR__ . '/..';
const LOG = ROOT . '/k6/replies.log';
const SUMMARY = ROOT . '/k6/summary.json';
const OUT = ROOT . '/public/load-test-report.html';
const PROM = 'http://localhost:9091/';
const GRAFANA = 'http://localhost:3001/d/brproperty-vllm-load';

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtMs(float $ms): string
{
    return $ms >= 1000 ? number_format($ms / 1000, 1) . ' s' : round($ms) . ' ms';
}

function fmtUnit(float $v, string $u): string
{
    return match ($u) {
        's' => rtrim(rtrim(number_format($v, 1), '0'), '.') . 's',
        'reqps' => number_format($v, 1) . '/s',
        'percentunit' => round($v * 100) . '%',
        default => (string) round($v),
    };
}

/** One Prometheus range query, returned as the raw result list (or []). */
function promRange(string $expr, int $start, int $end, int $step): array
{
    $url = PROM . 'api/v1/query_range?' . http_build_query([
        'query' => $expr,
        'start' => $start,
        'end' => $end,
        'step' => $step,
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return [];
    }
    $j = json_decode($raw, true);
    return $j['data']['result'] ?? [];
}

/** A named line series for a chart, flattened from a (single-series) query. */
function series(string $expr, string $label, int $start, int $end, int $step): array
{
    $points = [];
    foreach (promRange($expr, $start, $end, $step) as $r) {
        foreach ($r['values'] ?? [] as $v) {
            $points[] = [(float) $v[0], (float) $v[1]];
        }
    }
    usort($points, fn($a, $b) => $a[0] <=> $b[0]);
    return ['label' => $label, 'points' => $points];
}

const COLORS = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#0891b2'];

/** Render a small multi-line SVG time-series chart. */
function svgChart(string $title, array $seriesList, string $unit, int $start, int $end, ?float $cap = null): string
{
    $w = 600;
    $h = 210;
    $pl = 48;
    $pr = 12;
    $pt = 14;
    $pb = 26;
    $plotW = $w - $pl - $pr;
    $plotH = $h - $pt - $pb;

    $maxV = 0.0;
    $hasData = false;
    foreach ($seriesList as $s) {
        foreach ($s['points'] as $p) {
            $hasData = true;
            if ($p[1] > $maxV) {
                $maxV = $p[1];
            }
        }
    }
    if ($cap !== null) {
        $maxV = min($maxV, $cap);
    }
    $maxV = $maxV > 0 ? $maxV * 1.1 : 1.0;

    $x = fn(float $t) => $pl + ($end > $start ? ($t - $start) / ($end - $start) : 0) * $plotW;
    $y = fn(float $v) => $pt + (1 - min($v, $maxV) / $maxV) * $plotH;

    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="chart" role="img" aria-label="' . esc($title) . '">';

    for ($i = 0; $i <= 2; $i++) {
        $v = $maxV * $i / 2;
        $yy = $y($v);
        $svg .= '<line x1="' . $pl . '" y1="' . $yy . '" x2="' . ($w - $pr) . '" y2="' . $yy . '" class="grid"/>';
        $svg .= '<text x="' . ($pl - 6) . '" y="' . ($yy + 4) . '" class="ylab" text-anchor="end">' . esc(fmtUnit($v, $unit)) . '</text>';
    }

    if (!$hasData) {
        $svg .= '<text x="' . ($w / 2) . '" y="' . ($h / 2) . '" class="nodata" text-anchor="middle">no data (Prometheus unreachable or empty window)</text>';
    }

    $legend = '';
    foreach ($seriesList as $i => $s) {
        $color = COLORS[$i % count(COLORS)];
        if ($s['points']) {
            $pts = implode(' ', array_map(fn($p) => round($x($p[0]), 1) . ',' . round($y($p[1]), 1), $s['points']));
            $svg .= '<polyline points="' . $pts . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
        }
        $legend .= '<span class="lg"><i style="background:' . $color . '"></i>' . esc($s['label']) . '</span>';
    }

    $svg .= '<text x="' . $pl . '" y="' . ($h - 8) . '" class="xlab">' . date('H:i:s', $start) . '</text>';
    $svg .= '<text x="' . ($w - $pr) . '" y="' . ($h - 8) . '" class="xlab" text-anchor="end">' . date('H:i:s', $end) . '</text>';
    $svg .= '</svg>';

    return '<figure class="panel"><figcaption>' . esc($title) . '<span class="legend">' . $legend . '</span></figcaption>' . $svg . '</figure>';
}

if (!is_file(LOG) || !is_file(SUMMARY)) {
    fwrite(STDERR, "Missing k6/replies.log or k6/summary.json - run k6/load-model.js first.\n");
    exit(1);
}

$summary = json_decode((string) file_get_contents(SUMMARY), true) ?: [];
$log = (string) file_get_contents(LOG);

// k6's file log appends across runs; slice from the last deprecation notice to
// keep only the latest run (truncating before a run is the real fix).
$cut = strrpos($log, 'experimental-prometheus-rw output has been deprecated');
if ($cut !== false) {
    $log = substr($log, $cut);
}

// One block per answer: header, "Q: ...", then multi-line "A: ..." up to the next header.
preg_match_all(
    '/^=== vu (\d+) iter (\d+) \| (\d+)ms \| tokens (\S+) \| gen (\S+) \| (\S+) ===\nQ: (.*?)\nA: (.*?)(?=\n=== vu |\z)/ms',
    $log,
    $blocks,
    PREG_SET_ORDER
);

$entries = [];
$toolNames = [];
$prose = 0;
$tool = 0;
$tokenSum = 0;
$tokenN = 0;
$genSum = 0;
$genN = 0;
$msSum = 0;
foreach ($blocks as $b) {
    $answer = rtrim($b[8]);
    $isTool = $b[6] === 'tool_calls';
    $isTool ? $tool++ : $prose++;
    $msSum += (int) $b[3];
    if (is_numeric($b[4])) {
        $tokenSum += (int) $b[4];
        $tokenN++;
    }
    if (is_numeric($b[5])) {
        $genSum += (int) $b[5];
        $genN++;
    }
    if (preg_match_all('/\[tool_call\]\s*(\w+)\(/', $answer, $tm)) {
        foreach ($tm[1] as $name) {
            $toolNames[$name] = ($toolNames[$name] ?? 0) + 1;
        }
    }
    $entries[] = [
        'vu' => (int) $b[1],
        'iter' => (int) $b[2],
        'ms' => (int) $b[3],
        'tokens' => $b[4],
        'gen' => $b[5],
        'finish' => $b[6],
        'q' => trim($b[7]),
        'a' => $answer,
        'type' => $isTool ? 'tool' : 'prose',
    ];
}
$total = count($entries);
$avgTokens = $tokenN ? round($tokenSum / $tokenN) : 0;
$avgGen = $genN ? round($genSum / $genN) : 0;
$genThroughput = $msSum > 0 ? $genSum / ($msSum / 1000) : 0;
arsort($toolNames);

$end = (int) strtotime($summary['finished_at'] ?? 'now') + 5;
$start = $end - 230;
$step = 5;

$charts = '';
$charts .= svgChart('Virtual Users (k6)', [series('k6_vus', 'VUs', $start, $end, $step)], 'short', $start, $end);
$charts .= svgChart('Request Rate (k6)', [series('sum(rate(k6_http_reqs_total[1m]))', 'req/s', $start, $end, $step)], 'reqps', $start, $end);
$charts .= svgChart('Model Reply Latency p99', [series('k6_model_latency_ms_p99', 'p99', $start, $end, $step)], 's', $start, $end);
$charts .= svgChart('vLLM Running vs Waiting (queue)', [
    series('vllm:num_requests_running', 'running', $start, $end, $step),
    series('vllm:num_requests_waiting', 'waiting', $start, $end, $step),
], 'short', $start, $end);
$charts .= svgChart('vLLM KV Cache Usage', [series('vllm:kv_cache_usage_perc', 'KV cache', $start, $end, $step)], 'percentunit', $start, $end, 1.0);
$charts .= svgChart('vLLM Completed Requests /s', [series('sum(rate(vllm:request_success_total[1m]))', 'completed/s', $start, $end, $step)], 'reqps', $start, $end);
$charts .= svgChart('vLLM Generation Tokens/s', [series('sum(rate(vllm:generation_tokens_total[1m]))', 'generated/s', $start, $end, $step)], 'reqps', $start, $end);

$lat = $summary['model_latency_ms'] ?? [];
$card = fn(string $label, string $value, string $sub = '') =>
'<div class="card"><div class="cv">' . $value . '</div><div class="cl">' . esc($label) . '</div>'
    . ($sub !== '' ? '<div class="cs">' . esc($sub) . '</div>' : '') . '</div>';

$cards = '';
$cards .= $card('Requests', (string) ($summary['requests'] ?? $total));
$cards .= $card('Failures', (string) round((float) ($summary['failed_rate'] ?? 0) * 100) . '%', 'HTTP errors');
$cards .= $card('Max concurrent users', (string) ($summary['max_vus'] ?? '?'));
$cards .= $card('Answers recorded', (string) $total, $prose . ' prose / ' . $tool . ' tool');
$cards .= $card('Median latency (p50)', fmtMs((float) ($lat['med'] ?? 0)), '50% faster than this');
$cards .= $card('p95 latency', fmtMs((float) ($lat['p95'] ?? 0)), '95% faster than this');
$cards .= $card('p99 latency', fmtMs((float) ($lat['p99'] ?? 0)), '99% faster than this');
$cards .= $card('Max latency', fmtMs((float) ($lat['max'] ?? 0)), 'the single slowest request');
$cards .= $card('Avg tokens / turn', (string) $avgTokens, 'prompt + tools + reply');
$cards .= $card('Tokens generated', number_format($genSum), 'output tokens, all turns');
$cards .= $card('Avg output / turn', (string) $avgGen, 'generated tokens per reply');
$cards .= $card('Output throughput', number_format($genThroughput, 1) . ' tok/s', 'generated tokens per request-second');

$toolList = '';
foreach ($toolNames as $name => $n) {
    $toolList .= '<li><code>' . esc($name) . '</code> &times; ' . $n . '</li>';
}
$toolList = $toolList ?: '<li>none observed</li>';

$transcript = '';
foreach ($entries as $e) {
    $search = esc(mb_strtolower($e['q'] . ' ' . $e['a']));
    $finClass = $e['type'] === 'tool' ? 'b-tool' : 'b-prose';
    $transcript .= '<article class="qa" data-type="' . $e['type'] . '" data-text="' . $search . '">'
        . '<div class="qh"><span class="meta">vu ' . $e['vu'] . ' &middot; iter ' . $e['iter'] . '</span>'
        . '<span class="badge b-lat">' . fmtMs($e['ms']) . '</span>'
        . '<span class="badge b-tok">' . esc($e['tokens']) . ' tok</span>'
        . '<span class="badge b-tok">' . esc($e['gen']) . ' out</span>'
        . '<span class="badge ' . $finClass . '">' . esc($e['finish']) . '</span></div>'
        . '<div class="q"><span class="tag">Q</span>' . esc($e['q']) . '</div>'
        . '<div class="a"><span class="tag">A</span><span class="atext">' . esc($e['a']) . '</span></div>'
        . '</article>';
}

$css = <<<'CSS'
:root{--bg:#f6f7f9;--fg:#1f2937;--mut:#6b7280;--line:#e5e7eb;--card:#fff;--accent:#2563eb}
*{box-sizing:border-box}body{margin:0;font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--fg);background:var(--bg)}
.wrap{max-width:1080px;margin:0 auto;padding:28px 20px 80px}
h1{font-size:26px;margin:0 0 4px}h2{font-size:19px;margin:38px 0 12px;border-bottom:1px solid var(--line);padding-bottom:6px}
.sub{color:var(--mut);margin:0 0 20px}
a{color:var(--accent)}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px}
.cv{font-size:24px;font-weight:650}.cl{color:var(--mut);font-size:13px;margin-top:2px}.cs{color:var(--mut);font-size:12px;margin-top:4px}
.notes{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:6px 20px}
.notes li{margin:10px 0}.notes b{color:var(--fg)}
.panels{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.panel{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:12px;margin:0}
.panel figcaption{font-weight:600;font-size:14px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.legend{font-weight:400;font-size:12px;color:var(--mut);display:flex;gap:10px}
.lg{display:inline-flex;align-items:center;gap:5px}.lg i{width:10px;height:10px;border-radius:2px;display:inline-block}
.chart{width:100%;height:auto;margin-top:6px}
.grid{stroke:var(--line);stroke-width:1}.ylab,.xlab{fill:var(--mut);font-size:11px}.nodata{fill:var(--mut);font-size:13px}
.controls{position:sticky;top:0;background:var(--bg);padding:12px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;z-index:5;border-bottom:1px solid var(--line)}
.controls input{flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px}
.controls button{padding:8px 14px;border:1px solid var(--line);background:var(--card);border-radius:8px;cursor:pointer;font-size:13px}
.controls button.on{background:var(--accent);color:#fff;border-color:var(--accent)}
.count{color:var(--mut);font-size:13px}
.qa{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin:12px 0}
.qh{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
.meta{color:var(--mut);font-size:12px;margin-right:auto}
.badge{font-size:11px;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3}
.b-lat{background:#ecfdf5;color:#065f46}.b-tok{background:#f3f4f6;color:#374151}
.b-prose{background:#eff6ff;color:#1e40af}.b-tool{background:#fef3c7;color:#92400e}
.q{font-weight:600;margin:4px 0}.a{margin:6px 0 0;color:#111827}
.tag{display:inline-block;font-size:11px;font-weight:700;color:var(--mut);margin-right:8px}
.atext{white-space:pre-wrap}
.toolrow{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px 18px}
.toolrow ul{margin:6px 0;padding-left:20px}.toolrow code{background:#f3f4f6;padding:1px 6px;border-radius:5px}
@media(max-width:760px){.cards{grid-template-columns:repeat(2,1fr)}.panels{grid-template-columns:1fr}}
CSS;

$js = <<<'JS'
(function(){
  var input=document.getElementById('search');
  var buttons=document.querySelectorAll('.controls button');
  var items=Array.prototype.slice.call(document.querySelectorAll('.qa'));
  var count=document.getElementById('count');
  var filter='all';
  function apply(){
    var q=input.value.toLowerCase().trim();var shown=0;
    items.forEach(function(el){
      var okType=(filter==='all')||(el.getAttribute('data-type')===filter);
      var okText=!q||el.getAttribute('data-text').indexOf(q)!==-1;
      var show=okType&&okText;el.style.display=show?'':'none';if(show)shown++;
    });
    count.textContent=shown+' / '+items.length+' shown';
  }
  input.addEventListener('input',apply);
  buttons.forEach(function(b){b.addEventListener('click',function(){
    buttons.forEach(function(x){x.classList.remove('on');});b.classList.add('on');
    filter=b.getAttribute('data-filter');apply();
  });});
  apply();
})();
JS;

$notes = <<<'HTML'
<ul>
  <li><b>prose + tool can exceed the request count.</b> A single model response can contain <i>both</i> text and a tool call, so the two counts overlap &mdash; they are not a clean split of the total. The transcript filters by finish reason instead.</li>
  <li><b>Why latency climbs.</b> One GPU keeps roughly 4&ndash;8 generations running at once (the KV-cache ceiling). Past that, requests <i>queue</i>: median (p50) is the typical wait, p95/p99 are the slow tail (95%/99% of requests were faster), and max is the single worst request &mdash; not the same as p99. Nothing fails &mdash; it just waits.</li>
  <li><b>~2,000 tokens per turn.</b> Every model turn re-sends the system prompt (~1,630 tok) + the tool definitions (~810) + the question + the answer. Rule-answered questions cost 0 tokens; these model-only questions are the paid path.</li>
  <li><b>Finish reason.</b> <code>stop</code> = the model wrote a prose answer. <code>tool_calls</code> = it asked to run a tool (<code>search_properties</code> / <code>get_property</code> / <code>save_lead</code>) before answering &mdash; exactly what the comparison/recommendation questions are meant to trigger.</li>
  <li><b>Tool rows show a request, not an answer.</b> The harness sends one request per question and never runs the tool, so a <code>tool_calls</code> row shows the tool the model asked for plus its arguments. The final prose answer needs a second round (run the tool, send the result back) &mdash; that round is the Laravel app's job, not this GPU-only test.</li>
  <li><b>Generated vs prompt tokens.</b> &ldquo;Tokens generated&rdquo; counts only the model's output (completion tokens); the ~2,000-token prompt is input re-sent each turn. Output is what the GPU decodes token-by-token, so it drives the generation speed.</li>
  <li><b>failed_rate 0%.</b> No HTTP errors: every request returned a valid model response, even at 100 concurrent users.</li>
  <li><b>p99 in summary.json.</b> The first run omitted p99 (k6's default summary stats leave it out); the script now sets <code>summaryTrendStats</code> so it appears.</li>
</ul>
HTML;

$why = <<<'HTML'
<p>These questions are deliberately the ones that <b>reach the AI</b>. The assistant answers in a ladder &mdash;
lead capture &rarr; message cap &rarr; repeat guard &rarr; QuickAnswers &rarr; search rules &rarr; off-topic gate &rarr; <b>model</b>.
Greetings, price/size facts, plain searches (&ldquo;2 bed in Marina under 2M&rdquo;), process (&ldquo;how does buying work?&rdquo;),
deferrals (&ldquo;is the price negotiable?&rdquo;), leads and off-topic are all handled by rules for free. Only judgement, opinion,
creative and comparison/recommendation questions reach the model &mdash; so that is what this pool contains. A few questions
deliberately offer a name and phone number (to trigger <code>save_lead</code>) or name a listing slug (to trigger
<code>get_property</code>), so the run exercises all three tools, not just <code>search_properties</code>.</p>
HTML;

$finished = esc($summary['finished_at'] ?? '');
$target = esc($summary['target'] ?? '');
$poolN = (int) ($summary['questions_in_pool'] ?? 0);

$html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>BRProperty &mdash; Model Load Test Report</title>'
    . '<style>' . $css . '</style></head><body><div class="wrap">'
    . '<h1>Model Load Test Report</h1>'
    . '<p class="sub">Direct-to-vLLM ramp &middot; ' . $poolN . ' model-only questions &middot; finished ' . $finished
    . ' &middot; target <code>' . $target . '</code></p>'

    . '<h2>Summary</h2><div class="cards">' . $cards . '</div>'

    . '<h2>What the numbers mean</h2><div class="notes">' . $notes . '</div>'

    . '<h2>Why these questions</h2>' . $why

    . '<h2>Tool calls observed</h2><div class="toolrow"><ul>' . $toolList . '</ul></div>'

    . '<h2>Load &amp; GPU panels</h2>'
    . '<p class="sub">Rendered from the local Prometheus for this run window. Live dashboard: '
    . '<a href="' . GRAFANA . '" target="_blank" rel="noopener">Grafana &rarr;</a> (set the time range to the run).</p>'
    . '<div class="panels">' . $charts . '</div>'

    . '<h2>Q&amp;A transcript</h2>'
    . '<div class="controls">'
    . '<input id="search" type="search" placeholder="Search questions and answers&hellip;">'
    . '<button data-filter="all" class="on">All</button>'
    . '<button data-filter="prose">Prose</button>'
    . '<button data-filter="tool">Tool calls</button>'
    . '<span class="count" id="count"></span>'
    . '</div>'
    . '<div id="transcript">' . $transcript . '</div>'

    . '<script>' . $js . '</script>'
    . '</div></body></html>';

file_put_contents(OUT, $html);

echo 'Parsed ' . $total . ' answers (' . $prose . ' prose / ' . $tool . ' tool), avg ' . $avgTokens . " tok/turn\n";
echo 'Tool calls: ' . (implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($toolNames), $toolNames)) ?: 'none') . "\n";
echo 'Wrote ' . OUT . ' (' . number_format(strlen($html)) . " bytes)\n";
echo "Open: http://localhost:8000/load-test-report.html\n";
