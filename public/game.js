const token = document.body.dataset.token;
const $ = (id) => document.getElementById(id);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const pad = (n) => String(n).padStart(2, '0');

let current = null; // chat currently on screen, as returned by the server
let deadline = 0;
let timerId = null;
let run = 0;        // bumped on every decision so stale typing loops stop
let busy = false;

async function api(action, data) {
  const url = `api.php?action=${action}&s=${encodeURIComponent(token)}`;
  const res = await fetch(url, data ? { method: 'POST', body: new URLSearchParams(data) } : {});
  return res.json();
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function setButtons(enabled) {
  document.querySelectorAll('[data-verdict]').forEach((b) => (b.disabled = !enabled));
}

async function nextChat() {
  const state = await api('state');
  if (state.finished) return showResults(state);

  current = state;
  const myRun = ++run;
  const { people, messages } = state.chat;

  $('case-no').textContent = `CASE ${pad(state.index + 1)}/${pad(state.total)}`;
  $('case-id').textContent = `INTERCEPT #${state.chat.id}`;
  const chat = $('chat');
  chat.replaceChildren();
  setButtons(true);
  startTimer(state.remaining);

  for (const [who, text] of messages) {
    const side = who === 1 ? ' right' : '';
    const typing = el('div', 'typing' + side, `${people[who]} is typing...`);
    chat.append(typing);
    chat.scrollTop = chat.scrollHeight;

    await sleep(Math.min(3500, 1200 + text.length * 45));
    if (myRun !== run) return;

    const msg = el('div', 'msg' + side);
    msg.append(el('span', 'who', people[who]), text);
    typing.replaceWith(msg);
    chat.scrollTop = chat.scrollHeight;
  }
}

function startTimer(seconds) {
  clearInterval(timerId);
  deadline = Date.now() + seconds * 1000;
  tick();
  timerId = setInterval(tick, 250);
}

function tick() {
  const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
  $('timer').textContent = left;
  $('timer').classList.toggle('low', left <= 5);
  if (left === 0) decide('timeout');
}

async function decide(verdict) {
  if (busy || !current) return;
  busy = true;
  run++;
  clearInterval(timerId);
  setButtons(false);

  const overlay = $('stamp');
  overlay.firstElementChild.className = 'stamp ' + verdict;
  overlay.firstElementChild.textContent = { flag: 'Flagged', clear: 'Cleared', timeout: 'Time out' }[verdict];
  overlay.hidden = false;

  // If the server rejects (e.g. double submit), nextChat() simply resyncs.
  await Promise.all([api('decide', { idx: current.index, verdict }).catch(() => null), sleep(900)]);

  overlay.hidden = true;
  current = null;
  busy = false;
  nextChat();
}

const OUTCOMES = {
  correct: (r) => [r.criminal ? 'Arrest confirmed' : 'Correctly cleared', 'good'],
  wrongful: () => ['Wrongful flag', 'bad'],
  missed: () => ['Criminals missed', 'bad'],
  timeout: () => ['No decision', 'none'],
};

function showResults(r) {
  clearInterval(timerId);
  $('timer').textContent = '';
  $('game').hidden = true;

  const box = $('results');
  box.replaceChildren();
  box.hidden = false;

  box.append(el('h2', '', 'End of shift'));

  const summary = el('div', 'paper');
  summary.append(
    el('div', '', 'Score'),
    el('div', 'score', r.score),
    el('div', '', `${r.tally.correct}/${r.reports.length} correct · rank #${r.rank} of ${r.players}`),
    el('div', 'grade ' + (r.tally.correct >= r.reports.length * 0.7 ? 'green' : 'red'), r.grade),
  );
  box.append(summary);

  const evaluation = el('div', 'paper');
  evaluation.append(el('h3', '', 'Supervisor evaluation'), el('p', '', r.evaluation));
  if (r.name) {
    evaluation.append(el('p', '', `Recorded in the score table as ${r.name}.`));
  } else {
    const form = el('form', 'name-form');
    const input = el('input');
    input.maxLength = 16;
    input.placeholder = 'Officer name';
    input.required = true;
    const error = el('p', 'error');
    form.append(input, el('button', 'btn btn-stamp', 'Sign'));
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const res = await api('name', { name: input.value.trim() });
      if (res.ok) location.href = `board.php?s=${encodeURIComponent(token)}`;
      else error.textContent = res.error;
    });
    evaluation.append(el('p', '', 'Sign your report to enter the score table:'), form, error);
  }
  box.append(evaluation);

  box.append(el('h2', '', 'Police reports'));
  for (const report of r.reports) {
    const [label, tone] = OUTCOMES[report.outcome](report);
    const card = el('div', 'paper report');
    card.append(
      el('div', 'outcome ' + tone, `${label} · +${report.points}`),
      el('div', 'people', `#${report.id} · ${report.people.join(' & ')}`),
      el('div', '', report.report),
    );
    box.append(card);
  }

  const again = el('a', 'btn btn-stamp', 'Start new shift');
  again.href = 'index.php';
  const board = el('a', 'btn btn-ghost', 'Score table');
  board.href = `board.php?s=${encodeURIComponent(token)}`;
  board.style.marginTop = '12px';
  box.append(again, board);
}

document.querySelectorAll('[data-verdict]').forEach((b) =>
  b.addEventListener('click', () => decide(b.dataset.verdict)),
);

if (document.body.dataset.rules === '1') {
  $('rules').hidden = false;
  $('start').addEventListener('click', () => {
    $('rules').hidden = true;
    nextChat();
  });
} else {
  nextChat();
}
