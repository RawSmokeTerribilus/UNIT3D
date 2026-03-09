Actúa como un asistente de edición de código experto. Tu tarea es abrir el archivo `src/manual.md` y realizar las siguientes sustituciones exactas de texto. Tu objetivo es insertar los enlaces Markdown a los archivos locales integrándolos en la redacción actual. Debes mantener el formato y el resto del documento absolutamente intactos.

Por favor, busca los siguientes fragmentos de texto exactos y reemplázalos por su versión actualizada:

--- PUNTO 1 ---
BUSCAR: `app/Http/Livewire/SimilarTorrent.php` que realiza
REEMPLAZAR POR: `app/Http/Livewire/SimilarTorrent.php` ([archivo válido de ejemplo](/book/assets/manual/punto-1/SimilarTorrent.php)) que realiza

BUSCAR: subidas es `app/Http/Requests/StoreTorrentRequest.php`.
REEMPLAZAR POR: subidas es `app/Http/Requests/StoreTorrentRequest.php` ([archivo ya configurado](/book/assets/manual/punto-1/StoreTorrentRequest.php)).

BUSCAR: controlador `app/Http/Controllers/API/TorrentController.php` maneja
REEMPLAZAR POR: controlador `app/Http/Controllers/API/TorrentController.php` ([archivo válido de ejemplo](/book/assets/manual/punto-1/TorrentController.php)) maneja

BUSCAR: mediante `app/Helpers/Bencode.php`.
REEMPLAZAR POR: mediante `app/Helpers/Bencode.php` ([archivo ya configurado](/book/assets/manual/punto-1/Bencode.php)).

BUSCAR: DTO (`app/DTO/TorrentSearchFiltersDTO.php`):**
REEMPLAZAR POR: DTO (`app/DTO/TorrentSearchFiltersDTO.php`)** ([archivo válido de ejemplo](/book/assets/manual/punto-1/TorrentSearchFiltersDTO.php)):

--- PUNTO 2 ---
BUSCAR: Announce (`routes/announce.php`)
REEMPLAZAR POR: Announce (`routes/announce.php`) ([archivo válido de ejemplo](/book/assets/manual/punto-2/announce.php))

BUSCAR: Dentro de `AnnounceController`, cualquier
REEMPLAZAR POR: Dentro de `AnnounceController` ([archivo válido de ejemplo](/book/assets/manual/punto-2/AnnounceController.php)), cualquier

BUSCAR: lanza una `TrackerException`.
REEMPLAZAR POR: lanza una `TrackerException` ([archivo válido de ejemplo](/book/assets/manual/punto-2/TrackerException.php)).

--- PUNTO 3 ---
BUSCAR: `app/Http/Middleware/TrustProxies.php`:**
REEMPLAZAR POR: `app/Http/Middleware/TrustProxies.php`** ([archivo ya configurado](/book/assets/manual/punto-3/TrustProxies.php)):

BUSCAR: Nginx (`.docker/nginx/default.conf`):**
REEMPLAZAR POR: Nginx (`.docker/nginx/default.conf`)** ([archivo ya configurado](/book/assets/manual/punto-3/default.conf)):

--- PUNTO 4 ---
BUSCAR: en `config/database.php`:**
REEMPLAZAR POR: en `config/database.php`** ([archivo válido de ejemplo](/book/assets/manual/punto-4/database.php)):

BUSCAR: El archivo `app/Console/Kernel.php` define
REEMPLAZAR POR: El archivo `app/Console/Kernel.php` ([archivo válido de ejemplo](/book/assets/manual/punto-4/Kernel.php)) define

BUSCAR: - **`AutoUpdateUserLastActions` (cada 5 seg):**
REEMPLAZAR POR: - **`AutoUpdateUserLastActions`** ([archivo ya configurado](/book/assets/manual/punto-4/AutoUpdateUserLastActions.php)) **(cada 5 seg):**

--- PUNTO 5 ---
BUSCAR: archivo [./Config_Manual/punto-5/nginx_default.conf](./Config_Manual/punto-5/nginx_default.conf) (ubicado
REEMPLAZAR POR: archivo `nginx_default.conf` ([archivo ya configurado](/book/assets/manual/punto-5/nginx_default.conf)) (ubicado

BUSCAR: archivo [./Config_Manual/punto-5/TrustProxies.php](./Config_Manual/punto-5/TrustProxies.php) (ubicado
REEMPLAZAR POR: archivo `TrustProxies.php` ([archivo ya configurado](/book/assets/manual/punto-5/TrustProxies.php)) (ubicado

--- PUNTO 6 ---
BUSCAR: Implicados:** [./Config_Manual/punto-6/TMDBScraper.php](./Config_Manual/punto-6/TMDBScraper.php)
REEMPLAZAR POR: Implicados:** `TMDBScraper.php` ([archivo ya configurado](/book/assets/manual/punto-6/TMDBScraper.php))

BUSCAR: la cola: `ProcessMovieJob` o `ProcessTvJob`.
REEMPLAZAR POR: la cola: `ProcessMovieJob` o `ProcessTvJob` ([archivo ya configurado](/book/assets/manual/punto-6/ProcessTvJob.php)).

BUSCAR: Implicados:** [./Config_Manual/punto-6/ProcessMovieJob.php](./Config_Manual/punto-6/ProcessMovieJob.php)
REEMPLAZAR POR: Implicados:** `ProcessMovieJob.php` ([archivo ya configurado](/book/assets/manual/punto-6/ProcessMovieJob.php))

BUSCAR: Implicados:** [./Config_Manual/punto-6/MovieClient.php](./Config_Manual/punto-6/MovieClient.php)
REEMPLAZAR POR: Implicados:** `MovieClient.php` ([archivo válido de ejemplo](/book/assets/manual/punto-6/MovieClient.php))

BUSCAR: Visualización:** [./Config_Manual/punto-6/TorrentMeta.php](./Config_Manual/punto-6/TorrentMeta.php) (Trait
REEMPLAZAR POR: Visualización:** `TorrentMeta.php` ([archivo ya configurado](/book/assets/manual/punto-6/TorrentMeta.php)) (Trait