# Procedimiento de backup y restauración — SysTec GMB

> **Estado:** propuesta registrada; pendiente de aprobación de TI y no programada en el servidor real.
> **Actualizado:** 2026-11-09. **Zona horaria solicitada:** `America/Caracas`.
> Esta guía registra las respuestas recibidas y los controles pendientes antes de habilitar una tarea automática.

## 1. Parámetros recibidos (propuesta)

| Parámetro | Valor |
|---|---|
| Frecuencia | Diaria |
| Hora | 02:00, `America/Caracas` |
| Retención diaria | 30 copias |
| Retención semanal | 12 copias |
| Retención mensual | 12 copias |
| Selección semanal | Promover la última copia diaria de cada semana |
| Selección mensual | Promover la última copia diaria del mes |
| Contenido | `storage/db.db` y los CSV fuente `storage/*.csv` |
| Cifrado en reposo | Obligatorio |
| Método y custodio de claves | Pendiente de definición por TI |
| Prueba de restauración | Trimestral, en ubicación aislada |
| RPO objetivo | 24 horas |
| RTO objetivo | 1 día hábil |
| Mecanismo solicitado | `cron` en Linux |
| Destino propuesto | `code/bck/systec` (ruta lógica informada; falta confirmar ruta absoluta y ubicación física) |
| Destino fuera del host | Pendiente de asignación; `code/bck/systec` sigue sin confirmar si es absoluto o separado del host |
| Aprobación de TI | Propuesta; falta aprobación |

La frecuencia diaria solo cumple el RPO si la tarea termina correctamente cada día y TI recibe aviso ante fallos. Debe mantenerse al menos una copia cifrada en un sistema o medio que no dependa del mismo host de la aplicación.

## 2. Bloqueos antes de activar cron

No habilitar una copia automática hasta completar y verificar todos estos puntos:

1. **Destino:** asignar una ruta absoluta, de solo acceso para la cuenta de servicio, con espacio y permisos suficientes; confirmar si está en otro host o volumen independiente. `code/bck/systec` todavía no identifica si es una ruta local, remota o fuera del host.
2. **Cifrado:** TI debe elegir la herramienta/protocolo aprobado, asignar custodio y suplente, y documentar cómo se recupera la clave. La clave no debe estar en el repositorio, en el backup ni en una línea de comandos visible.
3. **Servidor:** confirmar el host Linux de producción, usuario que ejecutará `cron`, raíz real de la aplicación, `SYSTEC_STORAGE` y configuración de zona horaria.
4. **Rotación:** promover la última copia diaria de cada semana y del mes como copias semanal/mensual. Confirmar con TI la definición de semana y aplicar la selección solo entre copias exitosas, cifradas y verificadas.
5. **Custodia y alertas:** designar custodios de clave y quién revisa fallos diarios; definir canal de aviso y dónde se registra el resultado sin incluir datos personales, secretos ni contenido de la base.
6. **Aprobación:** la configuración sigue siendo propuesta y requiere aprobación de TI antes del despliegue.

Hasta cerrar estos puntos, el backup verificado de `storage/backups/2026-11-09/db-before-f2-normalization.db` es evidencia de recuperación de F2, no un servicio programado ni una copia operativa vigente garantizada.

## 3. Requisitos del proceso de backup

Al implementar el wrapper para cron:

1. Ejecutarlo con una cuenta de servicio de privilegio mínimo y `umask 077`.
2. Crear un snapshot consistente de SQLite usando la API de backup de SQLite o el mecanismo equivalente aprobado; no copiar `db.db` en caliente con una copia de archivos ordinaria.
3. Incluir la base y los CSV fuente en una carpeta identificada por fecha/hora local. Mantener la relación entre ambos bajo el mismo identificador.
4. Comprobar `PRAGMA integrity_check` sobre la copia temporal.
5. Cifrar la copia antes de trasladarla al destino definitivo. Si el cifrado, la verificación o la transferencia falla, conservar las copias anteriores y terminar con código de error.
6. Publicar la copia con renombrado atómico solo después de comprobar integridad y cifrado.
7. Aplicar retención únicamente después de verificar la nueva copia; conservar 30 diarias, 12 semanales y 12 mensuales. Promover la última copia exitosa de cada semana y mes, con la definición de semana que TI apruebe.
8. Emitir resultado de éxito/fallo al canal operativo que TI defina. No imprimir contraseñas, claves ni datos de la base en logs.

La expresión de horario prevista para cron es `0 2 * * *`, pero debe instalarse solo tras confirmar que el reloj/zona del host corresponde a `America/Caracas`. La programación, ruta, destino y método criptográfico aún no están activados.

## 4. Prueba trimestral de restauración

En cada prueba, TI debe:

1. Elegir una copia cifrada dentro de la retención acordada y restaurarla en un entorno aislado, nunca sobre la base productiva.
2. Recuperar la clave por el procedimiento institucional aprobado.
3. Descifrar y verificar `PRAGMA integrity_check = ok`.
4. Confirmar que la base abre y que los CSV están presentes y corresponden al mismo identificador/fecha.
5. Validar conteos y acceso funcional básico con datos de prueba, sin enviar la copia a servicios no autorizados.
6. Medir el tiempo desde la solicitud de recuperación hasta que la aplicación de prueba queda disponible; compararlo con el RTO de 1 día hábil.
7. Registrar fecha, identificador de copia, resultado, duración, responsable por cargo y acciones correctivas. No registrar claves ni hashes de contraseñas.

Si una prueba falla, abrir incidencia, conservar la copia fallida para diagnóstico seguro y no marcar el ciclo de respaldo como satisfactorio.

## 5. Criterio para cerrar F2-16

- [ ] TI asignó el destino absoluto y confirmó su separación del host (o aceptó y documentó el riesgo).
- [ ] TI aprobó herramienta de cifrado, custodios y recuperación de claves.
- [ ] TI confirmó la definición de semana para promoción y retención.
- [ ] Se confirmó host, usuario, ruta de almacenamiento y zona horaria.
- [ ] TI aprobó la política y el canal de alertas de fallos.
- [ ] El wrapper cifra, verifica integridad, devuelve errores y aplica retención después del éxito.
- [ ] El cron diario a las 02:00 está instalado y su ejecución se monitoriza.
- [ ] La primera restauración aislada trimestral se completó y el RPO/RTO se revisaron.

**Estado F2:** los parámetros están registrados como propuesta; el cron no puede habilitarse con seguridad mientras falten aprobación TI, destino fuera del host y método/custodia de cifrado.
