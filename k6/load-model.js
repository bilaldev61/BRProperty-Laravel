// Model-only load test: hits the vLLM OpenAI endpoint directly (no Laravel, no
// rate limiter) so the numbers reflect the GPU alone. Questions come from
// model-questions.json (only ones the rule ladder lets reach the model); the
// real system prompt + tools come from payload.json. Every Q&A is logged.
//
// Run from the Laravel laptop:
//   $env:K6_PROMETHEUS_RW_SERVER_URL="http://localhost:9091/api/v1/write"
//   $env:K6_PROMETHEUS_RW_TREND_STATS="p(50),p(95),p(99)"
//   Remove-Item k6/replies.log -ErrorAction SilentlyContinue  # k6 appends
//   k6 run --out experimental-prometheus-rw --log-format=raw --log-output=file=k6/replies.log k6/load-model.js
// Override target: VLLM_URL=http://<host>:8000/v1/chat/completions

import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend, Counter } from 'k6/metrics';

const template = new SharedArray('template', () => [JSON.parse(open('./payload.json'))]);
const questions = new SharedArray('questions', () => JSON.parse(open('./model-questions.json')));

const latency = new Trend('model_latency_ms', true);
const repliesOk = new Counter('model_replies_ok');
const proseReplies = new Counter('model_prose_replies');
const toolReplies = new Counter('model_tool_replies');
const promptTokens = new Counter('model_prompt_tokens');
const genTokens = new Counter('model_completion_tokens');
const totalTokens = new Counter('model_total_tokens');

export const options = {
  scenarios: {
    ramp_users: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '30s', target: 1 },    // baseline: one user
        { duration: '30s', target: 8 },    // ~the KV-cache concurrency ceiling
        { duration: '40s', target: 50 },   // well past it: watch the queue grow
        { duration: '60s', target: 100 },  // saturation
        { duration: '20s', target: 0 },    // drain
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.5'],
  },
  // k6 omits p99 by default.
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
};

const URL = __ENV.VLLM_URL || 'http://10.55.134.214:8000/v1/chat/completions';
const KEY = __ENV.VLLM_KEY || 'local'; // vLLM ignores the key, but the header must exist

function bodyFor(question) {
  const base = template[0];
  return {
    ...base,
    messages: [base.messages[0], { role: 'user', content: question }],
  };
}

function answerText(message) {
  if (!message) return '(no message)';
  const parts = [];
  if (message.content && message.content.trim() !== '') parts.push(message.content.trim());
  if (Array.isArray(message.tool_calls) && message.tool_calls.length > 0) {
    parts.push('[tool_call] ' + message.tool_calls
      .map((t) => `${t.function.name}(${t.function.arguments})`)
      .join(', '));
  }
  return parts.length ? parts.join('\n') : '(empty reply)';
}

export default function () {
  const question = questions[Math.floor(Math.random() * questions.length)];

  const res = http.post(URL, JSON.stringify(bodyFor(question)), {
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${KEY}` },
    timeout: '120s', // a queued 14B turn can take a while under load
  });

  latency.add(res.timings.duration);

  let message = null;
  let tokens = '';
  let gen = '';
  let finish = '';
  try {
    message = res.json('choices.0.message');
    finish = res.json('choices.0.finish_reason') || '';
    const usage = res.json('usage');
    if (usage && usage.total_tokens) {
      tokens = String(usage.total_tokens);
      totalTokens.add(usage.total_tokens);
      if (usage.prompt_tokens) promptTokens.add(usage.prompt_tokens);
      if (usage.completion_tokens) { gen = String(usage.completion_tokens); genTokens.add(usage.completion_tokens); }
    }
  } catch (e) { /* non-JSON or error body: leave placeholders */ }

  const ok = check(res, {
    'status 200': (r) => r.status === 200,
    'has a reply': () => !!message,
  });
  if (ok) repliesOk.add(1);

  const isProse = !!(message && message.content && message.content.trim() !== '');
  const isTool = !!(message && Array.isArray(message.tool_calls) && message.tool_calls.length > 0);
  if (isProse) proseReplies.add(1);
  if (isTool) toolReplies.add(1);

  // One atomic log call so concurrent VUs never interleave a block.
  console.log(
    `=== vu ${__VU} iter ${__ITER} | at ${Math.floor(Date.now() / 1000)} | ${Math.round(res.timings.duration)}ms | tokens ${tokens || '?'} | gen ${gen || '?'} | ${finish || res.status} ===\n` +
    `Q: ${question}\n` +
    `A: ${answerText(message)}\n`
  );

  sleep(1); // short think time between a user's questions
}

export function handleSummary(data) {
  const m = data.metrics;
  const val = (name, key) => (m[name] && m[name].values ? m[name].values[key] : undefined);

  const summary = {
    finished_at: new Date().toISOString(),
    target: URL,
    questions_in_pool: questions.length,
    requests: val('http_reqs', 'count'),
    failed_rate: val('http_req_failed', 'rate'),
    replies_ok: val('model_replies_ok', 'count'),
    prose_replies: val('model_prose_replies', 'count'),
    tool_replies: val('model_tool_replies', 'count'),
    max_vus: val('vus', 'max'),
    model_latency_ms: {
      avg: val('model_latency_ms', 'avg'),
      med: val('model_latency_ms', 'med'),
      p95: val('model_latency_ms', 'p(95)'),
      p99: val('model_latency_ms', 'p(99)'),
      max: val('model_latency_ms', 'max'),
    },
    tokens: {
      prompt: val('model_prompt_tokens', 'count'),
      generated: val('model_completion_tokens', 'count'),
      total: val('model_total_tokens', 'count'),
    },
  };

  const lines = [
    '',
    '================ MODEL LOAD TEST ================',
    `target            : ${summary.target}`,
    `requests          : ${summary.requests}`,
    `failed rate       : ${summary.failed_rate}`,
    `replies ok        : ${summary.replies_ok} (prose ${summary.prose_replies}, tool ${summary.tool_replies})`,
    `max concurrent    : ${summary.max_vus}`,
    `latency ms        : avg ${Math.round(summary.model_latency_ms.avg || 0)} | med ${Math.round(summary.model_latency_ms.med || 0)} | p95 ${Math.round(summary.model_latency_ms.p95 || 0)} | p99 ${Math.round(summary.model_latency_ms.p99 || 0)} | max ${Math.round(summary.model_latency_ms.max || 0)}`,
    `tokens            : prompt ${summary.tokens.prompt} | generated ${summary.tokens.generated} | total ${summary.tokens.total}`,
    'transcript        : k6/replies.log',
    '=================================================',
    '',
  ].join('\n');

  return {
    'k6/summary.json': JSON.stringify(summary, null, 2),
    stdout: lines,
  };
}