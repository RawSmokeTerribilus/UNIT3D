# Análisis Técnico: Stack de Notificaciones Telegram - UNIT3D

**Fecha**: 25 de marzo 2026 | **Objetivo**: Deployment seguro en producción  
**Versión Laravel**: 11.x | **Queue Driver**: Redis | **HTTP Client**: Guzzle (built-in)

---

## 1. DATABASE MIGRATION
📄 **Archivo**: [database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php](database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php)

### Propósito
Añade capacidad de Telegram al modelo `User`. Permite:
- Almacenar identificador único del chat de Telegram (vinculación 1:1)
- Generar tokens temporales para vincular cuentas de forma segura

### Campos Agregados
```sql
ALTER TABLE users ADD (
  telegram_chat_id BIGINT UNSIGNED UNIQUE NULL,
  telegram_token VARCHAR(64) UNIQUE NULL
);
```

### Dependencias Externas
- **Ninguna**. Solo esquema SQL nativo.

### Configuración Requerida
- ✅ Base de datos MySQL 8.0+ (ya existente en docker-compose.yml)
- ✅ Acceso de escritura al esquema de `users`

### Orden de Ejecución
```
0. Punto de partida (CRÍTICO)
   ↓
   Aplicar ESTA migración
   ↓
1. EventServiceProvider.php (registra el Observer)
2. TorrentObserver.php (escucha eventos del modelo Torrent)
3. config/services.php (variables Telegram)
4. Demás servicios
```

**Ejecución**:
```bash
php artisan migrate --path=database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php
# O en deploy:
php artisan migrate
```

### Puntos Críticos en Producción
- ⚠️ **Índice UNIQUE en `telegram_chat_id`**: Evita duplicados pero bloquea si hay NULL duplicados en algunas DBs. MySQL lo maneja bien.
- ⚠️ **Índice UNIQUE en `telegram_token`**: Revoca token después de uso (lo hace NULL). Riesgo: race condition si dos requests vinculan simultáneamente.
- ⚠️ **REVERSIÓN**: `down()` elimina las columnas. En prod, usar `down()` solo si hay rollback crítico.
- 🔐 **No hay hash del token**: Se almacena en plaintext. Considerar hash bcrypt si se expone en logs.

---

## 2. EVENT SERVICE PROVIDER
📄 **Archivo**: [app/Providers/EventServiceProvider.php](app/Providers/EventServiceProvider.php)

### Propósito
Registra listeners de eventos y observadores. **Punto de conexión crítico para el flujo Telegram**.

### Cambios Específicos
```php
// Boot method - línea 53
\App\Models\Torrent::observe(\App\Observers\TorrentObserver::class);
```

**¿Qué hace?**: Aunque no hay listener en `$listen`, el `boot()` registra Observer en Torrent.

**Flujo**:
```
Torrent::update() 
  → TorrentObserver::updated() dispara
  → SendTelegramNotification::dispatch($torrent) 
  → Job entra en queue (Redis)
```

### Dependencias Externas
- `App\Models\Torrent` (debe existir)
- `App\Observers\TorrentObserver` (debe estar implementado)

### Configuración Requerida
- ✅ `config('queue.default')` = `redis` (verificar en config/queue.php)
- ✅ QUEUE_CONNECTION=redis en .env

### Orden de Ejecución
```
Después de migración:
  1. ✅ Migración aplicada (usuarios con campos Telegram)
  2. ServiceProvider booteado (auto-load por Laravel)
  3. Observer listo para escuchar Torrent::update()
```

### Puntos Críticos en Producción
- ⚠️ **Observador está en boot()**. Se ejecuta en CADA request. Impacto mínimo pero verificar performance con profiler.
- ⚠️ **No hay validación de existencia**: Si TorrentObserver.php no existe → Error fatal.
- ⚠️ **Falta dispatcher de jobs verificación**: Confirmar que `SendTelegramNotification::dispatch()` está registrado correctamente (usa Dispatchable).
- 🔍 **Debug**: Si notificaciones no envían, verificar logs en `storage/logs/` y estado de Redis.

---

## 3. TORRENT OBSERVER
📄 **Archivo**: [app/Observers/TorrentObserver.php](app/Observers/TorrentObserver.php)

### Propósito
**Disparador de notificaciones Telegram**. Escucha cambios en el modelo `Torrent` y encolada el job de notificación.

### Lógica Exacta
```php
public function updated(Torrent $torrent): void {
    if (
        $torrent->wasChanged('status')           // Campo 'status' cambió
        && $torrent->status === ModerationStatus::APPROVED  // Nuevo valor: APROBADO
        && $torrent->getOriginal('status') !== ModerationStatus::APPROVED->value  // Antes: NO era APROBADO
    ) {
        SendTelegramNotification::dispatch($torrent);  // Encola el job
    }
}
```

**Triggers**: Solo cuando `status` pasa a `APPROVED` (ej: `PENDING → APPROVED`)

### Dependencias Externas
- `App\Enums\ModerationStatus` (enum que define estados de moderación)
- `App\Models\Torrent` (modelo existente)
- `App\Jobs\SendTelegramNotification` (implementado)

### Configuración Requerida
- ✅ Campo `status` en tabla `torrents` debe existir
- ✅ Enum `ModerationStatus` debe tener valor `APPROVED`
- ✅ Queue driver activo (Redis)

### Orden de Ejecución
```
EventServiceProvider::boot() registra este observer
  ↓
Cuando actualiza Torrent con status = APPROVED
  ↓
Dispara SendTelegramNotification::dispatch($torrent)
  ↓
Job entra en cola Redis
  ↓
Worker procesa: worker/scheduler lee cola → ejecuta handle()
```

### Puntos Críticos en Producción
- ⚠️ **Múltiples actualizaciones del mismo torrent** → múltiples notificaciones. Implementar idempotencia:
  ```php
  // Riesgo: actualizar status 2 veces en transacción dispara 2 jobs
  // Mitigación: usar transacciones + flag "notified"
  ```

- ⚠️ **Loop infinito potencial**: Si SendTelegramNotification actualiza Torrent → trigger nuevamente. Verificar que el job NO modifica `status`.

- ⚠️ **wasChanged() vs getOriginal()**: Comportamiento diferente en:
  - Transacciones (puede ser impredecible)
  - Actualizaciones batch (no triggeran observer)
  - Raw queries (se evita observer)

- ⚠️ **Enum casting**: Asegurar que `status` está castear a `ModerationStatus` en modelo Torrent:
  ```php
  protected $casts = [
      'status' => ModerationStatus::class,
  ];
  ```

---

## 4. TELEGRAM SERVICE
📄 **Archivo**: [app/Services/TelegramService.php](app/Services/TelegramService.php)

### Propósito
**Servicio genérico de comunicación con API Telegram Bot**. Expone 3 funciones:
1. `sendAnnouncement()` - Envía foto + caption o solo texto
2. `sendMessage()` - Envía mensaje de texto
3. `kickUser()` - Expulsa usuario del grupo

### Configuración Leída
```php
config('services.telegram.bot_token')  // Ej: "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
config('services.telegram.group_id')   // Ej: "-1001234567890" (grupo negativo)
```

### Métodos Críticos

#### sendAnnouncement($text, $photoUrl, $threadId)
- Usa `sendPhoto` si hay foto, sino `sendMessage`
- **Parse mode**: HTML (no Markdown)
- **Caption crop**: Si caption > 1024 chars se trunca

#### sendMessage($text, $chatId, $threadId)
- Punto por defecto al group_id si no se proporciona chatId
- **Validación**: Retorna `false` si token/chat_id vacíos (log warning)
- **Error handling**: Try-catch con logging de response body

#### kickUser($chatId)
- Usa endpoint `/kickChatMember` (deprecated en Telegram API v7.0, usar `/banChatMember`)
- `revoke_messages: true` elimina mensajes del usuario expulsado

### Dependencias Externas
- **Illuminate\Support\Facades\Http** (cliente HTTP nativo de Laravel)
- **Illuminate\Support\Facades\Log** (logging)
- **API Telegram Bot**: https://api.telegram.org/bot{TOKEN}/{METHOD}

### Configuración Requerida
En `config/services.php`:
```php
'telegram' => [
    'token'    => env('TELEGRAM_BOT_TOKEN'),      // CRÍTICO
    'chat_id'  => env('TELEGRAM_GROUP_ID'),       // CRÍTICO
    'topic_id' => env('TELEGRAM_TOPIC_NOVEDADES'), // Opcional (para topics)
],
```

En `.env.example` / `.env`:
```env
TELEGRAM_BOT_TOKEN=...    # Del @BotFather
TELEGRAM_GROUP_ID=...     # ID del grupo (número negativo)
TELEGRAM_TOPIC_NOVEDADES=... # ID del topic del grupo (si usa topics)
```

### Orden de Ejecución
```
config/services.php (define la estructura)
  ↓
.env cargado (proporciona valores)
  ↓
SendTelegramNotification::handle()
  ↓
Llama $this->telegramService->sendAnnouncement() o direct Http::post()
```

### Puntos Críticos en Producción
- ⚠️ **Token sensible en logs**: Los errores de API pueden mostrar el token. Implementar sanitización:
  ```php
  // ANTES: Log::error('URL: https://api.telegram.org/bot{$token}/...')
  // DESPUÉS: Log::error('URL: https://api.telegram.org/bot***REDACTED***/...')
  ```

- ⚠️ **Timeout**: Http::timeout(10) en SendTelegramNotification. TelegramService NO especifica timeout → default 30s. Inconsistencia.

- ⚠️ **ChatId negativo**: Los grupos Telegram tienen ID negativos (ej: -1001234567890). Si se pasa positivo → error silencioso.

- ⚠️ **API rate limit**: Telegram permite ~30 msg/sec. Si hay spike de torrents aprobados → cola se atrasa pero no hay retry automático (configurar en job).

- ⚠️ **Deprecated endpoints**: `/kickChatMember` fue deprecado. Usar `/banChatMember` en API v7.0+:
  ```php
  // Cambiar:
  "https://api.telegram.org/bot{$token}/kickChatMember"
  // A:
  "https://api.telegram.org/bot{$token}/banChatMember"
  ```

- ⚠️ **Topic ID (message_thread_id)**: Algunos payload incluyen `message_thread_id`. Si se envía ID inexistente → error. Validar que existe antes.

---

## 5. SEND TELEGRAM NOTIFICATION JOB
📄 **Archivo**: [app/Jobs/SendTelegramNotification.php](app/Jobs/SendTelegramNotification.php)

### Propósito
**Job acuado que envía notificación de torrent aprobado a Telegram**. Se dispara desde TorrentObserver cuando `status → APPROVED`.

### Flujo Completo
```
1. TorrentObserver detecta: Torrent.status cambió a APPROVED
2. Dispatch SendTelegramNotification::dispatch($torrent)
3. Job serializa el torrent y entra en cola (Redis)
4. Worker (docker/scheduler) procesa: $job->handle()
5. En handle():
   a) Valida configuración (token, chat_id)
   b) Extrae metadata: codec, audio (regex sobre mediainfo)
   c) Resuelve URL del poster (movie/tv relationship)
   d) Formatea caption con HTML
   e) Construye botones (IMDb, TMDb, Trailer, View Torrent)
   f) POST a Telegram API con sendPhoto + inline keyboard
6. Log de éxito o error
```

### Serialización & Desserialización
- Usa `SerializesModels` → Laravel guarda solo ID del torrent
- Al procesar en worker: recarga desde DB con `->find($id)`
- **Riesgo**: Si torrent fue eliminado meanwhile → error

### Extracción de Metadata

#### Codec
```php
preg_match("/(?s)Video.*?Format\s*:\s*([^\\n]+)/", $torrent->mediainfo, $v)
```
- Busca en mediainfo: `Video ... Format : [valor]`
- Retorna: Ej. "H.264" o "MPEG-4" o "N/A" si no encontrado

#### Audio
```php
preg_match("/(?s)Audio.*?Format\s*:\s*([^\\n]+)/", $torrent->mediainfo, $a)
```
- Busca: `Audio ... Format : [valor]`
- Retorna: Ej. "AAC LC" o "AC-3" o "N/A"

### Caption Construida
```html
🎬 <b>Nombre del Torrent</b>
📦 <b>Size:</b> 4.5 GB
🎞 <b>Codec:</b> H.264
🔊 <b>Audio:</b> AAC LC
```
- Max 1024 caracteres (límite Telegram para caption)
- Trunca con `...` si excede

### Botones (Inline Keyboard)
```
[IMDb]  [TMDb]
[Trailer] [View Torrent]
```

Estructura en Telegram API:
```php
'reply_markup' => [
    'inline_keyboard' => [
        [['text' => 'IMDb', 'url' => '...'], ['text' => 'TMDb', 'url' => '...']],
        [['text' => 'Trailer', 'url' => '...'], ['text' => 'View Torrent', 'url' => '...']]
    ]
]
```

### Resolución de Poster
```php
private function resolvePosterUrl(Torrent $torrent): string
```
- **Lógica**:
  1. Obtiene desde: `$torrent->movie?->poster ?? $torrent->tv?->poster`
  2. Si vacío → placeholder
  3. Si comienza con `http://` o `https://` → retorna como está
  4. Si es ruta relativa → NO procesa (retorna placeholder)

- **Riesgo**: URLs relativas se pierden. Debería construir URL absoluta.

### Resolución de Trailer
```php
private function resolveTrailerUrl(?Torrent $torrent): ?string
```
- Obtiene `$torrent->movie->trailer`
- Si comienza con `http/https` → retorna
- Si es solo ID (ej: "dQw4w9WgXcQ") → construye YouTube link
- Si vacío → returns null (botón no aparece)

### Dependencias Externas
- **Illuminate\Support\Facades\Http** - Cliente HTTP
- **Illuminate\Support\Facades\Log** - Logging
- **Telegram Bot API** - Endpoint `sendPhoto`
- **config/services.php** - Credenciales

### Configuración Requerida

`.env`:
```env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_GROUP_ID=-1001234567890
TELEGRAM_TOPIC_NOVEDADES=42  # Opcional, para topics del grupo
```

`config/services.php`:
```php
'telegram' => [
    'token'    => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'  => env('TELEGRAM_GROUP_ID'),
    'topic_id' => env('TELEGRAM_TOPIC_NOVEDADES'),
],
```

### Modelo Torrent (relaciones esperadas)
```php
class Torrent extends Model {
    public function movie() { return $this->belongsTo(Movie::class); }
    public function tv() { return $this->belongsTo(Tv::class); }
    // Campos: name, size, status, mediainfo, imdb, tmdb_movie_id, tmdb_tv_id, id
}
```

### Orden de Ejecución
```
1. Migración: campos Telegram en users (independiente)
2. Migración: tabla torrents + relaciones (must exist)
3. EventServiceProvider::boot() - registra Observer
4. TorrentObserver::updated() - escucha cambios
5. Config cargado - credenciales Telegram
6. Request: POST /api/torrents/update status=APPROVED
7. TorrentObserver::updated() triggered
8. SendTelegramNotification::dispatch($torrent) encolado
9. Redis queue contiene el job
10. Worker procesa: handle() ejecutado
11. API Telegram responde
12. Logger registra resultado
```

### Configuración de Queue (docker-compose.yml)
```yaml
worker:
    entrypoint: ["/usr/local/bin/entrypoint-worker.sh"]  # Procesa cola
    environment:
        - REDIS_HOST=redis
        # ...
```

El worker debe ejecutarse (docker-compose up worker) para procesar jobs.

### Puntos Críticos en Producción

- ⚠️ **Configuración incompleta**: Si `TELEGRAM_BOT_TOKEN` vacío → log error y retorna sin enviar. Sin excepción thrown → job marca como exitoso.

- ⚠️ **Race condition en poster**: Si el torrent tiene relación movie/tv, pero la película se elimina meanwhile → poster será null.

- ⚠️ **Mediainfo inválido**: Si regex no matchea → "N/A". Considerar valores comunes/fallback.

- ⚠️ **Timeout Telegram**: Http::timeout(10) es muy agresivo para conexiones internacionales. Aumentar a 15-20.

- ⚠️ **Retry policy**: Sí es ShouldQueue pero NO especifica retry count. Configurar:
  ```php
  public $tries = 3;
  public $maxExceptions = 3;
  public $backoff = [10, 60, 300]; // segundos
  ```

- ⚠️ **Job chain**: Si el job falla 3 veces (sin retries configuradas), se mueve a `failed_jobs`. Implementar:
  ```php
  protected $job = 'failed_jobs';
  ```

- ⚠️ **Seguridad token**: El token se ve en logs si hay error. Implementar middleware de sanitización:
  ```php
  'token' => str_repeat('*', strlen($token) - 8) . substr($token, -8)
  ```

- ⚠️ **Caption encoding**: Si torrent name tiene emojis/caracteres especiales → puede exceder 1024 chars en bytes UTF-8 (no caracteres). Usar mb_strlen y mb_substr correctamente (ya lo hace ✓).

- ⚠️ **Ciclo de vida config**:
  ```
  Si cambiamos TELEGRAM_BOT_TOKEN en .env:
    - Jobs encolados ANTES del cambio usan token viejo
    - No hay re-lectura de .env en workers
    - Mitigación: restart workers después de cambiar secretos
  ```

- ⚠️ **Poster URL validation**: No valida si URL es accesible. Telegram puede rechazar URLs rotas o muy grandes (> 5MB imagen). Implementar:
  ```php
  // Validar antes de enviar
  if ($poster && !$this->isValidImageUrl($poster)) {
      $poster = 'https://via.placeholder.com/600x900';
  }
  ```

- 🔐 **Log de secretos**: En catch block se loguea `$response->body()` que podría contener sensibles. Usar:
  ```php
  'body' => $this->sanitizeResponseBody($response->body()),
  ```

---

## 6. TELEGRAM WEBHOOK CONTROLLER
📄 **Archivo**: [app/Http/Controllers/API/TelegramWebhookController.php](app/Http/Controllers/API/TelegramWebhookController.php)

### Propósito
**Endpoint que recibe updates de Telegram Bot para vincular cuentas de usuario**. Implementa flow OAuth-like:
```
1. Usuario inicia bot con /start → Telegram envía webhook
2. Controller extrae comando + token temporal
3. Valida token contra DB (tabla users.telegram_token)
4. If válido: vincula chat_id y limpia token
5. Bot responde: confirmación
6. Usuario ya tiene notificaciones Telegram
```

### Flow Técnico Detallado

#### Paso 1: Validar estructura de mensaje
```php
$message = $request->input('message');
if (!$message || !isset($message['text'])) {
    return response()->json(['status' => 'ignored'], 200);
}
```
- Telegram envía diferentes tipos de updates. Solo procesamos `message` con `text`.

#### Paso 2: Extraer comando y token
```php
if (preg_match('/^\/start\s+(TRK-[a-zA-Z0-9]+)$/', $text, $matches)) {
    $token = $matches[1];
```
- Espera: `/start TRK-PULICION123ABC`
- Regex: `^\/start\s+TRK-[a-zA-Z0-9]+$`
- Captura el token en `$matches[1]`

- **Formato token**: `TRK-` + alphanumerics. Generado en algún lado (ej: UserController).

#### Paso 3: Buscar usuario
```php
$user = User::where('telegram_token', $token)->first();
```
- Consulta directa sin prepared statement (vulnerable a SQL injection si token no se valida previamente). **RIESGO**: El regex protege, pero usar bindings es más seguro:
  ```php
  User::where('telegram_token', '=', $token)->first()
  ```

#### Paso 4: Vincular cuenta
```php
if ($user) {
    $user->telegram_chat_id = $chatId;
    $user->telegram_token = null;  // Limpia token (one-time use)
    $user->save();
    
    // Envía confirmación
    Http::post($url, [...])
}
```
- Chat ID: `$message['chat']['id']` (número largo, de Telegram)
- Limpia token → imposibilita reutilización
- Responde a Telegram

#### Paso 5: Respuesta táctica
```php
return response()->json(['status' => 'ok'], 200);
```
- Telegram espera HTTP 200 para NO reintentar el webhook
- Aunque falle vinculación, responde 200 (no log warning)

### Dependencias Externas
- **Telegram Bot API Webhooks** - Telegram envía POST a este endpoint
- **App\Models\User** - tabla users con campos telegram_*
- **Illuminate\Support\Facades\Http** - para responder

### Configuración Requerida

#### En Web Routes (`routes/web.php` o `routes/api.php`)
```php
Route::post('/api/telegram/webhook', [TelegramWebhookController::class, 'handle']);
// O:
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
```

#### En @BotFather (Telegram)
```
/setwebhook https://your-domain.com/api/telegram/webhook
```

**CRÍTICO**: URL debe ser HTTPS, accesible públicamente, y reachable por Telegram servers.

#### En .env / config
```env
# NO necesita variables si solo vincula. Pero para responder:
TELEGRAM_BOT_TOKEN=...  # Para construir https://api.telegram.org/bot{TOKEN}/sendMessage
```

### Modelo User (cambios esperados)
```php
class User extends Model {
    protected $fillable = [
        'username',
        'telegram_chat_id',
        'telegram_token',
        // ...
    ];
}
```

### Orden de Ejecución
```
Migración: add_telegram_fields_to_users_table (ya hecha)
  ↓
Routes registradas (routes/api.php contiene POST /api/telegram/webhook)
  ↓
Canal Telegram reconfigurado con webhook (by admin)
  ↓
Usuario inicia bot: /start TRK-XXXX
  ↓
Telegram → POST /api/telegram/webhook
  ↓
TelegramWebhookController::handle() procesa
  ↓
User record actualizado + respuesta
```

### Puntos Críticos en Producción

- ⚠️ **Race condition en vinculación**: Si usuario envía `/start` dos veces rápido:
  ```
  Request 1: Busca token → encuentra → guarda chat_id → set token=NULL
  Request 2: Busca token → NO encuentra (NULL desde Request 1) → limpia falla
  
  Mitigación: Usar transacción + check final
  ```
  ```php
  DB::transaction(function() {
      $user->update([...]);
      if (!User::where('id', $user->id)->where('telegram_token', $token)->exists()) {
          throw new Exception('Token was modified');
      }
  });
  ```

- ⚠️ **Token storage**: Tokens en plaintext en DB. Si DB comprometida → tokens reutilizables.
  - Mitigación: Hash tokens (bcrypt) y comparar al buscar.
  - Adicional: TTL en token (expires_at timestamp).

- ⚠️ **HTTPS obligatorio**: Telegram rechaza webhooks HTTP. Si dominio NO es HTTPS → webhook fallará silenciosamente.

- ⚠️ **Webhook timeout**: Telegram espera respuesta en < 30 segundos. Si handle() es lento → timeout.

- ⚠️ **Logging incompleto**: 
  - Log::info solo si exitoso
  - Log::warning solo si token inválido
  - **No hay** log si update() falla o mensaje no tiene 'text'
  
  Mitigación:
  ```php
  Log::info('Webhook update received', ['chat_id' => $chatId, 'has_text' => isset($message['text'])]);
  // ... después de vinculación
  Log::info('Successfully linked', ['user_id' => $user->id]);
  ```

- ⚠️ **No valida estructura de respuesta Telegram**: Http::post no chequea si envío exitoso. Implementar:
  ```php
  $response = Http::post($url, [...]);
  if (!$response->successful()) {
      Log::error('Failed to send Telegram confirmation', [
          'status' => $response->status(),
      ]);
  }
  ```

- ⚠️ **XSS/Injection vía texto**: Si bien regex protege `/start`, el campo `text` podría contener payloads. No se procesa, así que riesgo bajo. Pero loguear texto completo podría exponer data.

- ⚠️ **IP whitelist**: Telegram envíos desde IPs específicas (ver docs de Telegram). Considerar IP whitelist en middleware:
  ```php
  Route::post('/api/telegram/webhook', [...])
       ->middleware('verify.telegram.ip');
  ```

- ⚠️ **Replay attack**: Si un atacante captura un webhook y lo reenva → podría forzar vinculación si token aún válido.
  - Mitigación: Signed webhooks (Telegram no lo hace nativamente), pero sí usar TTL en tokens.

---

## 7. CONFIG/SERVICES.PHP
📄 **Archivo**: [config/services.php](config/services.php)

### Propósito
Centraliza credenciales de servicios third-party. Para Telegram:
```php
'telegram' => [
    'token'    => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'  => env('TELEGRAM_GROUP_ID'),
    'topic_id' => env('TELEGRAM_TOPIC_NOVEDADES'),
],
```

### Estructura
- **token**: Bot token del @BotFather (secret)
- **chat_id**: ID del grupo o canal donde se publican notificaciones (usualmente negativo)
- **topic_id**: ID del topic/thread dentro del grupo (si usa Topics feature)

### Dependencias Externas
- **.env file** - proporciona valores en tiempo de carga

### Configuración Requerida
La estructura ya existe. Solo necesita valores en `.env`:
```env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_GROUP_ID=-1001234567890
TELEGRAM_TOPIC_NOVEDADES=42
```

### Access Pattern
```php
// Desde cualquier parte del código:
config('services.telegram.token')
config('services.telegram.chat_id')
config('services.telegram.topic_id')

// O en un servicio:
$token = config('services.telegram.token');
```

### Orden de Ejecución
```
1. Laravel bootstrap carga config/app.php
2. Carga config/services.php
3. env() lee .env y asigna valores
4. Quedan disponibles globalmente
5. SendTelegramNotification accede via config()
```

### Puntos Críticos en Producción

- ⚠️ **Config caching**: En producción, usa:
  ```bash
  php artisan config:cache
  ```
  Esto crea bootstrap/cache/config.php. Si cambias .env DESPUÉS del cache → cambios NO se aplican.
  
  Mitigación:
  ```bash
  # Después de cambiar .env:
  php artisan config:cache
  # O en docker:
  docker-compose exec app php artisan config:cache
  ```

- ⚠️ **Secretos en version control**: Si .env no está en .gitignore → credenciales expostas.
  - Verificar: `.gitignore` contiene `.env`
  - Usar: `.env.example` con placeholders

- ⚠️ **Empty values**: Si `TELEGRAM_BOT_TOKEN=` (sin valor):
  - `env('TELEGRAM_BOT_TOKEN')` retorna `null` o empty string
  - Algunos services fallan silenciosamente, otros throw error
  - SendTelegramNotification loguea warning pero no falla

- ⚠️ **Type casting**: `config()` retorna strings. Si esperas integer:
  ```php
  // INCORRECTO:
  config('services.telegram.topic_id') + 1  // Error si null
  
  // CORRECTO:
  (int) config('services.telegram.topic_id') ?: 0
  ```

- ⚠️ **Scope de credenciales**: Token de bot en producción NO debería estar en control de versiones. Usar:
  - AWS Secrets Manager
  - HashiCorp Vault
  - Kubernetes Secrets
  - Environment variable inyectada en container
  
  En docker-compose.yml ya se inyecta via environment → OK en staging.

---

## 8. DOCKER-COMPOSE.YML
📄 **Archivo**: [docker-compose.yml](docker-compose.yml)

### Propósito
Orquesta contenedores. **Cambios para Telegram**: Principalmente en variables de entorno y asegurar que `worker` esté activo.

### Servicios Relevantes

#### `worker`
```yaml
worker:
    build:
        context: .
        dockerfile: .docker/php/Dockerfile.app
    container_name: unit3d-staging-worker
    entrypoint: ["/usr/local/bin/entrypoint-worker.sh"]
    volumes:
        - .:/var/www/html
    environment:
        - DB_HOST=db
        - REDIS_HOST=redis
        - DB_DATABASE=${DB_DATABASE:-unit3d}
        - DB_USERNAME=${DB_USERNAME:-unit3d}
        - DB_PASSWORD=${DB_PASSWORD:-unit3d}
    depends_on:
        - db
        - redis
```

**Función**: Procesa cola de jobs (Redis). Sin esto, SendTelegramNotification jobs quedan encolados pero no se ejecutan.

#### `redis`
```yaml
redis:
    image: redis:alpine
    container_name: unit3d-staging-redis
    volumes:
        - ./.docker/data/redis:/data
    healthcheck:
        test: ["CMD", "redis-cli", "ping"]
```

**Función**: Queue backend. Si Redis no está activo:
- `dispatch()` puede fallar
- Jobs quedan sin procesar

#### Variables de entorno compartidas
```yaml
environment:
    - REDIS_HOST=redis  # DNS interno de docker
    - DB_HOST=db        # DNS interno
```

**Para Telegram**: Asegurar que `TELEGRAM_BOT_TOKEN` se pase al contenedor `app`, `worker`, `scheduler`.

### Cambios Requeridos para Telegram

Actualmente **NO se pasan** las variables de Telegram a los contenedores. Agregar:

```yaml
# En servicios: app, worker, scheduler
environment:
    - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
    - TELEGRAM_GROUP_ID=${TELEGRAM_GROUP_ID}
    - TELEGRAM_TOPIC_NOVEDADES=${TELEGRAM_TOPIC_NOVEDADES}
    # ... (resto de variables)
```

**Completo**:
```yaml
app:
    # ...
    environment:
        - LD_PRELOAD=/usr/lib/libiconv.so.2
        - DB_HOST=db
        - DB_DATABASE=${DB_DATABASE:-unit3d}
        - DB_USERNAME=${DB_USERNAME:-unit3d}
        - DB_PASSWORD=${DB_PASSWORD:-unit3d}
        - REDIS_HOST=redis
        - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
        - TELEGRAM_GROUP_ID=${TELEGRAM_GROUP_ID}
        - TELEGRAM_TOPIC_NOVEDADES=${TELEGRAM_TOPIC_NOVEDADES}

worker:
    # ...
    environment:
        - DB_HOST=db
        - REDIS_HOST=redis
        - DB_DATABASE=${DB_DATABASE:-unit3d}
        - DB_USERNAME=${DB_USERNAME:-unit3d}
        - DB_PASSWORD=${DB_PASSWORD:-unit3d}
        - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
        - TELEGRAM_GROUP_ID=${TELEGRAM_GROUP_ID}
        - TELEGRAM_TOPIC_NOVEDADES=${TELEGRAM_TOPIC_NOVEDADES}

scheduler:
    # ...
    environment:
        - DB_HOST=db
        - REDIS_HOST=redis
        - DB_DATABASE=${DB_DATABASE:-unit3d}
        - DB_USERNAME=${DB_USERNAME:-unit3d}
        - DB_PASSWORD=${DB_PASSWORD:-unit3d}
        - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
        - TELEGRAM_GROUP_ID=${TELEGRAM_GROUP_ID}
        - TELEGRAM_TOPIC_NOVEDADES=${TELEGRAM_TOPIC_NOVEDADES}
```

### Dependencias Externas
- Docker daemon
- docker-compose CLI
- Imágenes: `nginx:alpine`, `mysql:8.0`, `redis:alpine`, `getmeili/meilisearch`, etc.

### Configuración Requerida
- `.env` file en raíz con variables del entorno
- Puertos disponibles (58080, 53306, 56379, 57700, etc.)
- Volúmenes accesibles (./app, ./.docker/data/)

### Orden de Ejecución
```
1. docker-compose up -d
   └─ Inicia: nginx, app, worker, scheduler, db, redis, meilisearch
   
2. Healthcheck de servicios
   └─ db: mysqladmin ping ✓
   └─ redis: redis-cli ping ✓
   
3. App+Worker+Scheduler booteados
   └─ Leen config/services.php
   └─ Leen .env (vía environment)
   └─ Quedan listos para procesar

4. Request: POST /apitorrents/update status=APPROVED
   └─ Dispara job via Redis queue
   
5. Worker procesa cola
   └─ Ejecuta handle() de SendTelegramNotification
   └─ Envía a Telegram
```

### Puntos Críticos en Producción

- ⚠️ **Worker no está activo**: 
  ```bash
  docker-compose ps
  # Si worker != running → jobs no se ejecutan
  ```
  
  Verificar: `entrypoint: ["/usr/local/bin/entrypoint-worker.sh"]` debe existir en Dockerfile.

- ⚠️ **Redis persistence**: Sin volumen, si Redis reinicia → cola se pierde.
  ```yaml
  redis:
      volumes:
          - ./.docker/data/redis:/data  # Asegurar que existe y es writable
  ```

- ⚠️ **Database connection failure**:
  ```
  Si app no conecta a DB → config() puede fallar loading .env
  ```
  - Healthcheck en db service asegura startup orden
  - Pero app debe reintentar conexión

- ⚠️ **Variable interpolation**: 
  ```yaml
  - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
  ```
  Si `TELEGRAM_BOT_TOKEN` NO está en .env → pasa como vacío.
  
  Verificar: `.env` contiene los valores.

- ⚠️ **Secret leakage en logs**:
  ```bash
  docker-compose logs app
  # Podría mostrar TELEGRAM_BOT_TOKEN si hay error
  ```
  
  Mitigación: Filtrar logs o usar secrets de Docker.

- ⚠️ **Port conflicts**: Si puerto 58080 (HTTP) ya usado → error. Check:
  ```bash
  netstat -ln | grep 58080
  ```

- ⚠️ **Volumen permissions**: 
  ```
  ./.docker/data/redis/
  ./.docker/data/mysql/
  ./.docker/data/meilisearch/
  ```
  Si propietario es root y app corre como www-data → permission denied.
  
  Mitigación:
  ```bash
  sudo chown -R 1000:1000 .docker/data/  # 1000=www-data UID
  chmod 755 .docker/data
  ```

- ⚠️ **Scheduler daemon**: No se menciona específicamente pero aparece en docker-compose.
  ```yaml
  scheduler:
      entrypoint: ["/usr/local/bin/entrypoint-scheduler.sh"]
  ```
  
  Si scheduled jobs usan Telegram → también necesita vars de entorno (ya agregadas arriba).

---

## 9. .ENV.EXAMPLE
📄 **Archivo**: [.env.example](.env.example)

### Propósito
Template para `.env`. Documentación viva de qué variables necesita la app. **Debe actualizarse** cuando se agregan secretos.

### Sección Telegram (actual)
```env
# Telegram Notifications
TELEGRAM_BOT_TOKEN=
TELEGRAM_GROUP_ID=
TELEGRAM_TOPIC_NOVEDADES=
```

### Definiciones esperadas

**TELEGRAM_BOT_TOKEN**
- Formato: `{numero}:{token_alfanumerico}`
- Ej: `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`
- Obtenido de: @BotFather en Telegram
- Uso: Autenticación con API Telegram Bot
- ⚠️ SECRETO: No pushear, no compartir

**TELEGRAM_GROUP_ID**
- Formato: Número largo, usualmente negativo
- Ej: `-1001234567890`
- Obtenido de: Invitar bot al grupo y obtener con `/start` o API
- Uso: Destino donde se publican notificaciones
- Nota: Para grupos, ID siempre negativo. Para canales, puede ser positive o string like `@canal_name`

**TELEGRAM_TOPIC_NOVEDADES**
- Formato: Número entero positivo
- Ej: `42`
- Obtenido de: Si grupo tiene Topics enabled, ID del topic del subgrupo
- Uso: message_thread_id para agrupar notificaciones en topic
- Opcional: Si group no usa Topics, omitir o dejar vacío

### Cambios Requeridos

Agregar descripción y valores de ejemplo:
```env
# Telegram Notifications
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_GROUP_ID=-1001234567890
TELEGRAM_TOPIC_NOVEDADES=42
```

O con comentarios:
```env
# Telegram Bot Notifications
# Get token from @BotFather, group ID from @getuseridbot
TELEGRAM_BOT_TOKEN=your-bot-token-from-botfather
TELEGRAM_GROUP_ID=-1001234567890
TELEGRAM_TOPIC_NOVEDADES=42
```

### Dependencias Externas
- `.env` actual (copy de .env.example para desarrollo local)
- Infraestructura externa: @BotFather, @getuseridbot en Telegram

### Configuración Requerida
- ✅ Variables deben existir
- ✅ Deben tener valores reales (no vacíos) en `.env` de producción
- ✅ No deben estar versionadas (`.gitignore` protege)

### Orden de Ejecución
```
1. Clonar repo
2. cp .env.example .env
3. Editar .env con valores REALES
4. docker-compose up
5. php artisan migrate
6. App carga env() y usa valores
```

### Puntos Críticos en Producción

- ⚠️ **Ejemplo mínimo**: .env.example debería contener TODOS los valores necesarios. Actual: vacíos. Un dev podría olvidar llenarlos.
  
  Mejor:
  ```env
  # Telegram
  TELEGRAM_BOT_TOKEN=REQUIRED_GET_FROM_BOTFATHER
  TELEGRAM_GROUP_ID=REQUIRED_ID_OF_TARGET_GROUP
  TELEGRAM_TOPIC_NOVEDADES=OPTIONAL_ID_IF_USING_TOPICS
  ```

- ⚠️ **Cambio de credenciales**: Si TELEGRAM_BOT_TOKEN es comprometido:
  1. Crear nuevo bot (@BotFather)
  2. Cambiar en .env
  3. Ejecutar `php artisan config:cache` (importante en prod)
  4. Restart containers: `docker-compose restart`
  5. Jobs retenidos en queue usan token viejo → fallan
  
  Mitigación: Limpiar queue antes de cambio:
  ```bash
  php artisan queue:flush
  ```

- ⚠️ **Type casting**: Valores en .env son strings. Chat ID como string con signo negativo:
  ```
  TELEGRAM_GROUP_ID=-1001234567890  (string, no integer)
  ```
  Telegram API maneja strings así que OK.

- ⚠️ **Variable de ejemplo incompleta**: Actual muestra:
  ```env
  TELEGRAM_BOT_TOKEN=
  ```
  Sin descriptor. Debería mostrar:
  ```env
  # You must create a bot via @BotFather and get this token
  TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
  ```

---

## RESUMEN: ORDEN DE DEPLOYMENT SEGURO

### FASE 0: Verificaciones Pre-Deploy
```bash
1. Verificar que .env contiene:
   TELEGRAM_BOT_TOKEN=<valor real, no vacío>
   TELEGRAM_GROUP_ID=<valor real, ej: -1001234567890>
   TELEGRAM_TOPIC_NOVEDADES=<valor real o vacío>

2. Verificar que @BotFather token es válido:
   curl https://api.telegram.org/bot{TOKEN}/getMe
   → Debe retornar JSON con bot info

3. Verificar que grupo existe y bot es miembro:
   curl https://api.telegram.org/bot{TOKEN}/getChat?chat_id={CHAT_ID}
   → Debe retornar info del grupo
```

### FASE 1: Database
```bash
1. Backup existente (si es prod):
   docker-compose exec db mysqldump -u${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} > backup.sql

2. Aplicar migración:
   docker-compose exec app php artisan migrate --path=database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php
   
3. Verificar campos:
   docker-compose exec db mysql -u${DB_USERNAME} -p${DB_PASSWORD} -e "DESC ${DB_DATABASE}.users;" | grep telegram
   → Debe mostrar: telegram_chat_id, telegram_token
```

### FASE 2: Código y Configuración
```bash
1. Asegurar que archivos están en lugar:
   - app/Http/Controllers/API/TelegramWebhookController.php ✓
   - app/Observers/TorrentObserver.php ✓
   - app/Services/TelegramService.php ✓
   - app/Jobs/SendTelegramNotification.php ✓
   
2. Verificar config/services.php contiene estructura 'telegram' ✓

3. Verificar .env.example documentado ✓

4. Config cache:
   docker-compose exec app php artisan config:cache
   
5. Verificar config cargado:
   docker-compose exec app php artisan tinker
   >>> config('services.telegram.token')  # debe retornar token, no null
```

### FASE 3: EventServiceProvider y Observers
```bash
1. Verificar que EventServiceProvider.php boot() registra Observer:
   grep -n "TorrentObserver" app/Providers/EventServiceProvider.php
   → Debe mostrar: \App\Models\Torrent::observe(\App\Observers\TorrentObserver::class);
   
2. Test en tinker:
   docker-compose exec app php artisan tinker
   >>> $t = App\Models\Torrent::first();
   >>> $t->update(['status' => \App\Enums\ModerationStatus::APPROVED]);
   → Debería disparar observer
```

### FASE 4: Queue Setup
```bash
1. Verificar Redis está activo:
   docker-compose logs redis | grep "Ready to accept"
   
2. Verificar worker está activo:
   docker-compose logs worker | grep "Worker started"
   
3. Si no, iniciar:
   docker-compose up -d worker scheduler
   
4. Test queue:
   docker-compose exec app php artisan queue:work redis --once
   → Procesa 1 job y sale. Debe no haber errores.
```

### FASE 5: Webhook Setup (si se usa TelegramWebhookController)
```bash
1. Asegurar ruta está registrada en routes/api.php:
   Route::post('/api/telegram/webhook', [TelegramWebhookController::class, 'handle']);
   
2. Registrar webhook con Telegram:
   curl -X POST https://api.telegram.org/bot{TOKEN}/setWebhook \
     -H "Content-Type: application/json" \
     -d '{
       "url": "https://your-production-domain.com/api/telegram/webhook",
       "allowed_updates": ["message"]
     }'
   
3. Verificar:
   curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
   → "url" debe ser tu dominio, "pending_update_count": 0
```

### FASE 6: End-to-End Test
```bash
1. Crear torrent de test y aprobar:
   docker-compose exec app php artisan tinker
   >>> $t = App\Models\Torrent::create([...]);
   >>> $t->status = \App\Enums\ModerationStatus::APPROVED;
   >>> $t->save();
   
2. Aguardar 2-3 segundos (worker procesa)

3. Verificar logs:
   docker-compose logs app | grep "SendTelegramNotification"
   docker-compose logs worker | grep "Telegram"
   
4. Verificar en Telegram: el bot debe haber publicado en el grupo

5. Si no → revisar:
   - .env variables ✓
   - workers activos ✓
   - Redis accesible ✓
   - Registros de error en storage/logs/laravel.log
```

### FASE 7: Docker Compose actualizado
```yaml
# Asegurar que docker-compose.yml contiene:
# En servicios app, worker, scheduler:
environment:
    - TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
    - TELEGRAM_GROUP_ID=${TELEGRAM_GROUP_ID}
    - TELEGRAM_TOPIC_NOVEDADES=${TELEGRAM_TOPIC_NOVEDADES}
```

### FASE 8: Rollback Plan
```bash
# Si algo falla post-deploy:

1. Detener workers:
   docker-compose stop worker scheduler
   
2. Revertir migración:
   docker-compose exec app php artisan migrate:rollback --path=database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php
   
3. Restaurar código (git):
   git checkout HEAD -- app/Jobs/SendTelegramNotification.php
   
4. Reiniciar:
   docker-compose up -d
   
5. Clear queue:
   docker-compose exec app php artisan queue:flush
```

---

## TABLA RESUMEN: Dependencias y Orden

| Paso | Archivo | Tipo | Dependencias Previas | Configuración | Crítico | Reversible |
|------|---------|------|----------------------|----------------|---------|-----------|
| 0 | .env.example | Doc | - | Variables Telegram | No | Sí (doc) |
| 1 | 2026_03_24_010501_add_telegram_fields_to_users_table.php | Migration | DB existe | MySQL 8.0+ | **SÍ** | Sí (rollback) |
| 2 | docker-compose.yml | Config | Docker, .env | TELEGRAM_* vars | **SÍ** | Sí (compose stop) |
| 3 | config/services.php | Config | .env | Estructura Telegram | No | Texto (revert) |
| 4 | app/Providers/EventServiceProvider.php | Code | TorrentObserver existe | - | **SÍ** | Sí (git checkout) |
| 5 | app/Observers/TorrentObserver.php | Code | Torrent model, Enum | - | **SÍ** | Sí (git checkout) |
| 6 | app/Services/TelegramService.php | Code | Telegram Bot API | Token, GroupID | Parcial | Sí (git checkout) |
| 7 | app/Jobs/SendTelegramNotification.php | Code | Redis queue, config | QUEUE_CONNECTION | **SÍ** | Sí (git checkout) |
| 8 | app/Http/Controllers/API/TelegramWebhookController.php | Code | User model, router | Route en routes/ | Parcial | Sí (git checkout) |

---

## NOTAS FINALES PARA DEPLOYMENT SEGURO

### Secretos
- ✅ `.env` **NO debe** estar en git (validar `.gitignore`)
- ✅ `TELEGRAM_BOT_TOKEN` debe inyectarse desde CI/CD o Kubernetes secrets
- ✅ En docker-compose, usar `.env` local sin comprometer

### Monitoreo
- 📊 Monitorear `storage/logs/laravel.log` para errores de Telegram
- 📊 Monitorear Redis queue: `docker-compose exec redis redis-cli LRANGE queue:jobs 0 -1`
- 📊 Monitorear workers vivos: `docker-compose ps worker | grep running`

### Performance
- ⚡ Job timeout está en 10s (Telegram API). OK para mayoría de casos.
- ⚡ Retries NO configurados. Implementar en producción (ver puntos críticos job).
- ⚡ Rate limit Telegram: ~30 msg/sec. Monitor si hay picos de torrents.

### Seguridad
- 🔐 Tokens en plaintext en logs. Implementar sanitización en logger.
- 🔐 TelegramWebhookController debe estar en HTTPS y con IP whitelist.
- 🔐 Validar que migrations solo se ejecutan una vez (idempotencia).

---

**Análisis completado**: 25 de marzo 2026 - Stack listo para deployment seguro.
