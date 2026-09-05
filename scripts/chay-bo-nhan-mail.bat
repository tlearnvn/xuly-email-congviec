@echo off
REM =====================================================================
REM  Bo nhan mail - He thong phan luong Mail cong vu
REM  Thiet ke boi Truong Anh Tuan
REM ---------------------------------------------------------------------
REM  Nhay doi vao tep nay de mo bang dieu khien trong trinh duyet.
REM =====================================================================
chcp 65001 > nul
cd /d "%~dp0"

if not exist "mailrouter.ini" (
    if exist "mailrouter.example.ini" (
        copy /Y "mailrouter.example.ini" "mailrouter.ini" > nul
        echo Da tao tep cau hinh mailrouter.ini tu ban mau.
    )
)

mailrouter.exe giao-dien
if errorlevel 1 (
    echo.
    echo Chuong trinh ket thuc voi loi. Xem chi tiet trong thu muc nhat_ky.
    pause
)
