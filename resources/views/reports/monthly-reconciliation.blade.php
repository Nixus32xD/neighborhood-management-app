<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rendicion mensual</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 20px;
        }

        h1, h2, h3 {
            margin: 0;
        }

        .header {
            margin-bottom: 16px;
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
        }

        .value {
            font-weight: 700;
            font-size: 14px;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
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

        .section-title {
            margin: 14px 0 8px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Rendicion mensual</h1>
        <p class="muted">{{ $neighborhoodName }} - {{ ucfirst($periodLabel) }}</p>
        <p class="muted">Generado: {{ $generatedAt }}</p>
    </div>

    <div class="grid">
        <div class="card">
            <div class="muted">Expensas del mes</div>
            <div class="value">${{ number_format($totals['charged'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Cobrado a propietarios</div>
            <div class="value">${{ number_format($totals['collected'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Saldo pendiente</div>
            <div class="value">${{ number_format($totals['outstanding'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Ingresos del mes</div>
            <div class="value">${{ number_format($totals['income'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Egresos del mes</div>
            <div class="value">${{ number_format($totals['outflow'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="muted">Resultado neto</div>
            <div class="value">${{ number_format($totals['net'], 2, ',', '.') }}</div>
        </div>
    </div>

    <h2 class="section-title">Estado de expensas por propietario</h2>
    <table>
        <thead>
            <tr>
                <th>UF</th>
                <th>Propietario</th>
                <th class="text-right">Mensual</th>
                <th class="text-right">Extraordinaria</th>
                <th class="text-right">Multas</th>
                <th class="text-right">Total</th>
                <th class="text-right">Pagado</th>
                <th class="text-right">Pendiente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense['uf_number'] }}</td>
                    <td>{{ $expense['owner'] ?: '-' }}</td>
                    <td class="text-right">${{ number_format($expense['monthly'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['extraordinary'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['fines'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['total'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['paid'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense['outstanding'], 2, ',', '.') }}</td>
                    <td>{{ $expense['status'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay expensas para este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

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

