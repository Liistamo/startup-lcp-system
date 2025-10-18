/**
 * LCP Export (CSV-only) — Admin UI JavaScript
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17):
 * - This script powers the Tools → "Export LCP Data" admin page.
 * - It talks to a private WP REST route (admin-only) to fetch rows for:
 *     1) a small preview table (first 20 rows), and
 *     2) generating a CSV file client-side (no Excel dependency).
 * - Filtering:
 *     - postType is a CPT selector: 'lcp_entry' or 'lcp_city'.
 *     - team is a dropdown of user teams (user meta 'lcp_team'), populated server-side.
 *       IMPORTANT: Teams shown are only those that currently exist on the site.
 * - Column ordering:
 *     - Always start with id, title, team.
 *     - Then include any other keys in the order they first appear in the data.
 * - CSV:
 *     - UTF-8 with BOM (so Excel opens it as UTF-8).
 *     - Each cell is quoted, with embedded quotes doubled.
 *
 * DEPENDENCIES:
 * - The backend enqueues this script and provides window.LCP_EXPORT via wp_localize_script:
 *       LCP_EXPORT = {
 *         rest: { base: '.../wp-json/lcp-export/v1', nonce: '...' },
 *         filePrefix: 'startup-lcp'
 *       }
 * - No external JS libraries are required.
 *
 * ACCESSIBILITY:
 * - Preview table keeps header sticky for usability with many columns.
 */

(function () {
  // Small helper to query one element
  const $ = (sel) => document.querySelector(sel);

  // UI outlets (status message line and preview container)
  const statusEl = () => document.getElementById('lcp-export-status');
  const previewWrap = () => document.getElementById('lcp-export-preview-table');

  /**
   * Build a compact timestamp (YYYYMMDD-HHMM) for filenames.
   */
  function ts() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}`;
  }

  /**
   * Set a short, non-blocking status message under the action buttons.
   */
  function setStatus(msg) {
    const el = statusEl();
    if (el) el.textContent = msg;
  }

  // --- Link normalisation helpers ------------------------------------------

  // Best effort: parse JSON safely
  function tryParseJSON(s) {
    if (typeof s !== 'string') return null;
    const t = s.trim();
    if (!t.startsWith('{') || t.length < 5) return null;
    try { return JSON.parse(t); } catch { return null; }
  }

  // Clean URL for export/preview
  function cleanUrl(u) {
    if (typeof u !== 'string') return u;
    // Unescape JSON \/ to normal /
    let v = u.replace(/\\\//g, '/').trim();
    // Normalise accidental backslashes (https:\\ → https://)
    v = v.replace(/^https:\\\\/, 'https://').replace(/^http:\\\\/, 'http://');
    // Remove trailing slash (optional; comment out if you want to keep it)
    v = v.replace(/\/+$/, '');
    return v;
  }

  // If value is an ACF "Link" (object with url/title/target) → return plain URL.
  // Handles both real objects and JSON-encoded strings.
  function extractLinkUrl(value) {
    // Object form (from backend)?
    if (value && typeof value === 'object' && 'url' in value) {
      return cleanUrl(String(value.url || ''));
    }
    // String-JSON form?
    if (typeof value === 'string' && value.includes('"url"')) {
      const obj = tryParseJSON(value);
      if (obj && typeof obj.url === 'string') {
        return cleanUrl(obj.url);
      }
    }
    // Not a link object → return original
    return value;
  }

  // Walk a row and replace any ACF link value with its plain URL
  function normaliseLinkFieldsInRow(row) {
    const out = {};
    for (const k of Object.keys(row)) {
      out[k] = extractLinkUrl(row[k]);
    }
    return out;
  }

  /**
   * Fetch ONE page of rows from the private REST endpoint.
   * Used for the small preview (first 20 rows).
   *
   * @param {Object} opts
   * @param {string} opts.postType - 'lcp_entry' or 'lcp_city'
   * @param {string} [opts.team]   - selected team label (from user meta 'lcp_team')
   * @param {string} [opts.status] - WP post_status filter (default 'any')
   * @param {number} [opts.paged]  - current page (1-based)
   * @param {number} [opts.perPage]- rows per page
   * @returns {Promise<Object>} { rows: [...], pagination: {...}, ... }
   */
  async function fetchPage({ postType = 'lcp_entry', team = '', status = 'any', paged = 1, perPage = 20 } = {}) {
    const base = (LCP_EXPORT?.rest?.base || '').replace(/\/$/, '');
    const url = new URL(base + '/entries');
    url.searchParams.set('post_type', postType);
    url.searchParams.set('status', status);
    url.searchParams.set('per_page', String(perPage));
    url.searchParams.set('paged', String(paged));
    if (team) url.searchParams.set('team', team);

    const res = await fetch(url.toString(), { headers: { 'X-WP-Nonce': LCP_EXPORT?.rest?.nonce || '' } });
    if (!res.ok) throw new Error('Fetch failed: ' + res.status);

    const json = await res.json();
    // Normalise ACF Link fields to plain URL for preview
    if (Array.isArray(json.rows)) {
      json.rows = json.rows.map(normaliseLinkFieldsInRow);
    }
    return json;
  }

  /**
   * Fetch ALL pages of rows (used for CSV export).
   * Paginates until all data is collected to avoid memory issues on server.
   *
   * @param {Object} opts - same keys as fetchPage but without paged/perPage defaults.
   * @returns {Promise<Array<Object>>} all rows
   */
  async function fetchAllRows({ postType = 'lcp_entry', team = '', status = 'any', perPage = 1000 } = {}) {
    const base = (LCP_EXPORT?.rest?.base || '').replace(/\/$/, '');
    let paged = 1;
    let all = [];
    let maxPages = 1;
    let total = 0;

    do {
      const url = new URL(base + '/entries');
      url.searchParams.set('post_type', postType);
      url.searchParams.set('status', status);
      url.searchParams.set('per_page', String(perPage));
      url.searchParams.set('paged', String(paged));
      if (team) url.searchParams.set('team', team);

      setStatus(`Fetching page ${paged}...`);
      const res = await fetch(url.toString(), { headers: { 'X-WP-Nonce': LCP_EXPORT?.rest?.nonce || '' } });
      if (!res.ok) throw new Error('Fetch failed: ' + res.status);
      const json = await res.json();

      // Normalise ACF Link fields to plain URL before appending
      const pageRows = Array.isArray(json.rows) ? json.rows.map(normaliseLinkFieldsInRow) : [];
      all = all.concat(pageRows);

      maxPages = json.pagination?.max_pages || 1;
      total = json.pagination?.total || all.length;
      paged++;
    } while (paged <= maxPages);

    setStatus(`Fetched ${all.length} / ${total} rows.`);
    return all;
  }

  /**
   * Compute export column order:
   * - Always start with id, title, team (if present in data).
   * - Then include any other keys in first-seen order across the dataset.
   *
   * This is robust as new ACF/meta keys may appear over project lifetime.
   */
  function computeColumns(rows) {
    const seen = new Set();
    const ordered = [];

    // Core fields first
    ['id', 'title', 'team'].forEach((k) => {
      seen.add(k);
      ordered.push(k);
    });

    // Preserve first-seen order of remaining keys
    for (const r of rows) {
      for (const k of Object.keys(r)) {
        if (!seen.has(k)) {
          seen.add(k);
          ordered.push(k);
        }
      }
    }
    return ordered;
  }

  /**
   * CSV builder:
   * - UTF-8 with BOM (so Excel opens correctly).
   * - Quote all fields; double any embedded quotes.
   */
  function toCSV(rows, columns) {
    const esc = (v) => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
    const head = columns.map(esc).join(',');
    thead = head; // keep for debugging if needed
    const body = rows.map((r) => columns.map((c) => esc(r[c])).join(',')).join('\n');
    return '\uFEFF' + head + '\n' + body;
  }

  /**
   * Trigger a file download for given content.
   */
  function downloadBlob(content, mime, filename) {
    const blob = new Blob([content], { type: mime });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  /**
   * Main handler for the "Download CSV" button.
   * - Reads current filters (post type + team).
   * - Fetches all rows via paginated REST calls.
   * - Builds CSV and triggers a download.
   */
  async function handleExportCSV() {
    try {
      const postType = ($('#lcp-posttype')?.value || 'lcp_entry');
      const team = ($('#lcp-team-select')?.value || '').trim();

      const rows = await fetchAllRows({ postType, team });
      if (!rows.length) {
        alert('No data to export.');
        return;
      }
      // Fetch a tiny page just to get the preferred column order
      // (or have fetchAllRows return the first page's metadata if you want to optimize)
      const meta = await fetchPage({ postType, team, perPage: 1, paged: 1 });
      const columns = Array.isArray(meta.columns_preferred) && meta.columns_preferred.length
        ? meta.columns_preferred
        : computeColumns(rows);
      const csv = toCSV(rows, columns);
      const fname = `${(LCP_EXPORT?.filePrefix || 'export')}-${postType}-${ts()}.csv`;
      downloadBlob(csv, 'text/csv;charset=utf-8', fname);
      setStatus(`CSV downloaded (${rows.length} rows).`);
    } catch (e) {
      console.error(e);
      alert('CSV export failed.');
      setStatus('CSV export failed.');
    }
  }

  /**
   * Render the small preview table (first 20 rows).
   * - Sticky header for better UX when many columns.
   * - Monospaced font for numeric-ish columns (id/lat/lng/zoom).
   */
  function renderPreview(rows, colsOverride) {
    const container = previewWrap();
    if (!container) return;
    container.innerHTML = '';

    if (!rows || !rows.length) {
      container.innerHTML = '<p class="lcp-dim">No rows to preview.</p>';
      return;
    }

    const cols = Array.isArray(colsOverride) && colsOverride.length
      ? colsOverride
      : computeColumns(rows);
    const table = document.createElement('table');
    table.className = 'lcp-table';

    // Header
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    cols.forEach((c) => {
      const th = document.createElement('th');
      th.textContent = c;
      th.className = 'lcp-nowrap';
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);

    // Body (limit to first 20 rows)
    const tbody = document.createElement('tbody');
    rows.slice(0, 20).forEach((r) => {
      const tr = document.createElement('tr');
      cols.forEach((c) => {
        const td = document.createElement('td');
        let v = r[c];
        if (v == null) v = '';
        if (typeof v === 'object') {
          // Defensive: stringify arrays/objects if any slipped through.
          try {
            v = JSON.stringify(v);
          } catch (_) {
            v = String(v);
          }
        }
        td.textContent = String(v);
        td.className = /id|lat|lng|zoom/i.test(c) ? 'lcp-mono' : '';
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);

    container.appendChild(table);
  }

  /**
   * Orchestrate preview loading with current filters.
   * - Reads postType + team from UI.
   * - Calls fetchPage (perPage=20) and renders the preview.
   */
  async function refreshPreview() {
    try {
      const postType = ($('#lcp-posttype')?.value || 'lcp_entry');
      const team = ($('#lcp-team-select')?.value || '').trim();

      setStatus('Loading preview...');
      const json = await fetchPage({ postType, team, perPage: 20, paged: 1 });

      const cols = Array.isArray(json.columns_preferred) && json.columns_preferred.length
        ? json.columns_preferred
        : computeColumns(json.rows || []);

      renderPreview(json.rows || [], cols);
      setStatus('');
    } catch (e) {
      console.error(e);
      setStatus('Failed to load preview.');
    }
  }

  /**
   * Wire up buttons and auto-refresh behavior once the DOM is ready.
   * - "Download CSV" → handleExportCSV()
   * - "Refresh preview" → refreshPreview()
   * - Auto-refresh preview on filter changes + initial render.
   */
  document.addEventListener('DOMContentLoaded', () => {
    const csvBtn = document.getElementById('lcp-export-csv');
    const prevBtn = document.getElementById('lcp-refresh-preview');
    if (csvBtn) csvBtn.addEventListener('click', handleExportCSV);
    if (prevBtn) prevBtn.addEventListener('click', refreshPreview);

    // Auto-refresh when filters change
    const postSel = document.getElementById('lcp-posttype');
    const teamSel = document.getElementById('lcp-team-select');
    if (postSel) postSel.addEventListener('change', refreshPreview);
    if (teamSel) teamSel.addEventListener('change', refreshPreview);

    // Initial preview on page load
    refreshPreview();
  });
})();
