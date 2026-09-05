# =====================================================================
#  nhung_tai_nguyen.cmake
#  Nhúng các tệp giao diện (HTML/CSS/JS) thành mã C++ để đóng gói vào
#  một tệp thực thi duy nhất.
#
#  Cách dùng (chế độ script):
#     cmake -DTHU_MUC=<đường dẫn webui> -DDAU_RA=<tệp .cpp> -P nhung_tai_nguyen.cmake
# =====================================================================

if(NOT DEFINED THU_MUC OR NOT DEFINED DAU_RA)
  message(FATAL_ERROR "Thiếu tham số THU_MUC hoặc DAU_RA")
endif()

set(DS_TEP "index.html;app.css;app.js")

set(KIEU_index.html "text/html; charset=utf-8")
set(KIEU_app.css    "text/css; charset=utf-8")
set(KIEU_app.js     "application/javascript; charset=utf-8")

set(NOI_DUNG "// Tệp này được sinh tự động bởi nhung_tai_nguyen.cmake - KHÔNG sửa tay\n")
string(APPEND NOI_DUNG "#include \"webui.h\"\n\nnamespace mr {\n\n")

set(BANG "")
set(SO 0)

foreach(TEN ${DS_TEP})
  set(DUONG "${THU_MUC}/${TEN}")
  if(NOT EXISTS "${DUONG}")
    message(FATAL_ERROR "Không tìm thấy tệp giao diện: ${DUONG}")
  endif()
  file(READ "${DUONG}" HEX HEX)
  string(REGEX REPLACE "([0-9a-f][0-9a-f])" "0x\\1," BYTES "${HEX}")
  # Xuống dòng sau mỗi 16 byte cho dễ đọc và tránh dòng quá dài
  string(REGEX REPLACE "((0x..,){16})" "\\1\n" BYTES "${BYTES}")
  string(LENGTH "${HEX}" DO_DAI_HEX)
  math(EXPR DO_DAI "${DO_DAI_HEX} / 2")

  string(MAKE_C_IDENTIFIER "${TEN}" IDENT)
  string(TOUPPER "${IDENT}" IDENT)

  string(APPEND NOI_DUNG "static const unsigned char TN_${IDENT}[] = {\n${BYTES}\n0x00};\n\n")
  string(APPEND BANG "    { \"/${TEN}\", TN_${IDENT}, ${DO_DAI}u, \"${KIEU_${TEN}}\" },\n")
  math(EXPR SO "${SO} + 1")
endforeach()

string(APPEND NOI_DUNG "const TaiNguyen TAI_NGUYEN[] = {\n${BANG}    { nullptr, nullptr, 0u, nullptr }\n};\n")
string(APPEND NOI_DUNG "const unsigned int SO_TAI_NGUYEN = ${SO}u;\n\n} // namespace mr\n")

file(WRITE "${DAU_RA}" "${NOI_DUNG}")
message(STATUS "Đã nhúng ${SO} tệp giao diện vào ${DAU_RA}")
