# Reporte de Auditoría: Diagnóstico UNIT3D (Instancia B - Destino)

Este reporte resume los hallazgos tras la auditoría técnica realizada para identificar los fallos críticos en la **Instancia B (Destino)** en comparación con la instancia de referencia funcional **Instancia A (Referencia)**.

## 1. Resumen de Implementación
Se ha generado un manual de configuración detallado (`Config_Manual/manual.md`) y se han organizado los archivos de referencia en subdirectorios temáticos (`punto-1` a `punto-6`) para facilitar la comparación y corrección en el entorno de destino.

## 2. Hallazgos Críticos

### A. Crash del "Dupe Check" (Error 500)
- **Causa Raíz**: Desincronización de código (Code Desync).
- **Detalle**: El log reveló un error de `Unknown named parameter $genreIds`. Esto indica que el controlador de la API (`TorrentController.php`) fue actualizado para enviar parámetros que el constructor de `TorrentSearchFiltersDTO.php` no reconoce en la Instancia B.
- **Solución**: Asegurar que ambos archivos estén en la misma versión (la proporcionada en la carpeta `punto-1`).

### B. Conexiones de Pares y Estadísticas a Cero
- **Causa Raíz**: Inestabilidad Crítica de Redis.
- **Detalle**: Los logs muestran múltiples fallos de Redis (`Connection refused` y `Redis server went away`). 
- **Impacto**: 
    - El middleware de Announce requiere Redis para el throttling; si falla, el cliente recibe un error de red (Not Found/Fallo conexión).
    - El Scheduler de Laravel falla al intentar gestionar los bloqueos de tareas en Redis, impidiendo que `AutoUpsertPeers` sincronice los datos con MySQL.
- **Solución**: Revisar el servicio Redis en el servidor de destino (recursos de RAM, límites de conexiones y persistencia).

### C. El "Iceberg" de los Metadatos (TMDB, IMDB, Portadas)
- **Problema**: Mensaje "No meta found" y falta de carátulas en la Instancia B.
- **Causa Raíz Principal**: Fallo en la tubería de Jobs por inestabilidad de Redis. Al fallar Redis, el `RateLimiter` bloquea la ejecución de `ProcessMovieJob` y `ProcessTvJob`.
- **Causas Secundarias**: Posible falta de `TMDB_API_KEY` o colas de trabajo (workers) no activas.
- **Solución**: Reparar Redis primero, verificar conectividad con TMDB y asegurar que los workers están procesando la cola `default`.
- **Referencia**: Documentado en el Punto 6 del manual, con archivos de lógica en la carpeta `punto-6`.

## 3. Estructura de Entrega
La documentación final se encuentra en:
- `Config_Manual/manual.md`: Manual exhaustivo (Puntos 1 al 6).
- `Config_Manual/punto-1/`: Dupe Check / API.
- `Config_Manual/punto-2/`: Announce / Redis.
- `Config_Manual/punto-3/`: Redes / Infraestructura.
- `Config_Manual/punto-4/`: Configuración Base / Workers.
- `Config_Manual/punto-5/`: Real IP / Preservación.
- `Config_Manual/punto-6/`: Metadatos / TMDB.

## 4. Desafíos Encontrados
El principal desafío fue diagnosticar fallos de infraestructura (Redis) a partir de stacktraces de Laravel, confirmando que no se trata de errores de lógica de la aplicación UNIT3D, sino de un entorno de ejecución inestable en el servidor de destino.
