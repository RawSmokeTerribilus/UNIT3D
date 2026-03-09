#!/bin/bash
# --- SEGURO DE VIDA ---
set -e # Si cualquier comando falla, el script se detiene y NO miente.

# --- CONTROL DE PRIVILEGIOS ---
if [ "$EUID" -ne 0 ]; then
  echo "❌ ERROR FATAL: Este script requiere acceso absoluto a los volúmenes de Docker."
  echo "👉 Por favor, ejecútalo así: sudo ./backup.sh"
  exit 1
fi

# --- CONFIGURACIÓN ---
NOW=$(date +"%Y-%m-%d_%H%M")
DOCKER_DIR="/home/rawserver/UNIT3D_Docker"
BASE_BACKUP_DIR="$DOCKER_DIR/backups"
SNAPSHOT_DIR="$BASE_BACKUP_DIR/snapshot_$NOW"
MAX_BACKUPS=3

# Imágenes a blindar
IMAGES=("unit3d_docker-app:latest" "getmeili/meilisearch:latest" "redis:alpine" "nginx:alpine" "mysql/mysql-server:8.0" "axllent/mailpit:latest")

# --- 0. POSICIONAMIENTO TÁCTICO ---
# (Esto soluciona el error de 'no configuration file provided')
cd "$DOCKER_DIR"

mkdir -p "$SNAPSHOT_DIR/docker_images"

echo "🎬 Iniciando BÚNKER TOTAL ($NOW)..."

# 1. Volcado de DB en CALIENTE (El .sql limpio)
echo "💾 Volcando base de datos unit3d (.sql)..."
# Silenciamos el warning inofensivo de usar la contraseña en CLI
docker exec unit3d-db mysqldump -u unit3d -punit3d --no-tablespaces unit3d > "$SNAPSHOT_DIR/db_unit3d.sql" 2>/dev/null

# 2. COMPOSE STOP (Paramos máquinas para la copia sucia)
echo "🛑 Deteniendo el ecosistema (stop)..."
docker compose stop

# 3. Volcado de IMÁGENES (Motores con sus cosas)
echo "📦 Guardando imágenes del stack..."
for IMG in "${IMAGES[@]}"; do
    FILE_NAME=$(echo $IMG | tr ':/' '_')
    echo "  -> Volcando $IMG..."
    docker save $IMG | gzip > "$SNAPSHOT_DIR/docker_images/img_${FILE_NAME}.tar.gz"
done

# 4. COMPRIMIR PROYECTO (Cuerpo entero + Copia sucia de DB en frío)
echo "📂 Comprimiendo UNIT3D_Docker (El todo-en-uno)..."
# Excluimos los sockets (*.sock) porque tar no puede ni debe copiarlos
tar -czf "$SNAPSHOT_DIR/unit3d_full_snapshot_$NOW.tar.gz" \
    --exclude='./backups' \
    --exclude='./storage/logs/*.log' \
    --exclude='./node_modules/.cache' \
    --exclude='*.sock' \
    --exclude='*.sock.lock' \
    -C "$DOCKER_DIR" .

# 5. ROTACIÓN DE BACKUPS
echo "♻️ Rotando: manteniendo solo los $MAX_BACKUPS más recientes..."
cd "$BASE_BACKUP_DIR"
ls -dt snapshot_* 2>/dev/null | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm -rf

# 6. COMPOSE UP (Resurrección)
echo "🚀 Resucitando el tracker..."
cd "$DOCKER_DIR"
docker compose up -d

echo "--------------------------------------------------"
echo "✅ CICLO COMPLETADO CON ÉXITO Y VERIFICADO"
echo "📀 Todo a salvo en: $SNAPSHOT_DIR"
