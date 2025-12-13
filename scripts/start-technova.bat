@echo off
chcp 65001 >NUL

echo ========================================
echo Demarrage du serveur TechNova...
echo ========================================

wsl.exe -d Ubuntu -- bash -lc ". ~/.profile && cd /home/baptiste/projects/dwwm/technova-backend && /home/baptiste/.symfony5/bin/symfony serve --no-tls"

pause
