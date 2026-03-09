# 🎬 UNIT3D BÚNKER - Edición N.O.B.S

> **Un Tracker de Torrents Privado, Dockerizado y Curtido en Batalla**

```
███████████████████████████████████████████████████████████████
█                                                             █
█   🛡️  UNIT3D BÚNKER  |  Nuclear Order Bit Syndicate         █
█                                                             █
█   "From the Scene, For the Scene"                           █
█   100+ hours of stabilization, automation, and resilience   █
█                                                             █
███████████████████████████████████████████████████████████████
```


> **⚠️ AVISO PARA NAVEGANTES:** Si vas a tocar el stack, deja el café un segundo y lee. Entrar aquí sin pasar por la wiki es como intentar desarmar una bomba con palillos chinos. Bajo tu propio riesgo.

---

<p align="center">
  <a href="https://rawsmoketerribilus.github.io/UNIT3D/">
    <img src="https://img.shields.io/badge/📖_WIKI_Y_MANUAL-ESTADO:_ONLINE-brightgreen?style=for-the-badge&logo=gitbook&logoColor=white" alt="Manual Online">
  </a>
  <a href="https://github.com/rawsmoketerribilus/UNIT3D/actions">
    <img src="https://img.shields.io/badge/BOT_DEPLOY-ESTADO:_OPERATIVO-blue?style=for-the-badge&logo=github-actions&logoColor=white" alt="Bot Status">
  </a>
</p>

---

### 📚 La Biblia de Operaciones
Todo lo que necesitas para que el tracker no explote está en nuestra Wiki oficial:

👉 **[ACCEDER AL MANUAL COMPLETO](https://rawsmoketerribilus.github.io/UNIT3D/)**

**¿Qué encontrarás ahí dentro?**
* 🛠️ **Configuración del Entorno:** Cómo domar los contenedores sin morir en el intento.
* 💾 **Backups Blindados:** El sistema de snapshots que nos salva el culo a las 06:00 AM.
* 📑 **Guía de Desarrollo:** Para que el código nuevo no parezca escrito por el becario.
* 🏗️ **Testing:** Cómo montar un laboratorio de pruebas que no pese 22GB.

---

---

## 📚 ¿Qué es UNIT3D?

**[UNIT3D](https://github.com/HDInnovations/UNIT3D)** es un software moderno y rico en funciones para Trackers de Torrents Privados, construido sobre **Laravel 12**, **Livewire** y **AlpineJS**. Creado por el equipo de HDInnovations, impulsa comunidades de trackers privados de alto rendimiento con soporte para:

- 🔐 **Gestión Avanzada de Usuarios**: Roles, permisos, invitaciones, logros
- 🔍 **Integración con Meilisearch**: Búsqueda en milisegundos a través de millones de torrents
- 📊 **Analíticas Completas**: Estadísticas de torrents, actividad de usuarios, ratios de seedeo
- 🎨 **Sistema de Temas**: UI personalizable con Sass/CSS
- 📧 **Notificaciones por Correo**: Integración SMTP, alertas de actividad
- 🔗 **Integración IRC**: Anuncios en vivo e integración de bots
- 🌍 **Internacionalización**: Soporte para múltiples idiomas

### **Un Gran Agradecimiento a HDInnovations** ❤️

Este proyecto no existiría sin UNIT3D. Los desarrolladores originales crearon una plataforma increíble para comunidades de trackers privados. [**→ Visita el GitHub de UNIT3D**](https://github.com/HDInnovations/UNIT3D)

---

## 🔧 ¿Por qué N.O.B.S? Lo que Construimos

UNIT3D es una **plataforma brillante**, pero llega como código fuente, no como una implementación empaquetada. Tomamos la Edición Comunitaria e hicimos dos cosas:

### **Parte 1: Arreglamos las Piezas Rotas de UNIT3D**

La Edición Comunitaria tenía **bugs sin corregir y funciones faltantes**:

| Problema | Impacto | Nuestra Solución |
|---|---|---|
| **Instalador Eliminado** | El script de instalación oficial fue eliminado por los desarrolladores; dejado en un estado roto | Reimplementamos la lógica de configuración en `entrypoint.sh` (ejecución automática de migraciones, listas negras, caché) |
| **Meilisearch sin Configurar** | El motor de búsqueda se incluía pero no se indexaba ni sincronizaba | Implementamos indexación en arranque en frío, sincronización con observadores en tiempo real y protección con Llave Maestra |
| **Fuerza Bruta Demasiado Agresiva** | La configuración bloqueaba a usuarios legítimos (5 intentos = bloqueo de 24h) | Ajustamos FortifyServiceProvider (5→15 intentos, 24h→1h, creamos propietario de respaldo) |
| **Fragilidad de la Lista Negra de Correos** | El sistema se rompía si el CDN externo no era accesible | Creamos una caché local persistente (`storage/app/email-blacklist.json`) con un sistema de respaldo híbrido |

---

### **Parte 2: Lo Dockerizamos (No es una Tarea Trivial)**

El UNIT3D original **no es nativo de Docker**. Construimos la contenedorización completa:

| Desafío | Solución |
|---|---|
| **Servicios en Segundo Plano Faltantes** | Añadimos contenedores `scheduler` y `worker` con entrypoints dedicados |
| **Enmascaramiento de Direcciones IP** | Configuramos cabeceras de proxy inverso en Nginx + TrustProxies de Laravel (IPs reales en perfiles) |
| **Caos de Permisos en Contenedores** | Autoreparación en `entrypoint.sh` (chmod 775, chown www-data en el arranque) |
| **Enlace de Almacenamiento en Docker** | Configuramos montajes de volúmenes persistentes con los enlaces simbólicos correctos |
| **Sin Persistencia de Dependencias** | Incluimos `vendor/` y `node_modules/` en el repositorio (recuperación Plug & Play sin conexión) |

---

### **Parte 3: Añadimos Resiliencia (La Filosofía "Búnker")**

Más allá de arreglar y dockerizar, añadimos **características autónomas y orientadas al funcionamiento sin conexión**:

| Característica | Beneficio |
|---|---|
| **Estrategia de Backup en Frío** | Detener contenedores → copiar → reiniciar (cero corrupción, integridad de datos garantizada) |
| **Automatización de Health Check** | Monitoriza el puerto 8008, Meilisearch, MySQL, Redis; alerta en caso de fallo |
| **Entrypoints de Autoreparación** | Apagar/encender → todo funciona (sin intervención manual) |
| **Control con Makefile** | `make up`, `make backup`, `make health` (operaciones simples, curva de aprendizaje cero) |

**Resultado**: Un sistema listo para producción, autónomo y diseñado para **comunidades que gestionan su propia infraestructura**.

---

## 🚀 Mejoras Clave

### 1. **🔍 Meilisearch: Búsqueda Instantánea y Resiliente**

**El Desafío**: UNIT3D incluye Meilisearch como su motor de búsqueda, pero **no proporciona documentación ni configuración**. La instalación y configuración quedan a cargo del operador.

**Nuestra Solución**:

```
🏗️ INFRAESTRUCTURA:
  • Contenedor dedicado (getmeili/meilisearch:latest) en docker-compose.yml
  • Almacenamiento persistente de índices (volumen de Docker meilisearch-data)
  • Protección con Llave Maestra (MEILISEARCH_KEY en .env, nunca registrada en logs)

🔄 INICIALIZACIÓN:
  • Indexación en arranque en frío: entrypoint.sh ejecuta php artisan scout:import
  • Si faltan los índices, el sistema los reconstruye al arrancar (autoreparación)
  • Configuración: app/Http/Scout config mapea Torrent → Meilisearch

⚡ SINCRONIZACIÓN EN TIEMPO REAL:
  • Observadores de Laravel escuchan por torrents nuevos o actualizados
  • Indexación instantánea (milisegundos) a medida que los usuarios suben
  • Enriquecimiento de metadatos de TMDB/IGDB (pósters, géneros, valoraciones)

🛡️ RESILIENCIA:
  • Los índices sobreviven a los reinicios de los contenedores (persisten en el volumen)
  • Consulta de respaldo a MySQL si Meilisearch no está disponible
  • El Health Check monitoriza el endpoint /health
```

**Por qué es importante**: Buscar en más de 50,000 torrents toma **milisegundos** en lugar de segundos. La base de datos se mantiene ligera. Los usuarios obtienen resultados instantáneos y filtrados.

---

### 2. **📧 Lista Negra de Correos Resiliente**

**El Problema**: UNIT3D obtiene dominios de correo desechables de un CDN externo durante la validación del registro. **Si el CDN está caído o inaccesible, los registros fallan por completo.**

**Nuestra Solución - Estrategia de Lista Negra Híbrida**:

```
PRIMARIO (Online):
  ✅ Obtiene una lista actualizada del CDN (andreis/disposable-emails)
  ✅ Actualiza una vez al arrancar (php artisan auto:email-blacklist-update)

RESPALDO (Offline):
  ✅ Almacena una copia local: storage/app/email-blacklist.json
  ✅ Más de 7,160 dominios persistidos localmente
  ✅ Si el CDN no es accesible, usa la caché local (el registro sigue funcionando)

PERSISTENCIA:
  ✅ La caché sobrevive a los reinicios de los contenedores
  ✅ La caché sobrevive a `docker compose down/up`
  ✅ La caché se incluye en los backups completos
```

**Detalles de Implementación**:
- Se creó `app/Helpers/EmailBlacklistUpdater.php` (lógica de actualización automática)
- El entrypoint ejecuta `php artisan auto:email-blacklist-update` al arrancar
- Un comando de Artisan personalizado vigila el CDN y escribe en el JSON local
- El registro usa la caché local como principal (más rápido, fiable)

**Resultado**: El registro funciona **incluso si el CDN está caído**. El sistema es autónomo y capaz de funcionar sin conexión.

---

### 3. **🌐 Transparencia de Direcciones IP (Redes de Docker)**

**El Problema**: En Docker, Nginx y la aplicación Laravel se ejecutan en contenedores separados. Sin las cabeceras adecuadas, todas las peticiones parecen provenir de la puerta de enlace interna de Docker (`172.21.0.1`). **Todos los usuarios muestran la misma IP en sus perfiles.**

**Nuestra Solución - Cabeceras de Proxy Inverso + TrustProxies**:

```
CAPA DE NGINX (.docker/nginx/default.conf):
  • proxy_set_header X-Real-IP $remote_addr;
  • proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  • proxy_set_header X-Forwarded-Proto $scheme;

CAPA DE LARAVEL (app/Http/Middleware/TrustProxies.php):
  • protected $proxies = '*';  [Confiar en Nginx como proxy inverso]
  • Lee la cabecera X-Real-IP y la usa como la IP de origen del usuario

RESULTADO:
  ✅ IPs reales de los usuarios capturadas en la base de datos
  ✅ Cada usuario ve su IP pública real en su perfil
  ✅ El baneo y las estadísticas basadas en IP funcionan correctamente
```

**Verificación**: Inicia sesión, visita tu perfil → verás tu IP pública real, no la puerta de enlace de Docker.

---

### 4. **🔒 Protección contra Fuerza Bruta: Equilibrio entre Seguridad y Usabilidad**

**El Problema**: La configuración por defecto de Fortify en UNIT3D era **demasiado agresiva**:
- 5 inicios de sesión fallidos → bloqueado por 24 horas
- IP de puerta de enlace compartida única (172.21.0.1 en Docker) → usuarios legítimos bloqueados juntos
- Resultado: **Los desarrolladores se bloqueaban a sí mismos durante las pruebas/recuperación**

**Nuestro Ajuste** (`app/Providers/FortifyServiceProvider.php`):

```php
// Antes (demasiado estricto):
RateLimiter::for('login', 5 intentos por minuto);      // 5 fallos = bloqueo
$throttleKey = hashless unique attempt;

// Después (equilibrado):
RateLimiter::for('login', 15 intentos por minuto);     // 15 fallos = bloqueo
RateLimiter::for('two-factor', 6 intentos por minuto); // 2FA más indulgente
Duración del bloqueo: 24h → 1h                          // Recuperación más rápida
Verificación de multi-cuenta: umbral de 1 → 3           // Permite cambiar de cuenta
```

**Seguridad Adicional**:
- Se creó la cuenta `BackupOwner` con permisos completos (acceso de emergencia)
- Se puede usar la cuenta de respaldo si la principal está bloqueada
- Los logs rastrean los intentos fallidos para investigar ataques reales

**Resultado**: El sistema protege contra la fuerza bruta **mientras permite la recuperación y pruebas legítimas**.

---

### 5. **🛡️ Infraestructura Autónoma (El "Búnker")**

#### **Autoreparación al Iniciar**

Cada arranque de contenedor desencadena una recuperación automática:

```bash
# .docker/entrypoint.sh hace lo siguiente:
✅ Copia .env.example → .env (si .env no existe)
✅ composer install (si vendor/ no existe)
✅ npm install + build (si public/build/ no existe)
✅ Crea carpetas de almacenamiento
✅ Arregla permisos (chmod 775, chown www-data)
✅ Espera a MySQL
✅ Genera APP_KEY (si no existe)
✅ Ejecuta migraciones (--force)
✅ Actualiza la lista negra de correos
✅ Inicia PHP-FPM
```

**Resultado**: Apaga el servidor, enciéndelo → todo funciona. Sin intervención manual.

---

#### **Backup en Frío (Snapshot Quirúrgico)**

**Filosofía**: Los backups deben ser **a prueba de corrupción, completos y capaces de funcionar sin conexión**.

```bash
Flujo de trabajo de ./backup.sh:

1. 💾 Volcado de MySQL (volcado en caliente, --no-tablespaces para MySQL 8)
   └─ Captura el estado de la base de datos sin problemas de bloqueo

2. 🛑 Congelación de Contenedores (docker compose stop)
   └─ Detiene todos los contenedores para un snapshot de archivos consistente

3. 📦 Snapshot de Imágenes (docker save)
   └─ Exporta todas las imágenes de Docker (php:8.4, mysql:8.0, redis, meilisearch)
   └─ Se usa para la reconstrucción sin conexión si Docker Hub no está disponible

4. 📂 Archivo Completo (tar -czf)
   └─ Comprime: código de la aplicación, vendor/, node_modules/, configuraciones, datos
   └─ Incluye todo lo necesario para una recuperación completa sin conexión

5. ♻️ Rotación (mantiene los últimos 3 snapshots)
   └─ Evita que el disco se llene, mantiene backups recientes

6. 🚀 Resurrección (docker compose up)
   └─ Verifica que el backup se haya realizado con éxito
   └─ Reinicia el sistema inmediatamente (minimiza el tiempo de inactividad)
```

**¿Por qué "quirúrgico"?**:
- ✅ **Sin corrupción**: Detener los contenedores asegura la consistencia de los archivos durante la copia
- ✅ **Plug & Play**: Se incluyen `vendor/` y `node_modules/` completos
- ✅ **Recuperación sin conexión**: Imágenes de Docker + todas las dependencias = funciona en cualquier lugar
- ✅ **Atómico**: Snapshot completo en un único punto en el tiempo

---

#### **Health Checks (Chequeos de Salud)**

```bash
make health  # Ejecuta ./health_check.sh

Chequeos:
✅ El puerto 8008 responde con HTTP 200
✅ Endpoint /health de Meilisearch
✅ Conectividad con MySQL
✅ Conectividad con Redis
✅ El worker de la cola está vivo
✅ El scheduler se está ejecutando

Si alguno falla: Alertas + puede reiniciar automáticamente
```

---

### 6. **🎨 Branding de N.O.B.S (Tema Personalizado)**

UNIT3D viene con un tema por defecto. Creamos una identidad personalizada de N.O.B.S:

- **Tema SCSS Personalizado**: `resources/sass/themes/_refined-nobs.scss`
  - Estética neón cian/rosa
  - Paneles de glassmorphism con efectos de desenfoque
  - Tipografía industrial y de bloques

- **Personalización de Activos**:
  - **Favicon**: Icono de medalla de NOBS personalizado de 64x64
  - **Logo**: Marca de NOBS en las páginas de inicio de sesión/registro
  - **Imagen OG**: Imagen para compartir en redes sociales
  - **Páginas de Autenticación**: Fondos y estilos personalizados

- **Extensibilidad Fácil**:
  - Todos los estilos en Sass (variables temáticas)
  - Compilado con Vite (`npm run build`)
  - Cambia de tema a través del panel de administración o `config/other.php`

Esto **no es un cambio en el núcleo de UNIT3D** — es una piel personalizada que respeta la plataforma original.

---

### 7. **⚙️ Ajustes de Configuración**

Optimizaciones en **config/other.php**:
- Tiempo de espera para invitaciones: 24h → 1h (después de la activación de 2FA)
- Máximo de invitaciones sin usar por usuario: 1 → 10 (amigable para el staff)
- Subtítulo del sitio: Contextualizado para N.O.B.S
- Correo de respaldo: Valor por defecto seguro si falta en .env

**Endurecimiento de la seguridad**:
- `SESSION_SECURE_COOKIE=true` (solo HTTPS)
- `SESSION_DOMAIN=nobs.rawsmoke.net` (dominio explícito)
- `TRUSTED_PROXIES=*` (para cadenas de proxy inverso)

---

## 📦 Dos Rutas de Instalación

### **🚀 Ruta A: Instalación Fresca (Nuevo Tracker)**

Para un tracker completamente nuevo en una máquina limpia:

```bash
# 1. Clonar
git clone https://github.com/RawSmokeTerribilus/UNIT3D_Docker.git
cd UNIT3D_Docker

# 2. Configurar
cp .env.example .env
# Edita .env con tu configuración:
#   - APP_URL, ANNOUNCE_URL
#   - Credenciales de la BD
#   - Ajustes de MAIL_*
#   - MEILISEARCH_KEY
#   - TMDB_API_KEY (opcional)

# 3. Instalar
make install

# 4. Sembrar datos iniciales (opcional)
docker compose exec app php artisan db:seed
docker compose exec app php artisan scout:import

# 5. Acceder
# Web: http://localhost:8008
# Login: UNIT3D / UNIT3D (del seeder)
```

**Qué hace `make install`**:
- Crea directorios `storage/framework`
- Establece permisos (775 en `storage/`, `bootstrap/cache/`)
- Construye las imágenes de Docker
- Inicia todos los contenedores
- El entrypoint gestiona automáticamente `composer`/`npm`/migraciones

---

### **📀 Ruta B: Restaurar desde un Backup (Recuperación de Desastres)**

Si tu tracker se cae o te estás mudando a un nuevo servidor:

```bash
# 1. Ten tu backup
ls -lh backups/snapshot_*/unit3d_full_snapshot_*.tar.gz

# 2. En el nuevo host, extrae
mkdir -p /home/rawserver/UNIT3D_Docker
tar -xzf backup.tar.gz -C /home/rawserver/UNIT3D_Docker

# 3. Inicia los contenedores
cd /home/rawserver/UNIT3D_Docker
make up

# 4. Espera a que MySQL arranque
sleep 10

# 5. Restaura la base de datos
docker exec -i unit3d-db mysql -u unit3d -punit3d unit3d < db_unit3d.sql

# 6. Reinicia la capa de la aplicación
make restart

# 7. Verifica
make health
```

**Por qué funciona esto**:
- El backup incluye todo: código fuente, `vendor/`, `node_modules/`, configuraciones
- El volcado de la base de datos está incluido
- Las imágenes de Docker están incluidas (puede funcionar sin conexión)
- No es necesario descargar nada; es completamente autocontenido

---

## 🛠️ Gestión: El Makefile

```bash
make help        # Muestra todos los comandos
make up          # Inicia los contenedores (modo daemon)
make stop        # Detiene los contenedores
make restart     # Reinicia la app + web (después de cambios en el código)
make status      # Muestra el estado de los contenedores
make backup      # Ejecuta el backup quirúrgico
make health      # Ejecuta los chequeos de salud
make logs        # Muestra los logs de la app en vivo
make clean       # Limpia las cachés de Laravel (config, rutas, vistas)
```

---

## 📊 Arquitectura

```
┌──────────────────────────────────────────────────┐
│                     NGINX (Puerto 8008)          │
│                (Proxy Inverso + Estáticos)       │
└────────────┬─────────────────────────────────────┘
             │
      ┌──────▼──────┐
      │   PHP-FPM   │ (App de Laravel)
      │ (Puerto 9000)│
      └──────┬──────┘
             │
   ┌─────────┼─────────┬────────────┬──────────────┐
   │         │         │            │              │
┌──▼──┐   ┌──▼──┐   ┌───▼────┐   ┌───▼────┐   ┌───▼──┐
│MySQL│   │Redis│   │Meili   │   │Mailpit │   │Worker│
│8.0  │   │     │   │search  │   │(Buzón) │   │Cola  │
└─────┘   └─────┘   └────────┘   └────────┘   └──────┘

Scheduler: Ejecuta php artisan schedule:work (cron en segundo plano)
Worker: Ejecuta php artisan queue:work (trabajos en segundo plano)
```

---

## ⚙️ Mapeo de Puertos

| Servicio | Interno | Externo | Propósito |
|---|---|---|---|
| Nginx | 80 | 8008 | UI Web |
| PHP-FPM | 9000 | — | Entorno de ejecución de la app |
| MySQL | 3306 | 3307 | Base de datos |
| Redis | 6379 | 6380 | Caché/Sesiones/Cola |
| Meilisearch | 7700 | 7701 | Motor de Búsqueda |
| Mailpit | 1025/8025 | 8026 | Pruebas de Correo |

---

## 🔐 Notas de Seguridad

### Variables de Entorno (.env)

**Mantén esto a salvo:**
- `APP_KEY` — Clave de encriptación de Laravel (generada en la instalación)
- `MAIL_PASSWORD` — Credenciales SMTP
- `MEILISEARCH_KEY` — Llave Maestra del motor de búsqueda
- `TMDB_API_KEY` — Acceso a API de terceros

**Nunca comitees `.env`** al control de versiones. Usa `.env.example` como plantilla.

### Configuraciones Endurecidas

- Las sesiones son solo HTTPS (`SESSION_SECURE_COOKIE=true`)
- El dominio de la sesión es explícito (`SESSION_DOMAIN=tu-dominio`)
- La protección contra fuerza bruta está ajustada para evitar bloqueos
- Las direcciones IP se reenvían correctamente (sin exposición de la puerta de enlace de Docker)

---

## 📖 Solución de Problemas

### **Error 500 / Permiso Denegado**

```bash
# Se arregla automáticamente al reiniciar, pero para forzar:
docker compose restart app
docker exec unit3d-app chmod -R 775 storage bootstrap/cache
docker exec unit3d-app chown -R www-data:www-data storage bootstrap/cache
```

### **La búsqueda no funciona / Sin resultados**

```bash
# Reindexar Meilisearch
docker compose exec app php artisan scout:import

# Verificar salud
make health
```

### **El correo no se envía**

```bash
# Revisa el panel de Mailpit (si usas pruebas locales)
# Abre: http://localhost:8026

# Si usas SMTP:
docker compose logs app | grep -i mail

# Probar vía Tinker
docker compose exec app php artisan tinker
# >>> Mail::raw('Test', fn($m) => $m->to('test@example.com')->send());
```

### **Base de datos bloqueada / Problemas con MySQL**

```bash
# Revisa los logs de MySQL
docker compose logs db

# Si está corrupta, restaura desde el backup
# Ver "Ruta B: Restaurar desde un Backup" arriba
```

---

## 🎯 Filosofía: "De la Scene, Para la Scene"

Este proyecto refleja más de 100 horas de trabajo para resucitar UNIT3D de su estado roto en la edición comunitaria. Cada arreglo, cada automatización, cada redundancia existe porque **creemos en la plataforma**.

- **Primero sin conexión**: Funciona de forma completamente autónoma (sin dependencias en la nube)
- **Resiliente**: Se autorepara de fallos comunes (permisos, carpetas faltantes, tiempos de espera de red)
- **Transparente**: Los cambios están documentados y justificados (ver este README)
- **Mantenible**: Makefile simple + scripts que cualquiera puede entender
- **Peer-to-peer**: Diseñado para comunidades que gestionan su propia infraestructura

Este es un software de tracker **para gente que gestiona trackers**, no un producto SaaS con dependencia de un proveedor.

---

## 📝 Contribuciones

¿Encontraste un bug? ¿Tienes una mejora? ¡Los Issues y PRs son bienvenidos!

Este es un fork comunitario. Estamos mejorando UNIT3D en beneficio de los operadores de trackers privados de todo el mundo.

---

## 📜 Licencia

UNIT3D está licenciado bajo la GNU Affero General Public License v3.0. Ver [LICENSE.txt](./LICENSE.txt).

Este fork mantiene la misma licencia y espíritu: abierto, transparente e impulsado por la comunidad.

---

## ❤️ Agradecimientos

- **HDInnovations** por crear UNIT3D
- **La escena de trackers privados** por décadas de innovación y construcción de comunidades
- **El equipo de N.O.B.S** por las 100 horas que tomó hacer que esto funcionara

---

**Última Actualización**: Marzo 2026 | **Estado**: 🟢 Listo para Producción

```
Hecho con resiliencia y cuidado.
De la scene. Para la scene.
```
