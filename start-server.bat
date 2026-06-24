@echo off
cls
echo ========================================
echo   Demarrage du Serveur Symfony
echo ========================================
echo.

echo [1/3] Nettoyage du cache...
php bin/console cache:clear --no-warmup
echo ✓ Cache nettoye
echo.

echo [2/3] Warmup du cache...
php bin/console cache:warmup
echo ✓ Cache prechauffe
echo.

echo [3/3] Demarrage du serveur...
echo.
echo Serveur demarre sur : http://127.0.0.1:8000
echo Mailpit Web UI : http://localhost:56413
echo.
echo Appuyez sur CTRL+C pour arreter
echo.
php -S 127.0.0.1:8000 -t public -d max_execution_time=300 -d memory_limit=512M
