<style>
    /* ── Stock page layout ── */
    body.no-scroll, html.no-scroll { overflow: hidden !important; height: 100vh !important; }

    .stock-page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 4px;
    }

    .stock-page-header .btn-shine { flex-shrink: 0; margin-top: 2px; }

    .stock-view-tabs {
        display: flex;
        gap: 12px;
        margin: 20px 0 24px;
        max-width: 520px;
    }

    .stock-view-tab {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 18px;
        border: 1px solid #e8e0d6;
        border-radius: 12px;
        background: #fff;
        color: #6b635a;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
    }

    .stock-view-tab:hover {
        border-color: #d7c4b0;
        color: #5d4037;
        background: #fffcf8;
    }

    .stock-view-tab.active {
        background: #8b4513;
        border-color: #8b4513;
        color: #fff;
        box-shadow: 0 4px 12px rgba(139, 69, 19, 0.15);
    }

    .stock-view-tab[data-area="kitchen"].active {
        background: #2e7d32;
        border-color: #2e7d32;
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.15);
    }

    .stock-view-tab-icon { font-size: 1rem; line-height: 1; }

    .stock-main-area { margin-top: 0; }

    .stock-main-hint {
        margin: 0 0 18px;
        color: #9a9288;
        font-size: 0.875rem;
        font-weight: 400;
        line-height: 1.5;
    }

    .stock-main-hint.hidden { display: none; }

    .stock-list-search-wrap {
        padding: 18px 24px 4px;
    }

    .stock-list-search {
        width: 100%;
        max-width: 480px;
        height: 44px;
        padding: 0 18px 0 44px;
        border: 2px solid #8b7355;
        border-radius: 999px;
        font-size: 0.875rem;
        font-family: inherit;
        font-weight: 500;
        color: #333;
        outline: none;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%235d4037' stroke-width='2.25'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") 16px center no-repeat;
        box-shadow: 0 2px 8px rgba(93, 64, 55, 0.08);
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .stock-list-search:focus {
        border-color: #5d4037;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.15), 0 2px 8px rgba(93, 64, 55, 0.1);
    }

    .stock-list-search::placeholder {
        color: #9a8a78;
    }

    .stock-edit-tracking-wrap {
        margin: 18px 0 0;
        padding: 14px 16px;
        background: #fbf7f0;
        border: 1px solid #ebe4dc;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .stock-edit-track-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 0.8125rem;
        color: #5d4037;
        cursor: pointer;
        line-height: 1.4;
    }

    .stock-edit-track-option input {
        margin-top: 3px;
        flex-shrink: 0;
        accent-color: #8b4513;
    }

    .btn-view-action {
        background: #f5f0ea;
        color: #5d4037;
        border: 1px solid #d7c4b0;
        padding: 7px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8125rem;
        cursor: pointer;
        font-family: inherit;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-view-action:hover {
        background: #ebe4dc;
        border-color: #8b4513;
    }

    .stock-view-modal-card {
        width: 100%;
        max-width: min(720px, 96vw);
        max-height: min(92vh, 780px);
    }

    .stock-view-body {
        padding: 0 28px 20px;
        overflow-y: auto;
        flex: 1;
        min-height: 0;
        -webkit-overflow-scrolling: touch;
    }

    .stock-view-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }

    .stock-view-stat {
        padding: 14px 16px;
        background: #faf8f5;
        border: 1px solid #ebe4dc;
        border-radius: 12px;
        min-width: 0;
    }

    .stock-view-stat.full-width {
        grid-column: 1 / -1;
    }

    .stock-view-stat-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        color: #777;
        margin-bottom: 6px;
        line-height: 1.3;
    }

    .stock-view-stat-value {
        font-size: 1rem;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1.4;
        word-break: break-word;
    }

    .stock-view-stat-value.compact {
        color: #1a1a1a;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .stock-view-stat.compact-total {
        padding: 10px 14px;
    }

    .stock-view-stat.compact-total .stock-view-stat-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #666;
        margin-bottom: 3px;
        text-transform: none;
        letter-spacing: 0;
    }

    .stock-view-date-box {
        margin-bottom: 18px;
        padding: 14px 16px;
        background: #fafafa;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
    }

    .stock-view-date-title {
        margin: 0 0 10px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #666;
    }

    .stock-view-date-quick {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
    }

    .stock-date-chip {
        flex: 1;
        border: 1px solid #ddd;
        background: #fff;
        color: #333;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 600;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .stock-date-chip:hover {
        border-color: #bbb;
        background: #f5f5f5;
    }

    .stock-date-chip.active {
        background: #1a1a1a;
        border-color: #1a1a1a;
        color: #fff;
    }

    .stock-view-date-picker-row {
        margin-bottom: 12px;
    }

    .stock-view-date-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.875rem;
        color: #1a1a1a;
        background: #fff;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .stock-view-date-input:focus {
        border-color: #888;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.06);
    }

    .stock-view-day-heading {
        margin: 0 0 10px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #1a1a1a;
    }

    .stock-view-day-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stock-view-day-grid.kitchen-four {
        grid-template-columns: repeat(4, 1fr);
    }

    .stock-view-day-stat.day-opening {
        background: #fafafa;
        border-color: #e0e0e0;
    }

    .stock-view-day-stat.day-opening .stock-view-day-label {
        color: #666;
    }

    .stock-view-day-formula {
        margin: 10px 0 0;
        font-size: 0.8125rem;
        color: #666;
        line-height: 1.4;
        text-align: center;
    }

    .stock-view-day-stat.day-total-added {
        background: #f1f8e9;
        border-color: #c5e1a5;
    }

    .stock-view-day-stat.day-total-added .stock-view-day-label {
        color: #558b2f;
    }

    .stock-view-day-stat {
        padding: 10px 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e8e8e8;
    }

    .stock-view-day-stat.sold {
        border-color: #e0e0e0;
    }

    .stock-view-day-stat.added {
        border-color: #dcedc8;
    }

    .stock-view-day-stat.added.remaining {
        background: #f5f8ff;
        border-color: #c5cae9;
    }

    .stock-view-day-stat.added.remaining .stock-view-day-label {
        color: #3949ab;
    }

    .stock-view-day-label {
        display: block;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #666;
        margin-bottom: 4px;
    }

    .stock-view-day-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1.1;
    }

    .stock-view-day-unit {
        font-size: 0.6875rem;
        color: #888;
        margin-left: 4px;
    }

    .stock-view-history-date-hint {
        font-weight: 500;
        text-transform: none;
        letter-spacing: 0;
        color: #888;
    }

    .stock-current-qty-block {
        padding: 14px 16px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        margin-bottom: 18px;
    }

    .stock-current-qty-block .stock-edit-section-label {
        margin: 0 0 8px;
    }

    .stock-current-qty-view {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .stock-current-qty-value {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #111;
        line-height: 1.4;
    }

    .btn-stock-qty-edit {
        flex-shrink: 0;
        min-height: 36px;
        padding: 8px 14px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid #e8d5c4;
        background: #fff;
        color: #8b4513;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-stock-qty-edit:hover {
        background: #fbf3eb;
        border-color: #d4b896;
    }

    .stock-current-qty-edit {
        display: flex;
        flex-direction: column;
    }

    .stock-field-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #5d4037;
        margin: 0 0 10px;
    }

    .stock-qty-edit-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 14px;
    }

    .btn-stock-qty-update,
    .btn-stock-qty-cancel {
        min-height: 44px;
        padding: 12px 14px;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-stock-qty-update {
        background: #fbf3eb;
        color: #8b4513;
        border-color: #e8d5c4;
    }

    .btn-stock-qty-update:hover {
        background: #f0e0d0;
    }

    .btn-stock-qty-cancel {
        background: #fff;
        color: #666;
        border-color: #ddd;
    }

    .btn-stock-qty-cancel:hover {
        background: #f5f5f5;
        border-color: #ccc;
    }

    .stock-piece-adjust-panel {
        display: flex;
        flex-direction: column;
    }

    .stock-qty-add-fields {
        display: flex;
        flex-direction: column;
    }

    .stock-qty-action-view {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stock-qty-action-view.single-col {
        grid-template-columns: 1fr;
    }

    .btn-stock-qty-add-start,
    .btn-stock-qty-add {
        min-height: 44px;
        padding: 12px 14px;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid #a5d6a7;
        background: #e8f5e9;
        color: #2e7d32;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-stock-qty-remove-start,
    .btn-stock-qty-remove {
        min-height: 44px;
        padding: 12px 14px;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid #ef9a9a;
        background: #ffebee;
        color: #c62828;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-stock-qty-add-start:hover,
    .btn-stock-qty-add:hover {
        background: #c8e6c9;
    }

    .btn-stock-qty-remove-start:hover,
    .btn-stock-qty-remove:hover {
        background: #ffcdd2;
    }

    .stock-qty-add-form,
    .stock-qty-remove-form {
        display: flex;
        flex-direction: column;
    }

    .stock-qty-add-actions,
    .stock-qty-remove-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 14px;
    }

    .stock-view-history-heading.used {
        color: #c62828;
    }

    .stock-view-day-stat.used {
        background: #fff5f5;
        border-color: #ffcdd2;
    }

    .stock-view-day-stat.used .stock-view-day-label {
        color: #c62828;
    }

    .stock-view-section-label {
        margin: 0 0 10px;
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #a09080;
        font-weight: 600;
    }

    .stock-view-history-sections {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .stock-view-history-block {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .stock-view-history-heading {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 700;
        color: #5d4037;
    }

    .stock-view-history-heading.sold {
        color: #8b4513;
    }

    .stock-view-history-heading.added {
        color: #2e7d32;
    }

    .stock-view-history-wrap {
        max-height: min(28vh, 220px);
        overflow: auto;
        border: 1px solid #ebe4dc;
        border-radius: 10px;
        -webkit-overflow-scrolling: touch;
    }

    .stock-view-section-empty {
        padding: 16px;
        font-size: 0.875rem;
    }

    .stock-view-history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .stock-view-history-table th,
    .stock-view-history-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #f0ebe3;
        vertical-align: top;
        line-height: 1.4;
    }

    .stock-view-history-table th {
        background: #faf8f5;
        font-weight: 700;
        color: #6b635a;
        position: sticky;
        top: 0;
    }

    .stock-view-history-table tr:last-child td {
        border-bottom: none;
    }

    .stock-view-loading,
    .stock-view-empty {
        padding: 24px;
        text-align: center;
        color: #9a9288;
        font-size: 0.875rem;
        margin: 0;
    }

    .stock-view-loading.inline {
        padding: 10px 0 4px;
        font-size: 0.8125rem;
    }

    .stock-view-body.is-fetching-history .stock-view-history-sections {
        opacity: 0.45;
        pointer-events: none;
        transition: opacity 0.12s ease;
    }

    .stock-view-body.is-fetching-history .stock-view-day-grid {
        opacity: 0.7;
        transition: opacity 0.12s ease;
    }

    .stock-view-modal-footer .stock-confirm-btn {
        margin: 0;
    }

    .stock-view-panel { display: none; }
    .stock-view-panel.active { display: block; }

    .stock-list-card {
        margin-top: 0;
        padding-bottom: 4px;
    }

    .stock-list-card .menu-list-container {
        padding: 12px 24px 32px;
    }

    /* ── Stock table layout (desktop) ── */
    .stock-list-card .stock-table {
        table-layout: fixed;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }

    .stock-list-card .stock-table col.col-idx { width: 48px; }
    .stock-list-card .stock-table col.col-name { width: auto; }
    .stock-list-card .stock-table col.col-price { width: 90px; }
    .stock-list-card .stock-table col.col-date { width: 118px; }
    .stock-list-card .stock-table col.col-stock { width: 28%; min-width: 200px; }
    .stock-list-card .stock-table col.col-action { width: 200px; }

    .stock-row-status {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        color: #9a9288;
        margin-top: 4px;
    }

    .stock-list-card .stock-table thead th {
        text-align: left;
        padding: 0 16px 8px 16px;
        vertical-align: bottom;
        font-size: 0.75rem;
    }

    .stock-list-card .stock-table tbody td {
        padding: 16px;
        vertical-align: middle;
    }

    .stock-list-card .stock-table thead th.col-idx,
    .stock-list-card .stock-table tbody td.idx-col {
        text-align: center;
        padding-left: 12px;
        padding-right: 12px;
        width: 48px;
    }

    .stock-list-card .stock-table thead th.col-name {
        text-align: left;
        padding-left: 16px;
        padding-right: 16px;
    }

    .stock-list-card .stock-table tbody td.item-name-col {
        text-align: left;
        padding-left: 16px;
        padding-right: 16px;
        font-size: 1rem;
        font-weight: 700;
    }

    .stock-list-card .badge-pill {
        font-size: 0.65rem;
        padding: 5px 10px;
        white-space: nowrap;
    }

    .stock-list-card .stock-table thead th.col-price,
    .stock-list-card .stock-table tbody td.price-col {
        text-align: right;
        padding-left: 8px;
        padding-right: 16px;
    }

    .stock-list-card .stock-table thead th.col-date,
    .stock-list-card .stock-table tbody td.stock-date-col {
        text-align: center;
        padding-left: 10px;
        padding-right: 10px;
        font-size: 0.8125rem;
        color: #6b635a;
        white-space: nowrap;
    }

    .stock-list-card .stock-table thead th.col-stock,
    .stock-list-card .stock-table tbody td.stock-col-cell {
        text-align: center;
        padding-left: 12px;
        padding-right: 12px;
    }

    .stock-list-card .stock-table thead th.col-action,
    .stock-list-card .stock-table tbody td.action-col {
        text-align: right;
        padding-left: 8px;
        padding-right: 16px;
    }

    .stock-col-inner {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        flex-wrap: nowrap;
        max-width: 100%;
    }

    .stock-badge-wrap {
        flex-shrink: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        justify-content: center;
    }

    .stock-tracking-block {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: nowrap;
        border-left: 1px solid #eee;
        padding-left: 12px;
    }

    .stock-tracking-label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9a9288;
        white-space: nowrap;
    }

    .stock-track-toggle {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .stock-track-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .stock-track-slider {
        position: absolute;
        inset: 0;
        background: #e0e0e0;
        border-radius: 24px;
        transition: background 0.2s;
    }

    .stock-track-slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .stock-track-toggle input:checked + .stock-track-slider {
        background: #2e7d32;
    }

    .stock-track-toggle input:checked + .stock-track-slider::before {
        transform: translateX(20px);
    }

    .stock-track-toggle input:disabled + .stock-track-slider {
        opacity: 0.6;
    }

    .stock-tracking-on-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #2e7d32;
        background: #e8f5e9;
        border: 1px solid #a5d6a7;
        border-radius: 8px;
        padding: 3px 7px;
        white-space: nowrap;
        line-height: 1.2;
    }

    .stock-track-confirm-btn {
        border: none;
        background: #8b4513;
        color: #fff;
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 8px;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s;
    }

    .stock-track-confirm-btn:hover {
        background: #6d360f;
    }

    .stock-track-confirm-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .stock-table-empty td {
        text-align: center;
        padding: 48px 24px !important;
        color: #b0a89e;
        font-size: 0.875rem;
        font-style: italic;
        font-weight: 400;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .stock-table-empty tr:hover {
        transform: none !important;
        box-shadow: none !important;
    }

    .stock-action-btns {
        display: inline-flex;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    .stock-list-card .btn-edit-action,
    .stock-list-card .btn-delete-action {
        padding: 7px 12px;
        font-size: 0.8125rem;
    }

    /* Keep stock table as real table on desktop (override global card layout) */
    @media (min-width: 769px) {
        .stock-list-card .stock-table {
            display: table !important;
        }

        .stock-list-card .stock-table thead {
            display: table-header-group !important;
        }

        .stock-list-card .stock-table tbody {
            display: table-row-group !important;
        }

        .stock-list-card .stock-table tr {
            display: table-row !important;
        }

        .stock-list-card .stock-table th,
        .stock-list-card .stock-table td {
            display: table-cell !important;
        }

        .stock-list-card .stock-table tbody td.idx-col {
            display: table-cell !important;
        }
    }

    @media (max-width: 768px) {
        .stock-list-card .menu-list-container {
            padding: 12px 16px 24px;
        }

        .stock-col-inner {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .stock-tracking-block {
            border-left: none;
            padding-left: 0;
        }

        .stock-action-btns {
            flex-wrap: wrap;
        }
    }

    .btn-delete-action {
        background: #fff5f5;
        color: #e53935;
        border: 1px solid #ffcdd2;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8125rem;
        cursor: pointer;
        font-family: inherit;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-delete-action:hover {
        background: #ffebee;
        border-color: #ef9a9a;
    }

    /* ── Modals (Add Stock + Edit) ── */
    .stock-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 2400;
        background: rgba(30, 20, 15, 0.42);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: stockModalOverlayIn 0.22s ease;
    }

    @keyframes stockModalOverlayIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes stockModalCardIn {
        from {
            opacity: 0;
            transform: translateY(14px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    #stock-view-modal .stock-modal-card {
        max-width: min(720px, 96vw);
        animation: stockModalCardIn 0.28s ease;
    }

    #stock-view-modal .stock-modal-header {
        padding: 24px 28px 20px;
    }

    #stock-view-modal .stock-view-modal-footer {
        padding: 16px 28px 24px;
    }

    @media (prefers-reduced-motion: reduce) {
        .stock-modal-overlay,
        #stock-view-modal .stock-modal-card {
            animation: none;
        }
    }

    #stock-edit-modal { z-index: 2500; }
    #stock-view-modal { z-index: 2600; }
    #stock-confirm-modal { z-index: 2700; }

    .stock-confirm-card {
        width: 100%;
        max-width: 400px;
        background: #fff;
        border-radius: 18px;
        padding: 28px 24px 22px;
        box-shadow: 0 24px 60px rgba(30, 20, 15, 0.22);
        border: 1px solid #ebe4dc;
        text-align: center;
        animation: stockModalCardIn 0.28s ease;
    }

    .stock-confirm-icon-wrap {
        width: 56px;
        height: 56px;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: #ffebee;
        color: #c62828;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ffcdd2;
    }

    .stock-confirm-title {
        margin: 0 0 10px;
        font-size: 1.2rem;
        font-weight: 800;
        color: #3e2723;
        letter-spacing: -0.02em;
    }

    .stock-confirm-msg {
        margin: 0 0 24px;
        font-size: 0.9375rem;
        line-height: 1.55;
        color: #6b635a;
    }

    .stock-confirm-msg strong {
        color: #5d4037;
        font-weight: 700;
    }

    .stock-confirm-actions {
        display: flex;
        gap: 10px;
    }

    .stock-confirm-cancel,
    .stock-confirm-danger {
        flex: 1;
        padding: 13px 16px;
        border-radius: 12px;
        font-family: inherit;
        font-size: 0.9375rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s, transform 0.12s, box-shadow 0.2s;
    }

    .stock-confirm-cancel {
        background: #f5f0ea;
        border: 1px solid #d7c4b0;
        color: #5d4037;
    }

    .stock-confirm-cancel:hover {
        background: #ebe4dc;
        border-color: #bc8a5f;
    }

    .stock-confirm-danger {
        background: linear-gradient(180deg, #ef5350 0%, #c62828 100%);
        border: none;
        color: #fff;
        box-shadow: 0 4px 14px rgba(198, 40, 40, 0.28);
    }

    .stock-confirm-danger:hover {
        box-shadow: 0 6px 18px rgba(198, 40, 40, 0.35);
        transform: translateY(-1px);
    }

    .stock-confirm-danger:active,
    .stock-confirm-cancel:active {
        transform: translateY(0);
    }

    .stock-modal-card {
        width: 100%;
        max-width: 420px;
        max-height: min(90vh, 620px);
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(62, 39, 35, 0.16);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Outfit', 'Inter', system-ui, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    #stock-add-modal .stock-modal-card {
        min-height: 320px;
    }

    .stock-edit-modal-card {
        max-width: 440px;
        max-height: min(92vh, 680px);
    }

    #stock-edit-modal .stock-modal-header {
        flex-shrink: 0;
    }

    .stock-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 22px 22px 18px;
        background: #fbf7f0;
        border-bottom: 1px solid #f0ebe3;
    }

    .stock-modal-header h3 {
        margin: 0;
        font-size: 1.125rem;
        color: #5d4037;
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.3;
    }

    .stock-modal-sub {
        margin: 6px 0 0;
        font-size: 0.8125rem;
        color: #9a8f85;
        font-weight: 400;
        line-height: 1.45;
    }

    .stock-modal-close {
        border: none;
        background: #f0ebe3;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        font-size: 1.25rem;
        line-height: 1;
        color: #777;
        cursor: pointer;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }

    .stock-modal-close:hover {
        background: #e8e0d6;
        color: #444;
    }

    .stock-area-select-wrap {
        padding: 14px 22px 16px;
        flex-shrink: 0;
        border-bottom: 1px solid #f5f0ea;
    }

    .stock-area-select-label {
        margin: 0 0 10px;
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #a09080;
        font-weight: 600;
    }

    .stock-area-chips {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stock-area-chip {
        border: 1px solid #e8e0d6;
        background: #fff;
        color: #6b635a;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 600;
        padding: 11px 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .stock-area-chip:hover {
        border-color: #d7c4b0;
        background: #fffcf8;
    }

    .stock-area-chip.active {
        background: #8b4513;
        border-color: #8b4513;
        color: #fff;
    }

    .stock-area-chip[data-area="kitchen"].active {
        background: #2e7d32;
        border-color: #2e7d32;
    }

    .stock-modal-search-wrap {
        padding: 18px 22px 0;
    }

    .stock-modal-search {
        width: 100%;
        height: 42px;
        padding: 0 16px 0 42px;
        border: 1px solid #e8e0d6;
        border-radius: 21px;
        font-size: 0.875rem;
        font-family: inherit;
        font-weight: 400;
        color: #333;
        outline: none;
        background: #faf8f5 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") 14px center no-repeat;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .stock-modal-search:focus {
        border-color: #8b4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
    }

    .stock-modal-search::placeholder {
        color: #b0a89e;
    }

    .stock-picker-loading,
    .stock-picker-empty {
        padding: 36px 22px;
        text-align: center;
        color: #9a9288;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .stock-picker-loading.stock-picker-retry {
        cursor: pointer;
        color: #8b4513;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .stock-picker-loading.stock-picker-retry:hover {
        color: #6d360f;
    }

    .stock-picker-grid {
        flex: 1;
        overflow-y: auto;
        padding: 14px 22px 6px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 100px;
        max-height: min(40vh, 260px);
    }

    .stock-add-mode-panel {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    #stock-add-kitchen-panel {
        flex: 1;
        min-height: 0;
    }

    #stock-add-counter-panel .stock-modal-footer {
        margin-top: auto;
        flex-shrink: 0;
    }

    .kitchen-manual-intro {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 18px;
        padding: 32px 22px 28px;
        text-align: center;
    }

    .kitchen-manual-hint {
        margin: 0;
        font-size: 0.875rem;
        color: #7a6a5a;
        line-height: 1.55;
        max-width: 300px;
    }

    .btn-kitchen-manual-open {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 28px;
        border: 2px dashed #81c784;
        border-radius: 14px;
        background: #f1f8e9;
        color: #2e7d32;
        font-family: inherit;
        font-weight: 700;
        font-size: 0.9375rem;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
    }

    .btn-kitchen-manual-open:hover {
        background: #e8f5e9;
        border-color: #66bb6a;
    }

    .btn-kitchen-manual-open span {
        font-size: 1.25rem;
        line-height: 1;
    }

    .kitchen-manual-form {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    .kitchen-manual-form-scroll {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 12px 22px 8px;
        -webkit-overflow-scrolling: touch;
    }

    .kitchen-manual-form-footer {
        flex-shrink: 0;
        padding: 14px 22px 20px;
        border-top: 1px solid #f0ebe3;
        background: #faf8f5;
    }

    .kitchen-confirm-btn {
        width: 100%;
        margin: 0;
        background: #2e7d32;
    }

    .kitchen-confirm-btn:hover:not(:disabled) {
        background: #1b5e20;
        box-shadow: 0 4px 14px rgba(46, 125, 50, 0.3);
    }

    .kitchen-manual-back {
        display: block;
        width: 100%;
        border: none;
        background: none;
        color: #2e7d32;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 600;
        padding: 0;
        margin: 0 0 18px;
        text-align: left;
        cursor: pointer;
    }

    .kitchen-manual-back:hover {
        text-decoration: underline;
    }

    .kitchen-field-group {
        margin-bottom: 18px;
    }

    .kitchen-field-group:last-child {
        margin-bottom: 8px;
    }

    .kitchen-field-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9a8f85;
        margin: 0 0 8px;
        padding: 0;
        line-height: 1.3;
    }

    .kitchen-field-label.kitchen-qty-label {
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        color: #333;
    }

    .kitchen-field-input {
        display: block;
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid #e8e0d6;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.9375rem;
        margin: 0;
        background: #fff;
        color: #2d2a26;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .kitchen-field-input:focus {
        outline: none;
        border-color: #66bb6a;
        box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.18);
    }

    .kitchen-field-input::placeholder {
        color: #c4bcb4;
    }

    .kitchen-field-input::-webkit-outer-spin-button,
    .kitchen-field-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .kitchen-field-input[type="number"] {
        -moz-appearance: textfield;
    }

    .kitchen-unit-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0;
    }

    .kitchen-unit-chips .stock-unit-chip {
        flex: 0 1 auto;
        min-width: calc(33.333% - 6px);
        padding: 9px 10px;
        border-radius: 10px;
        font-size: 0.72rem;
        text-align: center;
    }

    .kitchen-unit-chips .stock-unit-chip.active {
        background: #2e7d32;
        border-color: #2e7d32;
        color: #fff;
    }

    .kitchen-unit-panels .kitchen-unit-panel {
        display: none;
        margin: 0;
    }

    .kitchen-unit-panels .kitchen-unit-panel.active {
        display: block;
    }

    .stock-picker-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid #ebe4dc;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        width: 100%;
        font-family: inherit;
        text-align: left;
        transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
    }

    .stock-picker-item:hover {
        border-color: #d7c4b0;
        background: #fffcf8;
    }

    .stock-picker-item.selected {
        border-color: #8b4513;
        background: #fbf3eb;
        box-shadow: 0 2px 10px rgba(139, 69, 19, 0.1);
    }

    .stock-picker-item-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: #2d2a26;
        line-height: 1.35;
    }

    .stock-picker-item-price {
        font-size: 0.8125rem;
        color: #8b4513;
        font-weight: 500;
        margin-top: 3px;
    }

    .stock-picker-item-check {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 1.5px solid #d5cdc3;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6875rem;
        font-weight: 700;
        color: transparent;
        transition: all 0.15s;
    }

    .stock-picker-item.selected .stock-picker-item-check {
        border-color: #8b4513;
        background: #8b4513;
        color: #fff;
    }

    .stock-modal-footer {
        padding: 18px 22px 22px;
        border-top: 1px solid #f0ebe3;
        background: #faf8f5;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .stock-footer-selected {
        padding: 14px 16px;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #ebe4dc;
    }

    .stock-footer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 44px;
    }

    .stock-footer-detail {
        flex: 1;
        min-width: 0;
    }

    .stock-footer-name {
        display: block;
        font-weight: 600;
        font-size: 0.9375rem;
        color: #2d2a26;
        line-height: 1.35;
        word-break: break-word;
    }

    .stock-footer-name.is-empty {
        color: #b0a89e;
        font-style: normal;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .stock-footer-price {
        display: block;
        font-size: 0.8125rem;
        color: #8b4513;
        font-weight: 600;
        margin-top: 2px;
    }

    .stock-footer-price:empty { display: none; }

    .stock-footer-area-badge {
        flex-shrink: 0;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 6px 10px;
        border-radius: 8px;
        line-height: 1;
        white-space: nowrap;
    }

    .stock-footer-area-badge.counter {
        color: #8b4513;
        background: #fbf3eb;
        border: 1px solid #e8d5c4;
    }

    .stock-footer-area-badge.kitchen {
        color: #2e7d32;
        background: #e8f5e9;
        border: 1px solid #c8e6c9;
    }

    .stock-confirm-btn {
        width: 100%;
        padding: 14px 20px;
        border: none;
        border-radius: 12px;
        background: #8b4513;
        color: #fff;
        font-family: inherit;
        font-weight: 700;
        font-size: 0.9375rem;
        letter-spacing: 0.02em;
        cursor: pointer;
        transition: opacity 0.2s, transform 0.15s, box-shadow 0.15s;
    }

    .stock-confirm-btn:hover:not(:disabled) {
        box-shadow: 0 4px 14px rgba(139, 69, 19, 0.25);
    }

    .stock-confirm-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    /* Edit modal */
    .stock-edit-body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 20px 22px 18px;
        -webkit-overflow-scrolling: touch;
    }

    .stock-edit-modal-footer {
        flex-shrink: 0;
    }

    .stock-edit-modal-footer .stock-confirm-btn {
        margin: 0;
    }

    .stock-edit-section-label {
        margin: 0 0 12px;
        font-size: 0.8125rem;
        text-transform: none;
        letter-spacing: 0;
        color: #333;
        font-weight: 600;
    }

    #stock-edit-modal .stock-unit-chips {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 18px;
    }

    .stock-unit-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    #stock-edit-modal .stock-unit-chip {
        min-width: 0;
        width: 100%;
        padding: 9px 8px;
        font-size: 0.75rem;
        text-align: center;
    }

    .stock-unit-panels {
        margin-top: 2px;
    }

    .stock-unit-chip {
        border: 1px solid #e8e0d6;
        background: #fff;
        padding: 9px 16px;
        border-radius: 20px;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        color: #6b635a;
        transition: all 0.15s;
    }

    .stock-unit-chip.active {
        background: #8b4513;
        border-color: #8b4513;
        color: #fff;
    }

    .stock-unit-panel { display: none; }
    .stock-unit-panel.active { display: block; }

    .stock-unit-panel label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #444;
        margin-bottom: 10px;
    }

    .stock-unit-panel input {
        width: 100%;
        box-sizing: border-box;
        padding: 13px 16px;
        border: 1px solid #e0d6cc;
        border-radius: 10px;
        font-size: 0.9375rem;
        font-family: inherit;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        -moz-appearance: textfield;
    }

    .stock-unit-panel input::-webkit-outer-spin-button,
    .stock-unit-panel input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .stock-unit-panel input:focus {
        border-color: #8b4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
    }

    .stock-unit-hint {
        display: block;
        margin-top: 10px;
        font-size: 0.75rem;
        color: #9a8f85;
        line-height: 1.45;
    }

    @media (max-width: 768px) {
        .stock-view-tabs {
            max-width: none;
            flex-direction: column;
            margin: 16px 0 20px;
        }

        .stock-list-card .menu-list-container {
            padding: 8px 16px 28px;
        }

        .stock-list-search-wrap {
            padding: 14px 16px 0;
        }

        .stock-list-search {
            max-width: none;
            height: 46px;
            font-size: 0.9rem;
            border-radius: 999px;
        }

        .stock-page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .stock-page-header .btn-shine {
            width: 100%;
            margin-top: 0;
        }

        .stock-modal-card {
            max-width: 100%;
            max-height: 92vh;
            border-radius: 18px;
        }

        .stock-modal-header,
        .stock-area-select-wrap,
        .stock-modal-search-wrap,
        .stock-picker-grid,
        .stock-modal-footer,
        .kitchen-manual-form-scroll,
        .kitchen-manual-form-footer {
            padding-left: 18px;
            padding-right: 18px;
        }

        .stock-view-body,
        .stock-view-modal-footer {
            padding-left: 18px;
            padding-right: 18px;
        }

        #stock-view-modal .stock-modal-card {
            max-width: 100%;
        }

        .stock-view-stats {
            grid-template-columns: 1fr;
        }

        .stock-view-day-grid,
        .stock-view-day-grid.kitchen-four {
            grid-template-columns: 1fr 1fr;
        }

        .kitchen-unit-chips .stock-unit-chip {
            min-width: calc(50% - 6px);
            font-size: 0.8125rem;
        }

        #stock-edit-modal .stock-unit-chips {
            grid-template-columns: repeat(2, 1fr);
        }

        .stock-edit-body {
            padding: 18px 18px 14px;
        }
    }
</style>

<script>
    const csrfToken = '<?php echo csrf_token(); ?>';
    let allMenuItems = [];
    let selectedStockItemId = null;
    let selectedStockArea = 'counter';
    let menuItemsLoaded = false;
    let menuItemsFetchController = null;
    let menuPickerReqSeq = 0;
    const MENU_LIST_API = '<?php echo BASE_URL; ?>api/menu_action.php?action=list';
    const MENU_FETCH_TIMEOUT_MS = 12000;
    let stockTrackedItems = [];
    let currentStockView = 'counter';
    let editingStockItemId = null;
    let editingStockArea = null;
    let stockViewItemId = null;
    let stockViewArea = null;
    let stockViewSelectedDate = '';
    let stockViewFetchController = null;
    let stockViewFetchSeq = 0;
    let stockViewItemSnapshot = null;
    let stockViewDateDebounceTimer = null;
    const stockViewDetailCache = new Map();
    const STOCK_VIEW_CACHE_MAX = 120;
    let currentStockUnit = 'piece';
    let kitchenAddUnit = 'piece';
    let lastStockSnapshot = '';

    function isStockUiBusy() {
        const add = document.getElementById('stock-add-modal');
        const edit = document.getElementById('stock-edit-modal');
        const view = document.getElementById('stock-view-modal');
        const confirm = document.getElementById('stock-confirm-modal');
        return (add && add.style.display === 'flex')
            || (edit && edit.style.display === 'flex')
            || (view && view.style.display === 'flex')
            || (confirm && confirm.style.display === 'flex');
    }

    let stockConfirmCallback = null;

    function showStockConfirm(title, messageHtml, confirmLabel, onConfirm) {
        const modal = document.getElementById('stock-confirm-modal');
        const titleEl = document.getElementById('stock-confirm-title');
        const msgEl = document.getElementById('stock-confirm-msg');
        const okBtn = document.getElementById('stock-confirm-ok-btn');
        if (!modal || !titleEl || !msgEl || !okBtn) return;
        titleEl.textContent = title;
        msgEl.innerHTML = messageHtml;
        okBtn.textContent = confirmLabel || 'Remove';
        stockConfirmCallback = onConfirm;
        modal.style.display = 'flex';
        document.body.classList.add('no-scroll');
        setTimeout(() => okBtn.focus(), 50);
    }

    function closeStockConfirm(confirmed) {
        const modal = document.getElementById('stock-confirm-modal');
        const cb = confirmed ? stockConfirmCallback : null;
        stockConfirmCallback = null;
        if (modal) modal.style.display = 'none';
        if (!isStockUiBusy()) {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
        if (cb) cb();
    }

    function onStockConfirmBackdrop(e) {
        if (e.target.id === 'stock-confirm-modal') closeStockConfirm(false);
    }

    function stockItemsSnapshot(items) {
        return JSON.stringify((items || []).map(i => ({
            id: i.id,
            area: i.area,
            stockQty: i.stockQty,
            trackStock: i.trackStock,
            trackSold: i.trackSold,
            totalSoldPieces: i.totalSoldPieces,
            unit: i.unit,
            unitValue: i.unitValue
        })));
    }

    const STOCK_AREA_LABELS = { counter: 'Tracking stock automatic', kitchen: 'Kitchen Stock' };
    const STOCK_UNIT_DEFS = [
        { id: 'piece', label: 'Piece', group: 'count' },
        { id: 'kg', label: 'Kg', group: 'kg' },
        { id: 'gram', label: 'Gram', group: 'gram' },
        { id: 'liter', label: 'Liter', group: 'liter' },
        { id: 'bora', label: 'Bora', group: 'count' },
        { id: 'packet', label: 'Packet', group: 'count' },
        { id: 'crate', label: 'Crate', group: 'count' },
        { id: 'plastic', label: 'Plastic', group: 'count' },
        { id: 'poka', label: 'Poka', group: 'count' },
        { id: 'glass', label: 'Glass', group: 'count' }
    ];
    const STOCK_UNIT_LABELS = Object.fromEntries(STOCK_UNIT_DEFS.map(u => [u.id, u.label]));
    const STOCK_COUNT_UNITS = STOCK_UNIT_DEFS.filter(u => u.group === 'count').map(u => u.id);

    function stockUnitPanelGroup(unit) {
        const def = STOCK_UNIT_DEFS.find(u => u.id === unit);
        return def ? def.group : 'count';
    }

    function getStockEditInputId(unit) {
        return stockUnitPanelGroup(unit) === 'count' ? 'stock-input-count' : 'stock-input-' + unit;
    }

    function getKitchenAmountInputId(unit) {
        return stockUnitPanelGroup(unit) === 'count' ? 'kitchen-input-count' : 'kitchen-input-' + unit;
    }

    function renderStockUnitChips(containerId, activeUnit, onSelectFn) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = STOCK_UNIT_DEFS.map(u =>
            `<button type="button" class="stock-unit-chip${u.id === activeUnit ? ' active' : ''}" data-unit="${u.id}" onclick="${onSelectFn}('${u.id}')">${escapeHtml(u.label)}</button>`
        ).join('');
    }

    function updateCountUnitLabel(labelElId, unit) {
        const el = document.getElementById(labelElId);
        if (!el) return;
        el.textContent = 'Total Quantity';
    }
    const STOCK_API = '<?php echo BASE_URL; ?>api/stock_action.php';
    const STOCK_SERVER_TODAY = '<?php echo date('Y-m-d'); ?>';

    function stockPost(action, data) {
        const payload = Object.assign({ csrf_token: csrfToken }, data || {});
        return App.request(STOCK_API + '?action=' + encodeURIComponent(action), 'POST', payload);
    }

    function mapStockItem(row) {
        return {
            id: row.id,
            stockId: row.stockId,
            name: row.name,
            price: row.price,
            area: row.area,
            unit: row.unit,
            unitValue: row.unitValue != null ? Number(row.unitValue) : null,
            trackStock: !!row.trackStock,
            stockQty: row.stockQty != null ? Number(row.stockQty) : 0,
            addedAt: row.addedAt || null,
            totalSoldPieces: row.totalSoldPieces != null ? Number(row.totalSoldPieces) : 0,
            totalUsedUnits: row.totalUsedUnits != null ? Number(row.totalUsedUnits) : 0,
            trackSold: !!row.trackSold,
            stockTrackingSince: row.stockTrackingSince || null,
            soldTrackingSince: row.soldTrackingSince || null,
            historySince: row.historySince || null,
            monthSold: row.monthSold != null ? Number(row.monthSold) : 0,
            monthAdded: row.monthAdded != null ? Number(row.monthAdded) : 0,
            monthLabel: row.monthLabel || ''
        };
    }

    function usesStockQtyAdjustPanel() {
        if (STOCK_COUNT_UNITS.includes(currentStockUnit)) {
            if (editingStockArea === 'counter' && currentStockUnit === 'piece') return true;
            if (editingStockArea === 'kitchen') return true;
        }
        return false;
    }

    function updateStockQtyFieldLabels() {
        const unitName = (STOCK_UNIT_LABELS[currentStockUnit] || currentStockUnit || 'units').toLowerCase();
        const setLabel = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };
        if (editingStockArea === 'counter' && currentStockUnit === 'piece') {
            setLabel('stock-qty-edit-label', 'Total pieces in stock');
            setLabel('stock-qty-add-label', 'How many pieces to add?');
            setLabel('stock-qty-remove-label', 'How many pieces were used?');
        } else if (editingStockArea === 'kitchen') {
            setLabel('stock-qty-edit-label', 'Total quantity (' + unitName + ')');
            setLabel('stock-qty-add-label', 'How many ' + unitName + ' to add?');
            setLabel('stock-qty-remove-label', 'How many ' + unitName + ' were used?');
        }
    }

    function updateStockEditQtyPanels() {
        const piecePanel = document.getElementById('stock-piece-adjust-panel');
        const otherPanel = document.getElementById('stock-other-count-panel');
        const useQtyAdjust = usesStockQtyAdjustPanel();
        const actionView = document.getElementById('stock-qty-action-view');
        const useBtn = document.getElementById('btn-stock-kitchen-use');
        const isKitchen = editingStockArea === 'kitchen';
        if (piecePanel) piecePanel.style.display = useQtyAdjust ? 'block' : 'none';
        if (otherPanel) otherPanel.style.display = useQtyAdjust ? 'none' : 'block';
        if (useBtn) useBtn.style.display = (isKitchen && useQtyAdjust) ? 'block' : 'none';
        if (actionView) actionView.classList.toggle('single-col', !(isKitchen && useQtyAdjust));
        updateStockQtyFieldLabels();
    }

    function refreshStockEditCurrentQty() {
        const el = document.getElementById('stock-edit-current-qty');
        if (!el || !editingStockItemId || !editingStockArea) return;
        const item = stockTrackedItems.find(i =>
            String(i.id) === String(editingStockItemId) && i.area === editingStockArea);
        if (!item) return;
        if (editingStockArea === 'counter' && currentStockUnit === 'piece') {
            el.textContent = (item.stockQty != null ? item.stockQty : 0) + ' pieces';
            return;
        }
        el.textContent = formatStockUnitLabel(item) || '0';
    }

    function startStockQtyEdit() {
        if (!editingStockItemId || !usesStockQtyAdjustPanel()) return;
        cancelStockQtyAdd();
        cancelStockQtyRemove();
        const item = stockTrackedItems.find(i =>
            String(i.id) === String(editingStockItemId) && i.area === editingStockArea);
        if (!item) return;
        const view = document.getElementById('stock-current-qty-view');
        const edit = document.getElementById('stock-current-qty-edit');
        const input = document.getElementById('stock-qty-edit-input');
        if (!view || !edit || !input) return;
        const currentQty = editingStockArea === 'kitchen'
            ? (item.unitValue != null ? item.unitValue : 0)
            : (item.stockQty != null ? item.stockQty : 0);
        input.value = currentQty;
        view.style.display = 'none';
        edit.style.display = 'flex';
        input.focus();
        input.select();
    }

    function cancelStockQtyEdit() {
        const view = document.getElementById('stock-current-qty-view');
        const edit = document.getElementById('stock-current-qty-edit');
        const input = document.getElementById('stock-qty-edit-input');
        if (view) view.style.display = 'flex';
        if (edit) edit.style.display = 'none';
        if (input) input.value = '';
    }

    function startStockQtyAdd() {
        if (!editingStockItemId || !usesStockQtyAdjustPanel()) return;
        cancelStockQtyEdit();
        cancelStockQtyRemove();
        const view = document.getElementById('stock-qty-action-view');
        const form = document.getElementById('stock-qty-add-form');
        const input = document.getElementById('stock-qty-add-input');
        if (!view || !form || !input) return;
        input.value = '';
        view.style.display = 'none';
        form.style.display = 'flex';
        input.focus();
    }

    function cancelStockQtyAdd() {
        const view = document.getElementById('stock-qty-action-view');
        const form = document.getElementById('stock-qty-add-form');
        const input = document.getElementById('stock-qty-add-input');
        if (view) view.style.display = 'grid';
        if (form) form.style.display = 'none';
        if (input) input.value = '';
    }

    function startStockQtyRemove() {
        if (!editingStockItemId || editingStockArea !== 'kitchen' || !usesStockQtyAdjustPanel()) return;
        cancelStockQtyEdit();
        cancelStockQtyAdd();
        const view = document.getElementById('stock-qty-action-view');
        const form = document.getElementById('stock-qty-remove-form');
        const input = document.getElementById('stock-qty-remove-input');
        if (!view || !form || !input) return;
        input.value = '';
        view.style.display = 'none';
        form.style.display = 'flex';
        input.focus();
    }

    function cancelStockQtyRemove() {
        const view = document.getElementById('stock-qty-action-view');
        const form = document.getElementById('stock-qty-remove-form');
        const input = document.getElementById('stock-qty-remove-input');
        if (view) view.style.display = 'grid';
        if (form) form.style.display = 'none';
        if (input) input.value = '';
    }

    function getStockEditTrackingPayload() {
        const payload = {};
        if (editingStockArea !== 'counter') return payload;
        const trackStock = document.getElementById('stock-edit-track-stock');
        const trackSold = document.getElementById('stock-edit-track-sold');
        if (trackStock && trackSold) {
            payload.track_stock = trackStock.checked ? '1' : '0';
            payload.track_sold = trackSold.checked ? '1' : '0';
        }
        return payload;
    }

    function stockEditTrackingChanged(item) {
        if (editingStockArea !== 'counter' || !item) return false;
        const trackStock = document.getElementById('stock-edit-track-stock');
        const trackSold = document.getElementById('stock-edit-track-sold');
        if (!trackStock || !trackSold) return false;
        return !!trackStock.checked !== !!item.trackStock || !!trackSold.checked !== !!item.trackSold;
    }

    function applyStockQtyAdjust(mode, inputId, closeAfter) {
        if (!editingStockItemId || !usesStockQtyAdjustPanel()) return;
        const input = document.getElementById(inputId || 'stock-qty-edit-input');
        const raw = input ? input.value.trim() : '';
        const check = stockValidateNumericValue(raw, 0);
        if (!check.ok) {
            App.showToast(check.msg, 'error');
            return;
        }
        if (mode === 'add' && check.value <= 0) {
            App.showToast('Enter how many to add', 'error');
            return;
        }
        stockPost('adjust_qty', Object.assign({
            menu_item_id: editingStockItemId,
            area: editingStockArea,
            mode: mode,
            qty: check.value
        }, getStockEditTrackingPayload()))
            .then(data => {
                if (data && data.success && data.item) {
                    upsertStockItem(mapStockItem(data.item));
                    lastStockSnapshot = stockItemsSnapshot(stockTrackedItems);
                    refreshStockEditCurrentQty();
                    if (mode === 'set') {
                        cancelStockQtyEdit();
                    } else if (mode === 'subtract') {
                        cancelStockQtyRemove();
                    } else {
                        cancelStockQtyAdd();
                    }
                    if (document.getElementById('stock-view-modal').style.display === 'flex' &&
                        String(stockViewItemId) === String(editingStockItemId) &&
                        stockViewArea === editingStockArea) {
                        invalidateStockViewCache(stockViewItemId, stockViewArea);
                        loadStockViewDetail(
                            stockViewItemId,
                            stockViewArea,
                            stockViewSelectedDate || stockTodayYmd(),
                            { force: true }
                        );
                    }
                    const unitName = (STOCK_UNIT_LABELS[currentStockUnit] || currentStockUnit || '').toLowerCase();
                    let toastMsg;
                    if (mode === 'add') {
                        toastMsg = editingStockArea === 'kitchen'
                            ? ('Added ' + check.value + ' ' + unitName)
                            : ('Added ' + check.value + ' pieces');
                    } else if (mode === 'subtract') {
                        toastMsg = 'Used ' + check.value + ' ' + unitName;
                    } else {
                        toastMsg = 'Stock updated to ' + check.value;
                    }
                    App.showToast(toastMsg);
                    if (closeAfter) closeStockEditModal();
                } else {
                    App.showToast(data && data.error ? data.error : 'Could not update stock', 'error');
                }
            })
            .catch(() => App.showToast('Connection error. Try again.', 'error'));
    }

    function applyStockQtyUpdate() {
        applyStockQtyAdjust('set', 'stock-qty-edit-input');
    }

    function applyStockQtyRemove(closeAfter) {
        applyStockQtyAdjust('subtract', 'stock-qty-remove-input', closeAfter);
    }

    function applyStockQtyAdd(closeAfter) {
        applyStockQtyAdjust('add', 'stock-qty-add-input', closeAfter);
    }

    function formatStockHistoryType(m) {
        const ch = parseFloat(m.qty_change);
        if (m.movement_type === 'sale' && ch > 0) return 'Returned';
        if (m.movement_type === 'sale') return 'Sold';
        if (m.movement_type === 'setup') return 'First add';
        if (m.movement_type === 'count') return 'Edited';
        if (m.movement_type === 'add') return 'Added';
        if (m.movement_type === 'adjust') return ch < 0 ? 'Used' : 'Returned';
        if (ch > 0) return 'Added';
        if (ch < 0) return 'Used';
        return m.movement_type || 'Change';
    }

    function formatStockDateTime(raw) {
        if (!raw) return '—';
        const d = new Date(String(raw).trim().replace(' ', 'T'));
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleString('en-NP', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatStockAddedDate(raw) {
        if (!raw) return '—';
        const normalized = String(raw).trim().replace(' ', 'T');
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-NP', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function upsertStockItem(item) {
        const idx = stockTrackedItems.findIndex(i => String(i.id) === String(item.id) && i.area === item.area);
        if (idx >= 0) stockTrackedItems[idx] = item;
        else stockTrackedItems.push(item);
        renderStockTrackedList();
    }

    function loadStockItems(silent) {
        if (silent && isStockUiBusy()) {
            return;
        }
        App.request(STOCK_API + '?action=list')
            .then(data => {
                if (data && data.success && Array.isArray(data.items)) {
                    const next = data.items.map(mapStockItem);
                    const snap = stockItemsSnapshot(next);
                    if (silent && snap === lastStockSnapshot) {
                        return;
                    }
                    lastStockSnapshot = snap;
                    stockTrackedItems = next;
                    stockViewDetailCache.clear();
                    renderStockTrackedList();
                } else if (!silent) {
                    App.showToast(data && data.error ? data.error : 'Could not load stock items', 'error');
                }
            })
            .catch(() => {
                if (!silent) App.showToast('Connection error loading stock', 'error');
            });
    }

    function startStockPolling() {
        setInterval(() => loadStockItems(true), 30000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !isStockUiBusy()) loadStockItems(true);
        });
    }

    function stockSanitizeNumericInput(input, maxDecimals) {
        if (!input || input.dataset.stockNumericBound) return;
        input.dataset.stockNumericBound = '1';
        input.addEventListener('input', function () {
            let v = this.value;
            if (maxDecimals === 0) {
                this.value = v.replace(/\D/g, '');
                return;
            }
            v = v.replace(/[^\d.]/g, '');
            const dot = v.indexOf('.');
            if (dot !== -1) {
                v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
                const parts = v.split('.');
                v = parts[0] + '.' + (parts[1] || '').slice(0, maxDecimals);
            }
            this.value = v;
        });
        input.addEventListener('blur', function () {
            if (this.value === '' || this.value === '.') {
                this.value = '';
                return;
            }
            const n = parseFloat(this.value);
            if (isNaN(n) || n < 0) {
                this.value = '';
                return;
            }
            if (maxDecimals === 0) {
                this.value = String(Math.floor(n));
            } else {
                this.value = String(parseFloat(n.toFixed(maxDecimals)));
            }
        });
    }

    function stockValidateNumericValue(raw, maxDecimals) {
        const s = (raw || '').trim();
        if (s === '') {
            return { ok: false, msg: 'Enter a value' };
        }
        if (maxDecimals === 0) {
            if (!/^\d+$/.test(s)) {
                return { ok: false, msg: 'Use whole numbers only (no decimals)' };
            }
            return { ok: true, value: parseInt(s, 10) };
        }
        if (!/^\d+(\.\d{1,2})?$/.test(s)) {
            return { ok: false, msg: 'Use whole numbers or up to 2 decimal places only' };
        }
        return { ok: true, value: parseFloat(parseFloat(s).toFixed(2)) };
    }

    function initStockNumericInputs() {
        stockSanitizeNumericInput(document.getElementById('kitchen-manual-price'), 2);
        stockSanitizeNumericInput(document.getElementById('kitchen-input-count'), 0);
        stockSanitizeNumericInput(document.getElementById('kitchen-input-gram'), 0);
        stockSanitizeNumericInput(document.getElementById('kitchen-input-kg'), 2);
        stockSanitizeNumericInput(document.getElementById('kitchen-input-liter'), 2);
        stockSanitizeNumericInput(document.getElementById('stock-qty-edit-input'), 0);
        stockSanitizeNumericInput(document.getElementById('stock-qty-add-input'), 0);
        stockSanitizeNumericInput(document.getElementById('stock-qty-remove-input'), 0);
        stockSanitizeNumericInput(document.getElementById('stock-input-count'), 0);
        stockSanitizeNumericInput(document.getElementById('stock-input-kg'), 2);
        stockSanitizeNumericInput(document.getElementById('stock-input-gram'), 0);
        stockSanitizeNumericInput(document.getElementById('stock-input-liter'), 2);
    }

    function stockAmountDecimals(unit) {
        return stockUnitPanelGroup(unit) === 'count' || unit === 'gram' ? 0 : 2;
    }

    function switchStockView(area) {
        currentStockView = area;
        localStorage.setItem('stock_active_view', area);

        document.querySelectorAll('.stock-view-tab').forEach(tab => {
            tab.classList.toggle('active', tab.getAttribute('data-area') === area);
        });
        document.querySelectorAll('.stock-view-panel').forEach(panel => {
            panel.classList.toggle('active', panel.id === 'stock-view-' + area);
        });

        updateStockViewHint();
    }

    function updateStockViewHint() {
        const hint = document.getElementById('stock-main-hint');
        const items = stockTrackedItems.filter(i => i.area === currentStockView);
        if (items.length) hint.classList.add('hidden');
        else hint.classList.remove('hidden');
    }

    function selectStockArea(area) {
        selectedStockArea = area;
        document.querySelectorAll('.stock-area-chip').forEach(chip => {
            chip.classList.toggle('active', chip.getAttribute('data-area') === area);
        });
        updateAddStockMode();
        if (area === 'counter') {
            ensureMenuItemsLoaded(true);
            updateStockFooter();
        }
    }

    function updateAddStockMode() {
        const isKitchen = selectedStockArea === 'kitchen';
        const sub = document.getElementById('stock-add-modal-sub');
        const counterPanel = document.getElementById('stock-add-counter-panel');
        const kitchenPanel = document.getElementById('stock-add-kitchen-panel');
        const loading = document.getElementById('stock-picker-loading');

        if (counterPanel) counterPanel.style.display = isKitchen ? 'none' : 'flex';
        if (kitchenPanel) kitchenPanel.style.display = isKitchen ? 'flex' : 'none';

        if (loading && isKitchen) {
            loading.style.display = 'none';
        }

        if (sub) {
            sub.textContent = isKitchen
                ? 'Add ingredients and supplies manually'
                : 'Tap an item to select · tap again to undo';
        }

        if (isKitchen) {
            hideKitchenManualForm(false);
        } else if (menuItemsLoaded) {
            if (loading) loading.style.display = 'none';
        }
    }

    function resetKitchenManualForm() {
        document.getElementById('kitchen-manual-name').value = '';
        document.getElementById('kitchen-manual-price').value = '';
        document.getElementById('kitchen-input-count').value = '';
        document.getElementById('kitchen-input-kg').value = '';
        document.getElementById('kitchen-input-gram').value = '';
        document.getElementById('kitchen-input-liter').value = '';
        kitchenAddUnit = 'piece';
        selectKitchenAddUnit('piece');
    }

    function showKitchenManualForm() {
        document.getElementById('kitchen-manual-intro').style.display = 'none';
        const form = document.getElementById('kitchen-manual-form');
        form.style.display = 'flex';
        resetKitchenManualForm();
        setTimeout(() => document.getElementById('kitchen-manual-name').focus(), 100);
    }

    function hideKitchenManualForm(resetForm) {
        document.getElementById('kitchen-manual-intro').style.display = 'flex';
        document.getElementById('kitchen-manual-form').style.display = 'none';
        if (resetForm !== false) resetKitchenManualForm();
    }

    function selectKitchenAddUnit(unit) {
        kitchenAddUnit = unit;
        document.querySelectorAll('.kitchen-unit-chips .stock-unit-chip').forEach(chip => {
            chip.classList.toggle('active', chip.getAttribute('data-unit') === unit);
        });
        const group = stockUnitPanelGroup(unit);
        document.querySelectorAll('.kitchen-unit-panels .kitchen-unit-panel').forEach(panel => {
            panel.classList.toggle('active', panel.getAttribute('data-unit-group') === group);
        });
        if (group === 'count') {
            updateCountUnitLabel('kitchen-count-label', unit);
        }
    }

    function confirmKitchenManualAdd() {
        const name = (document.getElementById('kitchen-manual-name').value || '').trim();
        const priceRaw = (document.getElementById('kitchen-manual-price').value || '').trim();
        const input = document.getElementById(getKitchenAmountInputId(kitchenAddUnit));
        const amountRaw = input ? input.value.trim() : '';

        if (!name) {
            App.showToast('Enter item name', 'error');
            return;
        }
        const priceCheck = stockValidateNumericValue(priceRaw, 2);
        if (!priceCheck.ok) {
            App.showToast(priceCheck.msg, 'error');
            return;
        }
        const amountCheck = stockValidateNumericValue(amountRaw, stockAmountDecimals(kitchenAddUnit));
        if (!amountCheck.ok) {
            App.showToast(amountCheck.msg, 'error');
            return;
        }

        const btn = document.getElementById('kitchen-manual-confirm-btn');
        btn.disabled = true;
        stockPost('add_kitchen_manual', {
            name: name,
            price: priceCheck.value,
            unit: kitchenAddUnit,
            unit_value: amountCheck.value
        })
            .then(data => {
                btn.disabled = false;
                if (data && data.success && data.item) {
                    upsertStockItem(mapStockItem(data.item));
                    App.showToast(name + ' added to Kitchen Stock');
                    closeAddStockCount();
                    if (currentStockView !== 'kitchen') switchStockView('kitchen');
                } else {
                    App.showToast(data && data.error ? data.error : 'Could not add item', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                App.showToast('Connection error. Try again.', 'error');
            });
    }

    function openAddStockCount() {
        document.getElementById('stock-add-modal').style.display = 'flex';
        document.body.classList.add('no-scroll');
        document.documentElement.classList.add('no-scroll');
        selectedStockItemId = null;
        selectStockArea(currentStockView);
        document.getElementById('stock-item-search').value = '';
        hideKitchenManualForm(false);
        updateAddStockMode();
        if (selectedStockArea === 'counter') {
            updateStockFooter();
            ensureMenuItemsLoaded(true);
            setTimeout(() => document.getElementById('stock-item-search').focus(), 150);
        }
    }

    function closeAddStockCount() {
        document.getElementById('stock-add-modal').style.display = 'none';
        hideKitchenManualForm();
        setStockPickerLoadingState(false);
        if (document.getElementById('stock-edit-modal').style.display !== 'flex') {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
    }

    function onStockModalBackdrop(e) {
        if (e.target.id === 'stock-add-modal') closeAddStockCount();
    }

    function setStockPickerLoadingState(visible, message, retryable) {
        const loading = document.getElementById('stock-picker-loading');
        if (!loading) return;
        if (!visible) {
            loading.style.display = 'none';
            loading.classList.remove('stock-picker-retry');
            return;
        }
        loading.textContent = message || 'Loading menu items…';
        loading.style.display = 'block';
        loading.classList.toggle('stock-picker-retry', !!retryable);
    }

    function showMenuPickerLoadError(message) {
        menuItemsLoaded = false;
        setStockPickerLoadingState(true, message + ' Tap to retry.', true);
    }

    function cancelMenuItemsFetch() {
        if (menuItemsFetchController) {
            menuItemsFetchController.abort();
            menuItemsFetchController = null;
        }
    }

    function retryLoadMenuItemsForStock() {
        const loading = document.getElementById('stock-picker-loading');
        if (!loading || !loading.classList.contains('stock-picker-retry')) return;
        cancelMenuItemsFetch();
        menuItemsLoaded = false;
        ensureMenuItemsLoaded(true);
    }

    function preloadMenuItemsForStock() {
        if (menuItemsLoaded || menuItemsFetchController) return;
        ensureMenuItemsLoaded(false);
    }

    function ensureMenuItemsLoaded(showLoadingUi) {
        const grid = document.getElementById('stock-picker-grid');
        const modalOpen = document.getElementById('stock-add-modal').style.display === 'flex';
        const onCounter = selectedStockArea === 'counter';

        if (menuItemsLoaded) {
            if (showLoadingUi && onCounter && modalOpen) {
                setStockPickerLoadingState(false);
                filterStockPicker();
            }
            return;
        }

        if (menuItemsFetchController) {
            if (showLoadingUi && onCounter && modalOpen) {
                setStockPickerLoadingState(true, 'Loading menu items…', false);
            }
            return;
        }

        if (showLoadingUi && onCounter && modalOpen) {
            setStockPickerLoadingState(true, 'Loading menu items…', false);
            if (grid) grid.innerHTML = '';
        }

        const reqId = ++menuPickerReqSeq;
        const controller = new AbortController();
        menuItemsFetchController = controller;
        const timeoutId = setTimeout(() => controller.abort(), MENU_FETCH_TIMEOUT_MS);

        App.request(MENU_LIST_API, 'GET', null, { signal: controller.signal })
            .then(data => {
                clearTimeout(timeoutId);
                if (reqId !== menuPickerReqSeq) return;
                menuItemsFetchController = null;

                if (data && data.success && Array.isArray(data.items)) {
                    allMenuItems = data.items;
                    menuItemsLoaded = true;
                    const stillCounter =
                        selectedStockArea === 'counter' &&
                        document.getElementById('stock-add-modal').style.display === 'flex';
                    if (stillCounter) {
                        setStockPickerLoadingState(false);
                        filterStockPicker();
                    }
                    return;
                }

                const errMsg = (data && data.error) ? data.error : 'Could not load menu items.';
                if (onCounter && modalOpen) showMenuPickerLoadError(errMsg);
            })
            .catch(() => {
                clearTimeout(timeoutId);
                if (reqId !== menuPickerReqSeq) return;
                menuItemsFetchController = null;
                if (onCounter && modalOpen) {
                    showMenuPickerLoadError('Connection error or timed out.');
                }
            });
    }

    function filterStockPicker() {
        const q = (document.getElementById('stock-item-search').value || '').toLowerCase().trim();
        const grid = document.getElementById('stock-picker-grid');
        const empty = document.getElementById('stock-picker-empty');
        if (!menuItemsLoaded) {
            if (empty) empty.style.display = 'none';
            return;
        }
        const trackedIds = stockTrackedItems
            .filter(i => i.area === selectedStockArea)
            .map(i => String(i.id));
        const filtered = allMenuItems.filter(item =>
            !trackedIds.includes(String(item.id)) && (!q || item.name.toLowerCase().includes(q))
        );
        if (!filtered.length) {
            grid.innerHTML = '';
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';
        grid.innerHTML = filtered.map(item => {
            const selected = String(item.id) === String(selectedStockItemId);
            const price = parseFloat(item.price).toFixed(0);
            return `<button type="button" class="stock-picker-item${selected ? ' selected' : ''}" onclick="selectStockItem(${item.id})">
                <div><div class="stock-picker-item-name">${escapeHtml(item.name)}</div>
                <div class="stock-picker-item-price">Rs. ${price}</div></div>
                <span class="stock-picker-item-check">${selected ? '✓' : ''}</span></button>`;
        }).join('');
    }

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function updateStockFooter() {
        const nameEl = document.getElementById('stock-footer-name');
        const priceEl = document.getElementById('stock-footer-price');
        const areaEl = document.getElementById('stock-footer-area');
        const btn = document.getElementById('stock-confirm-btn');

        const areaKey = selectedStockArea === 'kitchen' ? 'kitchen' : 'counter';
        areaEl.textContent = areaKey === 'kitchen' ? 'Kitchen' : 'Counter';
        areaEl.className = 'stock-footer-area-badge ' + areaKey;

        if (!selectedStockItemId) {
            nameEl.textContent = 'Select an item from the list';
            nameEl.classList.add('is-empty');
            priceEl.textContent = '';
            btn.disabled = true;
            return;
        }
        const item = allMenuItems.find(i => String(i.id) === String(selectedStockItemId));
        if (!item) {
            nameEl.textContent = 'Select an item from the list';
            nameEl.classList.add('is-empty');
            priceEl.textContent = '';
            btn.disabled = true;
            return;
        }
        nameEl.textContent = item.name;
        nameEl.classList.remove('is-empty');
        priceEl.textContent = 'Rs. ' + parseFloat(item.price).toFixed(0);
        btn.disabled = false;
    }

    function selectStockItem(id) {
        if (String(selectedStockItemId) === String(id)) selectedStockItemId = null;
        else selectedStockItemId = id;
        filterStockPicker();
        updateStockFooter();
    }

    function confirmAddStock() {
        if (!selectedStockItemId) {
            App.showToast('Select a menu item first', 'error');
            return;
        }
        const item = allMenuItems.find(i => String(i.id) === String(selectedStockItemId));
        if (!item) return;
        if (stockTrackedItems.some(i => String(i.id) === String(item.id) && i.area === selectedStockArea)) {
            App.showToast('Item already in ' + STOCK_AREA_LABELS[selectedStockArea], 'error');
            return;
        }
        const btn = document.getElementById('stock-confirm-btn');
        btn.disabled = true;
        stockPost('add', { menu_item_id: item.id, area: selectedStockArea })
            .then(data => {
                btn.disabled = false;
                if (data && data.success && data.item) {
                    upsertStockItem(mapStockItem(data.item));
                    App.showToast(item.name + ' added to ' + STOCK_AREA_LABELS[selectedStockArea]);
                    closeAddStockCount();
                } else {
                    App.showToast(data && data.error ? data.error : 'Could not add item', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                App.showToast('Connection error. Try again.', 'error');
            });
    }

    function renderStockRow(item, index) {
        const meta = formatStockUnitLabel(item);
        const hasStock = !!meta;
        let stockBadge;
        if (hasStock) {
            stockBadge = `<span class="badge-pill badge-success">${escapeHtml(meta)}</span>`;
        } else if (item.area === 'counter' && item.trackStock) {
            stockBadge = `<span class="badge-pill badge-success">Tracking</span>`;
        } else {
            stockBadge = `<span class="badge-pill badge-hidden">Not set</span>`;
        }

        let statusBits = [];
        if (item.area === 'counter') {
            if (item.trackStock) statusBits.push('Stock on');
            if (item.trackSold) statusBits.push('Sold on');
        }
        const statusLine = statusBits.length
            ? `<span class="stock-row-status">${escapeHtml(statusBits.join(' · '))}</span>`
            : '';

        return `<tr>
            <td class="idx-col">${index + 1}</td>
            <td class="item-name-col">${escapeHtml(item.name)}${statusLine}</td>
            <td class="price-col">Rs. ${parseFloat(item.price).toFixed(0)}</td>
            <td class="stock-date-col" data-label="Date Added">${escapeHtml(formatStockAddedDate(item.addedAt))}</td>
            <td class="stock-col-cell">
                <div class="stock-col-inner">
                    <div class="stock-badge-wrap">${stockBadge}</div>
                </div>
            </td>
            <td class="action-col">
                <div class="stock-action-btns">
                    <button type="button" class="btn-view-action" onclick="openStockViewModal(${item.id}, '${item.area}')">View</button>
                    <button type="button" class="btn-edit-action" onclick="openStockEditModal(${item.id}, '${item.area}')">Edit</button>
                    <button type="button" class="btn-delete-action" onclick="deleteStockItem(${item.id}, '${item.area}')">Delete</button>
                </div>
            </td>
        </tr>`;
    }

    function renderStockTableBody(tbodyId, items, emptyText) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = `<tr class="stock-table-empty"><td colspan="6">${escapeHtml(emptyText)}</td></tr>`;
            return;
        }
        tbody.innerHTML = items.map((item, i) => renderStockRow(item, i)).join('');
    }

    function deleteStockItem(itemId, area) {
        const item = stockTrackedItems.find(i => String(i.id) === String(itemId) && i.area === area);
        if (!item) return;
        const areaLabel = STOCK_AREA_LABELS[area] || area;
        showStockConfirm(
            'Remove from stock?',
            'Remove <strong>' + escapeHtml(item.name) + '</strong> from ' + escapeHtml(areaLabel) + '?<br><span style="font-size:0.85rem;color:#9a9288;">Stock history for this item will also be deleted.</span>',
            'Remove',
            () => {
                stockPost('remove', { menu_item_id: itemId, area: area })
                    .then(data => {
                        if (data && data.success) {
                            stockTrackedItems = stockTrackedItems.filter(i => !(String(i.id) === String(itemId) && i.area === area));
                            renderStockTrackedList();
                            App.showToast('Item removed');
                        } else {
                            App.showToast(data && data.error ? data.error : 'Could not remove item', 'error');
                        }
                    })
                    .catch(() => App.showToast('Connection error. Try again.', 'error'));
            }
        );
    }

    function getStockListSearchQuery() {
        const el = document.getElementById('stock-list-search');
        return el ? (el.value || '').toLowerCase().trim() : '';
    }

    function filterStockItemsByQuery(items, query) {
        if (!query) return items;
        const tokens = query.split(/\s+/).filter(Boolean);
        return items.filter(item => {
            const name = (item.name || '').toLowerCase();
            return tokens.every(token => name.includes(token));
        });
    }

    function filterStockList() {
        renderStockTrackedList();
    }

    function renderStockTrackedList() {
        const query = getStockListSearchQuery();
        const counterAll = stockTrackedItems.filter(i => i.area === 'counter');
        const kitchenAll = stockTrackedItems.filter(i => i.area === 'kitchen');
        const counterItems = filterStockItemsByQuery(counterAll, query);
        const kitchenItems = filterStockItemsByQuery(kitchenAll, query);
        const counterEmpty = query && counterAll.length && !counterItems.length
            ? 'No items match your search.'
            : 'No counter items yet.';
        const kitchenEmpty = query && kitchenAll.length && !kitchenItems.length
            ? 'No items match your search.'
            : 'No kitchen items yet.';
        renderStockTableBody('stock-list-counter', counterItems, counterEmpty);
        renderStockTableBody('stock-list-kitchen', kitchenItems, kitchenEmpty);
        updateStockViewHint();
    }

    function formatStockUnitLabel(item) {
        if (item.area === 'counter' && item.trackStock) {
            if (item.unit === 'piece' || !item.unit) {
                const qty = item.stockQty != null ? Number(item.stockQty) : 0;
                return qty + (qty === 1 ? ' piece' : ' pieces');
            }
        }
        if (!item.unit || item.unitValue === null || item.unitValue === '') return '';
        const label = STOCK_UNIT_LABELS[item.unit] || item.unit;
        if (item.unit === 'kg') return item.unitValue + ' kg';
        if (item.unit === 'gram') return item.unitValue + ' g';
        if (item.unit === 'liter') return item.unitValue + ' L';
        if (STOCK_COUNT_UNITS.includes(item.unit)) {
            return item.unitValue + ' ' + label.toLowerCase();
        }
        return item.unitValue + ' ' + label;
    }

    function openStockEditModal(itemId, area) {
        const item = stockTrackedItems.find(i => String(i.id) === String(itemId) && i.area === area);
        if (!item) return;
        editingStockItemId = itemId;
        editingStockArea = area;
        currentStockUnit = item.unit || 'piece';
        document.getElementById('stock-edit-item-name').textContent = item.name + ' · ' + (STOCK_AREA_LABELS[area] || area);
        cancelStockQtyEdit();
        cancelStockQtyAdd();
        cancelStockQtyRemove();
        document.getElementById('stock-input-count').value = '';
        document.getElementById('stock-input-kg').value = '';
        document.getElementById('stock-input-gram').value = '';
        document.getElementById('stock-input-liter').value = '';
        const u = item.unit || 'piece';
        if (!usesStockQtyAdjustPanel()) {
            const val = item.unitValue;
            const inputId = getStockEditInputId(u);
            const inp = document.getElementById(inputId);
            if (inp && val != null && val !== '') inp.value = val;
        }
        selectStockUnit(currentStockUnit);
        updateStockEditQtyPanels();
        refreshStockEditCurrentQty();
        const trackWrap = document.getElementById('stock-edit-tracking-wrap');
        const trackStock = document.getElementById('stock-edit-track-stock');
        const trackSold = document.getElementById('stock-edit-track-sold');
        if (trackWrap && trackStock && trackSold) {
            const showTrack = area === 'counter';
            trackWrap.style.display = showTrack ? 'flex' : 'none';
            trackStock.checked = showTrack && !!item.trackStock;
            trackSold.checked = showTrack && !!item.trackSold;
        }
        document.getElementById('stock-edit-modal').style.display = 'flex';
        document.body.classList.add('no-scroll');
        document.documentElement.classList.add('no-scroll');
    }

    function closeStockEditModal() {
        document.getElementById('stock-edit-modal').style.display = 'none';
        if (!isStockUiBusy()) {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
        cancelStockQtyEdit();
        cancelStockQtyAdd();
        cancelStockQtyRemove();
        editingStockItemId = null;
        editingStockArea = null;
        if (document.getElementById('stock-add-modal').style.display !== 'flex' &&
            document.getElementById('stock-view-modal').style.display !== 'flex') {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
        loadStockItems(true);
    }

    function onStockEditBackdrop(e) {
        if (e.target.id === 'stock-edit-modal') closeStockEditModal();
    }

    function selectStockUnit(unit) {
        currentStockUnit = unit;
        document.querySelectorAll('#stock-edit-modal .stock-unit-chip').forEach(chip => {
            chip.classList.toggle('active', chip.getAttribute('data-unit') === unit);
        });
        const group = stockUnitPanelGroup(unit);
        document.querySelectorAll('#stock-edit-modal .stock-unit-panel').forEach(panel => {
            panel.classList.toggle('active', panel.getAttribute('data-unit-group') === group);
        });
        if (group === 'count') {
            const countLabel = document.getElementById('stock-count-label');
            if (countLabel && currentStockUnit !== 'piece') {
                countLabel.textContent = (STOCK_UNIT_LABELS[currentStockUnit] || currentStockUnit) + ' amount';
            }
        }
        updateStockEditQtyPanels();
    }

    function saveStockEdit() {
        if (!editingStockItemId || !editingStockArea) return;
        const item = stockTrackedItems.find(i => String(i.id) === String(editingStockItemId) && i.area === editingStockArea);
        if (!item) return;

        const qtyAdjust = usesStockQtyAdjustPanel();
        const unitUnchanged = currentStockUnit === (item.unit || 'piece');

        if (qtyAdjust) {
            const addForm = document.getElementById('stock-qty-add-form');
            const addInput = document.getElementById('stock-qty-add-input');
            if (addForm && addForm.style.display !== 'none' && addInput && addInput.value.trim()) {
                applyStockQtyAdd(true);
                return;
            }
            const removeForm = document.getElementById('stock-qty-remove-form');
            const removeInput = document.getElementById('stock-qty-remove-input');
            if (removeForm && removeForm.style.display !== 'none' && removeInput && removeInput.value.trim()) {
                applyStockQtyRemove(true);
                return;
            }
            const editForm = document.getElementById('stock-current-qty-edit');
            const editInput = document.getElementById('stock-qty-edit-input');
            if (editForm && editForm.style.display !== 'none' && editInput && editInput.value.trim()) {
                applyStockQtyAdjust('set', 'stock-qty-edit-input', true);
                return;
            }
            if (unitUnchanged && !stockEditTrackingChanged(item)) {
                closeStockEditModal();
                return;
            }
        }

        const isPieceCounter = editingStockArea === 'counter' && currentStockUnit === 'piece';
        const isKitchenCount = editingStockArea === 'kitchen' && STOCK_COUNT_UNITS.includes(currentStockUnit);
        let unitValue;
        if (isPieceCounter) {
            unitValue = item.stockQty != null ? item.stockQty : 0;
        } else if (isKitchenCount) {
            unitValue = item.unitValue != null ? item.unitValue : 0;
        } else {
            const input = document.getElementById(getStockEditInputId(currentStockUnit));
            const raw = input ? input.value.trim() : '';
            const check = stockValidateNumericValue(raw, stockAmountDecimals(currentStockUnit));
            if (!check.ok) {
                App.showToast(check.msg, 'error');
                return;
            }
            unitValue = check.value;
        }

        const saveBtn = document.querySelector('#stock-edit-modal .stock-edit-modal-footer .stock-confirm-btn');
        if (saveBtn) saveBtn.disabled = true;
        const payload = Object.assign({
            menu_item_id: editingStockItemId,
            area: editingStockArea,
            unit: currentStockUnit,
            unit_value: unitValue
        }, getStockEditTrackingPayload());
        stockPost('update', payload)
            .then(data => {
                if (saveBtn) saveBtn.disabled = false;
                if (data && data.success && data.item) {
                    upsertStockItem(mapStockItem(data.item));
                    lastStockSnapshot = stockItemsSnapshot(stockTrackedItems);
                    App.showToast('Stock settings saved');
                    closeStockEditModal();
                } else {
                    App.showToast(data && data.error ? data.error : 'Could not save settings', 'error');
                }
            })
            .catch(() => {
                if (saveBtn) saveBtn.disabled = false;
                App.showToast('Connection error. Try again.', 'error');
            });
    }

    function stockLocalYmd(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function stockTodayYmd() {
        return STOCK_SERVER_TODAY;
    }

    function stockYesterdayYmd() {
        const parts = STOCK_SERVER_TODAY.split('-');
        if (parts.length !== 3) return '';
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        d.setDate(d.getDate() - 1);
        return stockLocalYmd(d);
    }

    function stockFormatDayHeading(dateYmd) {
        if (dateYmd === stockTodayYmd()) return 'Today';
        if (dateYmd === stockYesterdayYmd()) return 'Yesterday';
        const parts = dateYmd.split('-');
        if (parts.length !== 3) return dateYmd;
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        if (isNaN(d.getTime())) return dateYmd;
        return d.toLocaleDateString('en-NP', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function stockViewCacheKey(itemId, area, dateYmd) {
        return String(itemId) + '|' + area + '|' + dateYmd;
    }

    function invalidateStockViewCache(itemId, area) {
        const prefix = String(itemId) + '|' + area + '|';
        stockViewDetailCache.forEach((_, key) => {
            if (key.startsWith(prefix)) stockViewDetailCache.delete(key);
        });
    }

    function abortStockViewFetch() {
        if (stockViewFetchController) {
            stockViewFetchController.abort();
            stockViewFetchController = null;
        }
    }

    function setStockViewFetching(isFetching) {
        const body = document.querySelector('#stock-view-modal .stock-view-body');
        const loadingEl = document.getElementById('stock-view-loading');
        if (body) body.classList.toggle('is-fetching-history', isFetching);
        if (loadingEl) {
            loadingEl.classList.toggle('inline', isFetching);
            loadingEl.style.display = isFetching ? 'block' : 'none';
        }
    }

    function applyStockViewDetail(data, dateYmd, historyOnly) {
        if (!data || !data.success) {
            document.getElementById('stock-view-stats').innerHTML =
                '<p class="stock-view-empty">Could not load details.</p>';
            document.getElementById('stock-view-history-sections').style.display = 'none';
            document.getElementById('stock-view-history-empty').textContent =
                'Could not load history for this date.';
            document.getElementById('stock-view-history-empty').style.display = 'block';
            return;
        }
        if (data.item) {
            stockViewItemSnapshot = mapStockItem(data.item);
            renderStockViewStats(stockViewItemSnapshot);
        } else if (!historyOnly && !stockViewItemSnapshot) {
            document.getElementById('stock-view-stats').innerHTML =
                '<p class="stock-view-empty">Could not load details.</p>';
            return;
        }
        const detail = stockViewItemSnapshot;
        if (detail) {
            renderStockViewDayStats(detail, data.dayStats, dateYmd);
        }
        renderStockViewHistory(data.movements || []);
    }

    function updateStockViewDateUi(dateYmd) {
        stockViewSelectedDate = dateYmd;
        const input = document.getElementById('stock-view-date-input');
        if (input) {
            input.removeAttribute('min');
            input.max = stockTodayYmd();
            input.value = dateYmd || '';
        }
        document.querySelectorAll('.stock-date-chip').forEach(chip => {
            const preset = chip.getAttribute('data-preset');
            let active = false;
            if (preset === 'today') active = (dateYmd === stockTodayYmd());
            else if (preset === 'yesterday') active = (dateYmd === stockYesterdayYmd());
            chip.classList.toggle('active', active);
        });
        const heading = document.getElementById('stock-view-day-heading');
        if (heading) heading.textContent = dateYmd ? stockFormatDayHeading(dateYmd) : 'All Time';
        const hint = document.getElementById('stock-view-history-date-hint');
        if (hint) hint.textContent = '';
    }

    function setStockViewDatePreset(preset) {
        if (!stockViewItemId || !stockViewArea) return;
        if (preset === 'today') {
            const today = stockTodayYmd();
            if (stockViewSelectedDate === today) return;
            loadStockViewDetail(stockViewItemId, stockViewArea, today, { historyOnly: !!stockViewItemSnapshot });
        } else if (preset === 'yesterday') {
            const yesterday = stockYesterdayYmd();
            if (stockViewSelectedDate === yesterday) return;
            loadStockViewDetail(stockViewItemId, stockViewArea, yesterday, { historyOnly: !!stockViewItemSnapshot });
        }
    }

    function onStockViewDateChange(dateYmd) {
        if (!dateYmd || !stockViewItemId || !stockViewArea) return;
        const today = stockTodayYmd();
        if (dateYmd > today) {
            updateStockViewDateUi(stockViewSelectedDate || today);
            return;
        }
        if (dateYmd === stockViewSelectedDate) return;
        if (stockViewDateDebounceTimer) clearTimeout(stockViewDateDebounceTimer);
        stockViewDateDebounceTimer = setTimeout(function () {
            stockViewDateDebounceTimer = null;
            loadStockViewDetail(stockViewItemId, stockViewArea, dateYmd, { historyOnly: true });
        }, 280);
    }

    function loadStockViewDetail(itemId, area, dateYmd, options) {
        const opts = options || {};
        const force = !!opts.force;
        const initial = !!opts.initial;
        const historyOnly = !!opts.historyOnly;
        // dateYmd = '' means All Time; only clamp if a real date given
        if (dateYmd === undefined || dateYmd === null) dateYmd = '';
        const today = stockTodayYmd();
        if (dateYmd && dateYmd > today) dateYmd = today;

        updateStockViewDateUi(dateYmd);
        stockViewSelectedDate = dateYmd;

        const cacheKey = stockViewCacheKey(itemId, area, dateYmd);
        if (!force && stockViewDetailCache.has(cacheKey)) {
            applyStockViewDetail(stockViewDetailCache.get(cacheKey), dateYmd, historyOnly);
            setStockViewFetching(false);
            return;
        }

        abortStockViewFetch();
        stockViewFetchController = new AbortController();
        const seq = ++stockViewFetchSeq;
        const signal = stockViewFetchController.signal;

        if (initial) {
            document.getElementById('stock-view-loading').style.display = 'block';
            document.getElementById('stock-view-loading').classList.remove('inline');
            document.getElementById('stock-view-history-sections').style.display = 'none';
            document.getElementById('stock-view-history-empty').style.display = 'none';
        } else {
            setStockViewFetching(true);
        }

        let url = STOCK_API + '?action=detail&menu_item_id=' + encodeURIComponent(itemId) +
            '&area=' + encodeURIComponent(area) +
            (dateYmd ? '&date=' + encodeURIComponent(dateYmd) : '');
        if (historyOnly) url += '&history_only=1';

        App.request(url, 'GET', null, { signal: signal })
            .then(data => {
                if (seq !== stockViewFetchSeq) return;
                if (data === null) return;
                if (data && data.success) {
                    if (stockViewDetailCache.size >= STOCK_VIEW_CACHE_MAX) {
                        const firstKey = stockViewDetailCache.keys().next().value;
                        if (firstKey) stockViewDetailCache.delete(firstKey);
                    }
                    stockViewDetailCache.set(cacheKey, data);
                }
                applyStockViewDetail(data, dateYmd, historyOnly);
            })
            .catch(() => {
                if (seq !== stockViewFetchSeq) return;
                document.getElementById('stock-view-stats').innerHTML =
                    '<p class="stock-view-empty">Connection error.</p>';
            })
            .finally(() => {
                if (seq !== stockViewFetchSeq) return;
                setStockViewFetching(false);
                document.getElementById('stock-view-loading').style.display = 'none';
                stockViewFetchController = null;
            });
    }

    function openStockViewModal(itemId, area) {
        const item = stockTrackedItems.find(i => String(i.id) === String(itemId) && i.area === area);
        if (!item) return;

        stockViewItemId = itemId;
        stockViewArea = area;
        stockViewItemSnapshot = null;

        document.getElementById('stock-view-item-name').textContent =
            item.name + ' · ' + (STOCK_AREA_LABELS[area] || area);
        document.getElementById('stock-view-stats').innerHTML =
            '<p class="stock-view-loading">Loading…</p>';
        if (area === 'counter' || area === 'kitchen') {
            document.getElementById('stock-view-date-box').style.display = 'block';
            updateStockViewDateUi(stockTodayYmd());
        } else {
            document.getElementById('stock-view-date-box').style.display = 'none';
        }
        document.getElementById('stock-view-history-sections').style.display = 'none';
        document.getElementById('stock-view-history-empty').style.display = 'none';
        document.getElementById('stock-view-sold-table').style.display = 'none';
        document.getElementById('stock-view-added-table').style.display = 'none';
        const usedTable = document.getElementById('stock-view-used-table');
        if (usedTable) usedTable.style.display = 'none';
        document.getElementById('stock-view-sold-empty').style.display = 'none';
        document.getElementById('stock-view-added-empty').style.display = 'none';
        const usedEmpty = document.getElementById('stock-view-used-empty');
        if (usedEmpty) usedEmpty.style.display = 'none';
        document.getElementById('stock-view-loading').style.display = 'block';
        document.getElementById('stock-view-modal').style.display = 'flex';
        document.body.classList.add('no-scroll');
        document.documentElement.classList.add('no-scroll');

        loadStockViewDetail(itemId, area, stockTodayYmd(), { initial: true, force: true });
    }

    function renderStockViewStats(item) {
        const unitLabel = formatStockUnitLabel(item) || 'Not set';
        const stats = [
            { label: 'Added on', value: formatStockAddedDate(item.addedAt) },
            { label: 'Price', value: 'Rs. ' + parseFloat(item.price).toFixed(0) }
        ];
        if (item.historySince) {
            stats.push({
                label: 'History from',
                value: formatStockDateTime(item.historySince)
            });
        }

        if (item.area === 'kitchen') {
            stats.splice(1, 0, { label: 'Unit type', value: STOCK_UNIT_LABELS[item.unit] || item.unit || '—' });
            stats.push({
                label: 'Total quantity',
                value: unitLabel,
                compact: true,
                full: true
            });
            const unitName = (STOCK_UNIT_LABELS[item.unit] || item.unit || 'units').toLowerCase();
            const usedTotal = item.totalUsedUnits != null ? Number(item.totalUsedUnits) : 0;
            stats.push({
                label: 'Total ' + unitName + ' used up to now',
                value: formatStockHistoryQty(usedTotal) + ' ' + unitName,
                compact: true,
                full: true
            });
        } else {
            // no extra stats needed for counter
        }

        if (item.area === 'counter') {
            stats.push({
                label: 'Track stock',
                value: item.trackStock ? 'On' : 'Off'
            });
            stats.push({
                label: 'Stock tracking from',
                value: item.trackStock ? formatStockDateTime(item.stockTrackingSince) : '—'
            });
            const qty = item.stockQty != null ? Number(item.stockQty) : 0;
            stats.push({
                label: 'Available stock',
                value: (item.unit === 'piece' || !item.unit)
                    ? (qty + (qty === 1 ? ' piece' : ' pieces'))
                    : unitLabel
            });
            stats.push({
                label: 'Track sold',
                value: item.trackSold ? 'On' : 'Off'
            });
            stats.push({
                label: 'Sold tracking from',
                value: item.trackSold ? formatStockDateTime(item.soldTrackingSince) : '—'
            });
            const soldQty = item.totalSoldPieces != null ? Number(item.totalSoldPieces) : 0;
            stats.push({
                label: 'Upto now This much pieces has been sold from starting',
                value: item.trackSold
                    ? (soldQty + (soldQty === 1 ? ' piece' : ' pieces'))
                    : '—',
                compact: true,
                full: true
            });
        }

        document.getElementById('stock-view-stats').innerHTML = stats.map(s => `
            <div class="stock-view-stat${s.full ? ' full-width' : ''}${s.compact ? ' compact-total' : ''}">
                <span class="stock-view-stat-label">${escapeHtml(s.label)}</span>
                <span class="stock-view-stat-value${s.compact ? ' compact' : ''}">${escapeHtml(s.value)}</span>
            </div>
        `).join('');
    }

    function renderStockViewDayStats(item, dayStats, dateYmd) {
        const box = document.getElementById('stock-view-date-box');
        const soldEl = document.getElementById('stock-view-day-sold');
        const addedEl = document.getElementById('stock-view-day-added');
        const totalAddedEl = document.getElementById('stock-view-day-total-added');
        const openingEl = document.getElementById('stock-view-day-opening');
        const soldWrap = document.getElementById('stock-view-day-sold-wrap');
        const addedWrap = document.getElementById('stock-view-day-added-wrap');
        const totalAddedWrap = document.getElementById('stock-view-day-total-added-wrap');
        const openingWrap = document.getElementById('stock-view-day-opening-wrap');
        const soldLabel = document.getElementById('stock-view-day-sold-label');
        const addedLabel = document.getElementById('stock-view-day-added-label');
        const dayGrid = document.getElementById('stock-view-day-grid');
        const formulaEl = document.getElementById('stock-view-day-formula');
        if (!box || !soldEl || !addedEl) return;

        if (item.area !== 'counter' && item.area !== 'kitchen') {
            box.style.display = 'none';
            return;
        }

        box.style.display = 'block';
        updateStockViewDateUi(dateYmd);
        const isKitchen = item.area === 'kitchen';
        const isCounterTracked = item.area === 'counter' && !!item.trackStock;
        const showCounterSold = item.area === 'counter' && (!!item.trackStock || !!item.trackSold);
        const isToday = dateYmd === stockTodayYmd();
        const used = dayStats && dayStats.used != null ? dayStats.used : 0;
        const sold = dayStats && dayStats.sold != null ? dayStats.sold : 0;
        const added = dayStats && dayStats.added != null ? dayStats.added : 0;
        const correctedDown = dayStats && dayStats.correctedDown != null ? dayStats.correctedDown : 0;
        const opening = dayStats && dayStats.opening != null ? dayStats.opening : 0;
        let remaining = dayStats && dayStats.remaining != null ? dayStats.remaining : 0;
        if (isKitchen && isToday && item.unitValue != null) {
            remaining = item.unitValue;
        }
        if (isCounterTracked && isToday && item.stockQty != null) {
            remaining = item.stockQty;
        }

        soldEl.textContent = Math.abs(isKitchen ? used : sold).toLocaleString('en-NP');

        if (isKitchen) {
            if (openingEl) openingEl.textContent = opening.toLocaleString('en-NP');
            if (totalAddedEl) totalAddedEl.textContent = added.toLocaleString('en-NP');
            addedEl.textContent = remaining.toLocaleString('en-NP');
        } else if (isCounterTracked) {
            addedEl.textContent = remaining.toLocaleString('en-NP');
        } else {
            addedEl.textContent = '—';
        }

        const unitText = isKitchen
            ? (STOCK_UNIT_LABELS[item.unit] || item.unit || 'units').toLowerCase()
            : 'pieces';
        document.querySelectorAll('.stock-view-day-unit').forEach(el => {
            el.textContent = unitText;
        });

        if (soldLabel) soldLabel.textContent = isKitchen ? 'Used' : 'Sold';
        if (addedLabel) {
            if (isKitchen) {
                addedLabel.textContent = 'Remaining';
            } else if (isCounterTracked) {
                addedLabel.textContent = 'Available stock';
            } else {
                addedLabel.textContent = 'Added';
            }
        }
        if (openingWrap) openingWrap.style.display = isKitchen ? 'block' : 'none';
        if (totalAddedWrap) totalAddedWrap.style.display = isKitchen ? 'block' : 'none';
        if (soldWrap) {
            soldWrap.classList.toggle('used', isKitchen);
            soldWrap.style.display = (isKitchen || showCounterSold) ? '' : 'none';
        }
        if (addedWrap) {
            addedWrap.classList.toggle('remaining', isKitchen || isCounterTracked);
            addedWrap.style.display = (isKitchen || isCounterTracked) ? '' : 'none';
        }
        if (dayGrid) {
            dayGrid.classList.toggle('kitchen-four', isKitchen);
        }
        if (formulaEl) {
            if (isKitchen) {
                formulaEl.style.display = 'block';
                let expr = opening + ' + ' + added;
                if (correctedDown > 0) {
                    expr += ' − ' + correctedDown + ' corrected';
                }
                expr += ' − ' + used + ' = ' + remaining + ' ' + unitText + ' left';
                formulaEl.textContent = expr;
            } else {
                formulaEl.style.display = 'none';
                formulaEl.textContent = '';
            }
        }

        const isEmptyStats = isKitchen
            ? (used === 0 && remaining === 0)
            : (sold === 0 && remaining === 0);

        if (isEmptyStats) {
            if (dayGrid) dayGrid.style.display = 'none';
            const headingEl = document.getElementById('stock-view-day-heading');
            if (headingEl) headingEl.style.display = 'none';
            if (formulaEl) formulaEl.style.display = 'none';
        } else {
            if (dayGrid) dayGrid.style.display = '';
            const headingEl = document.getElementById('stock-view-day-heading');
            if (headingEl) headingEl.style.display = '';
        }
    }

    function formatStockHistoryQty(val) {
        const n = parseFloat(val);
        if (isNaN(n)) return String(val ?? '');
        if (Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
        return String(n);
    }

    function isStockSaleReturn(m) {
        return m.movement_type === 'sale' && parseFloat(m.qty_change) > 0;
    }

    function renderStockHistoryRows(movements, kind) {
        return movements.map(m => {
            const ch = parseFloat(m.qty_change);
            if (kind === 'sold') {
                const isReturn = isStockSaleReturn(m);
                const soldDisplay = ch < 0 ? Math.abs(ch) : (isReturn ? ch : 0);
                return `<tr>
                    <td>${escapeHtml(formatStockDateTime(m.created_at))}</td>
                    <td>${escapeHtml(formatStockHistoryQty(soldDisplay))}</td>
                    <td>${escapeHtml(formatStockHistoryQty(m.qty_after))}</td>
                    <td>${escapeHtml(m.note || '')}</td>
                </tr>`;
            }
            if (kind === 'used') {
                const usedQty = Math.abs(ch);
                return `<tr>
                    <td>${escapeHtml(formatStockDateTime(m.created_at))}</td>
                    <td>${escapeHtml(formatStockHistoryQty(usedQty))}</td>
                    <td>${escapeHtml(formatStockHistoryQty(m.qty_after))}</td>
                    <td>${escapeHtml(m.note || '')}</td>
                </tr>`;
            }
            const changeStr = (ch > 0 ? '+' : '') + formatStockHistoryQty(ch);
            return `<tr>
                <td>${escapeHtml(formatStockDateTime(m.created_at))}</td>
                <td>${escapeHtml(formatStockHistoryType(m))}</td>
                <td>${escapeHtml(changeStr)}</td>
                <td>${escapeHtml(formatStockHistoryQty(m.qty_after))}</td>
                <td>${escapeHtml(m.note || '')}</td>
            </tr>`;
        }).join('');
    }

    function renderStockViewHistory(movements) {
        const sections = document.getElementById('stock-view-history-sections');
        const allEmpty = document.getElementById('stock-view-history-empty');
        const soldTable = document.getElementById('stock-view-sold-table');
        const soldBody = document.getElementById('stock-view-sold-body');
        const soldEmpty = document.getElementById('stock-view-sold-empty');
        const usedBlock = document.getElementById('stock-view-used-block');
        const usedTable = document.getElementById('stock-view-used-table');
        const usedBody = document.getElementById('stock-view-used-body');
        const usedEmpty = document.getElementById('stock-view-used-empty');
        const addedTable = document.getElementById('stock-view-added-table');
        const addedBody = document.getElementById('stock-view-added-body');
        const addedEmpty = document.getElementById('stock-view-added-empty');
        const soldBlock = soldTable ? soldTable.closest('.stock-view-history-block') : null;
        const isKitchen = stockViewArea === 'kitchen';

        const dayLabel = stockViewSelectedDate ? stockFormatDayHeading(stockViewSelectedDate) : 'this period';

        // Helper: extract YYYY-MM-DD from a datetime string like "2026-06-01 11:55:00"
        function matchesSelectedDate(m) {
            if (!stockViewSelectedDate) return true;
            const dt = (m.created_at || '').substring(0, 10);
            return dt === stockViewSelectedDate;
        }

        // Sold/Used: filtered by selected date
        const allSold = movements.filter(m => m.movement_type === 'sale' && parseFloat(m.qty_change) < 0);
        const sold = allSold.filter(matchesSelectedDate);
        const allUsed = movements.filter(m => m.movement_type === 'adjust' && parseFloat(m.qty_change) < 0);
        const used = allUsed.filter(matchesSelectedDate);

        // Added: always all-time, never filtered by date
        const added = movements.filter(m => {
            if (m.movement_type === 'sale' && parseFloat(m.qty_change) < 0) return false;
            if (m.movement_type === 'adjust' && parseFloat(m.qty_change) < 0) return false;
            return true;
        });

        if (soldBlock) soldBlock.style.display = isKitchen ? 'none' : '';
        if (usedBlock) usedBlock.style.display = isKitchen ? 'flex' : 'none';

        // Check if there's truly nothing at all
        if (!movements.length) {
            sections.style.display = 'none';
            allEmpty.textContent = 'No history yet.';
            allEmpty.style.display = 'block';
            return;
        }

        allEmpty.style.display = 'none';
        sections.style.display = 'flex';

        if (sold.length) {
            soldTable.style.display = 'table';
            soldEmpty.style.display = 'none';
            soldBody.innerHTML = renderStockHistoryRows(sold, 'sold');
        } else if (!isKitchen) {
            soldTable.style.display = 'none';
            soldEmpty.textContent = 'No sales on ' + dayLabel + '.';
            soldEmpty.style.display = 'block';
            soldBody.innerHTML = '';
        }

        if (isKitchen) {
            if (used.length) {
                usedTable.style.display = 'table';
                usedEmpty.style.display = 'none';
                usedBody.innerHTML = renderStockHistoryRows(used, 'used');
            } else {
                usedTable.style.display = 'none';
                usedEmpty.textContent = 'Nothing used on ' + dayLabel + '.';
                usedEmpty.style.display = 'block';
                usedBody.innerHTML = '';
            }
        }

        if (added.length) {
            addedTable.style.display = 'table';
            addedEmpty.style.display = 'none';
            addedBody.innerHTML = renderStockHistoryRows(added, 'added');
        } else {
            addedTable.style.display = 'none';
            addedEmpty.textContent = 'No stock added yet.';
            addedEmpty.style.display = 'block';
            addedBody.innerHTML = '';
        }

        window.stockViewAllAddedMovements = added;
    }

    function openStockViewAllAddedModal() {
        const modal = document.getElementById('stock-view-all-added-modal');
        const body = document.getElementById('stock-view-all-added-body');
        const empty = document.getElementById('stock-view-all-added-empty');
        const table = document.getElementById('stock-view-all-added-table');
        const added = window.stockViewAllAddedMovements || [];

        if (added.length) {
            table.style.display = 'table';
            empty.style.display = 'none';
            body.innerHTML = renderStockHistoryRows(added, 'added');
        } else {
            table.style.display = 'none';
            empty.style.display = 'block';
            body.innerHTML = '';
        }

        modal.style.display = 'flex';
        document.body.classList.add('no-scroll');
    }

    function closeStockViewAllAddedModal() {
        document.getElementById('stock-view-all-added-modal').style.display = 'none';
        if (!isStockUiBusy()) {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
    }

    function onStockViewAllAddedBackdrop(e) {
        if (e.target.id === 'stock-view-all-added-modal') closeStockViewAllAddedModal();
    }

    function closeStockViewModal() {
        abortStockViewFetch();
        stockViewFetchSeq++;
        setStockViewFetching(false);
        document.getElementById('stock-view-modal').style.display = 'none';
        if (!isStockUiBusy()) {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
        }
        stockViewItemId = null;
        stockViewArea = null;
        stockViewItemSnapshot = null;
        stockViewSelectedDate = '';
        if (document.getElementById('stock-edit-modal').style.display !== 'flex' &&
            document.getElementById('stock-add-modal').style.display !== 'flex') {
            document.body.classList.remove('no-scroll');
        }
        loadStockItems(true);
    }

    function onStockViewBackdrop(e) {
        if (e.target.id === 'stock-view-modal') closeStockViewModal();
    }

    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('active');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    function updateClock() {
        const el = document.getElementById('clock');
        if (!el) return;
        el.textContent = new Date().toLocaleTimeString('en-NP', { hour: '2-digit', minute: '2-digit' });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (document.getElementById('stock-view-modal').style.display === 'flex') closeStockViewModal();
        else if (document.getElementById('stock-edit-modal').style.display === 'flex') closeStockEditModal();
        else if (document.getElementById('stock-add-modal').style.display === 'flex') closeAddStockCount();
    });

    function initStockPage() {
        renderStockUnitChips('stock-unit-chips', 'piece', 'selectStockUnit');
        renderStockUnitChips('kitchen-unit-chips', 'piece', 'selectKitchenAddUnit');
        initStockNumericInputs();
        updateClock();
        setInterval(updateClock, 1000);
        loadStockItems();
        preloadMenuItemsForStock();
        startStockPolling();
        const savedView = localStorage.getItem('stock_active_view');
        if (savedView === 'kitchen' || savedView === 'counter') {
            switchStockView(savedView);
        } else {
            updateStockViewHint();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initStockPage();
    });
</script>
