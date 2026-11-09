# docs/ — SysTec GMB

> **Estado:** v1.3 (F2 en ejecución) · **Actualizado:** 2026-11-09 · **Dueño:** Oficina de Informática / equipo de mantenimiento.
> **Naturaleza:** documentación inversa (as-built) del sistema actual, no un blueprint aspiracional. Cada afirmación se contrastó con el código y con `storage/db.db`. Lo que no está implementado se marca como deuda o propuesta.

## Mapa

| Archivo | Responde | Leer cuando |
|---|---|---|
| `PROJECT.md` | ¿Qué es, para quién y qué queda fuera? | Siempre primero |
| `ARCHITECTURE.md` | ¿Cómo se conectan las piezas? | Tocas estructura, flujos o despliegue |
| `DOMAIN.md` | Idioma ubicuo, entidades, estados y reglas | Tocas inventario, mantenimientos o reportes |
| `DATABASE.md` | Tablas, DDL, datos, calidad y migraciones | Tocas `storage/db.db` o `conn.php` |
| `BACKUP.md` | Política y procedimiento de backup/restore | Defines operación de TI o recuperación |
| `API.md` | Superficie HTTP real (páginas y acciones) | Tocas rutas, formularios o parámetros |
| `SECURITY.md` | Riesgos, hallazgos y controles | Tocas datos, despliegue o accesos |
| `CONVENTIONS.md` | Estilo, estructura y patrones | Escribes código |
| `DECISIONS.md` | ADR reconstruidos y propuestas | Dudas entre alternativas |
| `TODO.md` | Estado, backlog priorizado y bloqueos | Planificas o retomas |
| `CHANGELOG.md` | Qué cambió y cuándo | Auditoría documental |
| `anexos/` | Inventario técnico y trazabilidad del baseline F0/F1 | Consultar como evidencia histórica; usar `TODO.md` para el estado actual |

## Lectura por rol

- **Dev nuevo / agente IA:** `PROJECT + ARCHITECTURE + TODO` como mínimo; añade `DOMAIN` si tocas mantenimientos, `DATABASE` si tocas `storage/db.db`, `API` si tocas rutas y `SECURITY` antes de exponer el sistema.
- **Operador de inventario:** `PROJECT + DOMAIN (glosario/estados) + TODO` para estado vigente.
- **Técnico de mantenimiento:** `DOMAIN` (ciclo de mantenimiento y vencimiento) + `API` (formularios).
- **Auditor / dirección:** `SECURITY + TODO + DECISIONS`.
- **Quien hereda el sistema:** `ARCHITECTURE + DATABASE + anexos/matriz-trazabilidad`.

## Diagramas

- `grafo/logica-negocio.md`: mapa de capacidades, flujos de importación, ciclo de mantenimiento y estados del bien.
- `grafo/modelo-datos.md`: ERD y diccionario de datos.
- `ARCHITECTURE.md`: diagrama de componentes y ciclo de request.
- `DATABASE.md`: DDL y relaciones.
- `DOMAIN.md`: máquinas de estado.

Todos los diagramas usan Mermaid y se renderizan en GitHub/GitLab o con cualquier visor compatible.

## Proceso documental

1. **Al iniciar un cambio:** lee `PROJECT`, `ARCHITECTURE` y el documento del área.
2. **Al cerrar un cambio:** actualiza `TODO.md` y `CHANGELOG.md`; si cambió una decisión, crea un ADR en `DECISIONS.md`.
3. **Si cambia el esquema:** actualiza `DATABASE.md` y `grafo/modelo-datos.md` en el mismo cambio.
4. **Si cambia una ruta o parámetro:** actualiza `API.md`.
5. **Nunca marques un ítem como hecho sin criterio de aceptación cumplido.**

## Relación con `templatesOthersApp/`

`docs/templatesOthersApp/` es una **referencia externa** de otro sistema (ERP tributario). No describe SysTec. Se conserva como modelo de estructura y nivel de detalle. La documentación operativa de este proyecto empieza en este `README.md`.

## Advertencia

El directorio no tiene control de versiones. El estado vigente de la documentación refleja el árbol de trabajo, las migraciones y `storage/db.db` al **2026-11-09**, después de F2. Los anexos de inventario y trazabilidad preservan hallazgos del baseline F0/F1 y no sustituyen `TODO.md`. Si el código cambia sin actualizar `docs/`, la documentación queda obsoleta y debe tratarse como incidente documental.
