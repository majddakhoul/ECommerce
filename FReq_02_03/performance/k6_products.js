import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

const responseTime = new Trend('products_response_time', true);
export const options = { vus: Number(__ENV.VUS || 10), duration: __ENV.DURATION || '30s', thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<500', 'p(99)<1000'] } };
export default function () {
  const response = http.get(`${__ENV.BASE_URL || 'http://localhost:8000'}/api/v1/products?per_page=15`, { headers: { Accept: 'application/json', Authorization: `Bearer ${__ENV.TOKEN || ''}` } });
  responseTime.add(response.timings.duration);
  check(response, { 'products endpoint returns success': r => r.status === 200, 'etag is present': r => Boolean(r.headers.ETag) });
}
