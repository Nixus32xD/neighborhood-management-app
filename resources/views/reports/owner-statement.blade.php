<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de cuenta del propietario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 20px;
        }

        h1, h2 {
            margin: 0;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            margin-bottom: 16px;
        }

        .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 14px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 16px;
        }

        .value {
            font-weight: 700;
            margin-top: 4px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body onload="window.print()">
    <div class="header">
        <h1>Estado individual de propietario</h1>
        <p class="muted">{{ $neighborhoodName }}</p>
        <p class="muted">Generado: {{ $generatedAt }}</p>
    </div>

    <div class="card">
        <p><strong>Propietario:</strong> {{ $statement['owner']['name'] }} ({{ $statement['owner']['uf'] }})</p>
        <p><strong>Email:</strong> {{ $statement['owner']['email'] ?: 'Sin email cargado' }}</p>
        <p><strong>Filtro aplicado:</strong> {{ $statement['filter_label'] }}</p>
    </div>

    <div class="summary">
        <div class="card">
            <div class="muted">Total cargado</div>
            <div class="value">${{ number_format($statement['summary']['charged_total'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Pagado (filtro)</div>
            <div class="value">${{ number_format($statement['summary']['paid_in_filter_total'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Pagado historico</div>
            <div class="value">${{ number_format($statement['summary']['paid_total'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Saldo pendiente</div>
            <div class="value">${{ number_format($statement['summary']['outstanding_total'], 2, ',', '.') }}</div>
        </div>
    </div>

    <h2>Cargos por periodo</h2>
    <table>
        <thead>
            <tr>
                <th>Periodo</th>
                <th class="text-right">Mensual</th>
                <th class="text-right">Extra.</th>
                <th class="text-right">Multas</th>
                <th class="text-right">Cargado</th>
                <th class="text-right">Pagado (filtro)</th>
                <th class="text-right">Pagado total</th>
                <th class="text-right">Pendiente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['charges'] as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td class="text-right">${{ number_format($row['monthly'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['extraordinary'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['fines'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['charged'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['paid_in_filter'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['paid_total'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($row['outstanding'], 2, ',', '.') }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay cargos para el filtro aplicado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (!empty($statement['payment_plans']))
        <h2>Planes de pago</h2>
        <table>
            <thead><tr><th>Plan</th><th>Estado</th><th class="text-right">Deuda incluida</th><th class="text-right">Recargo</th><th class="text-right">Total acuerdo</th><th class="text-right">Abonado</th><th class="text-right">Saldo</th><th>Próximo vencimiento</th></tr></thead>
            <tbody>
                @foreach ($statement['payment_plans'] as $plan)
                    <tr><td>#{{ $plan['id'] }}</td><td>{{ $plan['status'] }}</td><td class="text-right">${{ number_format($plan['financed_debt_amount'], 2, ',', '.') }}</td><td class="text-right">${{ number_format($plan['financing_charge_amount'], 2, ',', '.') }}</td><td class="text-right">${{ number_format($plan['original_amount'], 2, ',', '.') }}</td><td class="text-right">${{ number_format($plan['paid_amount'], 2, ',', '.') }}</td><td class="text-right">${{ number_format($plan['outstanding_amount'], 2, ',', '.') }}</td><td>{{ $plan['next_due_date'] ?: '-' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Pagos registrados</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Periodo imputado</th>
                <th>Metodo</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['payments'] as $payment)
                <tr>
                    <td>{{ $payment['date'] ? \Carbon\Carbon::parse($payment['date'])->format('d/m/Y') : '-' }}</td>
                    <td>{{ $payment['period'] ?: '-' }}</td>
                    <td>{{ $payment['method'] ?: '-' }}</td>
                    <td class="text-right">${{ number_format($payment['amount'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No hay pagos para el filtro aplicado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
