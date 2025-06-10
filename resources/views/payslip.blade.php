<!DOCTYPE html>
<html lang="es">

<head>
    <title>Recibo de Salario - {{ $employee->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .company {
            font-size: 18px;
            font-weight: bold;
        }

        .title {
            font-size: 16px;
        }

        .details {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .totals {
            margin-top: 20px;
            font-weight: bold;
        }

        .signature {
            margin-top: 50px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company">Nombre de la Empresa</div>
        <div class="title">RECIBO DE SALARIO</div>
        <div class="period">Periodo: {{ $payroll->period }}</div>
    </div>

    <div class="details">
        <p><strong>Empleado:</strong> {{ $employee->name }}</p>
        <p><strong>ID:</strong> {{ $employee->id }}</p>
        <p><strong>Cargo:</strong> {{ $employee->position }}</p>
        <p><strong>Fecha de Pago:</strong> {{ $payroll->pay_date->format('d/m/Y') }}</p>
    </div>

    <h3>Detalle de Salario</h3>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Tipo</th>
                <th>Monto (Gs.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $type => $group)
                @foreach ($group as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>
                            @if ($type === 'salary')
                                Salario
                            @elseif($type === 'perception')
                                Percepción
                            @else
                                Deducción
                            @endif
                        </td>
                        <td>{{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p>Salario Base: {{ number_format($totals['salary'], 0, ',', '.') }} Gs.</p>
        <p>Total Percepciones: {{ number_format($totals['perceptions'], 0, ',', '.') }} Gs.</p>
        <p>Salario Bruto: {{ number_format($totals['gross'], 0, ',', '.') }} Gs.</p>
        <p>Total Deducciones: {{ number_format($totals['deductions'], 0, ',', '.') }} Gs.</p>
        <p>Salario Neto: {{ number_format($totals['net'], 0, ',', '.') }} Gs.</p>
    </div>

    <div class="signature">
        <p>_________________________</p>
        <p>Firma del Empleado</p>
    </div>
</body>

</html>