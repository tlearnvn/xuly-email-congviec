@echo off
REM Chay mot phien nhan mail roi thoat - dung cho Task Scheduler cua Windows
chcp 65001 > nul
cd /d "%~dp0"
mailrouter.exe nhan
