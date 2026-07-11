@extends('layouts.app')

@section('title', 'POS')
@section('page_title', 'POS')
@section('layout_mode', 'fullscreen')
@section('body_style', 'background: #dfe6f5;')

@php($canManageItems = admin_has_permission('items.manage'))
@php($canManageCategories = admin_has_permission('categories.manage'))
@php($canViewCustomers = admin_has_permission('customers.view'))
@php($canViewPriceLists = admin_has_permission('price_lists.view'))
@php($tenantSettings = array_merge([
    'store_name' => admin_tenant_display_name($selectedTenant),
    'contact_phone' => '',
    'contact_email' => '',
    'address' => '',
    'receipt_footer' => '',
], is_array($selectedTenant->general_settings) ? $selectedTenant->general_settings : []))
@php($storeName = trim((string) ($tenantSettings['store_name'] ?? '')) ?: admin_tenant_display_name($selectedTenant))
@php($categoryAccentPalette = ['sky', 'amber', 'mint', 'coral', 'violet', 'ink'])
@php($categoryIconResolver = function (string $name): string {
    $value = strtolower(trim($name));

    return match (true) {
        str_contains($value, 'meat'), str_contains($value, 'bbq'), str_contains($value, 'grill') => 'uil-drumstick',
        str_contains($value, 'burger') => 'uil-hamburger',
        str_contains($value, 'pizza') => 'uil-pizza-slice',
        str_contains($value, 'drink'), str_contains($value, 'juice'), str_contains($value, 'coffee'), str_contains($value, 'tea') => 'uil-glass',
        str_contains($value, 'dessert'), str_contains($value, 'cake'), str_contains($value, 'ice') => 'uil-ice-cream',
        str_contains($value, 'snack'), str_contains($value, 'cookie') => 'uil-cookie',
        default => 'uil-utensils',
    };
})

@push('styles')
<style>
    .pos-page {
        min-height: 100vh;
        padding: 12px;
        background: linear-gradient(180deg, #d7dbe0 0%, #cfd4da 100%);
    }

    .pos-terminal {
        width: 100%;
        min-height: calc(100vh - 24px);
        background: linear-gradient(180deg, #eef0f2 0%, #e6eaee 100%);
        border-radius: 18px;
        border: 1px solid rgba(120, 130, 142, 0.18);
        padding: 12px;
        box-shadow: 0 22px 48px rgba(37, 45, 55, 0.16);
    }

    .pos-layout {
        min-height: calc(100vh - 48px);
        display: grid;
        grid-template-columns: 110px minmax(0, 1fr) 320px;
        gap: 16px;
        align-items: stretch;
    }

    .pos-side-rail {
        min-height: 0;
        border-radius: 20px;
        background: linear-gradient(180deg, #e4e7eb 0%, #dbdfe4 100%);
        border: 1px solid rgba(148, 156, 166, 0.18);
        padding: 16px 10px;
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: 18px;
    }

    .pos-side-brand {
        display: grid;
        justify-items: center;
        gap: 6px;
        text-align: center;
        color: #434b55;
    }

    .pos-side-brand-mark {
        color: #313741;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.9rem;
    }

    .pos-side-brand strong {
        display: block;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .pos-side-brand span {
        display: block;
        font-size: 11px;
        color: #9098a3;
    }

    .pos-rail-stack,
    .pos-rail-footer {
        display: grid;
        gap: 10px;
    }

    .pos-rail-tool {
        border: 1px solid rgba(148, 156, 166, 0.12);
        border-radius: 16px;
        padding: 10px 8px;
        background: #ffffff;
        color: #4b5561;
        display: grid;
        justify-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 800;
        text-align: center;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(70, 79, 91, 0.08);
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .pos-rail-tool:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 24px rgba(70, 79, 91, 0.12);
    }

    .pos-rail-tool i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #a7b0bb;
    }

    .pos-rail-tool span {
        display: block;
        line-height: 1.2;
    }

    .pos-workspace,
    .pos-sidebar {
        min-width: 0;
        min-height: 0;
        border-radius: 20px;
    }

    .pos-workspace {
        background: transparent;
        padding: 0;
        display: grid;
        grid-template-rows: auto 1fr;
        gap: 16px;
    }

    .pos-workspace-toolbar {
        display: grid;
        grid-template-columns: 170px minmax(0, 1fr) minmax(230px, 290px) auto;
        gap: 12px;
        align-items: center;
    }

    .pos-filter-search {
        position: relative;
    }

    .pos-filter-search i {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        font-size: 16px;
        color: #8f98a3;
        pointer-events: none;
    }

    .pos-filter-search .pos-input {
        padding-left: 40px;
    }

    .pos-top-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .pos-toolbar-button,
    .pos-button,
    .pos-button-ghost,
    .pos-button-secondary {
        border-radius: 12px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        cursor: pointer;
    }

    .pos-toolbar-button {
        padding: 10px 14px;
        background: #ffffff;
        color: #3f4853;
        border: 1px solid #dde2e8;
        font-size: 12px;
        box-shadow: 0 8px 18px rgba(70, 79, 91, 0.08);
    }

    .pos-button {
        padding: 12px 16px;
        border: 0;
        background: linear-gradient(180deg, #62d2cd 0%, #45bebb 100%);
        color: #ffffff;
        box-shadow: 0 12px 24px rgba(69, 190, 187, 0.28);
    }

    .pos-button-ghost {
        padding: 12px 16px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: #f3f5f7;
        color: #45505d;
    }

    .pos-button-secondary {
        padding: 12px 16px;
        background: #ffffff;
        color: #3a4551;
        border: 1px solid #dce2e8;
    }

    .pos-button[disabled],
    .pos-button-ghost[disabled],
    .pos-button-secondary[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
    }

    .pos-input,
    .pos-select,
    .pos-textarea {
        width: 100%;
        border: 1px solid #dbe1e7;
        background: #ffffff;
        border-radius: 14px;
        color: #29323d;
        padding: 12px 14px;
        box-shadow: 0 8px 18px rgba(70, 79, 91, 0.08);
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .pos-input:focus,
    .pos-select:focus,
    .pos-textarea:focus {
        outline: none;
        border-color: #a8b5c1;
        box-shadow: 0 0 0 4px rgba(163, 176, 193, 0.15);
    }

    .pos-category-strip {
        display: grid;
        gap: 10px;
        align-content: start;
        overflow-y: auto;
        padding-right: 2px;
    }

    .pos-category-tab {
        border: 1px solid rgba(148, 156, 166, 0.12);
        border-radius: 18px;
        padding: 14px 8px;
        background: #ffffff;
        color: #545d68;
        text-align: center;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(70, 79, 91, 0.08);
        transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        display: grid;
        justify-items: center;
        gap: 8px;
        min-height: 76px;
    }

    .pos-category-tab:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 24px rgba(70, 79, 91, 0.12);
    }

    .pos-category-tab.active {
        background: linear-gradient(180deg, #323842 0%, #282e37 100%);
        color: #ffffff;
    }

    .pos-category-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        font-weight: 700;
        color: #b1b8c2;
    }

    .pos-category-copy {
        display: grid;
        gap: 2px;
    }

    .pos-category-copy strong {
        display: block;
        font-size: 12px;
        line-height: 1.2;
    }

    .pos-category-copy span {
        display: block;
        font-size: 10px;
        color: #9aa3ae;
    }

    .pos-category-tab.active .pos-category-badge {
        color: #ffffff;
    }

    .pos-category-tab.active .pos-category-copy span {
        color: rgba(255, 255, 255, 0.72);
    }

    .pos-order-type-toolbar {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
        padding: 4px;
        border-radius: 16px;
        background: #e4e8ed;
        border: 1px solid #d7dde4;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
    }

    .pos-order-type {
        border: 0;
        border-radius: 12px;
        padding: 10px 8px;
        background: transparent;
        color: #687382;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 800;
        text-align: center;
        cursor: pointer;
        transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
    }

    .pos-order-type i {
        width: 28px;
        height: 28px;
        border-radius: 10px;
        background: #dce2e8;
        color: #4a5564;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }

    .pos-order-type.active {
        background: #ffffff;
        color: #25303c;
        box-shadow: 0 8px 18px rgba(70, 79, 91, 0.1);
    }

    .pos-order-type.active i {
        background: #313741;
        color: #ffffff;
    }

    .pos-products-panel {
        display: grid;
        grid-template-rows: auto 1fr;
        gap: 12px;
        min-height: 0;
    }

    .pos-products-head h3 {
        margin: 0;
        color: #313844;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .pos-products-head p {
        margin: 4px 0 0;
        color: #8e97a3;
        font-size: 11px;
    }

    .pos-products-head {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
    }

    .pos-products-count {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6f7986;
        white-space: nowrap;
    }

    .pos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, 210px);
        gap: 16px;
        align-content: start;
        justify-content: start;
        overflow-y: auto;
        padding-right: 4px;
    }

    .pos-product-card {
        border-radius: 16px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(54, 63, 75, 0.1);
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        text-align: left;
        min-height: 100%;
        position: relative;
    }

    .pos-product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(54, 63, 75, 0.14);
    }

    .pos-product-card.in-cart {
        box-shadow: 0 0 0 2px rgba(89, 194, 196, 0.35), 0 18px 34px rgba(54, 63, 75, 0.16);
    }

    .pos-product-card.disabled,
    .pos-product-card[aria-disabled="true"] {
        opacity: 0.82;
        cursor: not-allowed;
        transform: none;
        box-shadow: 0 10px 18px rgba(54, 63, 75, 0.06);
    }

    .pos-product-art {
        width: 100%;
        aspect-ratio: 16 / 9;
        background: linear-gradient(145deg, #edf2f6 0%, #dde5ec 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .pos-product-art img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pos-product-cart-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border-radius: 999px;
        background: rgba(43, 49, 58, 0.88);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 800;
        backdrop-filter: blur(6px);
    }

    .pos-product-cart-badge i {
        font-size: 13px;
    }

    .pos-product-placeholder {
        width: 78px;
        height: 78px;
        border-radius: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #ffffff;
        text-transform: uppercase;
    }

    .pos-accent-mint { background: linear-gradient(135deg, #4dcf9f 0%, #27b579 100%); }
    .pos-accent-sky { background: linear-gradient(135deg, #65a8ff 0%, #4888f0 100%); }
    .pos-accent-amber { background: linear-gradient(135deg, #ffcc69 0%, #f0a43a 100%); }
    .pos-accent-coral { background: linear-gradient(135deg, #ff907f 0%, #ef6a63 100%); }
    .pos-accent-violet { background: linear-gradient(135deg, #9b87ff 0%, #6d63ef 100%); }
    .pos-accent-ink { background: linear-gradient(135deg, #536179 0%, #2b3447 100%); }

    .pos-product-unavailable {
        position: absolute;
        inset: 0;
        background: rgba(76, 82, 91, 0.54);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        backdrop-filter: blur(2px);
    }

    .pos-product-caption {
        background: #ffffff;
        color: #303944;
        padding: 12px 14px 16px;
        min-height: 92px;
        display: grid;
        gap: 6px;
    }

    .pos-product-title-row {
        display: block;
    }

    .pos-product-title-row h4 {
        margin: 0;
        font-size: 15px;
        line-height: 1.2;
        color: #2f3742;
        font-weight: 800;
    }

    .pos-product-title-row small {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: #9ca4af;
    }

    .pos-product-price {
        font-size: 13px;
        font-weight: 800;
        color: #28323d;
        white-space: nowrap;
        margin-top: 4px;
    }

    .pos-product-footer {
        margin-top: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .pos-product-add {
        border: 0;
        border-radius: 12px;
        padding: 9px 12px;
        background: linear-gradient(180deg, #5fd0cb 0%, #48bfbc 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 800;
        box-shadow: 0 10px 20px rgba(72, 191, 188, 0.24);
        cursor: pointer;
    }

    .pos-product-card.disabled .pos-product-add,
    .pos-product-card[aria-disabled="true"] .pos-product-add {
        background: #c5ccd4;
        box-shadow: none;
        cursor: not-allowed;
    }

    .pos-product-qty-chip {
        min-width: 42px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #eef3f6;
        color: #3c4753;
        text-align: center;
        font-size: 11px;
        font-weight: 800;
    }

    .pos-product-meta {
        font-size: 11px;
        color: #8e98a6;
        min-height: 16px;
    }

    .pos-stock-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #7ad89f;
        display: inline-block;
        margin-right: 6px;
    }

    .pos-empty {
        border: 1px dashed #cfd7df;
        border-radius: 18px;
        background: #ffffff;
        padding: 34px 24px;
        text-align: center;
    }

    .pos-empty i {
        display: inline-block;
        font-size: 46px;
        color: #6d7785;
        margin-bottom: 12px;
    }

    .pos-empty h4 {
        margin-bottom: 8px;
        color: #2d3641;
    }

    .pos-empty p {
        margin-bottom: 16px;
        color: #8b95a3;
    }

    .pos-empty-actions {
        display: inline-flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .pos-sidebar {
        background: #ffffff;
        border: 1px solid rgba(148, 156, 166, 0.15);
        padding: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto auto;
        overflow: hidden;
    }

    .pos-cart-top {
        background: linear-gradient(180deg, #40464f 0%, #343941 100%);
        color: #ffffff;
        padding: 14px 16px 12px;
    }

    .pos-cart-shell-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .pos-cart-label span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.01em;
        color: rgba(255, 255, 255, 0.88);
        margin-bottom: 8px;
    }

    .pos-cart-label strong {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 10px;
        border-radius: 8px;
        background: #58c2c4;
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.02em;
    }

    .pos-order-switcher {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 4px;
        margin-top: 14px;
        padding: 4px;
        border-radius: 12px;
        background: #f0f2f4;
    }

    .pos-order-switcher button {
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #8a95a3;
        font-size: 12px;
        font-weight: 800;
        padding: 10px 12px;
        transition: background 0.18s ease, color 0.18s ease;
    }

    .pos-order-switcher .active {
        background: #ffffff;
        color: #27313d;
        box-shadow: 0 8px 16px rgba(70, 79, 91, 0.08);
    }

    .pos-clear-button {
        border: 0;
        border-radius: 12px;
        width: 34px;
        height: 34px;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .pos-sidebar-body {
        min-height: 0;
        overflow: hidden;
    }

    .pos-sidebar-panel {
        display: none;
        height: 100%;
        overflow-y: auto;
        padding: 14px 16px;
        background: #ffffff;
    }

    .pos-sidebar-panel.active {
        display: block;
    }

    .pos-panel-copy {
        margin-bottom: 12px;
        color: #8a94a1;
        font-size: 11px;
        line-height: 1.45;
    }

    .pos-ticket-context {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .pos-ticket-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #f4f7fa;
        border: 1px solid #e4e9ef;
        color: #54606f;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .pos-ticket-chip strong {
        color: #27313d;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: none;
    }

    .pos-order-list {
        display: grid;
        gap: 0;
        max-height: 100%;
        overflow-y: auto;
    }

    .pos-order-item {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr) auto;
        gap: 12px;
        align-items: start;
        padding: 14px 0;
        border-bottom: 1px solid #edf1f5;
    }

    .pos-order-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .pos-order-thumb {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        overflow: hidden;
        background: linear-gradient(145deg, #edf2f6 0%, #dde5ec 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pos-order-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pos-order-thumb .pos-product-placeholder {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        font-size: 0.82rem;
    }

    .pos-order-copy strong {
        display: block;
        color: #303944;
        font-size: 13px;
        margin-bottom: 3px;
    }

    .pos-order-copy span {
        display: block;
        color: #9099a4;
        font-size: 11px;
        margin-bottom: 5px;
    }

    .pos-order-copy .pos-order-unit {
        color: #323c47;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .pos-order-controls {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
    }

    .pos-order-quantity {
        min-width: 22px;
        text-align: center;
        font-size: 12px;
        font-weight: 800;
        color: #25303c;
    }

    .pos-qty-button {
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 10px;
        background: #eef2f5;
        color: #687383;
        font-size: 16px;
        cursor: pointer;
    }

    .pos-order-side {
        text-align: right;
        display: grid;
        gap: 8px;
        justify-items: end;
    }

    .pos-order-price {
        color: #293440;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.2;
    }

    .pos-order-remove {
        border: 1px solid #eceff3;
        border-radius: 10px;
        background: #ffffff;
        color: #919aa6;
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        cursor: pointer;
    }

    .pos-summary {
        background: linear-gradient(180deg, #454a53 0%, #3a3f47 100%);
        padding: 14px 16px;
        color: #ffffff;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .pos-summary-row,
    .pos-summary-total,
    .pos-summary-change {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .pos-summary-row {
        color: rgba(255, 255, 255, 0.78);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .pos-summary-divider {
        height: 1px;
        margin: 12px 0;
        background: rgba(255, 255, 255, 0.12);
    }

    .pos-summary-total strong,
    .pos-summary-change span:first-child {
        color: #ffffff;
        font-size: 15px;
        font-weight: 800;
    }

    .pos-summary-total span:last-child {
        color: #ffffff;
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
    }

    .pos-summary-change {
        padding-top: 10px;
        border-top: 1px dashed rgba(255, 255, 255, 0.18);
    }

    .pos-summary-change span:last-child {
        color: #a9efc5;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .pos-promo-note,
    .pos-summary-meta {
        margin-top: 10px;
        font-size: 11px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.72);
    }

    .pos-sidebar-actions {
        display: grid;
        grid-template-columns: 76px 76px minmax(0, 1fr);
        gap: 10px;
        padding: 14px 16px 16px;
        background: linear-gradient(180deg, #454a53 0%, #3a3f47 100%);
    }

    .pos-sidebar-actions .pos-button,
    .pos-sidebar-actions .pos-button-ghost,
    .pos-sidebar-actions .pos-button-secondary {
        width: 100%;
    }

    .pos-hold-list {
        display: grid;
        gap: 8px;
    }

    .pos-hold-item {
        border-radius: 14px;
        background: #f5f7f9;
        border: 1px solid #e1e6ec;
        padding: 10px 11px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
    }

    .pos-hold-item strong {
        display: block;
        color: #29333f;
        font-size: 12px;
        margin-bottom: 3px;
    }

    .pos-hold-item span {
        display: block;
        color: #8a95a3;
        font-size: 10px;
        line-height: 1.35;
    }

    .pos-hold-actions {
        display: inline-flex;
        gap: 6px;
    }

    .pos-icon-button {
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 10px;
        background: #e8edf2;
        color: #4a5564;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .pos-icon-button.danger {
        background: #fff1f1;
        color: #de6565;
    }

    .pos-status {
        background: rgba(65, 71, 81, 0.94);
        padding: 10px 16px 12px;
        font-size: 12px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.76);
    }

    .pos-status.success {
        background: rgba(46, 138, 85, 0.18);
        color: #d5f5e0;
    }

    .pos-status.warning {
        background: rgba(179, 129, 22, 0.18);
        color: #ffebb8;
    }

    .pos-status.error {
        background: rgba(200, 88, 88, 0.18);
        color: #ffd2d2;
    }

    .pos-action-compact {
        padding: 12px 8px;
        flex-direction: column;
        gap: 6px;
        font-size: 11px;
    }

    .pos-action-compact i {
        font-size: 1.1rem;
    }

    .pos-settings-modal[hidden],
    .pos-receipt-footer {
        display: none;
    }

    .pos-settings-modal {
        position: fixed;
        inset: 0;
        z-index: 1080;
    }

    .pos-settings-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(22, 28, 36, 0.56);
        backdrop-filter: blur(4px);
    }

    .pos-settings-dialog {
        position: relative;
        width: min(620px, calc(100vw - 24px));
        max-height: calc(100vh - 32px);
        margin: 16px auto;
        border-radius: 24px;
        overflow: hidden;
        background: #f5f7fa;
        box-shadow: 0 30px 70px rgba(21, 28, 36, 0.28);
        display: grid;
        grid-template-rows: auto 1fr auto;
    }

    .pos-settings-head,
    .pos-settings-foot {
        padding: 18px 20px;
        background: #ffffff;
    }

    .pos-settings-head {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 1px solid #e5eaf0;
    }

    .pos-settings-head h3 {
        margin: 0;
        color: #27313d;
        font-size: 1.05rem;
        font-weight: 900;
    }

    .pos-settings-head p {
        margin: 6px 0 0;
        color: #8a94a1;
        font-size: 12px;
    }

    .pos-settings-close {
        width: 38px;
        height: 38px;
        border: 0;
        border-radius: 14px;
        background: #eef2f6;
        color: #53606f;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        cursor: pointer;
    }

    .pos-settings-body {
        padding: 20px;
        overflow-y: auto;
        display: grid;
        gap: 18px;
    }

    .pos-settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .pos-settings-block {
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid #e6ebf1;
        padding: 16px;
        display: grid;
        gap: 12px;
    }

    .pos-settings-block.full {
        grid-column: 1 / -1;
    }

    .pos-settings-block h4 {
        margin: 0;
        color: #27313d;
        font-size: 13px;
        font-weight: 900;
    }

    .pos-settings-label {
        display: grid;
        gap: 6px;
        color: #697586;
        font-size: 11px;
        font-weight: 800;
    }

    .pos-settings-label select,
    .pos-settings-label input,
    .pos-settings-label textarea {
        width: 100%;
        border-radius: 12px;
        border: 1px solid #dce3ea;
        background: #fbfcfd;
        color: #2d3742;
        padding: 11px 12px;
        font-size: 12px;
        font-weight: 600;
        outline: none;
    }

    .pos-settings-label textarea {
        min-height: 92px;
        resize: vertical;
    }

    #pos-payment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    #pos-payment-grid button,
    .pos-tender-chip {
        border: 1px solid #dce3ea;
        border-radius: 12px;
        background: #f7f9fb;
        color: #54606f;
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
    }

    #pos-payment-grid button.active {
        background: #27313d;
        border-color: #27313d;
        color: #ffffff;
        box-shadow: 0 10px 20px rgba(39, 49, 61, 0.18);
    }

    #pos-tender-chip-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .pos-settings-note {
        color: #8a94a1;
        font-size: 11px;
        line-height: 1.45;
    }

    .pos-settings-links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pos-settings-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 12px;
        border-radius: 12px;
        background: #f2f6fa;
        border: 1px solid #e1e8ef;
        color: #42505f;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .pos-settings-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-top: 1px solid #e5eaf0;
    }

    .pos-settings-meta {
        color: #8a94a1;
        font-size: 11px;
        line-height: 1.45;
    }

    .pos-settings-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 1399.98px) {
        .pos-layout {
            grid-template-columns: 110px 1fr;
        }

        .pos-sidebar {
            grid-column: 1 / -1;
            min-height: 620px;
        }

        .pos-side-rail {
            grid-row: span 2;
        }

        .pos-workspace-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .pos-layout {
            grid-template-columns: 1fr;
        }

        .pos-side-rail {
            grid-template-rows: auto auto auto;
        }

        .pos-rail-stack,
        .pos-rail-footer {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pos-workspace-toolbar,
        .pos-products-head {
            flex-direction: column;
            align-items: stretch;
        }

        .pos-grid,
        .pos-sidebar-actions {
            grid-template-columns: 1fr;
        }

        .pos-category-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pos-order-type-toolbar {
            width: 100%;
        }

        .pos-settings-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .pos-page {
            padding: 8px;
        }

        .pos-terminal {
            min-height: auto;
            padding: 10px;
        }

        .pos-layout {
            gap: 12px;
        }

        .pos-grid {
            grid-template-columns: 1fr;
        }

        .pos-category-strip,
        .pos-rail-footer {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
<div class="pos-page">
    <div class="pos-terminal">
        <div class="pos-layout">
            <aside class="pos-side-rail">
                <div class="pos-side-brand">
                    <div class="pos-side-brand-mark">
                        <i class="uil-restaurant"></i>
                    </div>
                    <div>
                        <strong>{{ $storeName }}</strong>
                        <span>Cashier menu</span>
                    </div>
                </div>

                <div class="pos-rail-stack">
                    <div class="pos-category-strip" id="pos-category-strip">
                        <button type="button" class="pos-category-tab {{ is_null($workspace['selected_category_id']) ? 'active' : '' }}" data-category-id="">
                            <span class="pos-category-badge pos-accent-ink">
                                <i class="uil-apps"></i>
                            </span>
                            <span class="pos-category-copy">
                                <strong>All Dishes</strong>
                                <span>{{ count($workspace['all_items']) }} choices</span>
                            </span>
                        </button>
                        @foreach ($workspace['categories'] as $category)
                            <button
                                type="button"
                                class="pos-category-tab {{ $workspace['selected_category_id'] === $category['id'] ? 'active' : '' }}"
                                data-category-id="{{ $category['id'] }}">
                                <span class="pos-category-badge pos-accent-{{ $categoryAccentPalette[$loop->index % count($categoryAccentPalette)] }}">
                                    <i class="{{ $categoryIconResolver((string) $category['name']) }}"></i>
                                </span>
                                <span class="pos-category-copy">
                                    <strong>{{ $category['name'] }}</strong>
                                    <span>{{ $category['count'] }} choices</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="pos-rail-footer">
                    <button type="button" class="pos-rail-tool teal" id="pos-hold-cart">
                        <i class="uil-save"></i>
                        <span>Hold Cart</span>
                    </button>
                    <button type="button" class="pos-rail-tool slate" id="pos-new-sale">
                        <i class="uil-file-plus-alt"></i>
                        <span>New Sale</span>
                    </button>
                </div>
            </aside>

            <section class="pos-workspace">
                <div class="pos-workspace-toolbar">
                    <select class="pos-select" id="pos-branch-filter">
                        <option value="">All Branches</option>
                        @foreach ($workspace['branches'] as $branch)
                            <option value="{{ $branch['id'] }}" @selected($workspace['selected_branch_id'] === $branch['id'])>
                                {{ $branch['name'] }}
                            </option>
                        @endforeach
                    </select>

                    <div class="pos-filter-search">
                        <i class="uil-search"></i>
                        <input
                            type="search"
                            class="pos-input"
                            id="pos-search"
                            placeholder="Search dishes, SKU, branch, or category"
                            value="{{ $workspace['search'] }}">
                    </div>

                    <div class="pos-order-type-toolbar" id="pos-order-type-group">
                        <button type="button" class="pos-order-type active" data-order-type="dine_in">
                            <i class="uil-shop"></i>
                            <span>Dine In</span>
                        </button>
                        <button type="button" class="pos-order-type" data-order-type="takeaway">
                            <i class="uil-shopping-basket"></i>
                            <span>Takeaway</span>
                        </button>
                        <button type="button" class="pos-order-type" data-order-type="delivery">
                            <i class="uil-truck"></i>
                            <span>Delivery</span>
                        </button>
                    </div>

                    <div class="pos-top-actions">
                        <a href="{{ route('home') }}" class="pos-toolbar-button">
                            <i class="uil-estate"></i>Dashboard
                        </a>
                        <button type="button" class="pos-toolbar-button" id="pos-reset-filters">
                            <i class="uil-refresh"></i>Reset
                        </button>
                        @if ($canManageItems)
                            <a href="{{ route('admin.items.create') }}" class="pos-toolbar-button">
                                <i class="uil-plus"></i>New Item
                            </a>
                        @endif
                    </div>
                </div>

                <div class="pos-products-panel">
                    <div class="pos-products-head">
                        <div>
                            <h3>Menu Catalog</h3>
                            <p><span id="pos-metric-items">{{ count($workspace['all_items']) }}</span> dishes · <span id="pos-metric-categories">{{ count($workspace['categories']) }}</span> categories · <span id="pos-ticket-time">Now</span></p>
                        </div>
                        <div class="pos-products-count" id="pos-products-count">
                            {{ count($workspace['visible_items']) }} items ready
                        </div>
                    </div>

                    <div id="pos-product-grid" class="pos-grid">
                        @forelse ($workspace['visible_items'] as $item)
                            <article
                                class="pos-product-card {{ $item['is_available'] ? '' : 'disabled' }}"
                                data-item-id="{{ $item['id'] }}"
                                aria-disabled="{{ $item['is_available'] ? 'false' : 'true' }}">
                                <div class="pos-product-art">
                                    @if ($item['image_url'])
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                                    @else
                                        <span class="pos-product-placeholder pos-accent-{{ $item['accent'] }}">{{ $item['placeholder'] }}</span>
                                    @endif
                                    @unless ($item['is_available'])
                                        <div class="pos-product-unavailable">Unavailable</div>
                                    @endunless
                                </div>
                                <div class="pos-product-caption">
                                    <div class="pos-product-title-row">
                                        <div>
                                            <h4>{{ $item['name'] }}</h4>
                                            <small>{{ $item['foreign_name'] ?: $item['category_name'] ?: $item['sku'] }}</small>
                                        </div>
                                    </div>
                                    <div class="pos-product-meta">{{ $item['is_available'] ? ($item['category_name'] ?: 'Ready to order') : 'Unavailable right now' }}</div>
                                    <div class="pos-product-footer">
                                        <div class="pos-product-price">{{ $item['display_price_label'] }}</div>
                                        <button type="button" class="pos-product-add" data-action="add-product" data-item-id="{{ $item['id'] }}" @disabled(! $item['is_available'])>
                                            <i class="uil-plus"></i>
                                            <span>Add</span>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="pos-empty" style="grid-column: 1 / -1;" id="pos-initial-empty">
                                <i class="uil-box"></i>
                                @if (! $workspace['has_items'])
                                    <h4>No active items yet</h4>
                                    <p>Create products and categories for this tenant to start taking orders from the POS screen.</p>
                                    <div class="pos-empty-actions">
                                        @if ($canManageItems)
                                            <a href="{{ route('admin.items.create') }}" class="pos-button">Create Item</a>
                                        @endif
                                        @if ($canManageCategories)
                                            <a href="{{ route('admin.categories.create') }}" class="pos-button-ghost">Create Category</a>
                                        @endif
                                    </div>
                                @else
                                    <h4>No items match these filters</h4>
                                    <p>Try another branch, category, or search keyword to see more products.</p>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <aside class="pos-sidebar">
                <div class="pos-cart-top">
                    <div class="pos-cart-shell-head">
                        <div class="pos-cart-label">
                            <span><i class="uil-restaurant"></i> Table 01</span>
                            <strong id="pos-receipt-number">Order #000001</strong>
                        </div>
                        <button type="button" class="pos-clear-button" id="pos-clear-cart" title="Clear current order">
                            <i class="uil-trash-alt"></i>
                        </button>
                    </div>
                    <div class="pos-order-switcher">
                        <button type="button" class="active" data-sidebar-view="order">New Order (<span id="pos-current-order-count">0</span>)</button>
                        <button type="button" data-sidebar-view="history">Order History (<span id="pos-held-count">0</span>)</button>
                    </div>
                </div>

                <div class="pos-sidebar-body">
                    <section class="pos-sidebar-panel active" data-panel="order">
                        <div class="pos-panel-copy" id="pos-summary-meta">Ready for checkout.</div>
                        <div class="pos-ticket-context" id="pos-ticket-context"></div>
                        <div class="pos-order-list" id="pos-order-list">
                            <div class="pos-empty">
                                <i class="uil-shopping-basket"></i>
                                <h4>Cart is empty</h4>
                                <p>Select a product card to build the current order.</p>
                            </div>
                        </div>
                    </section>

                    <section class="pos-sidebar-panel" data-panel="history" hidden>
                        <div class="pos-panel-copy">Saved carts on this device can be reopened here when you need to switch guests quickly.</div>
                        <div class="pos-hold-list" id="pos-hold-list">
                            <div class="pos-status">No held carts yet. Save a draft order to switch between customers quickly.</div>
                        </div>
                    </section>
                </div>

                <div class="pos-summary">
                    <div class="pos-summary-row">
                        <span>Subtotal</span>
                        <span id="pos-subtotal">{{ $workspace['base_currency']['code'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Discounts</span>
                        <span id="pos-discount">{{ $workspace['base_currency']['code'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Due</span>
                        <span id="pos-amount-due">{{ $workspace['base_currency']['code'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-divider"></div>
                    <div class="pos-summary-total">
                        <strong>Total</strong>
                        <span id="pos-total">{{ $workspace['base_currency']['code'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-change">
                        <span>Change</span>
                        <span id="pos-change-due">{{ $workspace['base_currency']['code'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-promo-note" id="pos-promotion-note">No promotion applied.</div>
                </div>

                <div class="pos-status" id="pos-status-note">
                    Orders stay in this browser for now, so held carts and completed sales behave like a real counter workflow without saving to the database yet.
                </div>

                <div class="pos-sidebar-actions">
                    <button type="button" class="pos-button-secondary pos-action-compact" id="pos-settings-action">
                        <i class="uil-setting"></i>
                        <span>Settings</span>
                    </button>
                    <button type="button" class="pos-button-ghost pos-action-compact" id="pos-print-receipt">
                        <i class="uil-bill"></i>
                        <span>Bill</span>
                    </button>
                    <button type="button" class="pos-button" id="pos-complete-sale">
                        <i class="uil-check-circle"></i>Submit Order
                    </button>
                </div>

                <div class="pos-settings-modal" id="pos-settings-modal" hidden>
                    <div class="pos-settings-backdrop" data-settings-close></div>
                    <div class="pos-settings-dialog" role="dialog" aria-modal="true" aria-labelledby="pos-settings-title">
                        <div class="pos-settings-head">
                            <div>
                                <h3 id="pos-settings-title">Ticket Settings</h3>
                                <p>Choose the customer, attach a price list, and tune ticket details before checkout.</p>
                            </div>
                            <button type="button" class="pos-settings-close" id="pos-settings-close" aria-label="Close settings">
                                <i class="uil-times"></i>
                            </button>
                        </div>

                        <div class="pos-settings-body">
                            <div class="pos-settings-grid">
                                <div class="pos-settings-block">
                                    <h4>Customer</h4>
                                    <label class="pos-settings-label">
                                        <span>Select Customer</span>
                                        <select id="pos-customer-select">
                                            <option value="">Walk-in Guest</option>
                                            @foreach ($workspace['customers'] as $customer)
                                                <option value="{{ $customer['id'] }}">{{ $customer['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="pos-settings-label">
                                        <span>Ticket Name</span>
                                        <input type="text" id="pos-customer-name" placeholder="Walk-in Guest">
                                    </label>
                                    <div class="pos-settings-note">Use a saved customer or type a custom name for this order.</div>
                                </div>

                                <div class="pos-settings-block">
                                    <h4>Pricing</h4>
                                    <label class="pos-settings-label">
                                        <span>Price List</span>
                                        <select id="pos-price-list-select">
                                            <option value="">Default Price</option>
                                            @foreach ($workspace['price_lists'] as $priceList)
                                                <option value="{{ $priceList['id'] }}">
                                                    {{ $priceList['name'] }}@if ($priceList['is_default']) · Default @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div class="pos-settings-note" id="pos-price-list-summary">Base item pricing is active for this ticket.</div>
                                </div>

                                <div class="pos-settings-block">
                                    <h4>Checkout</h4>
                                    <label class="pos-settings-label">
                                        <span>Order Type</span>
                                        <select id="pos-order-type-select">
                                            <option value="dine_in">Dine In</option>
                                            <option value="takeaway">Takeaway</option>
                                            <option value="delivery">Delivery</option>
                                        </select>
                                    </label>
                                    <div class="pos-settings-label">
                                        <span>Payment Method</span>
                                        <div id="pos-payment-grid">
                                            <button type="button" data-payment-method="cash">Cash</button>
                                            <button type="button" data-payment-method="card">Card</button>
                                            <button type="button" data-payment-method="other">Other</button>
                                        </div>
                                    </div>
                                    <label class="pos-settings-label">
                                        <span>Cash Received</span>
                                        <input type="number" min="0" step="0.01" id="pos-cash-received" placeholder="0.00">
                                    </label>
                                    <div id="pos-tender-chip-grid"></div>
                                </div>

                                <div class="pos-settings-block full">
                                    <h4>Order Note</h4>
                                    <label class="pos-settings-label">
                                        <span>Kitchen or delivery note</span>
                                        <textarea id="pos-order-note" placeholder="Special request, table number, delivery note"></textarea>
                                    </label>
                                    <div class="pos-settings-links">
                                        @if ($canViewCustomers)
                                            <a href="{{ route('admin.customers.index') }}" class="pos-settings-link">
                                                <i class="uil-users-alt"></i>Manage Customers
                                            </a>
                                        @endif
                                        @if ($canViewPriceLists)
                                            <a href="{{ route('admin.price-lists.index') }}" class="pos-settings-link">
                                                <i class="uil-tag-alt"></i>Manage Price Lists
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <button type="button" id="pos-clear-cart-secondary" hidden></button>
                            <button type="button" id="pos-hold-cart-secondary" hidden></button>
                            <button type="button" id="pos-new-sale-secondary" hidden></button>
                            <div id="pos-receipt-order-type" hidden></div>
                            <div id="pos-receipt-customer" hidden></div>
                            <div id="pos-receipt-branch" hidden></div>
                            <div id="pos-ticket-number" hidden></div>
                            <div id="pos-ticket-branch" hidden></div>
                            <div id="pos-metric-cart" hidden></div>
                        </div>

                        <div class="pos-settings-foot">
                            <div class="pos-settings-meta">
                                These settings stay on this browser for testing, and selected price lists are stored on the ticket UI for now.
                            </div>
                            <div class="pos-settings-actions">
                                <button type="button" class="pos-button-ghost" id="pos-settings-cancel">Close</button>
                                <button type="button" class="pos-button-secondary" id="pos-settings-save">Save Ticket Settings</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pos-receipt-footer" id="pos-receipt-footer-copy">
                    {{ trim((string) ($tenantSettings['receipt_footer'] ?? '')) ?: 'Set a receipt footer in General Settings to show thank-you or tax text here.' }}
                </div>
            </aside>
        </div>
    </div>
</div>

@php($posPayload = [
    'workspace' => $workspace,
    'tenant' => [
        'id' => (string) $selectedTenant->id,
        'store_name' => $storeName,
        'display_name' => admin_tenant_display_name($selectedTenant),
        'address' => (string) ($tenantSettings['address'] ?? ''),
        'contact_phone' => (string) ($tenantSettings['contact_phone'] ?? ''),
        'contact_email' => (string) ($tenantSettings['contact_email'] ?? ''),
        'receipt_footer' => (string) ($tenantSettings['receipt_footer'] ?? ''),
    ],
    'routes' => [
        'price' => route('admin.pos.price'),
        'items_create' => route('admin.items.create'),
        'categories_create' => route('admin.categories.create'),
        'customers_index' => $canViewCustomers ? route('admin.customers.index') : null,
        'price_lists_index' => $canViewPriceLists ? route('admin.price-lists.index') : null,
    ],
    'permissions' => [
        'items_manage' => $canManageItems,
        'categories_manage' => $canManageCategories,
        'customers_view' => $canViewCustomers,
        'price_lists_view' => $canViewPriceLists,
    ],
])
<script type="application/json" id="pos-payload">{!! json_encode($posPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const payloadElement = document.getElementById('pos-payload');

        if (!payloadElement) {
            return;
        }

        const payload = JSON.parse(payloadElement.textContent);
        const workspace = payload.workspace;
        const itemsById = new Map(workspace.all_items.map((item) => [Number(item.id), item]));
        const customersById = new Map((workspace.customers || []).map((customer) => [Number(customer.id), customer]));
        const priceListsById = new Map((workspace.price_lists || []).map((priceList) => [Number(priceList.id), priceList]));
        const defaultPriceListId = Number((workspace.price_lists || []).find((priceList) => priceList.is_default)?.id || 0) || null;
        const orderTypeLabels = {
            dine_in: 'Dine In',
            takeaway: 'Takeaway',
            delivery: 'Delivery',
        };
        const paymentLabels = {
            cash: 'Cash',
            card: 'Card',
            other: 'Other',
        };
        const storageKeys = {
            draft: `tenant-pos:${payload.tenant.id}:draft`,
            holds: `tenant-pos:${payload.tenant.id}:holds`,
            counter: `tenant-pos:${payload.tenant.id}:receipt-counter`,
        };
        const state = {
            branchId: workspace.selected_branch_id ? Number(workspace.selected_branch_id) : null,
            categoryId: workspace.selected_category_id ? Number(workspace.selected_category_id) : null,
            search: workspace.search || '',
            sidebarView: 'order',
            customerId: null,
            customerName: '',
            customerNote: '',
            priceListId: defaultPriceListId,
            orderType: 'dine_in',
            paymentMethod: 'cash',
            cashReceived: '',
            cart: {},
            heldCarts: [],
            receiptNumber: '',
            pricing: {
                items: [],
                subtotal: 0,
                discount_total: 0,
                final_total: 0,
                applied_promotion: null
            },
            pricingPending: false,
            status: {
                tone: '',
                message: ''
            }
        };

        const productGrid = document.getElementById('pos-product-grid');
        const categoryStrip = document.getElementById('pos-category-strip');
        const productsCount = document.getElementById('pos-products-count');
        const branchFilter = document.getElementById('pos-branch-filter');
        const searchInput = document.getElementById('pos-search');
        const customerNameInput = document.getElementById('pos-customer-name');
        const customerSelect = document.getElementById('pos-customer-select');
        const priceListSelect = document.getElementById('pos-price-list-select');
        const priceListSummary = document.getElementById('pos-price-list-summary');
        const orderTypeSelect = document.getElementById('pos-order-type-select');
        const orderNoteInput = document.getElementById('pos-order-note');
        const orderTypeGroup = document.getElementById('pos-order-type-group');
        const orderList = document.getElementById('pos-order-list');
        const holdList = document.getElementById('pos-hold-list');
        const sidebarViewButtons = Array.from(document.querySelectorAll('[data-sidebar-view]'));
        const sidebarPanels = Array.from(document.querySelectorAll('[data-panel]'));
        const subtotalElement = document.getElementById('pos-subtotal');
        const discountElement = document.getElementById('pos-discount');
        const amountDueElement = document.getElementById('pos-amount-due');
        const totalElement = document.getElementById('pos-total');
        const changeDueElement = document.getElementById('pos-change-due');
        const promotionNote = document.getElementById('pos-promotion-note');
        const summaryMeta = document.getElementById('pos-summary-meta');
        const ticketContextElement = document.getElementById('pos-ticket-context');
        const clearCartButton = document.getElementById('pos-clear-cart');
        const clearCartSecondaryButton = document.getElementById('pos-clear-cart-secondary');
        const resetFiltersButton = document.getElementById('pos-reset-filters');
        const paymentGrid = document.getElementById('pos-payment-grid');
        const tenderChipGrid = document.getElementById('pos-tender-chip-grid');
        const holdCartButton = document.getElementById('pos-hold-cart');
        const holdCartSecondaryButton = document.getElementById('pos-hold-cart-secondary');
        const newSaleButton = document.getElementById('pos-new-sale');
        const newSaleSecondaryButton = document.getElementById('pos-new-sale-secondary');
        const printReceiptButton = document.getElementById('pos-print-receipt');
        const completeSaleButton = document.getElementById('pos-complete-sale');
        const settingsActionButton = document.getElementById('pos-settings-action');
        const settingsModal = document.getElementById('pos-settings-modal');
        const settingsCloseButton = document.getElementById('pos-settings-close');
        const settingsCancelButton = document.getElementById('pos-settings-cancel');
        const settingsSaveButton = document.getElementById('pos-settings-save');
        const cashReceivedInput = document.getElementById('pos-cash-received');
        const receiptNumberElement = document.getElementById('pos-receipt-number');
        const receiptOrderTypeElement = document.getElementById('pos-receipt-order-type');
        const receiptCustomerElement = document.getElementById('pos-receipt-customer');
        const receiptBranchElement = document.getElementById('pos-receipt-branch');
        const statusNote = document.getElementById('pos-status-note');
        const metricCart = document.getElementById('pos-metric-cart');
        const ticketNumberElement = document.getElementById('pos-ticket-number');
        const ticketTimeElement = document.getElementById('pos-ticket-time');
        const ticketBranchElement = document.getElementById('pos-ticket-branch');
        const currentOrderCountElement = document.getElementById('pos-current-order-count');
        const heldCountElement = document.getElementById('pos-held-count');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function safeGet(key, fallback = null) {
            try {
                const value = window.localStorage.getItem(key);
                return value === null ? fallback : value;
            } catch (error) {
                return fallback;
            }
        }

        function safeSet(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function currencyForAmount() {
            return workspace.base_currency || null;
        }

        function formatAmount(amount, currency) {
            const numericValue = Number(amount || 0);
            const decimals = Number(currency?.decimals ?? 2);
            const formatted = numericValue.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });

            return currency?.code ? `${currency.code} ${formatted}` : formatted;
        }

        function numberAmount(value) {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function initials(value, fallback = 'NA') {
            const parts = String(value || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!parts.length) {
                return fallback;
            }

            if (parts.length === 1) {
                return parts[0].slice(0, 2).toUpperCase();
            }

            return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
        }

        function categoryIcon(value) {
            const normalized = String(value || '').trim().toLowerCase();

            if (normalized.includes('meat') || normalized.includes('bbq') || normalized.includes('grill')) {
                return 'uil-drumstick';
            }

            if (normalized.includes('burger')) {
                return 'uil-hamburger';
            }

            if (normalized.includes('pizza')) {
                return 'uil-pizza-slice';
            }

            if (normalized.includes('drink') || normalized.includes('juice') || normalized.includes('coffee') || normalized.includes('tea')) {
                return 'uil-glass';
            }

            if (normalized.includes('dessert') || normalized.includes('cake') || normalized.includes('ice')) {
                return 'uil-ice-cream';
            }

            if (normalized.includes('snack') || normalized.includes('cookie')) {
                return 'uil-cookie';
            }

            return 'uil-utensils';
        }

        function accentTone(seed) {
            const palette = ['sky', 'amber', 'mint', 'coral', 'violet', 'ink'];
            const numericSeed = Number(seed);

            if (Number.isFinite(numericSeed) && numericSeed > 0) {
                return palette[numericSeed % palette.length];
            }

            return palette[String(seed || '').length % palette.length];
        }

        function nowLabel() {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date());
        }

        function nextReceiptNumber() {
            const rawCounter = Number(safeGet(storageKeys.counter, '0')) || 0;
            const nextCounter = rawCounter + 1;
            safeSet(storageKeys.counter, String(nextCounter));

            return `POS-${String(nextCounter).padStart(6, '0')}`;
        }

        function activeBranchName() {
            const branch = (workspace.branches || []).find((entry) => Number(entry.id) === Number(state.branchId));
            return branch ? branch.name : 'All Branches';
        }

        function selectedCustomer() {
            return customersById.get(Number(state.customerId || 0)) || null;
        }

        function selectedPriceList() {
            return priceListsById.get(Number(state.priceListId || 0)) || null;
        }

        function cartLines() {
            return Object.values(state.cart);
        }

        function cartItemCount() {
            return cartLines().reduce((total, line) => total + Number(line.quantity || 0), 0);
        }

        function cashReceivedAmount() {
            return Math.max(numberAmount(state.cashReceived), 0);
        }

        function amountDue() {
            return Math.max(Number(state.pricing.final_total || 0) - cashReceivedAmount(), 0);
        }

        function changeDue() {
            return Math.max(cashReceivedAmount() - Number(state.pricing.final_total || 0), 0);
        }

        function isCheckoutReady() {
            return cartLines().length > 0;
        }

        function localPricingSnapshot() {
            const items = cartLines().map((line) => {
                const product = itemsById.get(Number(line.item_id));
                const unitPrice = Number(product?.display_price ?? 0);
                const subtotal = unitPrice * Number(line.quantity);

                return {
                    item_id: Number(line.item_id),
                    quantity: Number(line.quantity),
                    unit_price: unitPrice,
                    line_subtotal: subtotal,
                    discount_amount: 0,
                    line_total: subtotal,
                };
            });

            const subtotal = items.reduce((total, line) => total + Number(line.line_subtotal), 0);

            return {
                items,
                subtotal,
                discount_total: 0,
                final_total: subtotal,
                applied_promotion: null,
            };
        }

        function sanitizeCart(rawCart) {
            const nextCart = {};

            Object.values(rawCart || {}).forEach((line) => {
                const itemId = Number(line?.item_id || 0);
                const quantity = Number(line?.quantity || 0);
                const product = itemsById.get(itemId);

                if (!product || !product.is_available || quantity <= 0) {
                    return;
                }

                nextCart[itemId] = {
                    item_id: itemId,
                    quantity: Math.min(quantity, Math.max(Number(product.stock || quantity), 1)),
                };
            });

            return nextCart;
        }

        function snapshotDraft() {
            return {
                branchId: state.branchId,
                categoryId: state.categoryId,
                search: state.search,
                customerId: state.customerId,
                customerName: state.customerName,
                customerNote: state.customerNote,
                priceListId: state.priceListId,
                orderType: state.orderType,
                paymentMethod: state.paymentMethod,
                cashReceived: state.cashReceived,
                cart: state.cart,
                receiptNumber: state.receiptNumber,
            };
        }

        function persistDraft() {
            safeSet(storageKeys.draft, JSON.stringify(snapshotDraft()));
        }

        function loadDraft() {
            const rawDraft = safeGet(storageKeys.draft);

            if (!rawDraft) {
                return;
            }

            try {
                const parsed = JSON.parse(rawDraft);
                state.branchId = parsed.branchId ? Number(parsed.branchId) : null;
                state.categoryId = parsed.categoryId ? Number(parsed.categoryId) : null;
                state.search = parsed.search || '';
                state.customerId = customersById.has(Number(parsed.customerId)) ? Number(parsed.customerId) : null;
                state.customerName = parsed.customerName || '';
                state.customerNote = parsed.customerNote || '';
                state.priceListId = priceListsById.has(Number(parsed.priceListId)) ? Number(parsed.priceListId) : defaultPriceListId;
                state.orderType = orderTypeLabels[parsed.orderType] ? parsed.orderType : 'dine_in';
                state.paymentMethod = paymentLabels[parsed.paymentMethod] ? parsed.paymentMethod : 'cash';
                state.cashReceived = parsed.cashReceived || '';
                state.cart = sanitizeCart(parsed.cart || {});
                state.receiptNumber = String(parsed.receiptNumber || '').trim() || nextReceiptNumber();
            } catch (error) {
                state.receiptNumber = nextReceiptNumber();
            }
        }

        function persistHeldCarts() {
            safeSet(storageKeys.holds, JSON.stringify(state.heldCarts));
        }

        function loadHeldCarts() {
            const rawHolds = safeGet(storageKeys.holds);

            if (!rawHolds) {
                state.heldCarts = [];
                return;
            }

            try {
                const parsed = JSON.parse(rawHolds);
                state.heldCarts = Array.isArray(parsed)
                    ? parsed.map((entry) => ({
                        id: String(entry.id || Date.now()),
                        label: String(entry.label || 'Held Cart'),
                        created_at: String(entry.created_at || nowLabel()),
                        branch_name: String(entry.branch_name || 'All Branches'),
                        customer_name: String(entry.customer_name || ''),
                        snapshot: {
                            ...entry.snapshot,
                            cart: sanitizeCart(entry?.snapshot?.cart || {}),
                        },
                    })).filter((entry) => Object.keys(entry.snapshot.cart || {}).length > 0)
                    : [];
            } catch (error) {
                state.heldCarts = [];
            }
        }

        function setStatus(message, tone = '') {
            state.status = { message, tone };
            renderStatus();
        }

        function renderStatus() {
            statusNote.textContent = state.status.message || 'Ready on the counter.';
            statusNote.className = 'pos-status' + (state.status.tone ? ` ${state.status.tone}` : '');
        }

        function currentFilteredItems() {
            const normalizedSearch = state.search.trim().toLowerCase();

            return workspace.all_items.filter((item) => {
                if (state.branchId && Number(item.branch_id) !== state.branchId) {
                    return false;
                }

                if (state.categoryId && Number(item.category_id) !== state.categoryId) {
                    return false;
                }

                if (normalizedSearch === '') {
                    return true;
                }

                const haystacks = [
                    item.name,
                    item.foreign_name,
                    item.sku,
                    item.category_name,
                    item.branch_name,
                ].filter(Boolean).map((value) => value.toLowerCase());

                return haystacks.some((value) => value.includes(normalizedSearch));
            });
        }

        function categoryScopedCounts() {
            const normalizedSearch = state.search.trim().toLowerCase();

            return workspace.all_items.filter((item) => {
                if (state.branchId && Number(item.branch_id) !== state.branchId) {
                    return false;
                }

                if (normalizedSearch === '') {
                    return true;
                }

                const haystacks = [
                    item.name,
                    item.foreign_name,
                    item.sku,
                    item.category_name,
                    item.branch_name,
                ].filter(Boolean).map((value) => value.toLowerCase());

                return haystacks.some((value) => value.includes(normalizedSearch));
            });
        }

        function emptyStateMarkup() {
            const hasItems = workspace.has_items;
            const hasVisibleItems = currentFilteredItems().length > 0;

            if (hasVisibleItems) {
                return '';
            }

            if (!hasItems) {
                return `
                    <div class="pos-empty" style="grid-column: 1 / -1;">
                        <i class="uil-box"></i>
                        <h4>No active items yet</h4>
                        <p>Create products and categories for this tenant to start taking orders from the POS screen.</p>
                        <div class="pos-empty-actions">
                            ${payload.permissions.items_manage ? `<a href="${payload.routes.items_create}" class="pos-button">Create Item</a>` : ''}
                            ${payload.permissions.categories_manage ? `<a href="${payload.routes.categories_create}" class="pos-button-ghost">Create Category</a>` : ''}
                        </div>
                    </div>
                `;
            }

            return `
                <div class="pos-empty" style="grid-column: 1 / -1;">
                    <i class="uil-search"></i>
                    <h4>No items match these filters</h4>
                    <p>Try another branch, category, or search keyword to see more products.</p>
                </div>
            `;
        }

        function renderCategories() {
            const scopedItems = categoryScopedCounts();
            const categories = workspace.categories.map((category) => ({
                ...category,
                count: scopedItems.filter((item) => Number(item.category_id) === Number(category.id)).length,
            }));
            const allCount = scopedItems.length;

            categoryStrip.innerHTML = `
                <button type="button" class="pos-category-tab ${state.categoryId === null ? 'active' : ''}" data-category-id="">
                    <span class="pos-category-badge pos-accent-ink"><i class="uil-apps"></i></span>
                    <span class="pos-category-copy">
                        <strong>All Dishes</strong>
                        <span>${allCount} choices</span>
                    </span>
                </button>
                ${categories.map((category) => `
                    <button type="button" class="pos-category-tab ${Number(category.id) === state.categoryId ? 'active' : ''}" data-category-id="${category.id}">
                        <span class="pos-category-badge pos-accent-${escapeHtml(accentTone(category.id || category.name))}"><i class="${escapeHtml(categoryIcon(category.name))}"></i></span>
                        <span class="pos-category-copy">
                            <strong>${escapeHtml(category.name)}</strong>
                            <span>${category.count} choices</span>
                        </span>
                    </button>
                `).join('')}
            `;
        }

        function formatReceiptBadge(value) {
            const receipt = String(value || '').trim();

            if (!receipt) {
                return 'Order #000000';
            }

            return receipt.startsWith('POS-')
                ? `Order #${receipt.slice(4)}`
                : receipt;
        }

        function renderProducts() {
            const filteredItems = currentFilteredItems();

            productsCount.textContent = `${filteredItems.length} items ready`;

            if (filteredItems.length === 0) {
                productGrid.innerHTML = emptyStateMarkup();
                return;
            }

            productGrid.innerHTML = filteredItems.map((item) => {
                const quantity = Number(state.cart[String(item.id)]?.quantity || 0);
                const availabilityText = item.is_available ? (item.category_name || 'Ready to order') : 'Unavailable right now';
                const imageMarkup = item.image_url
                    ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}">`
                    : `<span class="pos-product-placeholder pos-accent-${escapeHtml(item.accent)}">${escapeHtml(item.placeholder)}</span>`;
                const cartBadgeMarkup = quantity > 0
                    ? `
                        <span class="pos-product-cart-badge">
                            <i class="uil-shopping-basket"></i>
                            ${quantity}
                        </span>
                    `
                    : '';

                return `
                    <article
                        class="pos-product-card ${item.is_available ? '' : 'disabled'} ${quantity > 0 ? 'in-cart' : ''}"
                        data-item-id="${item.id}"
                        aria-disabled="${item.is_available ? 'false' : 'true'}">
                        <div class="pos-product-art">
                            ${imageMarkup}
                            ${cartBadgeMarkup}
                            ${item.is_available ? '' : '<div class="pos-product-unavailable">Unavailable</div>'}
                        </div>
                        <div class="pos-product-caption">
                            <div class="pos-product-title-row">
                                <div>
                                    <h4>${escapeHtml(item.name)}</h4>
                                    <small>${escapeHtml(item.foreign_name || item.category_name || item.sku || '')}</small>
                                </div>
                            </div>
                            <div class="pos-product-meta">${escapeHtml(availabilityText)}</div>
                            <div class="pos-product-footer">
                                <div class="pos-product-price">${escapeHtml(item.display_price_label)}</div>
                                <button type="button" class="pos-product-add" data-action="add-product" data-item-id="${item.id}" ${item.is_available ? '' : 'disabled'}>
                                    <i class="uil-plus"></i>
                                    <span>${quantity > 0 ? 'Add More' : 'Add'}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');
        }

        function updateUrl() {
            const url = new URL(window.location.href);

            if (state.branchId) {
                url.searchParams.set('branch_id', state.branchId);
            } else {
                url.searchParams.delete('branch_id');
            }

            if (state.categoryId) {
                url.searchParams.set('category_id', state.categoryId);
            } else {
                url.searchParams.delete('category_id');
            }

            if (state.search.trim() !== '') {
                url.searchParams.set('search', state.search.trim());
            } else {
                url.searchParams.delete('search');
            }

            window.history.replaceState({}, '', url);
        }

        function renderTicketMeta() {
            const customer = state.customerName.trim() || selectedCustomer()?.name || 'Walk-in Guest';

            receiptNumberElement.textContent = formatReceiptBadge(state.receiptNumber);
            receiptOrderTypeElement.textContent = orderTypeLabels[state.orderType];
            receiptCustomerElement.textContent = customer;
            receiptBranchElement.textContent = activeBranchName();
            if (ticketNumberElement) {
                ticketNumberElement.textContent = state.receiptNumber;
            }
            if (ticketTimeElement) {
                ticketTimeElement.textContent = nowLabel();
            }
            if (ticketBranchElement) {
                ticketBranchElement.textContent = activeBranchName();
            }
            if (metricCart) {
                metricCart.textContent = String(cartItemCount());
            }

            if (currentOrderCountElement) {
                currentOrderCountElement.textContent = String(cartItemCount());
            }
        }

        function renderTicketContext() {
            if (!ticketContextElement) {
                return;
            }

            const customer = state.customerName.trim() || selectedCustomer()?.name || 'Walk-in Guest';
            const priceList = selectedPriceList();

            ticketContextElement.innerHTML = `
                <span class="pos-ticket-chip">
                    <i class="uil-user"></i>
                    Customer
                    <strong>${escapeHtml(customer)}</strong>
                </span>
                <span class="pos-ticket-chip">
                    <i class="uil-tag-alt"></i>
                    Price
                    <strong>${escapeHtml(priceList?.name || 'Default')}</strong>
                </span>
                <span class="pos-ticket-chip">
                    <i class="uil-credit-card"></i>
                    Payment
                    <strong>${escapeHtml(paymentLabels[state.paymentMethod])}</strong>
                </span>
            `;

            if (priceListSummary) {
                priceListSummary.textContent = priceList
                    ? (priceList.summary || `${priceList.name} will stay attached to this ticket.`)
                    : 'Base item pricing is active for this ticket.';
            }
        }

        function renderSidebarView() {
            sidebarViewButtons.forEach((button) => {
                const isActive = button.getAttribute('data-sidebar-view') === state.sidebarView;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            sidebarPanels.forEach((panel) => {
                const isActive = panel.getAttribute('data-panel') === state.sidebarView;
                panel.classList.toggle('active', isActive);
                panel.hidden = !isActive;
            });
        }

        function renderOrderTypeButtons() {
            orderTypeGroup.querySelectorAll('[data-order-type]').forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-order-type') === state.orderType);
            });
        }

        function renderPaymentButtons() {
            paymentGrid.querySelectorAll('[data-payment-method]').forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-payment-method') === state.paymentMethod);
            });
        }

        function renderQuickTender() {
            const total = Number(state.pricing.final_total || 0);
            const currency = currencyForAmount();

            if (total <= 0) {
                tenderChipGrid.innerHTML = '<div class="pos-status">Add items to show quick tender amounts.</div>';
                return;
            }

            const options = [
                total,
                Math.ceil(total / 5) * 5,
                Math.ceil(total / 10) * 10,
                Math.ceil(total / 20) * 20,
            ].filter((value, index, array) => value > 0 && array.indexOf(value) === index);

            tenderChipGrid.innerHTML = options.map((value) => `
                <button type="button" class="pos-tender-chip" data-tender-value="${value}">
                    ${escapeHtml(formatAmount(value, currency))}
                </button>
            `).join('');
        }

        function renderSummary() {
            const hasCart = cartLines().length > 0;
            const pricing = hasCart ? state.pricing : localPricingSnapshot();
            const currency = currencyForAmount();

            subtotalElement.textContent = formatAmount(pricing.subtotal, currency);
            discountElement.textContent = formatAmount(pricing.discount_total, currency);
            totalElement.textContent = formatAmount(pricing.final_total, currency);
            amountDueElement.textContent = formatAmount(amountDue(), currency);
            changeDueElement.textContent = formatAmount(changeDue(), currency);

            if (pricing.applied_promotion) {
                promotionNote.textContent = `${pricing.applied_promotion.name} applied · saved ${formatAmount(pricing.applied_promotion.savings, currency)}`;
            } else {
                promotionNote.textContent = state.pricingPending ? 'Updating promotion totals...' : 'No promotion applied.';
            }

            if (!hasCart) {
                summaryMeta.textContent = 'Start a new ticket by tapping a dish from the menu.';
            } else if (state.pricingPending) {
                summaryMeta.textContent = 'Refreshing totals for the current order.';
            } else if (pricing.applied_promotion) {
                summaryMeta.textContent = 'Promotion pricing is already reflected in the total below.';
            } else {
                summaryMeta.textContent = 'This order is ready to print or submit.';
            }

            completeSaleButton.disabled = !hasCart;
            renderQuickTender();
        }

        function lineCurrency(product) {
            return product?.currency || workspace.base_currency || null;
        }

        function renderCart() {
            const entries = cartLines();

            if (entries.length === 0) {
                orderList.innerHTML = `
                    <div class="pos-empty">
                        <i class="uil-shopping-basket"></i>
                        <h4>Cart is empty</h4>
                        <p>Select a product card to build the current order.</p>
                    </div>
                `;
                renderSummary();
                return;
            }

            const pricedItems = new Map((state.pricing.items || []).map((line) => [Number(line.item_id), line]));

            orderList.innerHTML = entries.map((line) => {
                const itemId = Number(line.item_id);
                const product = itemsById.get(itemId);
                const pricedLine = pricedItems.get(itemId);
                const quantity = Number(line.quantity);
                const lineTotal = pricedLine ? Number(pricedLine.line_total) : Number(product?.display_price ?? 0) * quantity;

                return `
                    <div class="pos-order-item">
                        <div class="pos-order-thumb">
                            ${product?.image_url
                                ? `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}">`
                                : `<span class="pos-product-placeholder pos-accent-${escapeHtml(product?.accent || 'ink')}">${escapeHtml(product?.placeholder || 'IT')}</span>`
                            }
                        </div>
                        <div class="pos-order-copy">
                            <strong>${escapeHtml(product?.name || 'Unknown Item')}</strong>
                            <span class="pos-order-unit">${escapeHtml(product?.display_price_label || '')}</span>
                            <span>${escapeHtml(product?.foreign_name || product?.category_name || product?.sku || '')}</span>
                            <div class="pos-order-controls">
                                <button type="button" class="pos-qty-button" data-action="decrement" data-item-id="${itemId}">-</button>
                                <span class="pos-order-quantity">${quantity}</span>
                                <button type="button" class="pos-qty-button" data-action="increment" data-item-id="${itemId}">+</button>
                            </div>
                        </div>
                        <div class="pos-order-side">
                            <div class="pos-order-price">${escapeHtml(formatAmount(lineTotal, lineCurrency(product)))}</div>
                            <button type="button" class="pos-order-remove" data-action="remove" data-item-id="${itemId}">
                                <i class="uil-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            renderSummary();
        }

        function renderHeldCarts() {
            if (heldCountElement) {
                heldCountElement.textContent = String(state.heldCarts.length);
            }

            if (!state.heldCarts.length) {
                holdList.innerHTML = '<div class="pos-status">No held carts yet. Save a draft order to switch between customers quickly.</div>';
                return;
            }

            holdList.innerHTML = state.heldCarts.map((entry) => {
                const lineCount = Object.values(entry.snapshot.cart || {}).reduce((total, line) => total + Number(line.quantity || 0), 0);

                return `
                    <div class="pos-hold-item">
                        <div>
                            <strong>${escapeHtml(entry.label)}</strong>
                            <span>${escapeHtml(entry.branch_name)} · ${lineCount} items · ${escapeHtml(entry.created_at)}</span>
                            <span>${escapeHtml(entry.customer_name || 'Walk-in Guest')}</span>
                        </div>
                        <div class="pos-hold-actions">
                            <button type="button" class="pos-icon-button" data-action="resume-hold" data-hold-id="${escapeHtml(entry.id)}" title="Resume held cart">
                                <i class="uil-play"></i>
                            </button>
                            <button type="button" class="pos-icon-button danger" data-action="delete-hold" data-hold-id="${escapeHtml(entry.id)}" title="Delete held cart">
                                <i class="uil-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderInputs() {
            branchFilter.value = state.branchId ? String(state.branchId) : '';
            searchInput.value = state.search;
            customerNameInput.value = state.customerName;
            customerSelect.value = state.customerId ? String(state.customerId) : '';
            priceListSelect.value = state.priceListId ? String(state.priceListId) : '';
            orderTypeSelect.value = state.orderType;
            orderNoteInput.value = state.customerNote;
            cashReceivedInput.value = state.cashReceived;
            renderOrderTypeButtons();
            renderPaymentButtons();
        }

        function renderAll() {
            renderInputs();
            renderCategories();
            renderProducts();
            renderTicketMeta();
            renderTicketContext();
            renderSidebarView();
            renderCart();
            renderHeldCarts();
            renderStatus();
            updateUrl();
            persistDraft();
        }

        function openSettingsModal() {
            if (!settingsModal) {
                return;
            }

            renderInputs();
            renderTicketContext();
            settingsModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeSettingsModal() {
            if (!settingsModal) {
                return;
            }

            settingsModal.hidden = true;
            document.body.style.overflow = '';
        }

        let pricingTimer = null;

        function schedulePricing() {
            window.clearTimeout(pricingTimer);

            if (!cartLines().length) {
                state.pricing = localPricingSnapshot();
                state.pricingPending = false;
                renderCart();
                persistDraft();
                return;
            }

            state.pricingPending = true;
            renderSummary();

            pricingTimer = window.setTimeout(async function () {
                try {
                    const response = await fetch(payload.routes.price, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            items: cartLines().map((line) => ({
                                item_id: Number(line.item_id),
                                quantity: Number(line.quantity),
                            })),
                        }),
                    });

                    if (!response.ok) {
                        throw new Error('Unable to price cart');
                    }

                    state.pricing = await response.json();
                } catch (error) {
                    state.pricing = localPricingSnapshot();
                    state.pricing.applied_promotion = null;
                    setStatus('Live pricing could not be refreshed, so local item prices are shown instead.', 'warning');
                } finally {
                    state.pricingPending = false;
                    renderCart();
                    persistDraft();
                }
            }, 180);
        }

        function addToCart(itemId) {
            const product = itemsById.get(Number(itemId));

            if (!product || !product.is_available) {
                return;
            }

            const currentQuantity = Number(state.cart[itemId]?.quantity || 0);
            const nextQuantity = Math.min(currentQuantity + 1, Math.max(Number(product.stock || currentQuantity + 1), 1));

            state.cart[itemId] = {
                item_id: Number(itemId),
                quantity: nextQuantity,
            };

            state.sidebarView = 'order';
            renderProducts();
            setStatus(`${product.name} added to the current order.`, 'success');
            renderCart();
            schedulePricing();
            renderTicketMeta();
        }

        function updateQuantity(itemId, delta) {
            const product = itemsById.get(Number(itemId));
            const currentQuantity = Number(state.cart[itemId]?.quantity || 0);

            if (!currentQuantity) {
                return;
            }

            const nextQuantity = currentQuantity + delta;

            if (nextQuantity <= 0) {
                delete state.cart[itemId];
            } else {
                const maxQuantity = Number(product?.stock || nextQuantity);
                state.cart[itemId].quantity = Math.min(nextQuantity, maxQuantity);
            }

            renderProducts();
            renderCart();
            schedulePricing();
            renderTicketMeta();
        }

        function removeLine(itemId) {
            const product = itemsById.get(Number(itemId));
            delete state.cart[itemId];
            renderProducts();
            setStatus(`${product?.name || 'Item'} removed from the current order.`, 'warning');
            renderCart();
            schedulePricing();
            renderTicketMeta();
        }

        function resetSaleDraft(keepFilters = true) {
            state.cart = {};
            state.pricing = localPricingSnapshot();
            state.pricingPending = false;
            state.sidebarView = 'order';
            state.customerId = null;
            state.customerName = '';
            state.customerNote = '';
            state.priceListId = defaultPriceListId;
            state.cashReceived = '';
            state.paymentMethod = 'cash';
            state.orderType = 'dine_in';
            state.receiptNumber = nextReceiptNumber();

            if (!keepFilters) {
                state.branchId = null;
                state.categoryId = null;
                state.search = '';
            }

            renderAll();
        }

        function holdCurrentCart() {
            if (!cartLines().length) {
                setStatus('Add items before holding a cart.', 'warning');
                return;
            }

            const label = state.customerName.trim() || `${orderTypeLabels[state.orderType]} Ticket`;

            state.heldCarts.unshift({
                id: `${Date.now()}`,
                label,
                created_at: nowLabel(),
                branch_name: activeBranchName(),
                customer_name: state.customerName.trim(),
                snapshot: snapshotDraft(),
            });

            state.heldCarts = state.heldCarts.slice(0, 12);
            persistHeldCarts();
            state.sidebarView = 'history';
            setStatus(`${label} saved to held carts.`, 'success');
            resetSaleDraft(true);
            state.sidebarView = 'history';
            persistHeldCarts();
            renderSidebarView();
            renderHeldCarts();
        }

        function resumeHeldCart(holdId) {
            const index = state.heldCarts.findIndex((entry) => entry.id === holdId);

            if (index === -1) {
                return;
            }

            const held = state.heldCarts.splice(index, 1)[0];
            const snapshot = held.snapshot || {};

            state.branchId = snapshot.branchId ? Number(snapshot.branchId) : null;
            state.categoryId = snapshot.categoryId ? Number(snapshot.categoryId) : null;
            state.search = snapshot.search || '';
            state.customerId = customersById.has(Number(snapshot.customerId)) ? Number(snapshot.customerId) : null;
            state.customerName = snapshot.customerName || '';
            state.customerNote = snapshot.customerNote || '';
            state.priceListId = priceListsById.has(Number(snapshot.priceListId)) ? Number(snapshot.priceListId) : defaultPriceListId;
            state.orderType = orderTypeLabels[snapshot.orderType] ? snapshot.orderType : 'dine_in';
            state.paymentMethod = paymentLabels[snapshot.paymentMethod] ? snapshot.paymentMethod : 'cash';
            state.cashReceived = snapshot.cashReceived || '';
            state.sidebarView = 'order';
            state.cart = sanitizeCart(snapshot.cart || {});
            state.receiptNumber = String(snapshot.receiptNumber || '').trim() || nextReceiptNumber();

            persistHeldCarts();
            renderAll();
            schedulePricing();
            setStatus(`${held.label} loaded back to the counter.`, 'success');
        }

        function deleteHeldCart(holdId) {
            const index = state.heldCarts.findIndex((entry) => entry.id === holdId);

            if (index === -1) {
                return;
            }

            const [removed] = state.heldCarts.splice(index, 1);
            persistHeldCarts();
            renderHeldCarts();
            setStatus(`${removed.label} removed from held carts.`, 'warning');
        }

        function receiptLinesHtml() {
            const pricedItems = new Map((state.pricing.items || []).map((line) => [Number(line.item_id), line]));

            return cartLines().map((line) => {
                const product = itemsById.get(Number(line.item_id));
                const pricedLine = pricedItems.get(Number(line.item_id));
                const lineTotal = pricedLine ? Number(pricedLine.line_total) : Number(product?.display_price ?? 0) * Number(line.quantity || 0);

                return `
                    <tr>
                        <td style="padding: 8px 0; vertical-align: top;">
                            <div style="font-weight: 700;">${escapeHtml(product?.name || 'Unknown Item')}</div>
                            <div style="color: #7b8498; font-size: 12px;">${escapeHtml(product?.sku || '')}</div>
                        </td>
                        <td style="padding: 8px 0; text-align: center;">${Number(line.quantity || 0)}</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;">${escapeHtml(formatAmount(lineTotal, lineCurrency(product)))}</td>
                    </tr>
                `;
            }).join('');
        }

        function printReceipt() {
            if (!cartLines().length) {
                setStatus('Add items before printing a receipt.', 'warning');
                return;
            }

            const currency = currencyForAmount();
            const receiptWindow = window.open('', '_blank', 'width=420,height=720');

            if (!receiptWindow) {
                setStatus('Pop-up blocked. Allow pop-ups to print the receipt.', 'error');
                return;
            }

            const promotionCopy = state.pricing.applied_promotion
                ? `<div style="margin-top: 10px; font-size: 12px; color: #3d8a61;">Promotion: ${escapeHtml(state.pricing.applied_promotion.name)}</div>`
                : '';

            receiptWindow.document.write(`
                <html>
                    <head>
                        <title>${escapeHtml(state.receiptNumber)}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 0; padding: 24px; color: #1d2433; }
                            h1 { margin: 0 0 6px; font-size: 24px; }
                            p { margin: 0 0 4px; color: #5e687d; font-size: 13px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 18px; }
                            .divider { border-top: 1px dashed #c8cfdd; margin: 16px 0; }
                            .totals div { display: flex; justify-content: space-between; margin-bottom: 8px; }
                            .total { font-size: 24px; font-weight: 800; }
                        </style>
                    </head>
                    <body>
                        <h1>${escapeHtml(payload.tenant.store_name)}</h1>
                        <p>${escapeHtml(payload.tenant.address || payload.tenant.display_name)}</p>
                        <p>${escapeHtml(payload.tenant.contact_phone || '')} ${payload.tenant.contact_email ? `· ${escapeHtml(payload.tenant.contact_email)}` : ''}</p>
                        <div class="divider"></div>
                        <p><strong>Receipt:</strong> ${escapeHtml(state.receiptNumber)}</p>
                        <p><strong>Time:</strong> ${escapeHtml(nowLabel())}</p>
                        <p><strong>Order Type:</strong> ${escapeHtml(orderTypeLabels[state.orderType])}</p>
                        <p><strong>Branch:</strong> ${escapeHtml(activeBranchName())}</p>
                        <p><strong>Customer:</strong> ${escapeHtml(state.customerName.trim() || 'Walk-in Guest')}</p>
                        <p><strong>Price List:</strong> ${escapeHtml(selectedPriceList()?.name || 'Default Price')}</p>
                        ${state.customerNote.trim() ? `<p><strong>Note:</strong> ${escapeHtml(state.customerNote.trim())}</p>` : ''}
                        <table>
                            <thead>
                                <tr>
                                    <th align="left">Item</th>
                                    <th align="center">Qty</th>
                                    <th align="right">Total</th>
                                </tr>
                            </thead>
                            <tbody>${receiptLinesHtml()}</tbody>
                        </table>
                        <div class="divider"></div>
                        <div class="totals">
                            <div><span>Subtotal</span><span>${escapeHtml(formatAmount(state.pricing.subtotal, currency))}</span></div>
                            <div><span>Discount</span><span>${escapeHtml(formatAmount(state.pricing.discount_total, currency))}</span></div>
                            <div class="total"><span>Total</span><span>${escapeHtml(formatAmount(state.pricing.final_total, currency))}</span></div>
                            <div><span>Payment</span><span>${escapeHtml(paymentLabels[state.paymentMethod])}</span></div>
                            <div><span>Cash Received</span><span>${escapeHtml(formatAmount(cashReceivedAmount(), currency))}</span></div>
                            <div><span>Change</span><span>${escapeHtml(formatAmount(changeDue(), currency))}</span></div>
                        </div>
                        ${promotionCopy}
                        <div class="divider"></div>
                        <p>${escapeHtml(payload.tenant.receipt_footer || 'Thank you for your order.')}</p>
                    </body>
                </html>
            `);
            receiptWindow.document.close();
            receiptWindow.focus();
            receiptWindow.print();
        }

        function completeSale() {
            if (!cartLines().length) {
                setStatus('Add items before submitting the order.', 'warning');
                return;
            }

            const completedReceipt = state.receiptNumber;
            printReceipt();
            resetSaleDraft(true);
            setStatus(`Order ${completedReceipt} submitted on this device.`, 'success');
        }

        sidebarViewButtons.forEach((button) => {
            button.addEventListener('click', function () {
                state.sidebarView = this.getAttribute('data-sidebar-view') || 'order';
                renderSidebarView();
            });
        });

        categoryStrip.addEventListener('click', function (event) {
            const button = event.target.closest('[data-category-id]');

            if (!button) {
                return;
            }

            const value = button.getAttribute('data-category-id');
            state.categoryId = value ? Number(value) : null;
            renderAll();
        });

        productGrid.addEventListener('click', function (event) {
            const addButton = event.target.closest('[data-action="add-product"][data-item-id]');

            if (addButton && !addButton.hasAttribute('disabled')) {
                addToCart(Number(addButton.getAttribute('data-item-id')));
                return;
            }

            const card = event.target.closest('.pos-product-card[data-item-id]');

            if (!card || card.classList.contains('disabled') || card.getAttribute('aria-disabled') === 'true') {
                return;
            }

            addToCart(Number(card.getAttribute('data-item-id')));
        });

        orderList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-action][data-item-id]');

            if (!button) {
                return;
            }

            const action = button.getAttribute('data-action');
            const itemId = Number(button.getAttribute('data-item-id'));

            if (action === 'increment') {
                updateQuantity(itemId, 1);
                return;
            }

            if (action === 'decrement') {
                updateQuantity(itemId, -1);
                return;
            }

            if (action === 'remove') {
                removeLine(itemId);
            }
        });

        holdList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-action][data-hold-id]');

            if (!button) {
                return;
            }

            const action = button.getAttribute('data-action');
            const holdId = button.getAttribute('data-hold-id');

            if (action === 'resume-hold') {
                resumeHeldCart(holdId);
                return;
            }

            if (action === 'delete-hold') {
                deleteHeldCart(holdId);
            }
        });

        orderTypeGroup.addEventListener('click', function (event) {
            const button = event.target.closest('[data-order-type]');

            if (!button) {
                return;
            }

            state.orderType = button.getAttribute('data-order-type');
            renderAll();
        });

        paymentGrid.addEventListener('click', function (event) {
            const button = event.target.closest('[data-payment-method]');

            if (!button) {
                return;
            }

            state.paymentMethod = button.getAttribute('data-payment-method');

            if (state.paymentMethod !== 'cash') {
                state.cashReceived = '';
            }

            renderAll();
        });

        tenderChipGrid.addEventListener('click', function (event) {
            const button = event.target.closest('[data-tender-value]');

            if (!button) {
                return;
            }

            state.cashReceived = String(button.getAttribute('data-tender-value'));
            renderAll();
        });

        branchFilter.addEventListener('change', function () {
            state.branchId = this.value ? Number(this.value) : null;
            renderAll();
        });

        searchInput.addEventListener('input', function () {
            state.search = this.value || '';
            renderAll();
        });

        function syncCustomerValue(value) {
            state.customerName = value || '';
            renderAll();
        }

        customerNameInput.addEventListener('input', function () {
            state.customerId = null;

            syncCustomerValue(this.value);
        });

        customerSelect.addEventListener('change', function () {
            const nextCustomerId = this.value ? Number(this.value) : null;
            const customer = nextCustomerId ? customersById.get(nextCustomerId) : null;

            state.customerId = customer ? nextCustomerId : null;
            state.customerName = customer ? String(customer.name || '') : '';
            renderAll();
        });

        priceListSelect.addEventListener('change', function () {
            const nextPriceListId = this.value ? Number(this.value) : null;
            state.priceListId = priceListsById.has(Number(nextPriceListId)) ? nextPriceListId : null;
            renderAll();
        });

        orderTypeSelect.addEventListener('change', function () {
            state.orderType = orderTypeLabels[this.value] ? this.value : 'dine_in';
            renderAll();
        });

        orderNoteInput.addEventListener('input', function () {
            state.customerNote = this.value || '';
            renderAll();
        });

        cashReceivedInput.addEventListener('input', function () {
            state.cashReceived = this.value || '';
            renderAll();
        });

        function clearCartAction() {
            state.cart = {};
            state.pricing = localPricingSnapshot();
            state.cashReceived = '';
            renderAll();
            setStatus('Current order cleared.', 'warning');
        }

        clearCartButton.addEventListener('click', clearCartAction);

        if (clearCartSecondaryButton) {
            clearCartSecondaryButton.addEventListener('click', clearCartAction);
        }

        resetFiltersButton.addEventListener('click', function () {
            state.branchId = null;
            state.categoryId = null;
            state.search = '';
            renderAll();
            setStatus('Catalog filters reset.', 'success');
        });

        holdCartButton.addEventListener('click', holdCurrentCart);

        if (holdCartSecondaryButton) {
            holdCartSecondaryButton.addEventListener('click', holdCurrentCart);
        }

        function startNewSale() {
            resetSaleDraft(true);
            setStatus('Started a fresh sale ticket.', 'success');
        }

        newSaleButton.addEventListener('click', startNewSale);

        if (newSaleSecondaryButton) {
            newSaleSecondaryButton.addEventListener('click', startNewSale);
        }

        if (settingsActionButton) {
            settingsActionButton.addEventListener('click', openSettingsModal);
        }

        if (settingsCloseButton) {
            settingsCloseButton.addEventListener('click', closeSettingsModal);
        }

        if (settingsCancelButton) {
            settingsCancelButton.addEventListener('click', closeSettingsModal);
        }

        if (settingsSaveButton) {
            settingsSaveButton.addEventListener('click', function () {
                renderAll();
                closeSettingsModal();
                setStatus('Ticket settings saved for this order.', 'success');
            });
        }

        if (settingsModal) {
            settingsModal.addEventListener('click', function (event) {
                if (event.target.hasAttribute('data-settings-close')) {
                    closeSettingsModal();
                }
            });
        }

        printReceiptButton.addEventListener('click', printReceipt);
        completeSaleButton.addEventListener('click', completeSale);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && settingsModal && !settingsModal.hidden) {
                closeSettingsModal();
            }
        });

        loadHeldCarts();
        loadDraft();

        if (!state.receiptNumber) {
            state.receiptNumber = nextReceiptNumber();
        }

        state.pricing = localPricingSnapshot();
        state.status.message = 'Counter ready. Add items to begin a new order.';
        renderAll();

        if (cartLines().length) {
            schedulePricing();
        }
    });
</script>
@endpush
