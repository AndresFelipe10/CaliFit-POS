@echo off
title CaliFit POS - Acceso en Red y Remoto
chcp 65001 > nul
cls
echo =====================================================================
echo          SABORCALIFIT POS - SERVIDOR DE ACCESO REMOTO Y RED
echo =====================================================================
echo.
echo [1] Acceso en la MISMA red Wi-Fi (Tablet / Celular local):
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4" ^| findstr "192.168."') do (
    set IP=%%a
)
echo     http:%IP:~0%:8081
echo.
echo [2] Iniciando tunel publico Cloudflare (para OTRA red Wi-Fi o datos)...
echo ---------------------------------------------------------------------
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://127.0.0.1:8081
pause
