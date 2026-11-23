@if (app()->getLocale() === 'ar')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endif
<style>
    @if (app()->getLocale() === 'ar')
        :root {
            --font-family: 'Cairo', sans-serif;
        }

        html[lang="ar"],
        html[lang="ar"] body,
        html[lang="ar"] .fi-body,
        html[lang="ar"] button,
        html[lang="ar"] input,
        html[lang="ar"] select,
        html[lang="ar"] textarea {
            font-family: 'Cairo', sans-serif !important;
        }
    @endif

    /* =========================================================================
       TOURISM-SALES Modern Executive CRM Theme (Light + Dark, RTL + LTR)
       Brand Palette: Deep Navy #0F172A + Brand Amber #F59E0B + Cool Slate
       ========================================================================= */

    :root {
        --ts-navy-950: #0b1320;
        --ts-navy-900: #0f172a;
        --ts-navy-800: #1e293b;
        --ts-amber-500: #f59e0b;
        --ts-amber-600: #d97706;
        --ts-canvas-light: #f4f6fa;
        --ts-canvas-dark: #090e1a;
    }

    /* App Canvas */
    body.fi-body {
        background-color: var(--ts-canvas-light) !important;
    }

    html.dark body.fi-body {
        background-color: var(--ts-canvas-dark) !important;
    }

    /* Executive Deep Navy Sidebar (both Light & Dark modes) */
    .fi-sidebar {
        background: linear-gradient(180deg, #0f172a 0%, #131f33 100%) !important;
        border-inline-end: 1px solid rgba(255, 255, 255, 0.06) !important;
        box-shadow: 0 0 24px rgba(15, 23, 42, 0.14);
    }

    .fi-sidebar-header {
        background: rgba(11, 19, 32, 0.55) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-sidebar-header .fi-icon-btn,
    .fi-sidebar-group-collapse-button {
        color: #94a3b8 !important;
    }

    .fi-sidebar-header .fi-icon-btn:hover,
    .fi-sidebar-group-collapse-button:hover {
        color: #ffffff !important;
    }

    /* Show white-text brand logo inside dark navy sidebar even in Light Mode */
    .fi-sidebar-header .fi-logo.dark\:hidden {
        display: none !important;
    }

    .fi-sidebar-header .fi-logo.dark\:flex {
        display: flex !important;
    }

    /* Sidebar Navigation Items */
    .fi-sidebar-item-button {
        border-radius: 0.625rem !important;
        padding-top: 0.575rem !important;
        padding-bottom: 0.575rem !important;
        transition: all 0.16s ease !important;
    }

    .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover {
        background-color: rgba(255, 255, 255, 0.07) !important;
    }

    .fi-sidebar-item-label {
        color: #cbd5e1 !important;
        font-weight: 500 !important;
    }

    .fi-sidebar-item-icon {
        color: #94a3b8 !important;
    }

    .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover .fi-sidebar-item-label {
        color: #ffffff !important;
    }

    .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover .fi-sidebar-item-icon {
        color: #fbbf24 !important;
    }

    /* Active Sidebar Item — Brand Amber Highlight */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-button {
        background: linear-gradient(90deg, rgba(245, 158, 11, 0.22) 0%, rgba(245, 158, 11, 0.09) 100%) !important;
        box-shadow: inset 3px 0 0 0 #f59e0b;
    }

    [dir="rtl"] .fi-sidebar-item.fi-active > .fi-sidebar-item-button {
        box-shadow: inset -3px 0 0 0 #f59e0b;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label {
        color: #ffffff !important;
        font-weight: 700 !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: #f59e0b !important;
    }

    .fi-sidebar-group-label {
        color: #64748b !important;
        font-size: 0.72rem !important;
        letter-spacing: 0.04em;
    }

    /* Clean Translucent Topbar */
    .fi-topbar > nav {
        background-color: rgba(255, 255, 255, 0.92) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid #e2e8f0 !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03) !important;
    }

    html.dark .fi-topbar > nav {
        background-color: rgba(15, 23, 42, 0.88) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
    }

    /* Elevated CRM Cards & Sections */
    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat {
        border-radius: 0.875rem !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03) !important;
        transition: box-shadow 0.18s ease, transform 0.18s ease;
    }

    html:not(.dark) .fi-section,
    html:not(.dark) .fi-ta-ctn,
    html:not(.dark) .fi-wi-stats-overview-stat {
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
    }

    .fi-wi-stats-overview-stat {
        border-top: 3px solid #f59e0b !important;
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08) !important;
    }

    /* Clean CRM Tables */
    html:not(.dark) .fi-ta-header-cell {
        background-color: #f8fafc !important;
        color: #475569 !important;
        font-weight: 700 !important;
    }

    .fi-ta-row:hover {
        background-color: rgba(245, 158, 11, 0.045) !important;
    }

    /* Primary Buttons — Exact Brand Amber #F59E0B */
    .fi-btn-color-primary:not(.fi-btn-outlined) {
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.28) !important;
        font-weight: 600 !important;
        border-radius: 0.6rem !important;
    }

    .fi-btn {
        border-radius: 0.6rem !important;
    }

    /* Page Headings */
    .fi-header-heading {
        font-weight: 700 !important;
        letter-spacing: -0.015em;
    }

    /* Form Inputs */
    .fi-input-wrp {
        border-radius: 0.6rem !important;
    }

    /* Keep Filament searchable selects (Choices.js) strictly single-line & height-aligned */
    .fi-fo-select .choices__list--single {
        overflow: hidden;
    }

    .fi-fo-select .choices__list--single .choices__item {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    /* =========================================================================
       FLOATING SUPPORT WIDGET (Agent Panel — Pinned Bottom-Left)
       ========================================================================= */
    .ts-support-fab {
        position: fixed;
        bottom: 24px;
        left: 24px;
        z-index: 45;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 18px 10px 12px;
        border-radius: 9999px;
        border: 1px solid rgba(245, 158, 11, 0.35);
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #ffffff;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.42), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    [dir="rtl"] .ts-support-fab {
        padding: 10px 12px 10px 18px;
    }

    .ts-support-fab:hover {
        transform: translateY(-2px);
        border-color: rgba(245, 158, 11, 0.8);
        box-shadow: 0 16px 32px -6px rgba(15, 23, 42, 0.5), 0 4px 14px rgba(245, 158, 11, 0.25);
    }

    .ts-support-fab-icon {
        width: 32px;
        height: 32px;
        border-radius: 9999px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .ts-support-fab-badge {
        position: absolute;
        top: -6px;
        right: -4px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 9999px;
        background: #e11d48;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #ffffff;
        box-shadow: 0 2px 6px rgba(225, 29, 72, 0.45);
    }

    html.dark .ts-support-fab-badge {
        border-color: #0f172a;
    }

    .ts-support-drawer {
        position: fixed;
        bottom: 88px;
        left: 24px;
        z-index: 50;
        width: 400px;
        max-width: calc(100vw - 32px);
        height: 560px;
        max-height: calc(100vh - 116px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 24px 55px -12px rgba(15, 23, 42, 0.3), 0 6px 18px -6px rgba(15, 23, 42, 0.12);
    }

    html.dark .ts-support-drawer {
        background: #0f172a;
        border-color: #1e293b;
        box-shadow: 0 24px 55px -12px rgba(0, 0, 0, 0.65);
    }

    @media (max-width: 640px) {
        .ts-support-fab {
            bottom: 16px;
            left: 16px;
        }
        .ts-support-drawer {
            bottom: 78px;
            left: 12px;
            width: calc(100vw - 24px);
            height: calc(100vh - 104px);
        }
    }

    .ts-support-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #ffffff;
        flex-shrink: 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .ts-support-header-main {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .ts-support-header-avatar {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        background: rgba(245, 158, 11, 0.18);
        color: #f59e0b;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .ts-support-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .ts-support-icon-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    .ts-support-header-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #ffffff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    .ts-support-header-sub {
        font-size: 0.74rem;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }

    .ts-support-subheader {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    html.dark .ts-support-subheader {
        background: #131f33;
        border-bottom-color: #1e293b;
    }

    .ts-support-subheader-label {
        font-size: 0.78rem;
        font-weight: 700;
        color: #475569;
    }

    html.dark .ts-support-subheader-label {
        color: #cbd5e1;
    }

    .ts-support-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 7px 13px;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #0f172a;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .ts-support-btn-primary:hover {
        opacity: 0.94;
        transform: translateY(-1px);
    }

    .ts-support-btn-primary:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none;
    }

    .ts-support-btn-full {
        width: 100%;
        padding: 10px 16px;
        font-size: 0.875rem;
        border-radius: 11px;
    }

    .ts-support-list {
        flex: 1;
        overflow-y: auto;
        background: #ffffff;
    }

    html.dark .ts-support-list {
        background: #0f172a;
    }

    .ts-support-ticket-item {
        width: 100%;
        display: block;
        text-align: start;
        padding: 13px 16px;
        background: transparent;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    html.dark .ts-support-ticket-item {
        border-bottom-color: #1e293b;
    }

    .ts-support-ticket-item:hover {
        background: #fffbeb;
    }

    html.dark .ts-support-ticket-item:hover {
        background: #172337;
    }

    .ts-support-ticket-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .ts-support-ticket-subject {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
    }

    html.dark .ts-support-ticket-subject {
        color: #f8fafc;
    }

    .ts-support-ticket-preview {
        margin-top: 3px;
        font-size: 0.77rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    html.dark .ts-support-ticket-preview {
        color: #94a3b8;
    }

    .ts-support-ticket-meta {
        margin-top: 7px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.71rem;
        color: #94a3b8;
    }

    .ts-support-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 9px;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .ts-badge-open { background: #ffe4e6; color: #be123c; }
    .ts-badge-in_progress { background: #e0f2fe; color: #0369a1; }
    .ts-badge-waiting_agent { background: #fef3c7; color: #b45309; }
    .ts-badge-closed { background: #d1fae5; color: #047857; }

    html.dark .ts-badge-open { background: rgba(225, 29, 72, 0.22); color: #fda4af; }
    html.dark .ts-badge-in_progress { background: rgba(14, 165, 233, 0.22); color: #7dd3fc; }
    html.dark .ts-badge-waiting_agent { background: rgba(245, 158, 11, 0.22); color: #fcd34d; }
    html.dark .ts-badge-closed { background: rgba(16, 185, 129, 0.22); color: #6ee7b7; }

    .ts-support-unread-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 9999px;
        background: #e11d48;
        color: #ffffff;
        font-size: 0.7rem;
        font-weight: 800;
        flex-shrink: 0;
    }

    .ts-support-empty {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 28px 24px;
    }

    .ts-support-empty-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: #fef3c7;
        color: #d97706;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }

    html.dark .ts-support-empty-icon {
        background: rgba(245, 158, 11, 0.16);
        color: #fbbf24;
    }

    .ts-support-form {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 13px;
        padding: 16px;
        overflow-y: auto;
        background: #ffffff;
    }

    html.dark .ts-support-form {
        background: #0f172a;
    }

    .ts-support-field-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .ts-support-label {
        display: block;
        margin-bottom: 5px;
        font-size: 0.77rem;
        font-weight: 700;
        color: #334155;
    }

    html.dark .ts-support-label {
        color: #cbd5e1;
    }

    .ts-support-input,
    .ts-support-select,
    .ts-support-textarea {
        width: 100%;
        box-sizing: border-box;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        color: #0f172a;
        padding: 9px 12px;
        font-size: 0.85rem;
        font-family: inherit;
        transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }

    .ts-support-select {
        appearance: none !important;
        -webkit-appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M6 8l4 4 4-4'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-size: 1.15rem 1.15rem !important;
        background-position: right 0.65rem center !important;
        padding-inline-start: 0.75rem !important;
        padding-inline-end: 2.2rem !important;
        cursor: pointer;
    }

    [dir="rtl"] .ts-support-select {
        background-position: left 0.65rem center !important;
    }

    html.dark .ts-support-input,
    html.dark .ts-support-select,
    html.dark .ts-support-textarea {
        border-color: #334155;
        background-color: #1e293b;
        color: #f8fafc;
    }

    html.dark .ts-support-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M6 8l4 4 4-4'/%3E%3C/svg%3E") !important;
    }

    .ts-support-input:focus,
    .ts-support-select:focus,
    .ts-support-textarea:focus {
        outline: none;
        border-color: #f59e0b;
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
    }

    html.dark .ts-support-input:focus,
    html.dark .ts-support-select:focus,
    html.dark .ts-support-textarea:focus {
        background-color: #162032;
    }

    .ts-support-error {
        margin-top: 4px;
        font-size: 0.74rem;
        color: #e11d48;
    }

    .ts-support-messages {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f1f5f9;
    }

    html.dark .ts-support-messages {
        background: #090e1a;
    }

    .ts-msg-row {
        display: flex;
        flex-direction: column;
    }

    .ts-msg-row-start { align-items: flex-start; }
    .ts-msg-row-end { align-items: flex-end; }

    .ts-msg-bubble {
        max-width: 85%;
        padding: 10px 13px;
        border-radius: 15px;
        font-size: 0.84rem;
        line-height: 1.5;
        word-break: break-word;
        white-space: pre-line;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    }

    .ts-msg-bubble-admin {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #f8fafc;
        border-bottom-left-radius: 4px;
    }

    [dir="ltr"] .ts-msg-bubble-admin {
        border-bottom-left-radius: 15px;
        border-bottom-right-radius: 4px;
    }

    .ts-msg-bubble-agent {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        border-bottom-right-radius: 4px;
    }

    html.dark .ts-msg-bubble-agent {
        background: #1e293b;
        color: #f8fafc;
        border-color: #334155;
    }

    [dir="ltr"] .ts-msg-bubble-agent {
        border-bottom-right-radius: 15px;
        border-bottom-left-radius: 4px;
    }

    .ts-msg-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 4px;
        font-size: 0.71rem;
        font-weight: 700;
    }

    .ts-msg-meta-admin { color: #fbbf24; }
    .ts-msg-meta-agent { color: #d97706; }

    .ts-msg-time {
        font-weight: 500;
        opacity: 0.72;
    }

    .ts-support-composer {
        flex-shrink: 0;
        padding: 11px 12px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
    }

    html.dark .ts-support-composer {
        background: #0f172a;
        border-top-color: #1e293b;
    }

    .ts-support-composer-row {
        display: flex;
        align-items: flex-end;
        gap: 8px;
    }

    .ts-support-send-btn {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        border: none;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .ts-support-send-btn:hover {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    /* =========================================================================
       ADMIN SUPPORT TICKET CONVERSATION PAGE
       ========================================================================= */
    .ts-admin-chat-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .ts-admin-chat-grid {
            grid-template-columns: 1fr;
        }
    }

    .ts-admin-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }

    html.dark .ts-admin-card {
        background: #0f172a;
        border-color: #1e293b;
    }

    .ts-admin-card-body {
        padding: 18px 20px;
    }

    .ts-admin-chat-shell {
        display: flex;
        flex-direction: column;
        height: 70vh;
        min-height: 540px;
        max-height: 760px;
    }

    .ts-admin-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 15px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    html.dark .ts-admin-chat-header {
        background: #131f33;
        border-bottom-color: #1e293b;
    }

    .ts-admin-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: #f1f5f9;
    }

    html.dark .ts-admin-chat-messages {
        background: #090e1a;
    }

    .ts-admin-chat-composer {
        padding: 16px 20px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
    }

    html.dark .ts-admin-chat-composer {
        background: #0f172a;
        border-top-color: #1e293b;
    }

    .ts-admin-side-stack {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .ts-admin-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }

    html.dark .ts-admin-info-row {
        border-bottom-color: #1e293b;
    }

    .ts-admin-info-row:last-child {
        border-bottom: none;
    }

    /* =========================================================================
       MODERN CRM DASHBOARD (Hero Banner, Pulse Bar, Insights Panel, Filter Bar)
       ========================================================================= */
    .ts-dash-hero {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        padding: 24px 26px;
        background: linear-gradient(135deg, #0f172a 0%, #162238 55%, #1e293b 100%);
        border: 1px solid rgba(245, 158, 11, 0.22);
        box-shadow: 0 14px 32px -12px rgba(15, 23, 42, 0.32);
        color: #ffffff;
    }

    .ts-dash-hero-glow {
        position: absolute;
        top: -80px;
        left: -60px;
        width: 280px;
        height: 280px;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(245, 158, 11, 0.24) 0%, rgba(245, 158, 11, 0) 70%);
        pointer-events: none;
    }

    .ts-dash-hero-top {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .ts-dash-hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        color: #cbd5e1;
        margin-bottom: 6px;
    }

    .ts-dash-hero-dot {
        width: 8px;
        height: 8px;
        border-radius: 9999px;
        background: #f59e0b;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.8);
    }

    .ts-dash-hero-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.015em;
        line-height: 1.25;
    }

    .ts-dash-hero-sub {
        margin: 6px 0 0 0;
        font-size: 0.86rem;
        color: #94a3b8;
    }

    .ts-dash-quick-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ts-dash-qa-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 14px;
        border-radius: 11px;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform 0.16s ease, background 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
        white-space: nowrap;
    }

    .ts-dash-qa-primary {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #0f172a !important;
        box-shadow: 0 6px 16px -4px rgba(245, 158, 11, 0.48);
    }

    .ts-dash-qa-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px -4px rgba(245, 158, 11, 0.6);
    }

    .ts-dash-qa-ghost {
        background: rgba(255, 255, 255, 0.08);
        color: #f1f5f9 !important;
        border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .ts-dash-qa-ghost:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(245, 158, 11, 0.45);
        transform: translateY(-1px);
    }

    .ts-dash-hero-pulse {
        position: relative;
        z-index: 1;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.09);
        display: grid;
        grid-template-columns: auto auto minmax(200px, 1fr);
        gap: 16px 28px;
        align-items: center;
    }

    @media (max-width: 768px) {
        .ts-dash-hero {
            padding: 18px;
        }
        .ts-dash-hero-pulse {
            grid-template-columns: 1fr 1fr;
        }
        .ts-dash-pulse-progress {
            grid-column: 1 / -1;
        }
    }

    .ts-dash-pulse-item {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .ts-dash-pulse-label {
        font-size: 0.74rem;
        color: #94a3b8;
        font-weight: 600;
    }

    .ts-dash-pulse-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: #ffffff;
    }

    .ts-dash-bar-track {
        width: 100%;
        height: 7px;
        margin-top: 5px;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.12);
        overflow: hidden;
    }

    .ts-dash-bar-fill {
        height: 100%;
        border-radius: 9999px;
        background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
        transition: width 0.35s ease;
    }

    /* Insights Card (Upcoming Departures & Reminders) */
    .ts-dash-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    html.dark .ts-dash-panel {
        background: #0f172a;
        border-color: #1e293b;
    }

    .ts-dash-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    html.dark .ts-dash-panel-header {
        background: #131f33;
        border-bottom-color: #1e293b;
    }

    .ts-dash-tabs {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px;
        border-radius: 10px;
        background: #e2e8f0;
    }

    html.dark .ts-dash-tabs {
        background: #1e293b;
    }

    .ts-dash-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    html.dark .ts-dash-tab {
        color: #94a3b8;
    }

    .ts-dash-tab.is-active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
    }

    html.dark .ts-dash-tab.is-active {
        background: #0f172a;
        color: #f8fafc;
    }

    .ts-dash-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 18px;
        padding: 0 6px;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 800;
        background: rgba(245, 158, 11, 0.18);
        color: #b45309;
    }

    html.dark .ts-dash-tab-count {
        color: #fbbf24;
    }

    .ts-dash-panel-link {
        font-size: 0.8rem;
        font-weight: 700;
        color: #d97706;
        text-decoration: none;
    }

    .ts-dash-panel-link:hover {
        text-decoration: underline;
    }

    .ts-dash-panel-body {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .ts-dash-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 18px;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        transition: background 0.15s ease;
    }

    html.dark .ts-dash-row {
        border-bottom-color: #1e293b;
    }

    .ts-dash-row:last-child {
        border-bottom: none;
    }

    .ts-dash-row:hover {
        background: rgba(245, 158, 11, 0.05);
    }

    .ts-dash-row-main {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex: 1;
    }

    .ts-dash-countdown {
        flex-shrink: 0;
        min-width: 66px;
        text-align: center;
        padding: 5px 8px;
        border-radius: 9px;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .ts-dash-countdown.is-urgent {
        background: #ffe4e6;
        color: #be123c;
    }

    .ts-dash-countdown.is-soon {
        background: #fef3c7;
        color: #b45309;
    }

    .ts-dash-countdown.is-normal {
        background: #e0f2fe;
        color: #0369a1;
    }

    html.dark .ts-dash-countdown.is-urgent { background: rgba(225, 29, 72, 0.22); color: #fda4af; }
    html.dark .ts-dash-countdown.is-soon { background: rgba(245, 158, 11, 0.22); color: #fcd34d; }
    html.dark .ts-dash-countdown.is-normal { background: rgba(14, 165, 233, 0.22); color: #7dd3fc; }

    .ts-dash-row-title {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    html.dark .ts-dash-row-title {
        color: #f8fafc;
    }

    .ts-dash-row-sub {
        font-size: 0.76rem;
        color: #64748b;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    html.dark .ts-dash-row-sub {
        color: #94a3b8;
    }

    .ts-dash-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        font-size: 0.86rem;
        color: #94a3b8;
        text-align: center;
    }

    /* Clean MonthlyTripsWidget Filter Bar */
    .ts-filter-header {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }

    .ts-filter-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    html.dark .ts-filter-title {
        color: #f8fafc;
    }

    .ts-filter-bar {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }

    .ts-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 165px;
    }

    .ts-filter-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
    }

    html.dark .ts-filter-label {
        color: #cbd5e1;
    }

    .ts-filter-input {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        color: #0f172a;
        padding: 7px 11px;
        font-size: 0.84rem;
    }

    html.dark .ts-filter-input {
        border-color: #334155;
        background-color: #1e293b;
        color: #f8fafc;
    }

    .ts-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ts-filter-btn {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .ts-filter-btn:hover {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    .ts-filter-btn-primary {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #0f172a;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
    }

    .ts-filter-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }

    html.dark .ts-filter-btn-secondary {
        background: #334155;
        color: #f1f5f9;
    }
</style>
