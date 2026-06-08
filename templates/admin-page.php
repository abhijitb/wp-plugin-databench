<?php defined( 'ABSPATH' ) || exit; ?>
<div id="databench-app" class="databench-app">
	<div class="databench-header">
		<div class="databench-header-logo">
			<span class="databench-header-icon">⬡</span>
			<span class="databench-header-name">DataBench</span>
			<span class="databench-header-tagline">Database Explorer</span>
		</div>
		<div class="databench-header-meta">
			<button id="databench-sql-btn" class="databench-sql-btn">⌨ SQL Runner</button>
			<span id="databench-header-db"></span>
		</div>
	</div>
	<div class="databench-body">
		<div id="databench-sidebar" class="databench-sidebar">
			<div class="databench-sidebar-header">
				<span class="databench-sidebar-label">Tables</span>
				<button id="databench-refresh-tables" title="Refresh table list">↺</button>
			</div>
			<ul id="databench-tables" class="databench-table-list">
				<li class="databench-loading">Loading tables…</li>
			</ul>
		</div>
		<div id="databench-main" class="databench-main">
			<div class="databench-empty-state">
				<p>← Select a table to get started.</p>
			</div>
		</div>
	</div>
</div>
