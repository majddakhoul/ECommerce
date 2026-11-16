import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const WRITE_API = `${BASE}/api/v2`;
const READ_API = `${BASE}/api/v3`;
const ROUNDS = Number(__ENV.ROUNDS || 200);
const EXPECT_STALE = (__ENV.EXPECT_STALE || 'false') === 'true';

const staleDetail = new Counter('stale_detail_reads');
const staleList = new Counter('stale_list_reads');
const staleStock = new Counter('stale_stock_reads');

export const options = {
  scenarios: {
    sequential: { executor: 'per-vu-iterations', vus: 1, iterations: 1, maxDuration: '10m' },
  },
  thresholds: EXPECT_STALE
    ? { stale_detail_reads: ['count>0'] }
    : { stale_detail_reads: ['count==0'], stale_list_reads: ['count==0'], stale_stock_reads: ['count==0'] },
};

const headers = (token) => ({ headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` } });

export function setup() {
  const login = http.post(`${WRITE_API}/auth/login`, JSON.stringify({ email: 'admin@example.com', password: 'password' }), headers(''));
  const token = login.json('data.access_token');
  const name = `Consistency ${Date.now()}`;

  const created = http.post(`${WRITE_API}/products`, JSON.stringify({ name, price: 1, stock: 1, category: 'consistency' }), headers(token));
  check(created, { 'product created': (r) => r.status === 201 });

  return { token, id: created.json('data.id'), name };
}

export default function (data) {
  for (let round = 1; round <= ROUNDS; round += 1) {
    const update = http.put(`${WRITE_API}/products/${data.id}`, JSON.stringify({ price: round, stock: round }), headers(data.token));
    check(update, { 'update ok': (r) => r.status === 200 });

    const detail = http.get(`${READ_API}/products/${data.id}`, headers(data.token));
    if (Number(detail.json('data.price')) !== round) {
      staleDetail.add(1);
    }
    if (Number(detail.json('data.stock')) !== round) {
      staleStock.add(1);
    }

    const list = http.get(`${READ_API}/products?search=${encodeURIComponent(data.name)}`, headers(data.token));
    const row = (list.json('data') || []).find((item) => item.id === data.id);
    if (!row || Number(row.price) !== round) {
      staleList.add(1);
    }
  }
}
