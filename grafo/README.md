# grafo/ — Lógica de negocio de SysTec GMB

> **Estado:** v1.2 (post-F2) · **Actualizado:** 2026-11-09 · **Fuente:** código, migraciones y datos actuales en `storage/db.db`.
> Este directorio responde una sola pregunta: **¿cómo funciona el negocio hoy, tal como está implementado?** No describe el sistema deseado; eso vive en `docs/`.

## Archivos

| Archivo | Responde | Formato |
|---|---|---|
| `logica-negocio.md` | Actores, capacidades, flujos, reglas, estados y puntos de fallo | Markdown + Mermaid |
| `modelo-datos.md` | Entidades, relaciones y ciclo de vida del dato | Markdown + Mermaid |
| `grafo.json` | Grafo legible por máquina (nodos/aristas) para análisis o herramientas | JSON |

## Cómo leer el grafo

1. Empieza por `logica-negocio.md` §Mapa de capacidades.
2. Baja a los flujos de importación, mantenimiento y reportes.
3. Contrasta cada regla con `docs/DOMAIN.md` (idioma ubicuo) y `docs/DATABASE.md` (persistencia).
4. Usa `grafo.json` si necesitas recorrer dependencias de forma programática.

## Leyenda

| Forma | Significado |
|---|---|
| Rectángulo | Proceso o pantalla |
| Cilindro | Almacén de datos (SQLite) |
| Rombo | Decisión o regla |
| Actor | Persona que opera el sistema |
| Línea continua | Flujo principal |
| Línea punteada | Dependencia implícita, no validada por el sistema |

## Convención de hallazgos

Los identificadores `H-xx` usados aquí son los mismos de `docs/anexos/matriz-trazabilidad.md` y `docs/TODO.md`. Esto permite pasar del grafo a una tarea concreta sin reanalizar el código.

## Advertencia de vigencia

El directorio no tiene control de versiones. El grafo refleja el estado de `storage/db.db` al **2026-11-09**, después de F2. Si cambia el esquema o los estados sin actualizar `docs/`, el grafo queda obsoleto: trátalo como documentación viva, no como verdad eterna.
