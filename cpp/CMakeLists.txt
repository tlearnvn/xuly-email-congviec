# =====================================================================
#  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ - BỘ NHẬN MAIL (MailRouter)
#  Thiết kế bởi Trương Anh Tuấn
#
#  Biên dịch Linux 64-bit:
#     cmake -B build -DCMAKE_BUILD_TYPE=Release && cmake --build build -j
#  Biên dịch chéo Windows 64-bit (mingw-w64):
#     cmake -B build-win -DCMAKE_TOOLCHAIN_FILE=cmake/mingw64.cmake \
#           -DCMAKE_BUILD_TYPE=Release && cmake --build build-win -j
# =====================================================================
cmake_minimum_required(VERSION 3.12)
project(mailrouter VERSION 1.0.0 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

if(NOT CMAKE_BUILD_TYPE)
  set(CMAKE_BUILD_TYPE Release CACHE STRING "" FORCE)
endif()

# --------------------------- Tài nguyên giao diện ---------------------------
set(WEBUI_DIR   ${CMAKE_CURRENT_SOURCE_DIR}/webui)
set(WEBUI_FILES ${WEBUI_DIR}/index.html ${WEBUI_DIR}/app.css ${WEBUI_DIR}/app.js)
set(WEBUI_GEN   ${CMAKE_CURRENT_BINARY_DIR}/webui_tai_nguyen.cpp)

add_custom_command(
  OUTPUT  ${WEBUI_GEN}
  COMMAND ${CMAKE_COMMAND} -DTHU_MUC=${WEBUI_DIR} -DDAU_RA=${WEBUI_GEN}
          -P ${CMAKE_CURRENT_SOURCE_DIR}/cmake/nhung_tai_nguyen.cmake
  DEPENDS ${WEBUI_FILES} ${CMAKE_CURRENT_SOURCE_DIR}/cmake/nhung_tai_nguyen.cmake
  COMMENT "Nhúng tài nguyên giao diện vào tệp thực thi"
  VERBATIM)

# ------------------------------- Mã nguồn -----------------------------------
set(NGUON
  src/main.cpp
  src/util.cpp
  src/json.cpp
  src/crypto.cpp
  src/net.cpp
  src/cauhinh.cpp
  src/nhatky.cpp
  src/mysql_client.cpp
  src/http_client.cpp
  src/http_server.cpp
  src/kholuutru.cpp
  src/gmail.cpp
  src/phanluong.cpp
  src/ai.cpp
  src/ungdung.cpp
  src/webui.cpp
  ${WEBUI_GEN}
)

add_executable(mailrouter ${NGUON})
target_include_directories(mailrouter PRIVATE include)

if(MSVC)
  target_compile_options(mailrouter PRIVATE /W3 /utf-8 /EHsc)
else()
  target_compile_options(mailrouter PRIVATE
    -Wall -Wextra -Wno-unused-parameter -finput-charset=UTF-8 -fexec-charset=UTF-8)
endif()

# ------------------------------ Thư viện ------------------------------------
find_package(Threads REQUIRED)
target_link_libraries(mailrouter PRIVATE Threads::Threads)

if(WIN32)
  target_link_libraries(mailrouter PRIVATE ws2_32 winhttp shlwapi)
  # Đóng gói tĩnh để tệp .exe chạy độc lập, không cần DLL của MinGW
  if(CMAKE_CXX_COMPILER_ID MATCHES "GNU")
    target_link_options(mailrouter PRIVATE
      -static -static-libgcc -static-libstdc++ -Wl,--gc-sections)
  endif()
else()
  target_link_libraries(mailrouter PRIVATE ${CMAKE_DL_LIBS})
  target_link_options(mailrouter PRIVATE -Wl,--gc-sections)
endif()

if(CMAKE_BUILD_TYPE STREQUAL "Release" AND NOT MSVC)
  target_compile_options(mailrouter PRIVATE -O2 -ffunction-sections -fdata-sections)
endif()

# ------------------------------ Cài đặt -------------------------------------
install(TARGETS mailrouter RUNTIME DESTINATION .)
install(FILES ${CMAKE_CURRENT_SOURCE_DIR}/mailrouter.example.ini DESTINATION .)
