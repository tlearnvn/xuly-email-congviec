# =====================================================================
#  Bộ công cụ biên dịch chéo Windows 64-bit bằng mingw-w64
#  Dùng: cmake -B build-win -DCMAKE_TOOLCHAIN_FILE=cmake/mingw64.cmake
# =====================================================================
set(CMAKE_SYSTEM_NAME Windows)
set(CMAKE_SYSTEM_PROCESSOR x86_64)

set(TIEN_TO x86_64-w64-mingw32)
set(CMAKE_C_COMPILER   ${TIEN_TO}-gcc)
set(CMAKE_CXX_COMPILER ${TIEN_TO}-g++)
set(CMAKE_RC_COMPILER  ${TIEN_TO}-windres)
set(CMAKE_AR           ${TIEN_TO}-ar)
set(CMAKE_RANLIB       ${TIEN_TO}-ranlib)

set(CMAKE_FIND_ROOT_PATH /usr/${TIEN_TO})
set(CMAKE_FIND_ROOT_PATH_MODE_PROGRAM NEVER)
set(CMAKE_FIND_ROOT_PATH_MODE_LIBRARY ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_INCLUDE ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_PACKAGE ONLY)

# Windows 7 trở lên
add_compile_definitions(_WIN32_WINNT=0x0601 WINVER=0x0601)
