# Introducción al Sistema

Bienvenido al sistema de Gestión de Recursos Humanos. Esta guía explica cómo usar cada módulo del panel administrativo.

## ¿Qué puede hacer el sistema?

El sistema cubre el ciclo completo de gestión del personal:

- **Organización:** estructura jerárquica de empresa, sucursales, departamentos y cargos
- **Empleados:** registro completo con contratos y documentos
- **Asistencias:** marcaciones por reconocimiento facial (terminal compartida o dispositivo personal)
- **Nóminas:** liquidación mensual/quincenal/semanal con percepciones, deducciones y recibos en PDF
- **Vacaciones y permisos:** solicitudes, aprobaciones y saldo de días por año
- **Ausencias:** registro y justificación de inasistencias con descuento automático
- **Créditos:** préstamos, adelantos de salario y retiros de mercadería con descuento automático en nómina
- **Amonestaciones:** registro documental de sanciones disciplinarias
- **Aguinaldo:** cálculo y emisión del 13.° salario
- **Liquidaciones:** finiquito al término de la relación laboral
- **Configuración:** usuarios, feriados y parámetros del sistema

## Jerarquía de datos

Dos ejes independientes que se unen en el Contrato:

```
Empresa → Sucursal → Empleado
Empresa → Departamento → Cargo
```

El empleado pertenece a una **Sucursal**. Su salario, cargo y fecha de ingreso están en el **Contrato activo**, no en el perfil del empleado directamente.

> **Importante:** Siempre use el contrato activo como fuente de verdad del cargo y salario. El campo de cargo en el perfil del empleado es un campo histórico.

## Flujo de trabajo inicial recomendado

1. Crear la **empresa** con sus datos legales y logo
2. Crear las **sucursales** de la empresa
3. Definir **departamentos** y **cargos** (con jerarquía si corresponde)
4. Configurar los **horarios** de trabajo con días y descansos
5. Registrar los **empleados** y crear sus **contratos** iniciales
6. Asignar un **horario** a cada empleado
7. Cargar los **feriados** del año
8. Habilitar las **marcaciones** (terminal o dispositivo personal con reconocimiento facial)
9. Cada período: generar **nómina**, revisar y aprobar
10. Gestionar **vacaciones**, **ausencias** y **préstamos** según necesidad
11. Al cierre de año: calcular y emitir el **aguinaldo**
