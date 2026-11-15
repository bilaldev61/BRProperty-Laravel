// k6 load test: replays the real captured payload (payload.json) straight at the
// vLLM OpenAI endpoint, ramping 1 -> 8 -> 50 -> 100 users. Run from the Laravel
// laptop; metrics go to the isolated local Prometheus (docker-compose.yml, :9091):
//   $env:K6_PROMETHEUS_RW_SERVER_URL="http://localhost:9091/api/v1/write"
//   $env:K6_PROMETHEUS_RW_TREND_STATS="p(50),p(95),p(99)"  # also export p50/p95
//   k6 run --out experimental-prometheus-rw k6/load.js
// Override target: VLLM_URL=http://<host>:8000/v1/chat/completions

import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend, Counter } from 'k6/metrics';

const payload = new SharedArray('payload', () => [JSON.parse(open('./payload.json'))]);

const replyLatency = new Trend('reply_latency_ms', true);
const replies = new Counter('replies_ok');

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
    http_req_failed: ['rate<0.5'], // alert if more than half the requests fail
  },
};

const URL = __ENV.VLLM_URL || 'http://10.30.141.214:8000/v1/chat/completions';
const KEY = __ENV.VLLM_KEY || 'local'; // vLLM ignores the key, but the header must exist

export default function () {
  const res = http.post(URL, JSON.stringify(payload[0]), {
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${KEY}` },
    timeout: '120s', // a queued 14B turn can take a while under load
  });

  replyLatency.add(res.timings.duration);

  const ok = check(res, {
    'status 200': (r) => r.status === 200,
    'has a reply': (r) => {
      try { return !!r.json('choices.0.message'); } catch (e) { return false; }
    },
  });
  if (ok) replies.add(1);

  sleep(1); // short think time between a user's questions
}