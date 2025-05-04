<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recibo de Pago - Macro Paraguay">
    <title>Recibo de Pago</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
        }

        .details,
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .details th,
        .details td,
        .summary th,
        .summary td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .details th {
            background-color: #f2f2f2;
            text-align: left;
        }

        .summary th {
            background-color: #f2f2f2;
            text-align: right;
        }

        .summary td {
            text-align: right;
        }

        .total {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Macro</h1>
        <p>
            <small>RUC: 12345678-9</small><br>
            <small>Dirección: Calle Falsa 123</small><br>
            <small>Ciudad: Capiatá</small><br>
            <small>Teléfono: +595 21 123 4567</small><br>
            <small>Email: info@macroparaguay.com</small><br>
        </p>
        <h2>Recibo de Pago</h2>
    </div>

    <table class="details">
        <tr>
            <th>Empleado</th>
            <td>{{ $payroll->employee->first_name.' '.$payroll->employee->last_name }}</td>
        </tr>
        <tr>
            <th>CI</th>
            <td>{{ $payroll->employee->ci }}</td>
        </tr>
        <tr>
            <th>Período</th>
            <td>{{ $payroll->period }}</td>
        </tr>
        <tr>
            <th>Departamento</th>
            <td>{{ $payroll->employee->department }}</td>
        </tr>
        <tr>
            <th>Cargo</th>
            <td>{{ $payroll->employee->position }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <th>Salario Base</th>
            <td>{{ number_format($payroll->base_salary, 0, ',', '.') }} PYG</td>
        </tr>
        <tr>
            <th>Bonificaciones</th>
            <td>{{ number_format($payroll->bonuses, 0, ',', '.') }} PYG</td>
        </tr>
        <tr>
            <th>Deducciones</th>
            <td>-{{ number_format($payroll->deductions, 0, ',', '.') }} PYG</td>
        </tr>
        <tr class="total">
            <th>Salario Neto</th>
            <td>{{ number_format($payroll->net_salary, 0, ',', '.') }} PYG</td>
        </tr>
    </table>

    <p><small>Emitido el: {{ now()->format('d/m/Y') }}</small></p>
</body>

</html>
