import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const API = `${BASE}/api/v2`;
const CONCURRENCY = Number(__ENV.CONCURRENCY || 10);

const created = new Counter('orders_created');
const conflicts = new Counter('orders_conflict');
const unexpected = new Counter('orders_unexpected');

export const options = {
  scenarios: {
    race: {
      executor: 'per-vu-iterations',
      vus: CONCURRENCY,
      iterations: 1,
      maxDuration: '60s',
    },
  },
  thresholds: {
    orders_created: ['count==1'],
    orders_conflict: [`count==${CONCURRENCY - 1}`],
    orders_unexpected: ['count==0'],
  },
};

const json = { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } };

const login = (email, password) => {
  const response = http.post(`${API}/auth/login`, JSON.stringify({ email, password }), json);
  return response.json('data.access_token');
};

const authorized = (token) => ({
  headers: { ...json.headers, Authorization: `Bearer ${token}` },
});

export function setup() {
  const adminToken = login('admin@example.com', 'password');
  const userToken = login('user@example.com', 'password');

  const product = http.post(
    `${API}/products`,
    JSON.stringify({ name: `Race product ${Date.now()}`, price: 10, stock: 1, category: 'race' }),
    authorized(adminToken),
  );
  check(product, { 'product created': (r) => r.status === 201 });

  return { userToken, productId: product.json('data.id') };
}

export default function (data) {
  const response = http.post(
    `${API}/orders`,
    JSON.stringify({ items: [{ product_id: data.productId, quantity: 1 }] }),
    authorized(data.userToken),
  );

  if (response.status === 201) {
    created.add(1);
  } else if (response.status === 409) {
    conflicts.add(1);
  } else {
    unexpected.add(1);
  }
}

export function teardown(data) {
  const product = http.get(`${API}/products/${data.productId}`, authorized(data.userToken));
  check(product, { 'stock is zero after the race': (r) => r.json('data.stock') === 0 });
}
