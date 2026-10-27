// Admin UI for Theme settings > Baza danych (export / import / search & replace).
// Talks to app/DbTools/DatabaseTools.php over admin-ajax; long jobs run in short server steps.
const cfg = window.WCO_DB || {};

const el = (tag, props = {}, ...children) => {
  const node = Object.assign(document.createElement(tag), props);
  children.flat().forEach((c) => node.append(c instanceof Node ? c : document.createTextNode(String(c))));
  return node;
};

const fmtSize = (n) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

async function post(action, data = {}, files = {}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('nonce', cfg.nonce);
  Object.entries(data).forEach(([k, v]) => fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v === true ? '1' : v === false ? '' : v));
  Object.entries(files).forEach(([k, f]) => fd.append(k, f));
  const res = await fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
  let json;
  try {
    json = await res.json();
  } catch {
    throw new Error('Serwer zwrócił nieprawidłową odpowiedź (możliwy limit czasu lub pamięci).');
  }
  if (!json.success) {
    throw new Error((json.data && json.data.message) || 'Operacja nie powiodła się.');
  }
  return json.data;
}

// ---- search / replace pairs -------------------------------------------------------------------
function mountPairs(container, first = ['', '']) {
  const add = (search = '', replace = '') => {
    const row = el('div', { className: 'wco-db__pair' },
      el('input', { type: 'text', placeholder: 'Szukaj', value: search, className: 'regular-text', spellcheck: false }),
      el('span', {}, '→'),
      el('input', { type: 'text', placeholder: 'Zamień na', value: replace, className: 'regular-text', spellcheck: false }),
      el('button', { type: 'button', className: 'button-link', title: 'Usuń parę', textContent: '✕' }));
    row.querySelector('button').addEventListener('click', () => { if (container.children.length > 1) row.remove(); });
    container.append(row);
  };
  container.replaceChildren();
  add(...first);
  return {
    add,
    get: () => [...container.children].map((r) => [...r.querySelectorAll('input')].map((i) => i.value)).filter(([s]) => s !== ''),
    set: (first2) => { container.replaceChildren(); add(...first2); },
  };
}

// ---- progress + result -------------------------------------------------------------------------
function panel(section) {
  const box = section.querySelector('[data-progress]');
  const bar = box.querySelector('.wco-db__bar span');
  const msg = box.querySelector('.wco-db__msg');
  const out = box.querySelector('.wco-db__result');
  return {
    show: () => { box.hidden = false; bar.style.width = '0%'; msg.textContent = ''; out.replaceChildren(); },
    progress: (p, m) => { bar.style.width = `${p}%`; msg.textContent = m || ''; },
    out,
    error: (e) => { msg.textContent = ''; out.replaceChildren(el('div', { className: 'notice notice-error inline' }, el('p', {}, e.message || String(e)))); },
  };
}

function renderReport(out, r, { dryText } = {}) {
  const rows = Object.entries(r.report || {}).filter(([, v]) => v.cells || v.skipped || v.note);
  const cells = Object.values(r.report || {}).reduce((s, v) => s + v.cells, 0);
  const frag = [];
  if (r.dry) {
    frag.push(el('div', { className: 'notice notice-info inline' }, el('p', {}, `Próba: ${cells} komórek do zmiany w ${rows.filter(([, v]) => v.cells).length} tabelach. Nic nie zostało zapisane.`, dryText ? ` ${dryText}` : '')));
  } else if (r.mode === 'replace' || (r.report && Object.keys(r.report).length)) {
    frag.push(el('div', { className: 'notice notice-success inline' }, el('p', {}, `Zamieniono w ${cells} komórkach.`)));
  }
  if (rows.length) {
    frag.push(el('table', { className: 'widefat striped' },
      el('thead', {}, el('tr', {}, el('th', {}, 'Tabela'), el('th', {}, 'Wiersze'), el('th', {}, 'Komórki'), el('th', {}, 'Uwagi'))),
      el('tbody', {}, rows.map(([t, v]) => el('tr', {}, el('td', {}, t), el('td', {}, v.rows), el('td', {}, v.cells),
        el('td', {}, [v.skipped ? `pominięto ${v.skipped} (obiekty niestandardowych klas)` : '', v.note].filter(Boolean).join('; ')))))));
  }
  out.append(...frag);
}

function renderJobResult(out, r) {
  if (r.mode === 'import') {
    const s = r.stats;
    out.append(el('div', { className: `notice ${s.failed ? 'notice-warning' : 'notice-success'} inline` },
      el('p', {}, `Import zakończony: wykonano ${s.run} instrukcji, błędy: ${s.failed}, pominięte niedozwolone: ${s.blocked}.`,
        r.backup ? ` Kopia zapasowa: ${r.backup}.` : '')));
    if (r.blocked && r.blocked.length) {
      out.append(el('p', {}, 'Pominięte instrukcje (niedozwolone w imporcie):'), el('pre', { className: 'wco-db__pre' }, r.blocked.join('\n')));
    }
    if (r.errors && r.errors.length) {
      out.append(el('p', {}, 'Błędy MySQL:'), el('pre', { className: 'wco-db__pre' }, r.errors.join('\n')));
    }
  } else if (r.backup) {
    out.append(el('p', { className: 'description' }, `Kopia zapasowa przed zamianą: ${r.backup}.`));
  }
  renderReport(out, r);
  if (r.mode === 'import') {
    out.append(el('p', {}, el('a', { className: 'button', href: window.location.href }, 'Odśwież stronę')));
  }
}

async function runJob(section, params) {
  const ui = panel(section);
  ui.show();
  try {
    let r = await post('wco_db_start', params);
    ui.progress(r.progress, r.message);
    while (!r.done) {
      r = await post('wco_db_step', { job: r.id });
      ui.progress(r.progress, r.message);
    }
    ui.progress(100, 'Gotowe.');
    renderJobResult(ui.out, r);
  } catch (e) {
    ui.error(e);
  }
}

// ---- import --------------------------------------------------------------------------------------
function initImport(section) {
  const pick = section.querySelector('[data-step="pick"]');
  const opts = section.querySelector('[data-step="options"]');
  const fileInput = section.querySelector('[data-file]');
  const analysisTable = section.querySelector('[data-analysis-tables]');
  const pairs = mountPairs(section.querySelector('[data-pairs]'), ['', '']);
  const ui = panel(section);
  const opt = (name) => section.querySelector(`[data-opt="${name}"]`).checked;
  let upload = null;

  section.querySelector('[data-add-pair]').addEventListener('click', () => pairs.add());
  section.querySelector('[data-cancel]').addEventListener('click', () => { upload = null; opts.hidden = true; pick.hidden = false; fileInput.value = ''; });

  section.querySelector('[data-upload]').addEventListener('click', async (ev) => {
    const file = fileInput.files[0];
    if (!file) { alert('Wybierz plik .sql.'); return; }
    if (cfg.maxUpload && file.size > cfg.maxUpload) { alert(`Plik (${fmtSize(file.size)}) przekracza limit serwera (${fmtSize(cfg.maxUpload)}).`); return; }
    const btn = ev.currentTarget; btn.disabled = true; btn.textContent = 'Wczytuję…';
    try {
      upload = await post('wco_db_upload', {}, { sql: file });
      const a = upload.analysis;
      section.querySelector('.wco-db__file').replaceChildren(
        el('strong', {}, upload.name), ` (${fmtSize(upload.size)}) · instrukcji: ${a.statements} · zakres: ${a.header.scope || 'nieznany'}`,
        a.header.site ? ` · adres źródłowy: ${a.header.site}` : '', ` · prefiks: ${a.header.prefix || '?'}`,
        a.header.prefix && a.header.prefix !== upload.current_prefix ? ` (zostanie zamieniony na ${upload.current_prefix})` : '');
      const rows = Object.entries(a.tables);
      analysisTable.hidden = !rows.length;
      analysisTable.replaceChildren(
        el('thead', {}, el('tr', {}, el('th', {}, 'Tabela w pliku'), el('th', {}, 'Instrukcje'), el('th', {}, 'Wiersze'))),
        el('tbody', {}, rows.map(([t, v]) => el('tr', {}, el('td', {}, t), el('td', {}, v.statements), el('td', {}, v.rows)))));
      if (a.blocked.length) {
        analysisTable.after(el('div', { className: 'notice notice-warning inline', 'data-blocked': '' }, el('p', {}, `Plik zawiera ${a.blocked.length}+ instrukcji, które import pominie (niedozwolone), np.: ${a.blocked[0]}`)));
      }
      const from = a.header.site && a.header.site !== cfg.home ? a.header.site : '';
      pairs.set([from, from ? cfg.home : '']);
      pick.hidden = true; opts.hidden = false;
    } catch (e) { alert(e.message); }
    btn.disabled = false; btn.textContent = 'Wczytaj plik';
  });

  section.querySelector('[data-run-import]').addEventListener('click', async () => {
    if (!upload) return;
    const dry = opt('dry');
    const p = { token: upload.token, mode: 'import', pairs: pairs.get(), ci: opt('ci'), backup: opt('backup'), keep_settings: opt('keep_settings'), keep_user: opt('keep_user') };
    if (dry) {
      ui.show();
      try {
        const a = await post('wco_db_analyze', { token: upload.token, pairs: p.pairs, ci: p.ci });
        const total = Object.values(a.tables).reduce((s, v) => s + v.matches, 0);
        ui.progress(100, '');
        ui.out.append(el('div', { className: 'notice notice-info inline' }, el('p', {},
          `Próba: w pliku jest ${a.statements} instrukcji w ${Object.keys(a.tables).length} tabelach; szukany tekst występuje ${total} razy. Nic nie zostało zmienione. Odznacz „Próba”, aby wykonać import.`)));
        const rows = Object.entries(a.tables).filter(([, v]) => v.matches);
        if (rows.length) {
          ui.out.append(el('table', { className: 'widefat striped' },
            el('thead', {}, el('tr', {}, el('th', {}, 'Tabela'), el('th', {}, 'Wystąpienia'))),
            el('tbody', {}, rows.map(([t, v]) => el('tr', {}, el('td', {}, t), el('td', {}, v.matches))))));
        }
        if (a.blocked.length) ui.out.append(el('p', {}, 'Pominięte instrukcje:'), el('pre', { className: 'wco-db__pre' }, a.blocked.join('\n')));
      } catch (e) { ui.error(e); }
      return;
    }
    if (!window.confirm('Import nadpisze bieżącą bazę danych (poza zachowanymi elementami). Czy kopia zapasowa jest włączona i chcesz kontynuować?')) return;
    opts.hidden = true;
    await runJob(section, p);
  });
}

// ---- search & replace ------------------------------------------------------------------------------
function initReplace(section) {
  const pairs = mountPairs(section.querySelector('[data-pairs]'), [cfg.home, '']);
  const select = section.querySelector('[data-tables]');
  const opt = (name) => section.querySelector(`[data-opt="${name}"]`).checked;
  section.querySelector('[data-add-pair]').addEventListener('click', () => pairs.add());
  section.querySelector('[data-tables-all]').addEventListener('click', (e) => { e.preventDefault(); [...select.options].forEach((o) => { o.selected = true; }); });
  section.querySelector('[data-tables-none]').addEventListener('click', (e) => { e.preventDefault(); [...select.options].forEach((o) => { o.selected = false; }); });

  section.querySelector('[data-run-replace]').addEventListener('click', async () => {
    const p = pairs.get();
    const tables = [...select.selectedOptions].map((o) => o.value);
    if (!p.length) { alert('Podaj, czego szukać.'); return; }
    if (!tables.length) { alert('Wybierz co najmniej jedną tabelę.'); return; }
    const dry = opt('dry');
    if (!dry && !window.confirm('Zamiana zmieni dane w wybranych tabelach. Kontynuować?')) return;
    await runJob(section, { mode: 'replace', pairs: p, tables, ci: opt('ci'), guid: opt('guid'), backup: opt('backup'), dry });
  });
}

export default function initDbTools() {
  const root = document.getElementById('wco-db-tools');
  if (!root || !cfg.enabled) return;
  const imp = root.querySelector('#wco-import');
  const rep = root.querySelector('#wco-replace');
  if (imp) initImport(imp);
  if (rep) initReplace(rep);
}
