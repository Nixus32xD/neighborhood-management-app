<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rendicion mensual</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 20px;
            background: #f8fafc;
        }

        h1,
        h2,
        h3 {
            margin: 0;
        }

        .header {
            margin-bottom: 16px;
            border: 1px solid #dbeafe;
            background: linear-gradient(135deg, #eff6ff, #f0f9ff);
            border-radius: 10px;
            padding: 12px;
        }

        .muted {
            color: #6b7280;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            background: #ffffff;
        }

        .value {
            font-weight: 700;
            font-size: 14px;
            margin-top: 4px;
        }

        .card-blue {
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .card-green {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .card-red {
            border-color: #fecaca;
            background: #fef2f2;
        }

        .card-amber {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .card-violet {
            border-color: #ddd6fe;
            background: #f5f3ff;
        }

        .card-cyan {
            border-color: #a5f3fc;
            background: #ecfeff;
        }

        .card-slate {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            background: #ffffff;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #e2e8f0;
            font-weight: 700;
            color: #0f172a;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .section-title {
            margin: 14px 0 8px;
            font-size: 14px;
            color: #1d4ed8;
        }

        .status-pill {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .th-monthly {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .th-extra {
            background: #ffedd5;
            color: #9a3412;
        }

        .amount-monthly {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 700;
        }

        .amount-extra-has {
            background: #fff7ed;
            color: #c2410c;
            font-weight: 700;
        }

        .amount-extra-none {
            color: #94a3b8;
            font-weight: 600;
        }

        .th-fines {
            background: #fce7f3;
            color: #9d174d;
        }

        .th-paid {
            background: #dcfce7;
            color: #166534;
        }

        .th-outstanding {
            background: #fef3c7;
            color: #92400e;
        }

        .amount-fines {
            background: #fdf2f8;
            color: #be185d;
            font-weight: 700;
        }

        .amount-paid {
            background: #f0fdf4;
            color: #166534;
            font-weight: 700;
        }

        .amount-outstanding {
            background: #fffbeb;
            color: #92400e;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Rendicion mensual</h1>
        <p class="muted">{{ $neighborhoodName }} - {{ ucfirst($periodLabel) }}</p>
        <p class="muted">Generado: {{ $generatedAt }}</p>
    </div>
{{--
    <div class="grid">
        <div class="card card-blue">
            <div class="muted">Expensas del mes</div>
            <div class="value">${{ number_format($totals['charged'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-green">
            <div class="muted">Cobrado a propietarios</div>
            <div class="value">${{ number_format($totals['collected'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-red">
            <div class="muted">Saldo pendiente</div>
            <div class="value">${{ number_format($totals['outstanding'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-amber">
            <div class="muted">Deuda anterior acumulada</div>
            <div class="value">${{ number_format($totals['historical_outstanding'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-violet">
            <div class="muted">Deuda total acumulada</div>
            <div class="value">${{ number_format($totals['cumulative_outstanding'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-cyan">
            <div class="muted">Ingresos del mes</div>
            <div class="value">${{ number_format($totals['income'], 2, ',', '.') }}</div>
        </div>
        <div class="card card-slate">
            <div class="muted">Egresos del mes</div>
            <div class="value">${{ number_format($totals['outflow'], 2, ',', '.') }}</div>
        </div>
        <div class="card {{ $totals['net'] >= 0 ? 'card-green' : 'card-red' }}">
            <div class="muted">Resultado neto</div>
            <div class="value">${{ number_format($totals['net'], 2, ',', '.') }}</div>
        </div>
    </div> --}}

    <h2 class="section-title">Estado de expensas por propietario</h2>
    <table>
        <thead>
            <tr>
                <th class="th-monthly">UF</th>
                <th class="th-monthly">Propietario</th>
                <th class="text-right th-monthly">Mensual (a pagar)</th>
                <?php if($totals['extraordinary'] > 0): ?>
                <th class="text-right th-extra">Extraordinaria</th>
                <?php endif; ?>
                <th class="text-right th-fines">Deudas</th>
                <th class="text-right">Total</th>
                <th class="text-right th-paid">Pagado</th>
                <th class="text-right th-outstanding">Pendiente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td class="amount-monthly">{{ $expense['uf_number'] }}</td>
                    <td class="amount-monthly">{{ $expense['owner'] ?: '-' }}</td>
                    <td class="text-right amount-monthly">${{ number_format($expense['monthly'], 2, ',', '.') }}</td>
                    <?php if($totals['extraordinary'] > 0): ?>
                    <td
                        class="text-right {{ (float) $expense['extraordinary'] > 0 ? 'amount-extra-has' : 'amount-extra-none' }}">
                        ${{ number_format($expense['extraordinary'], 2, ',', '.') }}
                    </td>
                    <?php endif; ?>
                    <td class="text-right amount-fines">${{ number_format($expense['fines'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['total'], 2, ',', '.') }}</td>
                    <td class="text-right amount-paid">${{ number_format($expense['paid'], 2, ',', '.') }}</td>
                    <td class="text-right amount-outstanding">
                        ${{ number_format($expense['outstanding'], 2, ',', '.') }}</td>
                    <td>
                        <span
                            class="status-pill {{ strtolower($expense['status']) === 'pagado' ? 'status-paid' : 'status-pending' }}">
                            {{ $expense['status'] }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay expensas para este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- <h2 class="section-title">Deudas acumuladas por propietario</h2>
    <table>
        <thead>
            <tr>
                <th>UF</th>
                <th>Propietario</th>
                <th class="text-right">Deuda anterior</th>
                <th class="text-right">Pendiente del periodo</th>
                <th class="text-right">Total adeudado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($debtByOwner as $debt)
                <tr>
                    <td>{{ $debt['uf_number'] }}</td>
                    <td>{{ $debt['owner'] ?: '-' }}</td>
                    <td class="text-right">${{ number_format($debt['historical_outstanding'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($debt['current_outstanding'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($debt['total_outstanding'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No hay deuda acumulada al cierre del periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table> --}}

    <h2 class="section-title">Movimientos bancarios del mes</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descripcion</th>
                <th>Beneficiario</th>
                <th>Metodo</th>
                <th>Cuenta</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}</td>
                    <td>{{ $movement['description'] }}</td>
                    <td>{{ $movement['recipient'] }}</td>
                    <td>{{ $movement['method'] }}</td>
                    <td>{{ $movement['account'] }}</td>
                    <td class="text-right">${{ number_format($movement['amount'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No hay movimientos bancarios para este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
