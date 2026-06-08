/* global wpDataBench */
(function () {
	'use strict';

	var API   = wpDataBench.restUrl;
	var NONCE = wpDataBench.nonce;

	// ── State ──────────────────────────────────────────────────────────────────
	var state = {
		currentTable:  null,
		currentView:   'browse', // browse | structure
		currentPage:   1,
		currentSearch: '',
		orderBy:       '',
		order:         'ASC',
		schema:        null,  // { columns, primary_key }
	};

	// ── API helper ─────────────────────────────────────────────────────────────
	function apiFetch(path, options) {
		options = options || {};
		return fetch(API + path, Object.assign(
			{
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   NONCE,
				},
			},
			options
		)).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok) {
					throw new Error(body.message || 'Request failed (' + res.status + ')');
				}
				return body;
			});
		});
	}

	// ── HTML escape ────────────────────────────────────────────────────────────
	var _escDiv = document.createElement('div');
	function esc(val) {
		_escDiv.textContent = (val == null) ? '' : String(val);
		return _escDiv.innerHTML;
	}

	// ── Toast notifications ────────────────────────────────────────────────────
	function toast(message, type) {
		type = type || 'error';
		var el = document.createElement('div');
		el.className = 'databench-toast ' + type;
		el.textContent = message;
		document.body.appendChild(el);
		setTimeout(function () { el.remove(); }, 3500);
	}

	// ── App height ─────────────────────────────────────────────────────────────
	function setAppHeight() {
		var app = document.getElementById('databench-app');
		if (!app) return;
		var top = app.getBoundingClientRect().top + window.scrollY;
		app.style.height = (window.innerHeight - top - 20) + 'px';
	}

	// ── Init ───────────────────────────────────────────────────────────────────
	function init() {
		setAppHeight();
		window.addEventListener('resize', setAppHeight);

		loadTables();

		document.getElementById('databench-refresh-tables')
			.addEventListener('click', loadTables);

		var dbMeta = document.getElementById('databench-header-db');
		if (dbMeta) dbMeta.textContent = wpDataBench.dbName || '';

		document.getElementById('databench-sql-btn')
			.addEventListener('click', openSQLRunner);
	}

	// ── Sidebar ────────────────────────────────────────────────────────────────
	function loadTables() {
		var listEl = document.getElementById('databench-tables');
		listEl.innerHTML = '<li class="databench-loading">Loading…</li>';
		apiFetch('tables')
			.then(renderSidebar)
			.catch(function () {
				listEl.innerHTML = '<li class="databench-loading">Failed to load tables.</li>';
			});
	}

	function renderSidebar(tables) {
		var listEl = document.getElementById('databench-tables');
		if (!tables.length) {
			listEl.innerHTML = '<li class="databench-loading">No tables found.</li>';
			return;
		}
		listEl.innerHTML = tables.map(function (t) {
			var active = state.currentTable === t.name ? ' active' : '';
			return '<li data-table="' + esc(t.name) + '" class="' + active + '">' +
				'<span class="table-name">' + esc(t.name) + '</span>' +
				'<span class="table-count">' + Number(t.rows).toLocaleString() + '</span>' +
				'</li>';
		}).join('');
		listEl.querySelectorAll('li[data-table]').forEach(function (li) {
			li.addEventListener('click', function () { openTable(li.dataset.table); });
		});
	}

	// ── Open table ─────────────────────────────────────────────────────────────
	function openTable(name) {
		state.currentTable  = name;
		state.currentView   = 'browse';
		state.currentPage   = 1;
		state.currentSearch = '';
		state.orderBy       = '';
		state.order         = 'ASC';

		document.querySelectorAll('#databench-tables li[data-table]').forEach(function (li) {
			li.classList.toggle('active', li.dataset.table === name);
		});
		setSQLBtnActive(false);

		document.getElementById('databench-main').innerHTML =
			'<div class="databench-empty-state"><p>Loading…</p></div>';

		apiFetch('tables/' + encodeURIComponent(name) + '/structure')
			.then(function (schema) {
				state.schema = schema;
				return loadRows();
			})
			.catch(function (e) { toast(e.message); });
	}

	// ── Tabs ───────────────────────────────────────────────────────────────────
	function renderTabs(active) {
		return '<div class="databench-tab-group">' +
			'<button class="databench-tab' + (active === 'browse' ? ' active' : '') + '" data-view="browse">Browse</button>' +
			'<button class="databench-tab' + (active === 'structure' ? ' active' : '') + '" data-view="structure">Structure</button>' +
			'</div>';
	}

	function wireTabEvents() {
		document.querySelectorAll('.databench-tab[data-view]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var view = btn.dataset.view;
				state.currentView = view;
				if (view === 'browse') loadRows();
				else if (view === 'structure') renderStructure();
			});
		});
	}

	// ── Load rows ──────────────────────────────────────────────────────────────
	function loadRows() {
		var params = new URLSearchParams({
			page:    state.currentPage,
			search:  state.currentSearch,
			orderby: state.orderBy,
			order:   state.order,
		});
		return apiFetch('tables/' + encodeURIComponent(state.currentTable) + '/rows?' + params)
			.then(renderGrid)
			.catch(function (e) { toast(e.message); });
	}

	// ── Grid ───────────────────────────────────────────────────────────────────
	function renderGrid(data) {
		var rows       = data.rows;
		var total      = data.total;
		var per_page   = data.per_page;
		var page       = data.page;
		var cols       = state.schema.columns;
		var pk         = state.schema.primary_key;
		var hasPK      = pk !== null;
		var totalPages = Math.max(1, Math.ceil(total / per_page));

		var headCols = cols.map(function (c) {
			var sorted = state.orderBy === c.name;
			var cls    = sorted ? ('sorted ' + state.order.toLowerCase()) : '';
			return '<th data-col="' + esc(c.name) + '" class="' + cls + '">' + esc(c.name) + '</th>';
		}).join('');
		if (hasPK) headCols += '<th class="actions-col">Actions</th>';

		var bodyRows = rows.length ? rows.map(function (row) {
			var cells = cols.map(function (c) {
				return '<td title="' + esc(row[c.name]) + '">' + esc(row[c.name]) + '</td>';
			}).join('');
			if (hasPK) {
				cells += '<td class="actions-col">' +
					'<button class="btn-edit" data-pk="' + esc(row[pk]) + '">Edit</button>' +
					'<button class="btn-delete" data-pk="' + esc(row[pk]) + '">Del</button>' +
					'</td>';
			}
			return '<tr>' + cells + '</tr>';
		}).join('') : '<tr><td colspan="' + (cols.length + (hasPK ? 1 : 0)) + '" class="no-rows">No rows found.</td></tr>';

		var html =
			'<div class="databench-toolbar">' +
			'<h2 class="databench-table-title">' + esc(state.currentTable) + '</h2>' +
			renderTabs('browse') +
			'<span class="toolbar-spacer"></span>' +
			'<input id="databench-search" type="text" placeholder="Search… (Enter)" value="' + esc(state.currentSearch) + '">' +
			(hasPK ? '<button id="databench-new-row">+ New Row</button>' : '') +
			'</div>' +
			'<div class="databench-grid-wrap">' +
			'<table class="databench-grid">' +
			'<thead><tr>' + headCols + '</tr></thead>' +
			'<tbody>' + bodyRows + '</tbody>' +
			'</table></div>' +
			'<div class="databench-pagination">' +
			'<span>' + Number(total).toLocaleString() + ' rows — Page ' + page + ' of ' + totalPages + '</span>' +
			'<button id="btn-prev"' + (page <= 1 ? ' disabled' : '') + '>← Prev</button>' +
			'<button id="btn-next"' + (page >= totalPages ? ' disabled' : '') + '>Next →</button>' +
			'</div>';

		var main = document.getElementById('databench-main');
		main.innerHTML = html;

		wireTabEvents();

		document.getElementById('databench-search').addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				state.currentSearch = e.target.value;
				state.currentPage   = 1;
				loadRows();
			}
		});

		if (hasPK) {
			document.getElementById('databench-new-row').addEventListener('click', function () {
				openModal(null);
			});
		}

		var prevBtn = document.getElementById('btn-prev');
		var nextBtn = document.getElementById('btn-next');
		if (prevBtn) prevBtn.addEventListener('click', function () { state.currentPage--; loadRows(); });
		if (nextBtn) nextBtn.addEventListener('click', function () { state.currentPage++; loadRows(); });

		main.querySelectorAll('thead th[data-col]').forEach(function (th) {
			th.addEventListener('click', function () {
				var col = th.dataset.col;
				if (state.orderBy === col) {
					state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
				} else {
					state.orderBy = col;
					state.order   = 'ASC';
				}
				loadRows();
			});
		});

		main.querySelectorAll('.btn-edit').forEach(function (btn) {
			btn.addEventListener('click', function () { openModal(btn.dataset.pk); });
		});
		main.querySelectorAll('.btn-delete').forEach(function (btn) {
			btn.addEventListener('click', function () { deleteRow(btn.dataset.pk); });
		});
	}

	// ── Structure view ─────────────────────────────────────────────────────────
	function renderStructure() {
		var cols = state.schema.columns;

		var rows = cols.map(function (col) {
			var keyHtml = '';
			if      (col.key === 'PRI') keyHtml = '<span class="key-badge key-pri">PRI</span>';
			else if (col.key === 'UNI') keyHtml = '<span class="key-badge key-uni">UNI</span>';
			else if (col.key === 'MUL') keyHtml = '<span class="key-badge key-mul">MUL</span>';

			var nullHtml = col.null
				? '<span class="null-yes">YES</span>'
				: '<span class="null-no">NO</span>';

			return '<tr>' +
				'<td class="col-name-cell">' + esc(col.name) + '</td>' +
				'<td class="col-type-cell">' + esc(col.type) + '</td>' +
				'<td>' + nullHtml + '</td>' +
				'<td>' + keyHtml + '</td>' +
				'<td class="col-default-cell">' + esc(col.default != null ? col.default : '—') + '</td>' +
				'<td class="col-extra-cell">' + esc(col.extra || '') + '</td>' +
				'</tr>';
		}).join('');

		var html =
			'<div class="databench-toolbar">' +
			'<h2 class="databench-table-title">' + esc(state.currentTable) + '</h2>' +
			renderTabs('structure') +
			'</div>' +
			'<div class="databench-structure-wrap">' +
			'<table class="databench-structure-table">' +
			'<thead><tr>' +
			'<th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>' +
			'</tr></thead>' +
			'<tbody>' + rows + '</tbody>' +
			'</table></div>';

		document.getElementById('databench-main').innerHTML = html;
		wireTabEvents();
	}

	// ── SQL Runner ─────────────────────────────────────────────────────────────
	function openSQLRunner() {
		state.currentTable = null;
		document.querySelectorAll('#databench-tables li').forEach(function (li) {
			li.classList.remove('active');
		});
		setSQLBtnActive(true);

		document.getElementById('databench-main').innerHTML =
			'<div class="databench-toolbar">' +
			'<h2 class="databench-table-title">SQL Runner</h2>' +
			'<span class="sql-hint">SELECT only · Ctrl+Enter to run</span>' +
			'</div>' +
			'<div class="databench-sql-wrap">' +
			'<div class="sql-editor-area">' +
			'<textarea id="sql-input" class="sql-textarea" placeholder="SELECT * FROM wp_users LIMIT 10" spellcheck="false"></textarea>' +
			'<div class="sql-toolbar">' +
			'<button id="sql-run" class="sql-run-btn">▶ Run Query</button>' +
			'</div></div>' +
			'<div id="sql-results" class="sql-results"><p class="sql-placeholder">Results will appear here.</p></div>' +
			'</div>';

		var textarea = document.getElementById('sql-input');
		document.getElementById('sql-run').addEventListener('click', runQuery);
		textarea.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
				e.preventDefault();
				runQuery();
			}
		});
		textarea.focus();
	}

	function runQuery() {
		var sql = document.getElementById('sql-input').value.trim();
		if (!sql) return;

		var runBtn    = document.getElementById('sql-run');
		var resultsEl = document.getElementById('sql-results');
		runBtn.disabled = true;
		resultsEl.innerHTML = '<p class="sql-running">Running…</p>';

		apiFetch('query', { method: 'POST', body: JSON.stringify({ sql: sql }) })
			.then(function (data) {
				runBtn.disabled = false;
				renderQueryResults(data);
			})
			.catch(function (e) {
				runBtn.disabled = false;
				resultsEl.innerHTML = '<div class="sql-error-msg">' + esc(e.message) + '</div>';
			});
	}

	function renderQueryResults(data) {
		var resultsEl = document.getElementById('sql-results');
		var meta = Number(data.count).toLocaleString() + ' row' + (data.count !== 1 ? 's' : '') + ' · ' + data.time_ms + 'ms' +
			(data.capped ? ' · capped at 1,000 rows — add LIMIT to your query' : '');

		if (!data.rows.length) {
			resultsEl.innerHTML = '<p class="sql-empty">' + esc(meta) + '</p>';
			return;
		}

		var headCols = data.columns.map(function (c) {
			return '<th>' + esc(c) + '</th>';
		}).join('');

		var bodyRows = data.rows.map(function (row) {
			return '<tr>' + data.columns.map(function (c) {
				return '<td title="' + esc(row[c]) + '">' + esc(row[c]) + '</td>';
			}).join('') + '</tr>';
		}).join('');

		resultsEl.innerHTML =
			'<div class="sql-result-meta">' +
			'<span>' + esc(meta) + '</span>' +
			'<button id="sql-export-csv" class="sql-export-btn">⬇ Export CSV</button>' +
			'</div>' +
			'<div class="sql-result-grid-wrap">' +
			'<table class="databench-grid">' +
			'<thead><tr>' + headCols + '</tr></thead>' +
			'<tbody>' + bodyRows + '</tbody>' +
			'</table></div>';

		document.getElementById('sql-export-csv').addEventListener('click', function () {
			exportCSV(data.columns, data.rows);
		});
	}

	function exportCSV(columns, rows) {
		function csvCell(val) {
			var str = (val == null) ? '' : String(val);
			if (str.search(/[",\r\n]/) !== -1) {
				return '"' + str.replace(/"/g, '""') + '"';
			}
			return str;
		}

		var lines = [];
		lines.push(columns.map(csvCell).join(','));
		rows.forEach(function (row) {
			lines.push(columns.map(function (c) { return csvCell(row[c]); }).join(','));
		});

		var blob = new Blob([ lines.join('\r\n') ], { type: 'text/csv;charset=utf-8;' });
		var url  = URL.createObjectURL(blob);
		var a    = document.createElement('a');
		a.href     = url;
		a.download = 'query-' + new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-') + '.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	function setSQLBtnActive(active) {
		var btn = document.getElementById('databench-sql-btn');
		if (btn) btn.classList.toggle('active', active);
	}

	// ── Modal ──────────────────────────────────────────────────────────────────
	function openModal(pkValue) {
		var isNew = pkValue === null;
		if (!isNew) {
			apiFetch('tables/' + encodeURIComponent(state.currentTable) + '/rows/' + encodeURIComponent(pkValue))
				.then(function (row) { showModal(row, false); })
				.catch(function (e) { toast(e.message); });
		} else {
			showModal(null, true);
		}
	}

	function showModal(row, isNew) {
		var cols = state.schema.columns;
		var pk   = state.schema.primary_key;

		var fields = cols.map(function (col) {
			var val         = row ? esc(row[col.name]) : '';
			var readonly    = (!isNew && col.name === pk) ? ' readonly' : '';
			var placeholder = col.null ? ' placeholder="NULL"' : '';
			return '<div class="form-row">' +
				'<label>' + esc(col.name) +
				'<span class="col-type">' + esc(col.type) + '</span></label>' +
				'<input type="text" name="' + esc(col.name) + '" value="' + val + '"' + readonly + placeholder + '>' +
				'</div>';
		}).join('');

		var overlay = document.createElement('div');
		overlay.className = 'databench-overlay';
		overlay.innerHTML =
			'<div class="databench-modal">' +
			'<div class="modal-header">' +
			'<h3>' + (isNew ? 'New Row' : 'Edit Row') + '</h3>' +
			'<button class="modal-close" aria-label="Close">✕</button>' +
			'</div>' +
			'<form class="modal-form">' + fields +
			'<div class="form-actions">' +
			'<button type="button" class="btn-cancel">Cancel</button>' +
			'<button type="submit">' + (isNew ? 'Insert' : 'Update') + '</button>' +
			'</div></form></div>';

		document.body.appendChild(overlay);

		overlay.querySelector('.modal-close').addEventListener('click', function () { overlay.remove(); });
		overlay.querySelector('.btn-cancel').addEventListener('click', function () { overlay.remove(); });
		overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });

		overlay.querySelector('form').addEventListener('submit', function (e) {
			e.preventDefault();
			var submitBtn = overlay.querySelector('button[type="submit"]');
			submitBtn.disabled = true;

			var data = {};
			new FormData(e.target).forEach(function (val, key) { data[key] = val; });

			var path, method;
			if (isNew) {
				path   = 'tables/' + encodeURIComponent(state.currentTable) + '/rows';
				method = 'POST';
			} else {
				path   = 'tables/' + encodeURIComponent(state.currentTable) + '/rows/' + encodeURIComponent(row[pk]);
				method = 'PUT';
			}

			apiFetch(path, { method: method, body: JSON.stringify(data) })
				.then(function () {
					overlay.remove();
					return loadRows();
				})
				.then(function () { return loadTables(); })
				.then(function () { toast(isNew ? 'Row inserted.' : 'Row updated.', 'success'); })
				.catch(function (err) {
					submitBtn.disabled = false;
					toast(err.message);
				});
		});
	}

	// ── Delete ─────────────────────────────────────────────────────────────────
	function deleteRow(pkValue) {
		var pk = state.schema.primary_key;
		if (!confirm('Delete row where ' + pk + ' = ' + pkValue + '?\n\nThis cannot be undone.')) return;

		apiFetch(
			'tables/' + encodeURIComponent(state.currentTable) + '/rows/' + encodeURIComponent(pkValue),
			{ method: 'DELETE' }
		)
			.then(function () { return loadRows(); })
			.then(function () { return loadTables(); })
			.then(function () { toast('Row deleted.', 'success'); })
			.catch(function (e) { toast(e.message); });
	}

	// ── Boot ───────────────────────────────────────────────────────────────────
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
