@extends('layouts.portal')

@section('title', 'Overview')

@section('content')
<div class="dashboard-shell">
    <aside class="sidebar">
        <a class="brand brand-dark" href="{{ route('dashboard') }}">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>meterwise</span>
        </a>
        <div class="workspace-switcher">
            <span class="workspace-avatar">{{ strtoupper(substr($merchant->name, 0, 1)) }}</span>
            <span class="workspace-name"><strong>{{ $merchant->name }}</strong><small>Merchant workspace</small></span>
            <span class="switcher-chevron" aria-hidden="true">⌄</span>
        </div>
        <p class="nav-caption">WORKSPACE</p>
        <nav class="side-nav" aria-label="Main navigation">
            <a class="side-link active" href="#overview" aria-current="page">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><rect x="13.5" y="13.5" width="7" height="7" rx="1"/></svg>
                Overview
            </a>
            <a class="side-link" href="#usage">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7"/><path d="M16 6h3v3"/></svg>
                Usage
            </a>
            <a class="side-link" href="#customers">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0M16 5.2a3.5 3.5 0 0 1 0 6.6M17 14a5.4 5.4 0 0 1 4.2 5.3"/></svg>
                Customers
            </a>
            <a class="side-link" href="#invoices">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5h9l4 4V21l-2-1.3L15 21l-2-1.3L11 21l-2-1.3L7 21l-1-1V3.5Z"/><path d="M14.5 3.5v5h5M9 12h6M9 15.5h6"/></svg>
                Invoices
            </a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-status"><span class="status-dot"></span><span><strong>All systems normal</strong><small>Usage pipeline active</small></span></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-button" type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10M14 16l4-4-4-4M18 12H9"/></svg>
                    Sign out
                </button>
            </form>
            <div class="sidebar-version">METERWISE <span>·</span> MERCHANT PORTAL</div>
        </div>
    </aside>

    <main class="dashboard-main" id="overview">
        <header class="dashboard-topbar">
            <div class="breadcrumb"><span>Workspace</span><b>/</b><strong>Overview</strong></div>
            <div class="topbar-actions">
                <span class="period-pill"><span class="period-dot"></span>{{ $monthLabel }} <span aria-hidden="true">⌄</span></span>
                <span class="topbar-avatar" title="{{ $merchant->name }}">{{ strtoupper(substr($merchant->name, 0, 1)) }}</span>
                <form method="POST" action="{{ route('logout') }}" class="mobile-logout-form">
                    @csrf
                    <button class="mobile-logout-button" type="submit" aria-label="Sign out" title="Sign out">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10M14 16l4-4-4-4M18 12H9"/></svg>
                    </button>
                </form>
            </div>
        </header>

        <div class="dashboard-content">
            <section class="page-heading">
                <div>
                    <p class="eyebrow">YOUR BILLING AT A GLANCE</p>
                    <h1>Good work, {{ explode(' ', trim($merchant->name))[0] }}.</h1>
                    <p class="heading-copy">Here’s how your customer usage is shaping up this month.</p>
                </div>
                <span class="live-badge"><span></span> LIVE OVERVIEW</span>
            </section>

            @if (session('new_api_key'))
                <section class="api-key-notice" aria-label="New API key">
                    <span class="notice-icon" aria-hidden="true">✓</span>
                    <div class="notice-copy"><strong>Your workspace is ready</strong><span>Copy this API key now. It will only be shown once.</span></div>
                    <code id="new-api-key">{{ session('new_api_key') }}</code>
                    <button class="copy-button" type="button" data-copy="new-api-key" aria-label="Copy API key" title="Copy API key">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                    </button>
                    <button class="notice-close" type="button" data-dismiss="api-key-notice" aria-label="Dismiss API key notice">×</button>
                </section>
            @endif

            <section class="metric-grid" aria-label="Monthly summary">
                <article class="metric-card metric-featured">
                    <div class="metric-topline"><span>USAGE THIS MONTH</span><span class="metric-icon metric-icon-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7"/></svg></span></div>
                    <div class="metric-value">{{ number_format($usageThisMonth) }} <small>units</small></div>
                    <div class="metric-foot"><span class="metric-dot"></span>Through {{ now()->format('M j') }} <span class="metric-foot-right">{{ $metrics['usage_aggregated_through'] ? 'Rollup current' : 'Waiting for first rollup' }}</span></div>
                    <div class="metric-sparkline" aria-hidden="true"><i style="--h:35%"></i><i style="--h:48%"></i><i style="--h:42%"></i><i style="--h:63%"></i><i style="--h:54%"></i><i style="--h:73%"></i><i style="--h:67%"></i><i style="--h:89%"></i><i style="--h:78%"></i><i style="--h:100%"></i></div>
                </article>
                <article class="metric-card">
                    <div class="metric-topline"><span>ACTIVE CUSTOMERS</span><span class="metric-icon metric-icon-coral"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0M16 5.2a3.5 3.5 0 0 1 0 6.6M17 14a5.4 5.4 0 0 1 4.2 5.3"/></svg></span></div>
                    <div class="metric-value">{{ number_format($activeSubscriptions) }} <small>subscribed</small></div>
                    <div class="metric-foot">{{ number_format($customerCount) }} total customers</div>
                    <div class="metric-mini-line"><span data-width="{{ $customerCount > 0 ? min(100, round($activeSubscriptions / $customerCount * 100)) : 0 }}"></span></div>
                </article>
                <article class="metric-card">
                    <div class="metric-topline"><span>PROJECTED OVERAGE</span><span class="metric-icon metric-icon-yellow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M17 7.5c0-1.4-1.8-2.5-4-2.5S9 6.1 9 7.5 10.8 10 13 10s4 1.1 4 2.5-1.8 2.5-4 2.5-4-1.1-4-2.5"/></svg></span></div>
                    @forelse ($metrics['projected_overage_revenue'] as $projection)
                        <div class="metric-value metric-currency">{{ $projection['currency'] }} {{ number_format($projection['amount_cents'] / 100, 2) }}</div>
                    @empty
                        <div class="metric-value">—</div>
                        <div class="metric-foot">No projected overage this cycle</div>
                    @endforelse
                    @if (count($metrics['projected_overage_revenue']) > 0)
                        <div class="metric-foot">Projected from current cycle pace</div>
                    @endif
                    <div class="metric-mini-line metric-mini-yellow"><span data-width="{{ count($metrics['projected_overage_revenue']) ? 68 : 0 }}"></span></div>
                </article>
            </section>

            <section class="content-grid" id="usage">
                <article class="panel usage-panel" id="customers">
                    <div class="panel-heading">
                        <div><p class="eyebrow">CUSTOMER ACTIVITY</p><h2>Top usage</h2></div>
                        <span class="panel-period">MONTH TO DATE <span aria-hidden="true">↗</span></span>
                    </div>
                    @php
                        $topCustomers = $metrics['top_customers_this_month'];
                        $maxUsage = max(1, (int) $topCustomers->max('usage_units'));
                    @endphp
                    @forelse ($topCustomers as $index => $customer)
                        <div class="usage-row">
                            <span class="rank-number">{{ sprintf('%02d', $index + 1) }}</span>
                            <span class="customer-initial">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                            <span class="customer-usage"><strong>{{ $customer->name }}</strong><span class="usage-track"><i data-width="{{ round($customer->usage_units / $maxUsage * 100) }}"></i></span></span>
                            <span class="usage-total">{{ number_format($customer->usage_units) }} <small>units</small></span>
                        </div>
                    @empty
                        <div class="empty-state"><span class="empty-mark" aria-hidden="true">↗</span><strong>No rolled-up usage yet</strong><span>Customer usage will show here after the next aggregation batch.</span></div>
                    @endforelse
                    <div class="panel-footnote"><span class="status-dot"></span>Usage updates after each rollup batch</div>
                </article>

                <article class="panel churn-panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">RETENTION SIGNAL</p><h2>Worth a check-in</h2></div>
                        <span class="risk-count">{{ count($metrics['churn_risk_customers']) }}</span>
                    </div>
                    @forelse ($metrics['churn_risk_customers']->take(4) as $customer)
                        <div class="risk-row">
                            <span class="risk-avatar">{{ strtoupper(substr($customer['name'], 0, 1)) }}</span>
                            <span class="risk-name"><strong>{{ $customer['name'] }}</strong><small>{{ number_format($customer['current_period_units']) }} vs {{ number_format($customer['previous_period_units']) }} units</small></span>
                            <span class="risk-drop">−{{ number_format($customer['drop_percent'], 0) }}%</span>
                        </div>
                    @empty
                        <div class="risk-empty"><span class="risk-check" aria-hidden="true">✓</span><span><strong>No churn signals</strong><small>No customers are down more than 50% in the comparable period.</small></span></div>
                    @endforelse
                    @if (count($metrics['churn_risk_customers']) > 4)
                        <p class="more-risks">+ {{ count($metrics['churn_risk_customers']) - 4 }} more customers to review</p>
                    @endif
                </article>
            </section>

            <section class="panel invoices-panel" id="invoices">
                <div class="panel-heading invoice-heading">
                    <div><p class="eyebrow">LATEST BILLING ACTIVITY</p><h2>Recent invoices</h2></div>
                    <span class="invoice-period">{{ $monthLabel }}</span>
                </div>
                @if ($recentInvoices->isNotEmpty())
                    <div class="invoice-table-wrap">
                        <table class="invoice-table">
                            <thead><tr><th>Customer</th><th>Billing period</th><th>Usage</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($recentInvoices as $invoice)
                                    <tr>
                                        <td><span class="table-customer"><span class="table-avatar">{{ strtoupper(substr($invoice->customer?->name ?? 'C', 0, 1)) }}</span>{{ $invoice->customer?->name ?? 'Customer' }}</span></td>
                                        <td>{{ $invoice->period_start->format('M j') }} – {{ $invoice->period_end->format('M j, Y') }}</td>
                                        <td>{{ number_format($invoice->usage_units) }} units</td>
                                        <td class="table-amount">{{ $invoice->currency }} {{ number_format($invoice->total_amount_cents / 100, 2) }}</td>
                                        <td><span class="invoice-status"><i></i>{{ ucfirst($invoice->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="invoice-empty"><span class="invoice-empty-icon" aria-hidden="true">▤</span><span><strong>No invoices yet</strong><small>Your first invoice will appear here at the end of a customer’s billing cycle.</small></span></div>
                @endif
            </section>

            <footer class="dashboard-footer"><span>USAGE IS AGGREGATED IN THE BACKGROUND</span><span>LAST ROLLUP {{ $metrics['usage_aggregated_through'] ? \Carbon\Carbon::parse($metrics['usage_aggregated_through'])->diffForHumans() : 'NOT STARTED' }}</span></footer>
        </div>
    </main>
</div>
@endsection