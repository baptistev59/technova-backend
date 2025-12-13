@echo off
chcp 65001 >NUL

echo ========================================
echo Redemarrage du serveur TechNova...
echo ========================================

echo Arret via Symfony CLI...
wsl.exe -d Ubuntu -- bash -lc ". ~/.profile && cd /home/baptiste/projects/dwwm/technova-backend && /home/baptiste/.symfony5/bin/symfony server:stop" >NUL 2>&1
echo Forcage de l'arret du serveur PHP...
wsl.exe -d Ubuntu -- pkill -f "php -S 127.0.0.1:8000" >NUL 2>&1

echo Nettoyage du cache Symfony...
wsl.exe -d Ubuntu -- bash -lc ". ~/.profile && cd /home/baptiste/projects/dwwm/technova-backend && php bin/console cache:clear"

echo Relance du serveur...
wsl.exe -d Ubuntu -- bash -lc ". ~/.profile && cd /home/baptiste/projects/dwwm/technova-backend && /home/baptiste/.symfony5/bin/symfony serve --no-tls"

echo ========================================
echo Serveur TechNova operationnel !
echo API dispo : http://127.0.0.1:8000
echo ========================================
pause
