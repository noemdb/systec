# Inventario técnico — SysTec GMB

> **Estado:** v1.0 (snapshot pre-F1/F2) · **Actualizado:** 2026-11-09.
> Análisis folder por folder y archivo por archivo del estado **antes de F1**; no refleja F2 (transacciones, migraciones, normalización, triggers e índices). Se conserva como evidencia histórica del baseline. Para el estado vigente, ver `docs/TODO.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md` y `docs/SECURITY.md`. Los conteos de líneas son del análisis original y pueden variar.

---

## 1. Resumen

| Métrica | Valor |
|---|---|
| Archivos PHP de aplicación | 27 |
| Líneas PHP de aplicación (sin vendor/docs) | ~2.000 |
| Archivos de datos (actuales) | `storage/db.db`, `storage/properties.csv`, `storage/maintenances.csv`, `storage/backups/` |
| Framework / Composer | Ninguno |
| Pruebas | Manuales (`php -l` + humo); sin suite automatizada |
| Migraciones | Ninguna |
| Documentación previa | Solo `README.md` de 6 líneas y `docs/templatesOthersApp/` (referencia externa) |

---

## 2. Raíz del proyecto

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `index.php` | 21 | Portada institucional | Usa `layout.php`; enlace al dashboard |
| `dashboard.php` | 51 | Listado de bienes y búsqueda | Inicializa DataTables; incluye tabla |
| `maintenances.php` | 164 | Listado, alta y edición de mantenimientos | Orquesta formularios, filtros y modal |
| `historials.php` | 35 | Historial imprimible por bien | Membrete alternativo; fecha hardcodeada |
| `report.php` | 97 | Reporte de bienes dañados | Imprime un bloque por bien |
| `batch.php` | 111 | Dos historiales en una página | Variable indefinida al inicio |
| `imports.php` | 32 | Importa `maintenances.csv` | No enlazado en nav; escribe al abrir |
| `updates.php` | 59 | Importa `properties.csv` | No enlazado en nav; escribe al abrir |
| `conn.php` | 223 | Clase `DB`, conexión, catálogos, esquema | Núcleo de datos; SQL concatenado |
| `Makefile` | 2 | `make dev` | `php -S localhost:5050` |
| `README.md` | 6 | Instrucción mínima | Sin contexto funcional |
| `db.db` | binario | Base SQLite | 617 bienes / 547 mantenimientos |
| `properties.csv` | 617 filas | Inventario legacy | Separador `;` |
| `maintenances.csv` | 110 filas | Mantenimientos legacy | Separador `,` |
| `favicon.svg` | 4 | Icono | — |
| `.gitignore` | 1 | `/.` | No es repo git; patrón sospechoso |
| `maintenances.csv` / `properties.csv` | — | Datos de entrada | No versionados formalmente |

### 2.1 `conn.php` en detalle

| Método | Propósito | Riesgo |
|---|---|---|
| `__construct` | Abre SQLite y crea tablas | Esquema sin migraciones |
| `createOrUpdate` | Upsert de inventario por `ident` | Solo actualiza `status` si existe; cierra conexión |
| `create` | INSERT genérico | Cierra conexión; no transaccional |
| `update` | UPDATE genérico | `id` concatenado (SQLi) |
| `indexWithMaintenances` | Reporte de dañados | JOIN puede duplicar |
| `index` | SELECT genérico | `conditions`, `fields`, `sort` concatenados |
| `getListProperties` | Lista id → código | N+1 |
| `getCodeId` | Compone código | `id` concatenado; padding inconsistente |
| `select` | SELECT libre | SQL libre |
| `getFirstForId` | Busca por id | `id` concatenado |
| `getFirstForConditions` | Busca por condición | Condición concatenada |
| `getListPropertyStatus` | Catálogo de estados de bien | No incluye `MUY BUENO` |
| `getListMaintenanceStatus` | Catálogo de estados | No incluye `FINALIZADO` |
| `getListMaintenanceType` | Catálogo de tipos | No incluye `evaluativo` |
| `getListMaintenanceTechnician` | Catálogo de técnicos | No incluye `ndomiguez` |
| `restarFechasEnHoras` | Diferencia horaria | Solo `DateInterval->h` |

---

## 3. `include/`

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `layout.php` | 52 | Layout base | `ob_start()` + `$main`; acepta `header_file`, `no_footer`, `main_class` |
| `head.php` | 45 | Meta, CSS, JS | Rutas relativas `./vendor/...`; estilos inline |
| `header.php` | 10 | Navegación | Inicio, Dashboard, Mantenimientos; Importar/Actualizar comentados |
| `footer.php` | 2 | Pie | Enlaces institucionales |
| `script.php` | 1 | Bootstrap bundle | — |
| `helpers.php` | 71 | Badges y etiquetas | `match` de PHP 8; el entorno es 7.4 (verificar compatibilidad) |
| `es-ES.json` | 241 | Traducción DataTables | No referenciada por ninguna vista |

**Nota de compatibilidad:** `helpers.php` usa `match`, introducido en PHP 8.0. El entorno analizado reporta PHP 7.4.33. Si el servidor de producción es 7.4, las vistas que incluyen helpers fallarían. Verificar la versión real de producción (H-16 potencial).

---

## 4. `dashboard/`

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `table/index.php` | 41 | Tabla de bienes | N+1 por bien para contar mantenimientos; salida sin escape |
| `actions/status.php` | 29 | Cambio de estado del bien | GET; sin CSRF; ciclo no lineal; captura `PDOException` con `SQLite3` |

---

## 5. `maintenances/`

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `register.php` | 44 | Alta de mantenimiento | Incluye `validations/register.php`; nace `iniciado`; no calcula horas |
| `update.php` | 41 | Edición | **Incluye `validations/register.php`** (H-04); actualiza todos los campos |
| `actions/status.php` | 30 | Transición de estado | GET; sin guardia; recalcula `time_taken` |
| `form/fields.php` | 91 | Campos compartidos | `property_id`, tipo, técnico, fecha, razón, descripción, notas, próximo |
| `form/edit.php` | 19 | Campo estado + id | Reutiliza `fields.php` |
| `table/crud.php` | 154 | Tabla, filtros y modal | Vencimiento en vista; salida sin escape; filtros DataTables |
| `table/import.php` | 75 | Importa `maintenances.csv` | Inserta si el bien existe; omite en silencio; `rand()` |
| `validations/register.php` | 20 | Validaciones de alta | `die()`; no valida existencia del bien |
| `validations/update.php` | 24 | Validaciones de edición | **Código muerto**: no se incluye |

---

## 6. `properties/`

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `table/properties.php` | 32 | Tabla de bienes | Sin uso directo actual; salida sin escape |
| `table/propertiesCsv.php` | 44 | Importa `properties.csv` | Upsert por `ident`; solo actualiza `status`; sin reporte |

---

## 7. `historials/`

| Archivo | Líneas | Propósito | Observaciones |
|---|---:|---|---|
| `main.php` | 49 | Historial + observaciones | Define `$technician`; salida sin escape; usa `property["id"]` |
| `report.php` | 55 | Variante para reporte masivo | Consulta por bien; usa `$count` del padre; firma |
| `membrete.php` | 17 | Encabezado institucional | Fecha hardcodeada `10-06-2025`; datos del bien sin escape |
| `footer.php` | 8 | Pie de historial | Fecha actual |
| `chart.php` | 20 | Gráfico de tipos | No incluido en ninguna vista |

---

## 8. `docs/`

| Elemento | Propósito | Observaciones |
|---|---|---|
| `templatesOthersApp/` | Referencia externa de documentación | No describe SysTec; se conserva |
| `README.md` … `CHANGELOG.md` | Documentación operativa | Creada en esta entrega |
| `anexos/` | Inventario, trazabilidad y plan | Creada en esta entrega |

---

## 9. `grafo/`

| Archivo | Propósito |
|---|---|
| `README.md` | Índice y leyenda |
| `logica-negocio.md` | Capacidades, flujos, estados, reglas y hallazgos |
| `modelo-datos.md` | ERD, diccionario y calidad |
| `grafo.json` | Grafo nodos/aristas legible por máquina |

---

## 10. `vendor/`

| Componente | Versión | Archivos relevantes |
|---|---|---|
| Bootstrap | 5.3.2 | `css/bootstrap.min.css`, `js/bootstrap.bundle.min.js`, `icon/` |
| DataTables | 1.13.7 | `datatables.min.css`, `datatables.min.js`, `DataTables-1.13.7/` |
| jQuery | 3.7.0 | `jquery-3.7.0.min.js` |

**Observaciones:**

- 17 MB de dependencias vendorizadas.
- No hay gestor de paquetes ni verificación de integridad.
- `include/head.php` carga `./vendor/datatables/datatables.min.js` antes de jQuery; DataTables requiere jQuery, que se carga dentro del bundle de DataTables o después. Verificar orden real en el HTML generado.
- `include/es-ES.json` no se usa.

---

## 11. `bck/`

| Archivo | Contenido | Observaciones |
|---|---|---|
| `bck/2025-04-09/db.db` | 597 bienes / 321 mantenimientos | Backup manual; sin cifrar |
| `bck/csv/properties.csv` | Inventario | Copia de la misma fecha |

---

## 12. Archivos de datos

| Archivo | Formato | Filas | Delimitador | Observaciones |
|---|---|---:|---|---|
| `properties.csv` | CSV | 617 | `;` | Campos: grupo, subgrupo, sección, ident, descripción, modelo, serial, color, estado, extra |
| `maintenances.csv` | CSV | 110 | `,` | Campos: id, tipo, descripción, fname, usuario, comentario, fecha, obs, estado |
| `db.db` | SQLite | 617 + 547 | — | Datos vivos |

---

## 13. Cobertura de análisis

| Carpeta | Archivos revisados | Estado |
|---|---|---|
| Raíz | 17 | ✅ |
| `include/` | 7 | ✅ |
| `dashboard/` | 2 | ✅ |
| `maintenances/` | 9 | ✅ |
| `properties/` | 2 | ✅ |
| `historials/` | 5 | ✅ |
| `vendor/` | Inventario y versiones | ✅ |
| `bck/` | 2 | ✅ |
| `docs/templatesOthersApp/` | 11 | ✅ (referencia) |
| `grafo/` | 4 | ✅ (creado) |

---

## 14. Hallazgos adicionales del inventario

| ID | Hallazgo | Archivo |
|---|---|---|
| H-16 | `match` (PHP 8) en helpers con entorno PHP 7.4 | `include/helpers.php` |
| H-17 | Orden de carga de DataTables/jQuery dudoso | `include/head.php` |
| H-18 | `properties/table/properties.php` sin uso | `properties/table/properties.php` |
| H-19 | `historials/chart.php` sin uso | `historials/chart.php` |
| H-20 | `validations/update.php` sin uso | `maintenances/validations/update.php` |
| H-21 | `.gitignore` con patrón `/.` | `.gitignore` |
| H-22 | `README.md` sin información funcional | `README.md` |

Estos hallazgos se suman a los H-01…H-15 de `docs/anexos/matriz-trazabilidad.md` y `docs/TODO.md`.

---

Ver también: `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/anexos/matriz-trazabilidad.md`, `grafo/logica-negocio.md`.
