<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PolicyFlow — Fault-Tolerant Insurance Engine</title>
    <style>
        :root {
            --bg-page: #f7f6f2;
            --bg-card: #ffffff;
            --bg-subtle: #f0eee6;
            --border-color: #e2ded4;
            --border-dark: #cfc9be;
            --text-main: #24292e;
            --text-muted: #586069;
            --text-light: #8c959f;
            --primary: #2f3e46;
            --primary-hover: #1e282d;
            --accent: #3b5249;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
            --success-border: #a7f3d0;
            --warning-bg: #fffbeb;
            --warning-text: #92400e;
            --warning-border: #fde68a;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
            --danger-border: #fecaca;
            --info-bg: #eff6ff;
            --info-text: #1e40af;
            --info-border: #bfdbfe;
            --purple-bg: #faf5ff;
            --purple-text: #6b21a8;
            --purple-border: #e9d5ff;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, monospace;
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -1px rgba(0,0,0,0.03);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* Top Navigation Bar */
        header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }
        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-badge {
            background: #e9ecef;
            color: #495057;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .brand h1 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }
        .brand-sub {
            font-size: 12px;
            color: var(--text-muted);
        }
        .candidate-tag {
            font-family: var(--font-mono);
            font-size: 12px;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
        }

        /* Layout Container */
        .container {
            max-width: 1400px;
            margin: 24px auto 0;
            padding: 0 32px;
        }

        /* Metrics summary bar */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            box-shadow: var(--shadow-sm);
        }
        .metric-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .metric-value {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-main);
            font-family: var(--font-mono);
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .tab-btn {
            background: transparent;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.15s ease;
        }
        .tab-btn.active {
            color: var(--text-main);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        /* Grid layout for Intake & Pipeline */
        .main-grid {
            display: grid;
            grid-template-columns: 440px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1080px) {
            .main-grid { grid-template-columns: 1fr; }
        }

        /* Card styles */
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .panel-header {
            padding: 16px 20px;
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
        }
        .panel-body {
            padding: 20px;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 14px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .form-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.15s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 1px var(--primary);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Preset buttons */
        .presets-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
            padding: 10px;
            background: var(--bg-subtle);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }
        .preset-title {
            width: 100%;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .btn-preset {
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            font-size: 11px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            color: var(--text-main);
            transition: all 0.15s ease;
        }
        .btn-preset:hover {
            border-color: var(--primary);
            background: #fdfdfd;
        }

        /* Live Calculation Box */
        .calc-preview-box {
            background: #faf9f6;
            border: 1px dashed var(--border-dark);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin: 16px 0;
            font-size: 12px;
        }
        .calc-title {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }
        .calc-formula {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .calc-highlight {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            font-family: var(--font-mono);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-secondary {
            background: #ffffff;
            color: var(--text-main);
            border-color: var(--border-dark);
        }
        .btn-secondary:hover {
            background: #f8f9fa;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        .btn-block {
            width: 100%;
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: var(--radius-sm);
            letter-spacing: 0.3px;
            font-family: var(--font-mono);
            white-space: nowrap;
        }
        .status-VALIDATED { background: var(--info-bg); color: var(--info-text); border: 1px solid var(--info-border); }
        .status-SUBMITTED { background: var(--warning-bg); color: var(--warning-text); border: 1px solid var(--warning-border); }
        .status-PENDING_CONFIRMATION { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .status-ISSUED { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
        .status-FAILED { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .status-NEEDS_RECONCILIATION { background: var(--purple-bg); color: var(--purple-text); border: 1px solid var(--purple-border); }

        /* Table */
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 12px;
        }
        th {
            background: var(--bg-subtle);
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        tr:hover td {
            background-color: #faf9f6;
        }
        .mono { font-family: var(--font-mono); }

        /* Notification Toast */
        .alert {
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            margin-bottom: 14px;
            display: none;
        }
        .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
        .alert-danger { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .alert-info { background: var(--info-bg); color: var(--info-text); border: 1px solid var(--info-border); }

        /* Modal / Inspector Drawer */
        .drawer-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(2px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: flex-end;
        }
        .drawer-overlay.active { display: flex; }
        .drawer-panel {
            background: #ffffff;
            width: 600px;
            max-width: 95vw;
            height: 100vh;
            box-shadow: -4px 0 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: slideIn 0.2s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .drawer-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-subtle);
        }
        .drawer-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .drawer-close {
            background: none;
            border: none;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            color: var(--text-muted);
        }

        /* Timeline in drawer */
        .timeline {
            position: relative;
            padding-left: 20px;
            margin-top: 14px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 6px; bottom: 6px; left: 6px;
            width: 2px;
            background: var(--border-color);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 16px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -18px; top: 5px;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid #fff;
        }
        .timeline-time {
            font-size: 11px;
            color: var(--text-light);
            font-family: var(--font-mono);
        }
        .timeline-content {
            font-size: 12px;
            margin-top: 2px;
        }

        /* Action Buttons Grid in Drawer */
        .actions-box {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 20px;
        }
        .actions-box h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        /* Code Block */
        pre.code-block {
            background: #2b2b2b;
            color: #f8f8f2;
            padding: 14px;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: 11px;
            overflow-x: auto;
            margin: 10px 0;
            line-height: 1.5;
        }
        .copy-btn {
            float: right;
            background: #444;
            color: #fff;
            border: none;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="header-inner">
            <div class="brand">
                <span class="brand-badge">Placement Assessment</span>
                <div>
                    <h1>PolicyFlow</h1>
                    <div class="brand-sub">Fault-Tolerant Insurance Issuance & Reconciliation Engine</div>
                </div>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <span class="candidate-tag">Candidate: CS2026-0842</span>
                <span class="candidate-tag">Stack: Laravel 12 / PHP 8.2</span>
                <button class="btn btn-secondary btn-sm" onclick="runCliReconcile()">Run Reconcile (CLI)</button>
            </div>
        </div>
    </header>

    <div class="container">

        <!-- Top Metrics Summary -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Total Policy Requests</div>
                <div class="metric-value">{{ $stats['total'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Confirmed Issued</div>
                <div class="metric-value" style="color: var(--success-text);">{{ $stats['issued'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Needs Reconciliation</div>
                <div class="metric-value" style="color: var(--purple-text);">{{ $stats['needs_reconciliation'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Pending Confirmation</div>
                <div class="metric-value" style="color: #c2410c;">{{ $stats['pending_confirmation'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Failed (Retriable)</div>
                <div class="metric-value" style="color: var(--danger-text);">{{ $stats['failed'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Total Premium (₹)</div>
                <div class="metric-value">₹{{ number_format($stats['total_premium'], 2) }}</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('tab-pipeline')">1. Pipeline & Intake</button>
            <button class="tab-btn" onclick="switchTab('tab-reliability')">2. Reliability Matrix & Scenarios</button>
            <button class="tab-btn" onclick="switchTab('tab-api')">3. API Contract & cURL Examples</button>
        </div>

        <!-- TAB 1: Intake & Pipeline Grid -->
        <div id="tab-pipeline" class="tab-content">
            <div class="main-grid">

                <!-- Left: Intake Studio -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Policy Request Intake</span>
                        <span style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">POST /api/policy-requests</span>
                    </div>
                    <div class="panel-body">

                        <!-- Presets -->
                        <div class="presets-bar">
                            <div class="preset-title">Quick Test Presets (Click to load):</div>
                            <button type="button" class="btn-preset" onclick="loadPreset('spec1')">Spec: Agent-17 (Int'l Plus)</button>
                            <button type="button" class="btn-preset" onclick="loadPreset('senior')">Domestic Senior (Basic)</button>
                            <button type="button" class="btn-preset" onclick="loadPreset('youth')">Youth International (Premium)</button>
                        </div>

                        <!-- Status Alert -->
                        <div id="intake-alert" class="alert"></div>

                        <!-- Intake Form -->
                        <form id="intakeForm" onsubmit="handleIntakeSubmit(event)">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Client Request ID</label>
                                    <input type="text" id="client_request_id" class="form-control mono" value="AGT-2026-0001" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Agent ID</label>
                                    <input type="text" id="agent_id" class="form-control mono" value="AGENT-17" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Customer Full Name</label>
                                <input type="text" id="customer_name" class="form-control" value="Sample Customer" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" id="date_of_birth" class="form-control" value="1998-04-10" oninput="updateLivePricing()" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Destination</label>
                                    <select id="destination" class="form-control" onchange="updateLivePricing()">
                                        <option value="INTERNATIONAL" selected>INTERNATIONAL (×1.80)</option>
                                        <option value="DOMESTIC">DOMESTIC (×1.00)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Trip Start</label>
                                    <input type="date" id="trip_start" class="form-control" value="2026-11-10" oninput="updateLivePricing()" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Trip End</label>
                                    <input type="date" id="trip_end" class="form-control" value="2026-11-14" oninput="updateLivePricing()" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Plan Tier</label>
                                    <select id="plan_code" class="form-control" onchange="updateLivePricing()">
                                        <option value="BASIC">BASIC (₹12/day)</option>
                                        <option value="PLUS" selected>PLUS (₹20/day)</option>
                                        <option value="PREMIUM">PREMIUM (₹30/day)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sum Insured</label>
                                    <select id="sum_insured" class="form-control" onchange="updateLivePricing()">
                                        <option value="100000">₹100,000 (×1.00)</option>
                                        <option value="300000" selected>₹300,000 (×1.25)</option>
                                        <option value="500000">₹500,000 (×1.50)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Reactive Live Premium Preview Box -->
                            <div class="calc-preview-box">
                                <div class="calc-title">
                                    <span>Live Deterministic Quote:</span>
                                    <span class="calc-highlight" id="liveQuoteTotal">₹225.00</span>
                                </div>
                                <div class="calc-formula" id="liveQuoteFormula">
                                    Base (₹20) × 5 Days × Age(1.00) × Dest(1.80) × Cov(1.25)
                                </div>
                            </div>

                            <button type="submit" id="btnSubmitIntake" class="btn btn-primary btn-block">
                                Submit Policy Intake Request
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right: Policy Requests Pipeline Table -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Policy Pipeline & Ledger</span>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter customer, ID, policy..." oninput="filterTable()" style="width: 220px; font-size: 11px;">
                            <button class="btn btn-secondary btn-sm" onclick="location.reload()">Refresh</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="policiesTable">
                            <thead>
                                <tr>
                                    <th>Client ID / Agent</th>
                                    <th>Customer & Age</th>
                                    <th>Trip Dates & Dest</th>
                                    <th>Plan & Coverage</th>
                                    <th>Premium</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($policies as $item)
                                <tr id="row-{{ $item->id }}" data-search="{{ strtolower($item->customer_name . ' ' . $item->client_request_id . ' ' . ($item->policy_number ?? '')) }}">
                                    <td>
                                        <div class="mono" style="font-weight: 600;">{{ $item->client_request_id }}</div>
                                        <div class="form-hint mono">{{ $item->agent_id }}</div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;">{{ $item->customer_name }}</div>
                                        <div class="form-hint">{{ $item->date_of_birth->format('Y-m-d') }} ({{ $item->premium_breakdown['age_on_trip_start'] ?? 'N/A' }} yrs)</div>
                                    </td>
                                    <td>
                                        <div>{{ $item->trip_start->format('M d') }} - {{ $item->trip_end->format('M d, Y') }}</div>
                                        <div class="form-hint">{{ $item->travel_days }} days &bull; {{ $item->destination }}</div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">{{ $item->plan_code }}</div>
                                        <div class="form-hint">₹{{ number_format($item->sum_insured) }}</div>
                                    </td>
                                    <td>
                                        <span class="mono" style="font-weight: 600;">₹{{ number_format($item->premium_amount, 2) }}</span>
                                    </td>
                                    <td>
                                        <span class="status-pill status-{{ $item->status->value }}">{{ $item->status->value }}</span>
                                        @if($item->policy_number)
                                            <div class="mono form-hint" style="font-size: 10px;">{{ $item->policy_number }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="openPolicyDrawer('{{ $item->id }}')">
                                            Inspect & Test
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        No policy requests in database yet. Use the intake form to create one!
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB 2: Reliability Matrix & Interactive Scenarios -->
        <div id="tab-reliability" class="tab-content" style="display: none;">
            <div class="panel" style="margin-bottom: 24px;">
                <div class="panel-header">
                    <span class="panel-title">Fault-Tolerance & Reliability Verification Matrix</span>
                    <span class="brand-badge">Section 3.4 & 5 Compliance</span>
                </div>
                <div class="panel-body">
                    <p style="color: var(--text-muted); margin-bottom: 16px;">
                        The system is engineered specifically to prevent duplicate issuance, race conditions, and inconsistent terminal states across unreliable networks. Below is the operational behavior for each scenario:
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                        <div class="metric-card" style="border-left: 4px solid var(--primary);">
                            <div class="metric-label">Scenario 1: Duplicate Intake</div>
                            <h4 style="margin: 4px 0 8px;">Idempotent Request Handling</h4>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                                Repeated submissions with the same <code>agent_id + client_request_id</code> are intercepted. DB compound unique index <code>UNIQUE(agent_id, client_request_id)</code> enforces zero duplicate rows even under concurrent execution.
                            </p>
                            <span class="status-pill status-VALIDATED">HTTP 200 OK (Existing Record)</span>
                        </div>

                        <div class="metric-card" style="border-left: 4px solid var(--purple-text);">
                            <div class="metric-label">Scenario 2: Timeout After Issue</div>
                            <h4 style="margin: 4px 0 8px;">Safety via Provider Reconciliation</h4>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                                If external insurer times out after writing a policy internally, the client flags policy as <code>NEEDS_RECONCILIATION</code> instead of blindly issuing again. Reconciliation verifies provider status and promotes to <code>ISSUED</code> safely.
                            </p>
                            <span class="status-pill status-NEEDS_RECONCILIATION">Reconciliation Guard</span>
                        </div>

                        <div class="metric-card" style="border-left: 4px solid var(--success-text);">
                            <div class="metric-label">Scenario 3: Duplicate Webhook Callback</div>
                            <h4 style="margin: 4px 0 8px;">Event Deduplication</h4>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                                Every webhook requires a unique <code>event_id</code> recorded in <code>provider_events</code>. Repeated delivery of the same event returns <code>duplicate_ignored</code> and never mutates or overwrites state.
                            </p>
                            <span class="status-pill status-ISSUED">Idempotent Webhook</span>
                        </div>

                        <div class="metric-card" style="border-left: 4px solid var(--danger-text);">
                            <div class="metric-label">Scenario 4: Retry After Temporary Failure</div>
                            <h4 style="margin: 4px 0 8px;">Safe Retry with Immutable Snapshot</h4>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                                If mock provider returns 503 temporary error, policy transitions to <code>FAILED</code>. Re-submitting retries safely without recalculating or corrupting the original accepted premium snapshot.
                            </p>
                            <span class="status-pill status-FAILED">Safe Retry</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: API Contract & cURL Examples -->
        <div id="tab-api" class="tab-content" style="display: none;">
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">REST API Specifications & cURL Samples</span>
                    <span style="font-size: 11px; color: var(--text-muted);">Exact Match for Section 4 API Contract</span>
                </div>
                <div class="panel-body">

                    <h4 style="font-size: 13px; margin-top: 10px;">1. Intake Endpoint (Create or Return Existing)</h4>
                    <p style="font-size: 12px; color: var(--text-muted);">POST /api/policy-requests &bull; HTTP 201 (New), HTTP 200 (Duplicate), HTTP 422 (Validation)</p>
                    <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'</pre>

                    <h4 style="font-size: 13px; margin-top: 18px;">2. Submit Endpoint (Start or Resume Issuance)</h4>
                    <p style="font-size: 12px; color: var(--text-muted);">POST /api/policy-requests/{id}/submit &bull; Optional scenario: SUCCESS, TIMEOUT_AFTER_ISSUE, TEMPORARY_FAILURE, DELAYED_CALLBACK</p>
                    <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>curl -X POST http://localhost:8000/api/policy-requests/{POLICY_UUID}/submit \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{ "scenario": "SUCCESS" }'</pre>

                    <h4 style="font-size: 13px; margin-top: 18px;">3. Webhook Callback Endpoint</h4>
                    <p style="font-size: 12px; color: var(--text-muted);">POST /api/mock-insurer/callback &bull; Enforces deduplication on event_id</p>
                    <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>curl -X POST http://localhost:8000/api/mock-insurer/callback \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-99201",
    "provider_reference": "PRV-ASYNC-XXXXXX",
    "event_type": "POLICY_ISSUED",
    "policy_number": "POL-2026-554433"
  }'</pre>

                    <h4 style="font-size: 13px; margin-top: 18px;">4. Reconciliation Endpoint & Command</h4>
                    <p style="font-size: 12px; color: var(--text-muted);">POST /api/policy-requests/{id}/reconcile OR via CLI</p>
                    <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button># HTTP Endpoint
curl -X POST http://localhost:8000/api/policy-requests/{POLICY_UUID}/reconcile -H "Accept: application/json"

# CLI Artisan Command
php artisan policy:reconcile</pre>
                </div>
            </div>
        </div>

    </div>

    <!-- Policy Inspector / Reliability Sandbox Drawer -->
    <div id="policyDrawer" class="drawer-overlay" onclick="handleDrawerOverlayClick(event)">
        <div class="drawer-panel" onclick="event.stopPropagation()">
            <div class="drawer-header">
                <div>
                    <h3 id="drawerPolicyId" style="font-size: 15px; font-weight: 600; font-family: var(--font-mono);">Policy Details</h3>
                    <div id="drawerSubTitle" style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Audit History & Sandbox Actions</div>
                </div>
                <button class="drawer-close" onclick="closePolicyDrawer()">&times;</button>
            </div>
            <div class="drawer-body">

                <!-- Quick Metadata Cards -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px;">
                    <div style="background: var(--bg-subtle); padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <div class="metric-label">Status</div>
                        <div id="drawerStatusPill"></div>
                    </div>
                    <div style="background: var(--bg-subtle); padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <div class="metric-label">Policy Number</div>
                        <div id="drawerPolicyNumber" class="mono" style="font-weight: 600; font-size: 13px;">Not yet issued</div>
                    </div>
                </div>

                <!-- Simulation Action Box -->
                <div class="actions-box">
                    <h4>Simulate External Provider Interaction:</h4>
                    <div class="actions-grid">
                        <button class="btn btn-primary btn-sm" onclick="runScenario('SUCCESS')">
                            Submit: SUCCESS
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="runScenario('TIMEOUT_AFTER_ISSUE')">
                            Submit: TIMEOUT
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="runScenario('TEMPORARY_FAILURE')">
                            Submit: TEMP FAILURE
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="runScenario('DELAYED_CALLBACK')">
                            Submit: ASYNC CALLBACK
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="triggerDrawerCallback(false)">
                            Send Webhook Callback
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="triggerDrawerCallback(true)">
                            Send Duplicate Webhook
                        </button>
                        <button class="btn btn-primary btn-sm" style="grid-column: span 2;" onclick="reconcileDrawerPolicy()">
                            Reconcile Against Provider Status
                        </button>
                    </div>
                    <div id="drawerActionAlert" class="alert" style="margin-top: 10px;"></div>
                </div>

                <!-- Rate Breakdown Snapshot -->
                <h4 style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Rate Breakdown Snapshot</h4>
                <div id="drawerBreakdown" style="font-size: 12px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 18px;">
                    <!-- Injected via JS -->
                </div>

                <!-- Audit Trail Timeline -->
                <h4 style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">State Machine Audit Trail</h4>
                <div id="drawerTimeline" class="timeline">
                    <!-- Injected via JS -->
                </div>

            </div>
        </div>
    </div>

    <!-- Pure JavaScript Interactions -->
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let currentPolicy = null;
        let lastCallbackEventId = null;

        // Pricing Rules
        const BASE_RATES = { BASIC: 12.00, PLUS: 20.00, PREMIUM: 30.00 };
        const DEST_MULTIPLIERS = { DOMESTIC: 1.00, INTERNATIONAL: 1.80 };
        const COV_MULTIPLIERS = { 100000: 1.00, 300000: 1.25, 500000: 1.50 };

        function getAgeMultiplier(age) {
            if (age <= 17) return 1.10;
            if (age <= 60) return 1.00;
            if (age <= 70) return 1.50;
            return 2.00;
        }

        // Live Dynamic Pricing Preview
        function updateLivePricing() {
            const plan = document.getElementById('plan_code').value;
            const dest = document.getElementById('destination').value;
            const cov = parseInt(document.getElementById('sum_insured').value);
            const dobVal = document.getElementById('date_of_birth').value;
            const startVal = document.getElementById('trip_start').value;
            const endVal = document.getElementById('trip_end').value;

            if (!dobVal || !startVal || !endVal) return;

            const startDate = new Date(startVal);
            const endDate = new Date(endVal);
            const dobDate = new Date(dobVal);

            if (endDate < startDate) {
                document.getElementById('liveQuoteTotal').textContent = 'Invalid Dates';
                document.getElementById('liveQuoteFormula').textContent = 'Trip End cannot be earlier than Trip Start.';
                return;
            }

            // Inclusive days
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

            // Age on trip start
            let age = startDate.getFullYear() - dobDate.getFullYear();
            const m = startDate.getMonth() - dobDate.getMonth();
            if (m < 0 || (m === 0 && startDate.getDate() < dobDate.getDate())) {
                age--;
            }
            if (age < 0) age = 0;

            const baseRate = BASE_RATES[plan] || 20;
            const destMult = DEST_MULTIPLIERS[dest] || 1.0;
            const covMult = COV_MULTIPLIERS[cov] || 1.0;
            const ageMult = getAgeMultiplier(age);

            const total = (baseRate * diffDays * ageMult * destMult * covMult).toFixed(2);

            document.getElementById('liveQuoteTotal').textContent = `₹${total}`;
            document.getElementById('liveQuoteFormula').textContent = 
                `Base (₹${baseRate}) × ${diffDays} Days × Age(${ageMult.toFixed(2)}) × Dest(${destMult.toFixed(2)}) × Cov(${covMult.toFixed(2)})`;
        }

        // Load Preset Form Data
        function loadPreset(type) {
            if (type === 'spec1') {
                document.getElementById('client_request_id').value = 'AGT-2026-0001';
                document.getElementById('agent_id').value = 'AGENT-17';
                document.getElementById('customer_name').value = 'Aditi Sharma';
                document.getElementById('date_of_birth').value = '1998-04-10';
                document.getElementById('destination').value = 'INTERNATIONAL';
                document.getElementById('trip_start').value = '2026-11-10';
                document.getElementById('trip_end').value = '2026-11-14';
                document.getElementById('plan_code').value = 'PLUS';
                document.getElementById('sum_insured').value = '300000';
            } else if (type === 'senior') {
                document.getElementById('client_request_id').value = 'AGT-2026-' + Math.floor(1000 + Math.random() * 9000);
                document.getElementById('agent_id').value = 'AGENT-09';
                document.getElementById('customer_name').value = 'Rajesh Verma';
                document.getElementById('date_of_birth').value = '1959-02-15';
                document.getElementById('destination').value = 'DOMESTIC';
                document.getElementById('trip_start').value = '2026-12-01';
                document.getElementById('trip_end').value = '2026-12-10';
                document.getElementById('plan_code').value = 'BASIC';
                document.getElementById('sum_insured').value = '100000';
            } else if (type === 'youth') {
                document.getElementById('client_request_id').value = 'AGT-2026-' + Math.floor(1000 + Math.random() * 9000);
                document.getElementById('agent_id').value = 'AGENT-42';
                document.getElementById('customer_name').value = 'Kabir Patel';
                document.getElementById('date_of_birth').value = '2010-06-20';
                document.getElementById('destination').value = 'INTERNATIONAL';
                document.getElementById('trip_start').value = '2026-11-15';
                document.getElementById('trip_end').value = '2026-11-25';
                document.getElementById('plan_code').value = 'PREMIUM';
                document.getElementById('sum_insured').value = '500000';
            }
            updateLivePricing();
        }

        // Intake Form Submit
        async function handleIntakeSubmit(e) {
            e.preventDefault();
            const alertBox = document.getElementById('intake-alert');
            const submitBtn = document.getElementById('btnSubmitIntake');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Validating and persiting...';

            const payload = {
                client_request_id: document.getElementById('client_request_id').value.trim(),
                agent_id: document.getElementById('agent_id').value.trim(),
                customer_name: document.getElementById('customer_name').value.trim(),
                date_of_birth: document.getElementById('date_of_birth').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('trip_start').value,
                trip_end: document.getElementById('trip_end').value,
                plan_code: document.getElementById('plan_code').value,
                sum_insured: parseInt(document.getElementById('sum_insured').value),
            };

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                alertBox.style.display = 'block';
                if (res.status === 201) {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = `✓ Created new policy request [${data.data.client_request_id}] (HTTP 201). Premium: ₹${data.data.premium_amount}`;
                    setTimeout(() => location.reload(), 1200);
                } else if (res.status === 200 && data.is_duplicate) {
                    alertBox.className = 'alert alert-info';
                    alertBox.textContent = `ℹ Safe Idempotent Intake: Request [${payload.client_request_id}] already exists for ${payload.agent_id}. Returned existing record (HTTP 200).`;
                } else if (res.status === 422) {
                    alertBox.className = 'alert alert-danger';
                    const errMsgs = Object.values(data.errors || {}).flat().join('; ');
                    alertBox.textContent = `Validation Error: ${errMsgs}`;
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message || 'Submission failed';
                }
            } catch (err) {
                alertBox.style.display = 'block';
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = `Network / server error: ${err.message}`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Policy Intake Request';
            }
        }

        // Switch Tabs
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }

        // Filter Pipeline Table
        function filterTable() {
            const query = document.getElementById('tableSearch').value.toLowerCase();
            document.querySelectorAll('#policiesTable tbody tr').forEach(tr => {
                const text = tr.getAttribute('data-search') || '';
                tr.style.display = text.includes(query) ? '' : 'none';
            });
        }

        // Open Policy Drawer
        async function openPolicyDrawer(id) {
            const drawer = document.getElementById('policyDrawer');
            drawer.classList.add('active');
            document.getElementById('drawerPolicyId').textContent = 'Loading policy ' + id + '...';

            try {
                const res = await fetch(`/api/policy-requests/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                currentPolicy = json.data;
                renderDrawerDetails(currentPolicy);
            } catch (err) {
                document.getElementById('drawerPolicyId').textContent = 'Error loading policy';
            }
        }

        function closePolicyDrawer() {
            document.getElementById('policyDrawer').classList.remove('active');
            document.getElementById('drawerActionAlert').style.display = 'none';
        }

        function handleDrawerOverlayClick(e) {
            if (e.target.id === 'policyDrawer') closePolicyDrawer();
        }

        // Render details in drawer
        function renderDrawerDetails(policy) {
            document.getElementById('drawerPolicyId').textContent = policy.client_request_id;
            document.getElementById('drawerSubTitle').textContent = `UUID: ${policy.id} • Agent: ${policy.agent_id}`;
            document.getElementById('drawerStatusPill').innerHTML = `<span class="status-pill status-${policy.status}">${policy.status}</span>`;
            document.getElementById('drawerPolicyNumber').textContent = policy.policy_number || '(Not Issued Yet)';

            // Breakdown
            const b = policy.premium_breakdown || {};
            document.getElementById('drawerBreakdown').innerHTML = `
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Base Daily Rate (${b.plan_code}):</span> <span class="mono">₹${b.base_daily_rate || 0}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Travel Days (${policy.trip_start} to ${policy.trip_end}):</span> <span class="mono">${policy.travel_days} days</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Age Multiplier (${b.age_on_trip_start} yrs):</span> <span class="mono">×${b.age_multiplier || 1.0}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Destination Multiplier (${policy.destination}):</span> <span class="mono">×${b.destination_multiplier || 1.0}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Coverage Multiplier (₹${b.sum_insured}):</span> <span class="mono">×${b.coverage_multiplier || 1.0}</span>
                </div>
                <div style="border-top: 1px solid var(--border-color); padding-top: 6px; margin-top: 6px; display: flex; justify-content: space-between; font-weight: 600;">
                    <span>Calculated Total Premium:</span> <span class="mono">₹${policy.premium_amount}</span>
                </div>
            `;

            // Timeline
            const history = policy.state_histories || [];
            let timelineHtml = '';
            history.forEach(h => {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-time">${new Date(h.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})}</div>
                        <div class="timeline-content">
                            <strong>${h.from_status ? h.from_status + ' → ' : ''}${h.to_status}</strong>: ${h.reason || 'Status update'}
                        </div>
                    </div>
                `;
            });
            document.getElementById('drawerTimeline').innerHTML = timelineHtml || '<div style="color:var(--text-muted);font-size:12px;">No state changes logged yet.</div>';
        }

        // Run Scenario from Drawer
        async function runScenario(scenario) {
            if (!currentPolicy) return;
            const alertBox = document.getElementById('drawerActionAlert');
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-info';
            alertBox.textContent = `Executing scenario [${scenario}] on provider...`;

            try {
                const res = await fetch(`/api/policy-requests/${currentPolicy.id}/submit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ scenario })
                });

                const data = await res.json();
                if (data.policy) {
                    currentPolicy = data.policy;
                    renderDrawerDetails(currentPolicy);
                }

                if (res.status === 200) {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = `✓ ${data.message} Status: ${currentPolicy.status}`;
                } else if (res.status === 202) {
                    alertBox.className = 'alert alert-info';
                    alertBox.textContent = `ℹ ${data.message} (Provider Ref: ${data.provider_reference})`;
                } else if (res.status === 504) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = `⚠ Timeout simulated! Policy marked [NEEDS_RECONCILIATION] to prevent duplicate reissue.`;
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = `Provider returned: ${data.message || data.error}`;
                }
            } catch (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Error: ' + err.message;
            }
        }

        // Send simulated webhook callback
        async function triggerDrawerCallback(isDuplicate) {
            if (!currentPolicy || !currentPolicy.provider_reference) {
                alert('No provider reference associated with this policy yet. Run a submission first.');
                return;
            }

            const alertBox = document.getElementById('drawerActionAlert');
            alertBox.style.display = 'block';

            if (!isDuplicate) {
                lastCallbackEventId = 'EVT-' + Math.floor(100000 + Math.random() * 900000);
            }

            try {
                const res = await fetch(`/api/mock-insurer/trigger-callback/${currentPolicy.provider_reference}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ event_id: lastCallbackEventId })
                });

                const data = await res.json();
                const callbackRes = data.callback_result;

                if (callbackRes.status === 'duplicate_ignored') {
                    alertBox.className = 'alert alert-info';
                    alertBox.textContent = `ℹ Duplicate callback [${lastCallbackEventId}] was safely ignored! Deduplication successful.`;
                } else {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = `✓ Webhook callback processed! ${callbackRes.message}`;
                    openPolicyDrawer(currentPolicy.id);
                }
            } catch (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Error: ' + err.message;
            }
        }

        // Reconcile Drawer Policy
        async function reconcileDrawerPolicy() {
            if (!currentPolicy) return;
            const alertBox = document.getElementById('drawerActionAlert');
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-info';
            alertBox.textContent = 'Querying provider ledger for status...';

            try {
                const res = await fetch(`/api/policy-requests/${currentPolicy.id}/reconcile`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                const data = await res.json();
                alertBox.className = 'alert alert-success';
                alertBox.textContent = `✓ ${data.message}`;
                openPolicyDrawer(currentPolicy.id);
            } catch (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Error: ' + err.message;
            }
        }

        // Run CLI Reconcile helper
        async function runCliReconcile() {
            const btn = event.target;
            btn.textContent = 'Reconciling...';
            try {
                const res = await fetch('/api/policy-requests');
                const json = await res.json();
                const items = json.data || [];
                let reconciledCount = 0;
                for (const item of items) {
                    if (item.status === 'NEEDS_RECONCILIATION' || item.status === 'PENDING_CONFIRMATION') {
                        await fetch(`/api/policy-requests/${item.id}/reconcile`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
                        reconciledCount++;
                    }
                }
                alert(`Reconciliation scan finished. Reconciled ${reconciledCount} policy/policies.`);
                location.reload();
            } catch (err) {
                alert('Scan error: ' + err.message);
            } finally {
                btn.textContent = 'Run Reconcile (CLI)';
            }
        }

        function copyCode(btn) {
            const pre = btn.parentElement;
            const text = pre.innerText.replace('Copy', '').trim();
            navigator.clipboard.writeText(text);
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy', 1500);
        }

        // Initialize Live Pricing on load
        window.addEventListener('DOMContentLoaded', () => {
            updateLivePricing();
        });
    </script>
</body>
</html>
