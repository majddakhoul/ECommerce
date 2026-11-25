import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { API, INSTANCES, RESULTS_DIR, MODE, authorized, createProduct, counted, login, servedBy, share, withoutSetup } from './lib.js';

const TOTAL = Number(__ENV.TOTAL || 500);
const SECONDS = Number(__ENV.SECONDS || 120);
const WORKLOAD = __ENV.WORKLOAD || 'read';
const LABEL = __ENV.LABEL || `scale-${WORKLOAD}-${MODE}`;
const FAIL_RATE = __ENV.FAIL_RATE || '0.01';
const MIN_SHARE = Number(__ENV.MIN_SHARE || 0);

const served = new Counter('served');
const latency = new Trend('latency_request', true);

const thresholds = {
  http_req_failed: [`rate<${FAIL_RATE}`],
  checks: ['rate>0.95'],
};
INSTANCES.concat(['none']).forEach((name) => {
  thresholds[`served{instance:${name}}`] = ['count>=0'];
});

export const options = {
  scenarios: {
    distribution: {
      executor: 'constant-arrival-rate',
      rate: TOTAL,
      timeUnit: `${SECONDS}s`,
      duration: `${SECONDS}s`,
      preAllocatedVUs: Number(__ENV.PRE_VUS || 20),
      maxVUs: Number(__ENV.MAX_VUS || 100),
    },
  },
  thresholds,
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

export function setup() {
  const token = login('admin@example.com', 'password');
  if (WORKLOAD !== 'order') {
    return { token };
  }
  return { token, productId: createProduct(token, LABEL, 1000000) };
}

export default function (data) {
  let response;

  if (WORKLOAD === 'order') {
    response = http.post(
      `${API}/orders`,
      JSON.stringify({ items: [{ product_id: data.productId, quantity: 1 }] }),
      { headers: authorized(data.token), tags: { workload: 'order' } },
    );
    check(response, { 'order created': (r) => r.status === 201 });
  } else {
    response = http.get(`${API}/products`, { headers: authorized(data.token), tags: { workload: 'read' } });
    check(response, { 'products listed': (r) => r.status === 200 });
  }

  served.add(1, { instance: servedBy(response) });
  latency.add(response.timings.duration);
}

export function handleSummary(data) {
  const names = INSTANCES.concat(['none']);
  const total = names.reduce((sum, name) => sum + counted(data, `served{instance:${name}}`), 0);
  const rows = names.map((name) => {
    const count = counted(data, `served{instance:${name}}`);
    return { instance: name, requests: count, share: share(count, total) };
  });

  const failedMetric = data.metrics.http_req_failed;
  const failedRate = failedMetric && failedMetric.values && failedMetric.values.rate ? failedMetric.values.rate : 0;

  const lines = rows.map((row) => `${row.instance.padEnd(8)} ${String(row.requests).padStart(6)}  ${row.share.padStart(6)}%`);
  const text = [
    `${LABEL}: ${total} requests over ${SECONDS}s, workload=${WORKLOAD}, mode=${MODE}`,
    'instance  requests   share',
    ...lines,
    '',
    `failed requests: ${(failedRate * 100).toFixed(2)}%`,
  ].join('\n');

  const starved = MIN_SHARE > 0 ? rows.filter((row) => row.instance !== 'none' && Number(row.share) < MIN_SHARE) : [];
  const verdict = starved.length === 0 ? 'distribution check: passed\n' : `distribution check: failed for ${starved.map((row) => row.instance).join(', ')}\n`;

  const out = {};
  out[`${RESULTS_DIR}/${LABEL}.json`] = JSON.stringify({ rows, summary: withoutSetup(data) }, null, 2);
  out.stdout = `${text}${verdict}`;
  return out;
}
