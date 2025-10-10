// apps.js — Modern UI 2025 helpers (vanilla, Bootstrap-friendly)

/* -------------------- tiny DOM utils -------------------- */
const qs  = (s, r = document) => r.querySelector(s);
const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
const on  = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts);

/* -------------------- nav active -------------------- */
export function setActiveNav(id) {
  try {
    qsa('.nav-link[data-nav]').forEach(a => {
      a.classList.toggle('active', a.dataset.nav === id);
      a.setAttribute('aria-current', a.dataset.nav === id ? 'page' : 'false');
    });
  } catch { /* noop */ }
}

/* -------------------- auto refresh (tableContainer) -------------------- */
/**
 * Auto refresh with:
 * - AbortController to avoid overlapping fetch
 * - pause on hidden tab to save CPU
 * - smooth swap (reduced-motion aware)
 * - optional callback rebinds (e.g., bindCopyUid)
 */
export function enableAutoRefresh(intervalMs = 10000, options = {}) {
  const chk = qs('#autoRefresh');
  let uiPresent = !!chk;

  const icon = qs('#arIcon');
  const txt  = qs('#arText');
  const applyUi = (on) => {
    if (uiPresent) {
      icon?.classList.toggle('auto-refresh-active', on);
      if (txt) txt.textContent = on ? 'Muat otomatis ON' : 'Muat otomatis';
    }
  };

  let timer = null;
  let ctrl  = null;
  let stateOn = false;
  const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

  const doSwap = (cur, fresh) => {
    if (!cur || !fresh) return;
    if (prefersReduced) {
      cur.innerHTML = fresh.innerHTML;
      return;
    }
    cur.animate([{opacity:1},{opacity:0}], {duration:120, easing:'ease-out'})
      .finished
      .then(() => {
        cur.innerHTML = fresh.innerHTML;
        cur.animate([{opacity:0},{opacity:1}], {duration:140, easing:'ease-in'});
        // re-run optional rebind hooks
        if (typeof options.onUpdate === 'function') options.onUpdate(cur);
      })
      .catch(() => { cur.innerHTML = fresh.innerHTML; });
  };

  const tick = async () => {
    if (document?.body?.classList?.contains('modal-open')) {
      return;
    }
    try {
      ctrl?.abort();
      ctrl = new AbortController();

      const url = new URL(window.location.href);
      url.searchParams.set('t', Date.now());
      const r = await fetch(url, {
        headers: { 'X-Requested-With': 'fetch' },
        signal: ctrl.signal,
        cache: 'no-store'
      });
      const html = await r.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newWrap = doc.querySelector('#tableContainer');
      const curWrap = document.querySelector('#tableContainer');
      if (newWrap && curWrap) doSwap(curWrap, newWrap);
    } catch {/* ignore */}
  };

  const start = () => {
    stop(); // ensure single interval
    timer = setInterval(tick, intervalMs);
  };
  const stop = () => {
    if (timer) clearInterval(timer);
    timer = null;
    ctrl?.abort();
  };

  // visibility: pause when hidden, resume when visible (if checked)
  on(document, 'visibilitychange', () => {
    if (document.hidden) {
      stop();
      applyUi(false);
    } else if ((uiPresent ? chk.checked : stateOn)) {
      start();
      applyUi(true);
      tick(); // immediate refresh on return
    }
  });

  // ensure timers resume after bfcache (iOS Safari/Android)
  window.addEventListener('pageshow', (e) => {
    if (uiPresent ? chk.checked : stateOn) {
      start(); applyUi(true); tick();
    }
  });

  // restore saved state (default ON jika belum ada preferensi)
  try {
    const raw = localStorage.getItem('autoRefresh');
    const saved = (raw === '1') ? true : (raw === '0') ? false : null;
    if (saved === null) {
      stateOn = true;
      if (uiPresent) chk.checked = true;
      start(); applyUi(true); tick();
      try { localStorage.setItem('autoRefresh', '1'); } catch {}
    } else if (saved) {
      stateOn = true;
      if (uiPresent) chk.checked = true;
      start(); applyUi(true); tick();
    } else {
      stateOn = false;
      if (uiPresent) chk.checked = false;
      applyUi(false);
    }
  } catch { /* noop */ }

  if (uiPresent) on(chk, 'change', () => {
    const onState = chk.checked; stateOn = onState;
    if (onState) start(); else stop();
    try { localStorage.setItem('autoRefresh', onState ? '1' : '0'); } catch {}
    applyUi(onState);
    if (onState) tick();
  });
}

/* -------------------- copy UID (with subtle feedback) -------------------- */
export function bindCopyUid(root = document) {
  qsa('[data-uid]', root).forEach(el => {
    on(el, 'click', async () => {
      const uid = el.dataset.uid;
      try {
        await navigator.clipboard?.writeText(uid);
        el.classList.add('text-success');
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-label', 'UID disalin');
        setTimeout(() => {
          el.classList.remove('text-success');
          el.removeAttribute('aria-live');
          el.removeAttribute('aria-label');
        }, 800);
      } catch { /* ignore */ }
    });
  });
}

/* -------------------- poll unknown UIDs (JSON endpoint) -------------------- */
/**
 * - Renders tbody rows
 * - Provides "Pakai" fill buttons
 * - Optional autoFillCheckbox + focusSelector
 * - Returns a stop() function to cancel polling
 */
export function pollUnknownUids(endpoint, tableBodySelector, prefillInputSelector, opts = 5000) {
  const tbody  = qs(tableBodySelector);
  const prefill = qs(prefillInputSelector);
  if (!tbody) return () => {};

  const options   = typeof opts === 'number' ? { intervalMs: opts } : (opts || {});
  const intervalMs = options.intervalMs ?? 5000;
  const autoFillChk = options.autoFillCheckbox ? qs(options.autoFillCheckbox) : null;
  const focusEl     = options.focusSelector ? qs(options.focusSelector) : null;

  let lastFirst = '';
  let timer = null;
  let aborted = false;

  async function refresh() {
    try {
      const r = await fetch(endpoint, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      const js = await r.json();
      if (!js?.ok) return;

      tbody.innerHTML = (js.items || []).map(it => `
        <tr>
          <td><code>${it.uid_hex}</code></td>
          <td>${it.last_ts}</td>
          <td>${it.cnt}</td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-primary" data-fill-uid="${it.uid_hex}">
              Pakai
            </button>
          </td>
        </tr>
      `).join('');

      qsa('[data-fill-uid]', tbody).forEach(btn => {
        on(btn, 'click', () => {
          if (prefill) {
            prefill.value = btn.dataset.fillUid;
            prefill.dataset.autofilled = '1';
          }
          focusEl?.focus();
        });
      });

      const first = js.items?.[0]?.uid_hex || '';
      if (first && autoFillChk?.checked) {
        const canReplace = !prefill?.value || prefill.dataset.autofilled === '1' || prefill.value === lastFirst;
        if (canReplace && prefill) {
          prefill.value = first;
          prefill.dataset.autofilled = '1';
          focusEl?.focus();
        }
      }
      lastFirst = first;
    } catch { /* ignore */ }
  }

  const start = () => {
    stop();
    if (aborted) return;
    timer = setInterval(refresh, intervalMs);
    refresh();
  };
  const stop = () => { if (timer) clearInterval(timer); timer = null; };

  // pause when hidden
  on(document, 'visibilitychange', () => {
    if (document.hidden) stop();
    else start();
  });

  start();
  return () => { aborted = true; stop(); };
}

/* -------------------- theme & contrast -------------------- */
function setAttr(key, val){ document.documentElement.setAttribute(key, val); }
function getAttr(key, def){ return document.documentElement.getAttribute(key) || def; }

function getStored(key){
  try { return localStorage.getItem(key); } catch { return null; }
}
function setStored(key, val){
  try { localStorage.setItem(key, val); } catch { /* ignore */ }
}

function applyContrast(contrast) {
  setAttr('data-contrast', contrast);
  setStored('contrast', contrast);
}

function applyTheme(theme) {
  setAttr('data-theme', theme);
  setStored('theme', theme);
  // optional: auto-contrast mode (you can change to a separate toggle if needed)
  const prefersMore = window.matchMedia?.('(prefers-contrast: more)')?.matches;
  // If user has explicit contrast in storage, use it; otherwise adapt lightly
  const storedContrast = getStored('contrast');
  if (storedContrast) {
    applyContrast(storedContrast);
  } else {
    applyContrast(theme === 'dark' ? (prefersMore ? 'high' : 'normal') : 'normal');
  }
}

/** Initialize theme from storage or system */
export function initTheme() {
  let theme = 'light';
  try {
    const saved = getStored('theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    theme = saved || (prefersDark ? 'dark' : 'light');
  } catch { /* noop */ }
  applyTheme(theme);

  // If user hasn't chosen explicitly, follow system changes live
  if (!getStored('theme')) {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    on(mq, 'change', e => applyTheme(e.matches ? 'dark' : 'light'));
  }

  // follow prefers-contrast change if user hasn't overridden
  if (!getStored('contrast') && window.matchMedia) {
    const mc = window.matchMedia('(prefers-contrast: more)');
    on(mc, 'change', () => applyTheme(getAttr('data-theme','light'))); // re-apply to reevaluate contrast
  }
}

/** Toggle button (light <-> dark) */
export function initThemeToggle() {
  const btn = qs('#themeToggle');
  if (!btn) return;

  const ico = btn.querySelector('i');
  const txt = btn.querySelector('span');

  const updateUi = (t) => {
    if (ico && txt) {
      if (t === 'dark') { ico.className = 'bi bi-sun me-1';  txt.textContent = 'Terang'; }
      else             { ico.className = 'bi bi-moon-stars me-1'; txt.textContent = 'Gelap'; }
    }
  };

  const apply = (t) => { applyTheme(t); updateUi(t); };

  const cur = getAttr('data-theme', 'light');
  apply(cur);

  on(btn, 'click', () => {
    const next = (getAttr('data-theme','light') === 'dark') ? 'light' : 'dark';
    apply(next);
  });
}

/** Initialize contrast from storage or media */
export function initContrast() {
  const stored = getStored('contrast');
  if (stored) return applyContrast(stored);

  // fall back to media if available (no storage preference)
  const prefersMore = window.matchMedia?.('(prefers-contrast: more)')?.matches;
  applyContrast(prefersMore ? 'high' : 'normal');
}

/** Optional: expose a simple toggle for high contrast (if you add a button) */
export function initContrastToggle(buttonSelector = '#contrastToggle') {
  const btn = qs(buttonSelector);
  if (!btn) return;
  on(btn, 'click', () => {
    const cur = getAttr('data-contrast','normal');
    const next = cur === 'high' ? 'normal' : 'high';
    applyContrast(next);
  });
}

/* -------------------- progressive enhancements on load -------------------- */
/**
 * Call this once after DOM ready to bind common UX:
 *   initModernUX({ rebinds:[bindCopyUid], autoRefresh: true })
 */
export function initModernUX(opts = {}) {
  const { rebinds = [], autoRefresh = false, autoRefreshInterval = 10000 } = opts;

  // initial binds
  rebinds.forEach(fn => { try { fn(document); } catch {} });

  // auto refresh + rebind after swap
  if (autoRefresh) {
    enableAutoRefresh(autoRefreshInterval, {
      onUpdate: (root) => {
        rebinds.forEach(fn => { try { fn(root); } catch {} });
      }
    });
  }
}

/* -------------------- convenience: DOM ready -------------------- */
export function onReady(fn){
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once:true });
  } else {
    fn();
  }
}

// Example bootstrapper (optional):
// onReady(() => {
//   initTheme();
//   initThemeToggle();
//   initContrast(); // if you need it
//   setActiveNav(document.body.dataset.nav || 'dashboard');
//   initModernUX({ rebinds:[bindCopyUid], autoRefresh:true, autoRefreshInterval:10000 });
// });
