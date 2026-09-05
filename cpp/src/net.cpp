// =====================================================================
//  net.cpp - Lớp socket mỏng dùng chung
// =====================================================================
#include "net.h"

#include <cstring>
#include <cstdio>
#include <mutex>

#ifndef _WIN32
  #include <errno.h>
  #include <fcntl.h>
  #include <sys/select.h>
#endif

namespace mr {

void netKhoiTao() {
#ifdef _WIN32
    static std::once_flag co;
    std::call_once(co, []() {
        WSADATA wsa;
        WSAStartup(MAKEWORD(2, 2), &wsa);
    });
#endif
}

void netDong(socket_t s) {
    if (s == MR_SOCK_INVALID) return;
#ifdef _WIN32
    closesocket(s);
#else
    ::close(s);
#endif
}

std::string netLoiCuoi() {
#ifdef _WIN32
    int e = WSAGetLastError();
    char buf[64];
    std::snprintf(buf, sizeof(buf), "mã lỗi socket %d", e);
    return buf;
#else
    return std::string(std::strerror(errno));
#endif
}

static void datKhongChan(socket_t s, bool khongChan) {
#ifdef _WIN32
    u_long m = khongChan ? 1 : 0;
    ioctlsocket(s, FIONBIO, &m);
#else
    int f = fcntl(s, F_GETFL, 0);
    if (f < 0) return;
    fcntl(s, F_SETFL, khongChan ? (f | O_NONBLOCK) : (f & ~O_NONBLOCK));
#endif
}

void netDatTimeout(socket_t s, int giay) {
    if (giay <= 0) return;
#ifdef _WIN32
    DWORD ms = (DWORD)(giay * 1000);
    setsockopt(s, SOL_SOCKET, SO_RCVTIMEO, (const char*)&ms, sizeof(ms));
    setsockopt(s, SOL_SOCKET, SO_SNDTIMEO, (const char*)&ms, sizeof(ms));
#else
    struct timeval tv;
    tv.tv_sec = giay;
    tv.tv_usec = 0;
    setsockopt(s, SOL_SOCKET, SO_RCVTIMEO, (const char*)&tv, sizeof(tv));
    setsockopt(s, SOL_SOCKET, SO_SNDTIMEO, (const char*)&tv, sizeof(tv));
#endif
}

socket_t netKetNoi(const std::string& host, int cong, int timeoutGiay, std::string& loi) {
    netKhoiTao();
    char congStr[16];
    std::snprintf(congStr, sizeof(congStr), "%d", cong);

    struct addrinfo hints;
    std::memset(&hints, 0, sizeof(hints));
    hints.ai_family = AF_UNSPEC;
    hints.ai_socktype = SOCK_STREAM;
    hints.ai_protocol = IPPROTO_TCP;

    struct addrinfo* res = nullptr;
    int rc = getaddrinfo(host.c_str(), congStr, &hints, &res);
    if (rc != 0 || !res) {
        loi = "Không phân giải được tên máy chủ '" + host + "'";
        return MR_SOCK_INVALID;
    }

    socket_t sk = MR_SOCK_INVALID;
    for (struct addrinfo* ai = res; ai; ai = ai->ai_next) {
        sk = ::socket(ai->ai_family, ai->ai_socktype, ai->ai_protocol);
        if (sk == MR_SOCK_INVALID) continue;

        if (timeoutGiay > 0) datKhongChan(sk, true);
        int r = ::connect(sk, ai->ai_addr, (int)ai->ai_addrlen);
        bool ok = (r == 0);
        if (!ok && timeoutGiay > 0) {
#ifdef _WIN32
            bool dangCho = (WSAGetLastError() == WSAEWOULDBLOCK);
#else
            bool dangCho = (errno == EINPROGRESS);
#endif
            if (dangCho) {
                fd_set wf;
                FD_ZERO(&wf);
                FD_SET(sk, &wf);
                struct timeval tv;
                tv.tv_sec = timeoutGiay;
                tv.tv_usec = 0;
                int sel = ::select((int)sk + 1, nullptr, &wf, nullptr, &tv);
                if (sel > 0) {
                    int err = 0;
                    socklen_t len = sizeof(err);
                    if (getsockopt(sk, SOL_SOCKET, SO_ERROR, (char*)&err, &len) == 0 && err == 0) ok = true;
                }
            }
        }
        if (timeoutGiay > 0) datKhongChan(sk, false);

        if (ok) {
            int one = 1;
            setsockopt(sk, IPPROTO_TCP, TCP_NODELAY, (const char*)&one, sizeof(one));
            netDatTimeout(sk, timeoutGiay);
            freeaddrinfo(res);
            return sk;
        }
        netDong(sk);
        sk = MR_SOCK_INVALID;
    }
    freeaddrinfo(res);
    loi = "Không kết nối được tới " + host + ":" + congStr + " (" + netLoiCuoi() + ")";
    return MR_SOCK_INVALID;
}

bool netGuiHet(socket_t s, const void* du_lieu, size_t n) {
    const char* p = (const char*)du_lieu;
    size_t daGui = 0;
    while (daGui < n) {
        int lan = (int)((n - daGui > 1 << 20) ? (1 << 20) : (n - daGui));
#ifdef _WIN32
        int r = ::send(s, p + daGui, lan, 0);
#else
        int r = (int)::send(s, p + daGui, lan, MSG_NOSIGNAL);
#endif
        if (r <= 0) return false;
        daGui += (size_t)r;
    }
    return true;
}

long netNhan(socket_t s, void* dem, size_t n) {
#ifdef _WIN32
    int r = ::recv(s, (char*)dem, (int)n, 0);
#else
    long r = ::recv(s, dem, n, 0);
#endif
    return (long)r;
}

bool netNhanHet(socket_t s, void* dem, size_t n) {
    char* p = (char*)dem;
    size_t daNhan = 0;
    while (daNhan < n) {
        long r = netNhan(s, p + daNhan, n - daNhan);
        if (r <= 0) return false;
        daNhan += (size_t)r;
    }
    return true;
}

} // namespace mr
