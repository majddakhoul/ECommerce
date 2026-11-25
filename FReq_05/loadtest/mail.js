import http from 'k6/http';
import { sleep } from 'k6';

const MAILPIT = __ENV.MAILPIT_URL || 'http://localhost:8025';
const WAIT = Number(__ENV.WAIT_SECONDS || 30);
const PAGE = 200;

export const options = {
  scenarios: {
    audit: { executor: 'shared-iterations', vus: 1, iterations: 1, maxDuration: '10m' },
  },
};

function fetchAll() {
  const messages = [];
  let start = 0;
  let total = 0;
  do {
    const response = http.get(`${MAILPIT}/api/v1/messages?start=${start}&limit=${PAGE}`);
    const body = response.json();
    total = body.total || 0;
    (body.messages || []).forEach((message) => messages.push(message));
    start += PAGE;
  } while (start < total);
  return messages;
}

export default function () {
  sleep(WAIT);

  const messages = fetchAll();
  const seen = {};
  const duplicates = [];

  messages.forEach((message) => {
    const recipients = (message.To || []).map((entry) => entry.Address).join(',');
    const key = `${recipients}|${message.Subject}`;
    seen[key] = (seen[key] || 0) + 1;
    if (seen[key] === 2) {
      duplicates.push(key);
    }
  });

  console.log(`messages: ${messages.length}, distinct: ${Object.keys(seen).length}, duplicated: ${duplicates.length}`);
  duplicates.slice(0, 20).forEach((key) => console.log(`duplicate: ${key}`));

  if (messages.length === 0) {
    throw new Error('No mail reached Mailpit. Set MAIL_MAILER=smtp, start the mail profile and place orders first');
  }

  if (duplicates.length > 0) {
    throw new Error(`${duplicates.length} mails were delivered more than once`);
  }
}
