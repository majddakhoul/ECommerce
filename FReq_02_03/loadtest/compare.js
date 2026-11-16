import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const VERSION = __ENV.VERSION || 'v2';
const LABEL = __ENV.LABEL || `${VERSION}-run`;
const RESULTS_DIR = __ENV.RESULTS_DIR || '/results';
const API = `${BASE}/api/${VERSION}`;

const notModified = new Counter('responses_304');
const cacheHits = new Counter('cache_hits');
const listLatency = new Trend('latency_list', true);
const searchLatency = new Trend('latency_search', true);
const detailLatency = new Trend('latency_detail', true);
const categoryLatency = new Trend('latency_category', true);

export const options = {
  scenarios: {
    mixed: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 20),
      duration: __ENV.DURATION || '60s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

const TERMS = ['phone', 'laptop', 'wireless', 'premium', 'camera', 'jacket', 'compact', 'router'];
const CATEGORIES = ['electronics', 'clothing', 'books', 'home', 'sports', 'toys', 'garden', 'beauty'];

const json = { 'Content-Type': 'application/json', Accept: 'application/json' };

const pick = (values) => values[Math.floor(Math.random() * values.length)];

const etags = {};

const get = (url, token, metric, tag) => {
  const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };

  if (VERSION === 'v3' && etags[url]) {
    headers['If-None-Match'] = etags[url];
  }

  const response = http.get(url, { headers, tags: { endpoint: tag } });

  if (response.status === 304) {
    notModified.add(1);
  }
  if (response.headers['X-Cache'] === 'HIT') {
    cacheHits.add(1);
  }
  if (response.headers['Etag']) {
    etags[url] = response.headers['Etag'];
  }

  metric.add(response.timings.duration);
  check(response, { 'status is 200 or 304': (r) => r.status === 200 || r.status === 304 });

  return response;
};

export function setup() {
  const login = http.post(`${API}/auth/login`, JSON.stringify({ email: 'admin@example.com', password: 'password' }), { headers: json });
  const token = login.json('data.access_token');

  const page = http.get(`${API}/products?per_page=100`, { headers: { ...json, Authorization: `Bearer ${token}` } });
  const ids = (page.json('data') || []).map((product) => product.id);

  return { token, ids, total: page.json('meta.total') };
}

export default function (data) {
  const roll = Math.random();

  if (roll < 0.35) {
    const pageNumber = 1 + Math.floor(Math.random() * 20);
    get(`${API}/products?page=${pageNumber}`, data.token, listLatency, 'list');
  } else if (roll < 0.6) {
    get(`${API}/products?search=${pick(TERMS)}`, data.token, searchLatency, 'search');
  } else if (roll < 0.9) {
    get(`${API}/products/${pick(data.ids)}`, data.token, detailLatency, 'detail');
  } else {
    get(`${API}/products?category=${pick(CATEGORIES)}`, data.token, categoryLatency, 'category');
  }
}

const value = (metric, key) => (metric && metric.values[key] !== undefined ? Number(metric.values[key].toFixed(2)) : null);

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration;
  const summary = {
    label: LABEL,
    version: VERSION,
    vus: Number(__ENV.VUS || 20),
    requests: data.metrics.http_reqs.values.count,
    rps: value(data.metrics.http_reqs, 'rate'),
    failed_rate: value(data.metrics.http_req_failed, 'rate'),
    p50_ms: value(duration, 'med'),
    p95_ms: value(duration, 'p(95)'),
    p99_ms: value(duration, 'p(99)'),
    avg_ms: value(duration, 'avg'),
    cache_hits: data.metrics.cache_hits ? data.metrics.cache_hits.values.count : 0,
    not_modified: data.metrics.responses_304 ? data.metrics.responses_304.values.count : 0,
    by_endpoint: {
      list: value(data.metrics.latency_list, 'p(95)'),
      search: value(data.metrics.latency_search, 'p(95)'),
      detail: value(data.metrics.latency_detail, 'p(95)'),
      category: value(data.metrics.latency_category, 'p(95)'),
    },
  };

  const text = [
    `label        ${summary.label}`,
    `requests     ${summary.requests}`,
    `rps          ${summary.rps}`,
    `p50 / p95 / p99 (ms)  ${summary.p50_ms} / ${summary.p95_ms} / ${summary.p99_ms}`,
    `cache hits   ${summary.cache_hits}   304 responses ${summary.not_modified}`,
    `failed rate  ${summary.failed_rate}`,
    '',
  ].join('\n');

  return {
    stdout: text,
    [`${RESULTS_DIR}/${LABEL}.json`]: JSON.stringify(summary, null, 2),
  };
}
