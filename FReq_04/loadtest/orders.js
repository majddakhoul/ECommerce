import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const MODE = __ENV.MODE || 'async';
const LABEL = __ENV.LABEL || `${MODE}-run`;
const RESULTS_DIR = __ENV.RESULTS_DIR || '/results';
const API = `${BASE}/api/v4/${MODE}`;
const ADMIN = `${BASE}/api/v4/sync`;

const orderLatency = new Trend('latency_place_order', true);
const created = new Counter('orders_created');
const rejected = new Counter('orders_rejected');

export const options = {
  scenarios: {
    orders: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 10),
      duration: __ENV.DURATION || '45s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

const json = { 'Content-Type': 'application/json', Accept: 'application/json' };

export function setup() {
  const login = http.post(`${ADMIN}/auth/login`, JSON.stringify({ email: 'admin@example.com', password: 'password' }), { headers: json });
  const token = login.json('data.access_token');
  const headers = { ...json, Authorization: `Bearer ${token}` };

  const product = http.post(
    `${ADMIN}/products`,
    JSON.stringify({ name: `Load ${LABEL} ${Date.now()}`, description: 'k6', price: 5, cost: 1, stock: 1000000, category: 'loadtest' }),
    { headers },
  );

  return { token, productId: product.json('data.id') };
}

export default function (data) {
  const headers = { ...json, Authorization: `Bearer ${data.token}` };
  const response = http.post(
    `${API}/orders`,
    JSON.stringify({ items: [{ product_id: data.productId, quantity: 1 }] }),
    { headers, tags: { mode: MODE } },
  );

  orderLatency.add(response.timings.duration);
  if (response.status === 201) {
    created.add(1);
  } else {
    rejected.add(1);
  }
  check(response, { 'order created': (r) => r.status === 201 });
}

export function handleSummary(data) {
  const published = Object.assign({}, data);
  delete published.setup_data;
  const out = {};
  out[`${RESULTS_DIR}/${LABEL}.json`] = JSON.stringify(published, null, 2);
  out.stdout = `${LABEL}: p50=${data.metrics.latency_place_order.values.med.toFixed(1)}ms p95=${data.metrics.latency_place_order.values['p(95)'].toFixed(1)}ms orders=${data.metrics.orders_created ? data.metrics.orders_created.values.count : 0}\n`;
  return out;
}
