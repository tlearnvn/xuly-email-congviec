<?php
/**
 * View.php - Kết xuất giao diện
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class View
{
    private static $bien = [];
    public static $tieuDe = '';
    public static $trangHienTai = '';

    public static function dat(string $k, $v): void { self::$bien[$k] = $v; }

    // Biến cục bộ của hai hàm dưới đây đều mang tiền tố "__vi" là có chủ ý:
    // extract() dùng EXTR_SKIP nên biến nào của hàm trùng tên với biến của view
    // thì biến của view bị BỎ LẶNG LẼ, view nhận giá trị nội bộ của hàm. Trước
    // đây hàm noiDung() dùng tên $f cho đường dẫn tệp, nên view nào truyền vào
    // biến tên "f" cũng nhận được đường dẫn view thay vì dữ liệu của mình.

    /** Hiển thị một view kèm khung trang */
    public static function hien(string $__viTen, array $__viBien = [], string $__viKhung = 'khung'): void
    {
        $__viDuLieu = array_merge(self::$bien, $__viBien);
        $noiDung = self::noiDung($__viTen, $__viDuLieu);      // khung.php in ra biến $noiDung
        if ($__viKhung === '') { echo $noiDung; return; }
        extract($__viDuLieu, EXTR_SKIP);
        require DUONG_DAN_GOC . '/app/views/layout/' . $__viKhung . '.php';
    }

    /** Lấy nội dung một view dưới dạng chuỗi */
    public static function noiDung(string $__viTen, array $__viBien = []): string
    {
        $__viTep = DUONG_DAN_GOC . '/app/views/' . $__viTen . '.php';
        if (!is_file($__viTep)) throw new RuntimeException('Không tìm thấy giao diện: ' . $__viTen);
        extract(array_merge(self::$bien, $__viBien), EXTR_SKIP);
        ob_start();
        require $__viTep;
        return (string)ob_get_clean();
    }

    /** Chèn một mảnh giao diện */
    public static function manh(string $__viTen, array $__viBien = []): void
    {
        echo self::noiDung($__viTen, $__viBien);
    }

    // ------------------------------------------------------------------
    //  Thành phần dùng lại
    // ------------------------------------------------------------------
    public static function huyHieuTrangThai(string $tt): string
    {
        $lop = [
            'cho_phan_luong' => 'vang', 'cho_xu_ly' => 'xanh-duong', 'dang_xu_ly' => 'tim',
            'da_xu_ly' => 'luc', 'tu_choi' => 'do', 'trung_lap' => 'xam',
            'da_phan_luong' => 'luc', 'ban_moi' => 'tim', 'moi' => 'xanh-duong', 'loi' => 'do',
        ][$tt] ?? 'xam';
        return '<span class="hh hh-' . $lop . '">' . Util::h(Util::tenTrangThai($tt)) . '</span>';
    }

    public static function huyHieuNguon(string $n): string
    {
        $lop = [
            'ten_tep' => 'luc', 'tieu_de' => 'xanh-duong', 'ai' => 'tim',
            'thu_cong' => 'vang', 'mac_dinh' => 'xam', 'nguoi_gui' => 'xanh-duong',
        ][$n] ?? 'xam';
        return '<span class="hh hh-' . $lop . ' hh-nhat">' . Util::h(Util::tenNguon($n)) . '</span>';
    }

    /**
     * Hộp liệt kê link chia sẻ (Google Drive, OneDrive, Dropbox…) dán trong thân thư.
     * Trả về '' nếu thư không có link nào.
     *
     * $khongCoTep = true nghĩa là thư KHÔNG đính kèm tệp nào - kho lưu trữ không
     * giữ được bản tệp. Phải nói thẳng ra, đừng để người xử lý tưởng đã có rồi
     * đi tìm mãi không thấy; và để họ biết mà nhắc trường lần sau đính kèm thẳng.
     */
    public static function hopLinkChiaSe(?string $raw, bool $khongCoTep = false): string
    {
        $ds = Util::dsLinkChiaSe($raw);
        if (!$ds) return '';

        $h = '<div class="the">'
           . '<div class="the-dau"><h2>Link chia sẻ trong thư (' . count($ds) . ')</h2></div>';
        if ($khongCoTep) {
            $h .= '<div class="nhan nhan-canh" style="margin-bottom:.9rem">'
                . '<strong>Thư này không đính kèm tệp.</strong> Kho lưu trữ chỉ giữ được đường dẫn '
                . 'bên dưới, không giữ bản tệp. Nếu người gửi đổi quyền chia sẻ hoặc xoá tệp trên '
                . 'Drive thì hồ sơ coi như mất — nên tải về rồi gửi lại dạng đính kèm, và nhắc đơn '
                . 'vị lần sau đính kèm thẳng vào thư.</div>';
        } else {
            $h .= '<div class="nhan nhan-tin" style="margin-bottom:.9rem">'
                . 'Ngoài tệp đính kèm, trong thân thư còn có đường dẫn chia sẻ. '
                . 'Kho lưu trữ không giữ nội dung của các đường dẫn này.</div>';
        }

        $h .= '<div class="ds-tep">';
        foreach ($ds as $lk) {
            $u = $lk['url'];
            $h .= '<div class="tep tep-lienket">'
                . '<span class="bt bt-lienket">LINK</span>'
                . '<span class="ten"><strong>' . Util::h(Util::nhanLinkChiaSe($lk)) . '</strong>'
                . '<small>' . Util::h(Util::catChu($u, 120)) . '</small></span>'
                . '<span class="viec"><a class="nut nut-phu nho" target="_blank" '
                . 'rel="noopener noreferrer nofollow" href="' . Util::h($u) . '">Mở link</a></span>'
                . '</div>';
        }
        return $h . '</div></div>';
    }

    /** Thanh phân trang */
    public static function phanTrang(int $trang, int $tongDong, int $moiTrang): string
    {
        $tongTrang = max(1, (int)ceil($tongDong / max(1, $moiTrang)));
        if ($tongTrang <= 1) return '';
        $out = '<nav class="phan-trang" aria-label="Phân trang">';
        $out .= '<span class="pt-tt">' . Util::so($tongDong) . ' bản ghi · trang '
              . $trang . '/' . $tongTrang . '</span><span class="pt-nut">';

        $nut = function ($p, $nhan, $hienTai = false, $tat = false) {
            if ($tat) return '<span class="pt-o tat">' . $nhan . '</span>';
            if ($hienTai) return '<span class="pt-o dang">' . $nhan . '</span>';
            return '<a class="pt-o" href="' . Util::h(Util::urlGiu(['trang' => $p])) . '">' . $nhan . '</a>';
        };

        $out .= $nut($trang - 1, '‹', false, $trang <= 1);
        $bd = max(1, $trang - 2);
        $kt = min($tongTrang, $bd + 4);
        $bd = max(1, $kt - 4);
        if ($bd > 1) $out .= $nut(1, '1') . ($bd > 2 ? '<span class="pt-o tat">…</span>' : '');
        for ($i = $bd; $i <= $kt; $i++) $out .= $nut($i, (string)$i, $i === $trang);
        if ($kt < $tongTrang) {
            $out .= ($kt < $tongTrang - 1 ? '<span class="pt-o tat">…</span>' : '')
                  . $nut($tongTrang, (string)$tongTrang);
        }
        $out .= $nut($trang + 1, '›', false, $trang >= $tongTrang);
        $out .= '</span></nav>';
        return $out;
    }

    /** Vẽ biểu đồ tròn (donut) bằng SVG */
    public static function bieuDoTron(array $muc, int $kichThuoc = 168, string $giua = '', string $duoi = ''): string
    {
        $tong = 0;
        foreach ($muc as $m) $tong += max(0, (float)$m['gia_tri']);
        $r = 62;
        $chuVi = 2 * M_PI * $r;
        $svg = '<svg class="bd-tron" viewBox="0 0 160 160" width="' . $kichThuoc . '" height="' . $kichThuoc . '" role="img">';
        $svg .= '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="#eef2f8" stroke-width="22"/>';
        if ($tong > 0) {
            $goc = 0;
            foreach ($muc as $m) {
                $gt = max(0, (float)$m['gia_tri']);
                if ($gt <= 0) continue;
                $phan = $gt / $tong;
                $dai = $phan * $chuVi;
                $svg .= '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="' . Util::h($m['mau'])
                      . '" stroke-width="22" stroke-dasharray="' . round($dai, 2) . ' '
                      . round($chuVi - $dai, 2) . '" stroke-dashoffset="' . round(-$goc * $chuVi, 2)
                      . '" transform="rotate(-90 80 80)" stroke-linecap="butt">'
                      . '<title>' . Util::h($m['nhan'] . ': ' . Util::so($gt)) . '</title></circle>';
                $goc += $phan;
            }
        }
        if ($giua !== '') {
            $svg .= '<text x="80" y="76" text-anchor="middle" class="bd-giua">' . Util::h($giua) . '</text>';
            $svg .= '<text x="80" y="96" text-anchor="middle" class="bd-duoi">' . Util::h($duoi) . '</text>';
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** Vẽ biểu đồ cột dọc bằng SVG */
    public static function bieuDoCot(array $duLieu, string $mau = '#1e5eff', int $cao = 210): string
    {
        if (!$duLieu) return '<p class="rong-nho">Chưa có dữ liệu.</p>';
        $max = 1;
        foreach ($duLieu as $d) $max = max($max, (float)$d['gia_tri']);
        $n = count($duLieu);
        // Khung nhìn có tỉ lệ cố định để hình không bị kéo dãn trên màn hình rộng
        $rong = 1000;
        $chanTren = 24;
        $chanDuoi = 34;
        $caoCot = $cao - $chanTren - $chanDuoi;
        $buoc = $rong / $n;
        $rongCot = min(78, $buoc * 0.5);

        $svg = '<svg class="bd-cot" viewBox="0 0 ' . $rong . ' ' . $cao . '" role="img">';
        for ($i = 0; $i <= 3; $i++) {
            $y = $chanTren + $caoCot * $i / 3;
            $svg .= '<line x1="0" y1="' . round($y, 1) . '" x2="' . $rong . '" y2="' . round($y, 1)
                  . '" stroke="#eef2f8" stroke-width="1"/>';
        }
        $i = 0;
        foreach ($duLieu as $d) {
            $gt = (float)$d['gia_tri'];
            $h = $max > 0 ? ($gt / $max) * $caoCot : 0;
            $x = $i * $buoc + ($buoc - $rongCot) / 2;
            $y = $chanTren + $caoCot - $h;
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($rongCot, 1)
                  . '" height="' . round(max(1, $h), 1) . '" rx="4" fill="' . Util::h($d['mau'] ?? $mau) . '">'
                  . '<title>' . Util::h($d['nhan'] . ': ' . Util::so($gt)) . '</title></rect>';
            if ($gt > 0) {
                $svg .= '<text x="' . round($x + $rongCot / 2, 1) . '" y="' . round($y - 4, 1)
                      . '" text-anchor="middle" class="bd-so">' . Util::h(Util::so($gt)) . '</text>';
            }
            $svg .= '<text x="' . round($x + $rongCot / 2, 1) . '" y="' . ($cao - 10)
                  . '" text-anchor="middle" class="bd-nhan">' . Util::h($d['nhan_ngan'] ?? $d['nhan']) . '</text>';
            $i++;
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** Thanh tiến độ ngang */
    public static function thanhTienDo(float $phanTram, string $mau = '#12a45c'): string
    {
        $p = max(0, min(100, $phanTram));
        return '<div class="thanh-nho"><i style="width:' . round($p, 1) . '%;background:' . Util::h($mau) . '"></i></div>';
    }
}
