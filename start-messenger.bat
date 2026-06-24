@echo off
echo ========================================
echo   Demarrage du Worker Messenger
echo ========================================
echo.
echo Consommation des messages depuis "async"...
echo Appuyez sur CTRL+C pour arreter
echo.
php bin/console messenger:consume async -vv
