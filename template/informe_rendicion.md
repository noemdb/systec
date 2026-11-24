# {{REPORT_TITLE}}

| **Código de Informe** | `{{REPORT_CODE}}` | **Estado de Rendición** | **{{REPORT_STATUS_LABEL}}** |
| :--- | :--- | :--- | :--- |
| **Período Rendido** | {{DATE_FROM_LABEL}} al {{DATE_TO_LABEL}} | **Fecha de Emisión** | {{ISSUED_AT}} |
| **Unidad Emisora** | {{ISSUING_UNIT}} | **Dirigido a** | {{RECIPIENT_NAME}} |
| **Responsable de Rendición** | {{PREPARED_BY}} | **Criterios y Alcance** | {{SCOPE_SUMMARY}} |

---

## 1. Resumen Ejecutivo del Período

{{AI_EXECUTIVE_SUMMARY}}

---

## 2. Indicadores Consolidados de Gestión

| Indicador de Gestión | Valor Consolidado | Desglose Operativo del Período |
| :--- | :---: | :--- |
| **Total de mantenimientos registrados** | **{{TOTAL_MAINTENANCES}}** | {{BREAKDOWN_BY_STATUS}} |
| **Bienes institucionales intervenidos** | **{{TOTAL_PROPERTIES}}** | {{BREAKDOWN_BY_CATEGORY}} |
| **Horas técnicas registradas** | **{{TOTAL_HOURS}} h** | Promedio: {{AVG_HOURS}} h por intervención |
| **Distribución por tipo de mantenimiento** | **{{TOTAL_MAINTENANCES}}** | Preventivos: {{COUNT_PREVENTIVO}} · Correctivos: {{COUNT_CORRECTIVO}} · Predictivos: {{COUNT_PREDICTIVO}} · Evaluativos: {{COUNT_EVALUATIVO}} |
| **Actividades asociadas (tickets)** | **{{TOTAL_TICKETS}}** | Finalizados: {{TICKETS_CLOSED}} · En seguimiento: {{TICKETS_OPEN}} |

---

## 3. Síntesis Técnica de Actividades de Mantenimiento

{{AI_ACTIVITIES_NARRATIVE}}

---

## 4. Registro Cronológico de Acciones de Mantenimiento

> Cada registro presenta en su fila principal los metadatos de la intervención (`Fecha`, `Código del Bien`, `Descripción del Bien`, `Tipo`, `Estado`, `Técnico`, `Horas` y `Ticket`) y, justo debajo, el detalle extendido de **Trabajo Realizado / Causa / Notas** abarcando el ancho de dichas columnas (`rowspan="2"` en `#` y `colspan="8"` en el detalle).

{{MAINTENANCE_LOG_TABLE}}

---

## 5. Actividades Asociadas (Tickets e Incidencias Atendidas)

{{AI_TICKETS_NARRATIVE}}

{{TICKETS_LOG_TABLE}}

---

## 6. Hallazgos Críticos, Recomendaciones y Observaciones

{{AI_OBSERVATIONS_AND_CONCLUSIONS}}

---

## 7. Conformidad y Aprobación de Rendición

| Elaborado y Rendido por | Revisado y Conformado por |
| :---: | :---: |
| _______________________________ | _______________________________ |
| **{{PREPARED_BY}}** | **{{RECIPIENT_NAME}}** |
| {{ISSUING_UNIT}} | Estado: {{REPORT_STATUS_LABEL}} · Fecha: {{APPROVED_AT_LABEL}} |

