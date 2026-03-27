# Makefile para el Búnker de UNIT3D_Docker (NOBS)
.PHONY: help install up stop restart status backup health logs clean

help:
	@echo "--- 🛡️ UNIT3D BÚNKER CONTROL ---"
	@echo "make install  - Instalación fresca (Carpetas, Permisos, Build)"
	@echo "make up       - Levantar contenedores (Producción)"
	@echo "make stop     - Parar contenedores"
	@echo "make restart  - Reiniciar app y web"
	@echo "make status   - Ver estado de los contenedores"
	@echo "make backup   - Ejecutar backup quirúrgico (Búnker manual)"
	@echo "make health   - Verificar salud HTTP (Puerto 8008)"
	@echo "make logs     - Ver logs de la app en vivo"
	@echo "make clean    - Limpiar caché de Laravel (Producción)"

install:
	@echo "🎬 Inicializando el búnker..."
	mkdir -p storage/framework/{cache/data,sessions,views} storage/app/public storage/logs bootstrap/cache backups
	chmod -R 775 storage bootstrap/cache
	docker compose build
	docker compose up -d
	@echo "✅ Búnker listo. Usa 'make up' para levantar."

up:
	docker compose up -d

stop:
	docker compose stop

restart:
	docker compose restart app web

status:
	docker compose ps

backup:
	sudo ./backup.sh

health:
	./health_check.sh

logs:
	docker compose logs -f app

clean:
	docker exec unit3d-app php artisan config:clear
	docker exec unit3d-app php artisan route:clear
	docker exec unit3d-app php artisan view:clear
	docker exec unit3d-app php artisan config:cache
	docker exec unit3d-app php artisan route:cache
	docker exec unit3d-app php artisan view:cache
