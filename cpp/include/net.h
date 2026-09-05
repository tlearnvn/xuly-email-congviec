// =====================================================================
//  net.h - Lớp socket mỏng dùng chung (Windows Winsock / POSIX)
// =====================================================================
#pragma once

#include <string>
#include <cstdint>

#ifdef _WIN32
  #ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
  #endif
  #include <winsock2.h>
  #include <ws2tcpip.h>
  typedef SOCKET socket_t;
  #define MR_SOCK_INVALID INVALID_SOCKET
#else
  #include <sys/socket.h>
  #include <netinet/in.h>
  #include <netinet/tcp.h>
  #include <arpa/inet.h>
  #include <netdb.h>
  #include <unistd.h>
  typedef int socket_t;
  #define MR_SOCK_INVALID (-1)
#endif

namespace mr {

// Khởi tạo thư viện mạng (chỉ có tác dụng trên Windows), an toàn khi gọi nhiều lần
void netKhoiTao();

// Đóng socket
void netDong(socket_t s);

// Kết nối TCP có thời gian chờ (giây). Trả về socket hoặc MR_SOCK_INVALID.
socket_t netKetNoi(const std::string& host, int cong, int timeoutGiay, std::string& loi);

// Gửi/nhận đầy đủ. Trả về false nếu lỗi hoặc đối tác đóng kết nối.
bool netGuiHet(socket_t s, const void* du_lieu, size_t n);
bool netNhanHet(socket_t s, void* dem, size_t n);
// Nhận tối đa n byte, trả về số byte thực nhận (0 = đóng, -1 = lỗi)
long netNhan(socket_t s, void* dem, size_t n);

// Đặt thời gian chờ đọc/ghi (giây)
void netDatTimeout(socket_t s, int giay);
// Mô tả lỗi socket hiện tại
std::string netLoiCuoi();

} // namespace mr
