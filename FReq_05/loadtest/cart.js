import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { API, INSTANCES, RESULTS_DIR, authorized, counted, createProduct, login, servedBy, withoutSetup } from './lib.js';

const READS = Number(__ENV.READS || 8);
const ITERATIONS = Number(__ENV.ITERATIONS || 25);
const ACCOUNTS = [
  { email: 'admin@example.com', password: 'password' },
  { email: 'user@example.com', password: 'password' },
];

const cartReads = new Counter('cart_reads');
const cartMismatch = new Counter('cart_mismatch');

const thresholds = {
  cart_mismatch: ['count==0'],
  checks: ['rate>0.99'],
};
INSTANCES.forEach((name) => {
  thresholds[`cart_reads{instance:${name}}`] = ['count>=0'];
});

export const options = {
  scenarios: {
    carts: {
      executor: 'per-vu-iterations',
      vus: ACCOUNTS.length,
      iterations: ITERATIONS,
      maxDuration: __ENV.MAX_DURATION || '5m',
    },
  },
  thresholds,
};

export function setup() {
  const tokens = ACCOUNTS.map((account) => login(account.email, account.password));
  return { tokens, productId: createProduct(tokens[0], 'cart', 1000000) };
}

export default function (data) {
  const token = data.tokens[(__VU - 1) % data.tokens.length];
  const headers = authorized(token);
  const quantity = 1 + (__ITER % 5);

  http.del(`${API}/cart`, null, { headers });

  const added = http.post(`${API}/cart/items`, JSON.stringify({ product_id: data.productId, quantity }), { headers });
  check(added, { 'item added': (r) => r.status === 200 });

  for (let read = 0; read < READS; read += 1) {
    const response = http.get(`${API}/cart`, { headers });
    const item = (response.json('data.items') || [])[0];
    const matches = response.status === 200 && item && item.product_id === data.productId && item.quantity === quantity;

    cartReads.add(1, { instance: servedBy(response) });
    if (!matches) {
      cartMismatch.add(1);
    }
    check(response, { 'cart shows the added item': () => matches });
  }

  http.del(`${API}/cart`, null, { headers });
}

export function handleSummary(data) {
  const rows = INSTANCES.map((name) => `${name.padEnd(8)} ${String(counted(data, `cart_reads{instance:${name}}`)).padStart(6)} reads`);
  const mismatches = counted(data, 'cart_mismatch');
  const out = {};
  out[`${RESULTS_DIR}/cart.json`] = JSON.stringify(withoutSetup(data), null, 2);
  out.stdout = `cart consistency across instances\n${rows.join('\n')}\nmismatches: ${mismatches}\n`;
  return out;
}
