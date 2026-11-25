import http from 'k6/http';

export const BASE = __ENV.BASE_URL || 'http://localhost:8000';
export const MODE = __ENV.MODE || 'async';
export const RESULTS_DIR = __ENV.RESULTS_DIR || '/results';
export const INSTANCES = (__ENV.INSTANCES || 'app1,app2,app3').split(',').map((name) => name.trim()).filter((name) => name !== '');
export const API = `${BASE}/api/v4/${MODE}`;
export const SYNC = `${BASE}/api/v4/sync`;
export const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

export function login(email, password) {
  const response = http.post(`${SYNC}/auth/login`, JSON.stringify({ email, password }), { headers: JSON_HEADERS });
  const token = response.json('data.access_token');
  if (!token) {
    throw new Error(`Login failed for ${email} with status ${response.status}`);
  }
  return token;
}

export function authorized(token) {
  return { ...JSON_HEADERS, Authorization: `Bearer ${token}` };
}

export function createProduct(token, label, stock) {
  const response = http.post(
    `${SYNC}/products`,
    JSON.stringify({ name: `Scale ${label} ${Date.now()}`, description: 'k6 scale test', price: 5, cost: 1, stock, category: 'loadtest' }),
    { headers: authorized(token) },
  );
  const id = response.json('data.id');
  if (!id) {
    throw new Error(`Product creation failed with status ${response.status}`);
  }
  return id;
}

export function servedBy(response) {
  return response.headers['X-Served-By'] || 'none';
}

export function withoutSetup(data) {
  const copy = Object.assign({}, data);
  delete copy.setup_data;
  return copy;
}

export function share(count, total) {
  return total === 0 ? '0.0' : ((count / total) * 100).toFixed(1);
}

export function counted(data, metric) {
  const entry = data.metrics[metric];
  return entry && entry.values && entry.values.count ? entry.values.count : 0;
}
