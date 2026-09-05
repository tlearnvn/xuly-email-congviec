<?php
/**
 * LuuMail.php - Tiếp nhận và lưu email do bộ nhận mail gửi lên
 *               (chống trùng, lưu tệp dạng BLOB, tạo công việc)
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class LuuMail
{
    /** Danh mục dùng cho bộ nhận mail */
    public static function danhMuc(): array
    {
        $truong = [];
        foreach (Db::tatCa('SELECT id, ma_truong, ma_chuan, ten_truong, ten_viet_tat, email
                            FROM truong WHERE trang_thai = 1 ORDER BY thu_tu, ma_truong') as $d) {
            $truong[] = [
                'id' => (int)$d['id'], 'ma' => $d['ma_truong'],
                'ma_chuan' => $d['ma_chuan'] ?: Util::chuanHoaMaSo($d['ma_truong']),
                'ten' => $d['ten_truong'], 'ten_viet_tat' => (string)$d['ten_viet_tat'],
                'email' => (string)$d['email'],
            ];
        }
        $nguoi = [];
        foreach (Db::tatCa('SELECT id, ma_nguoi_xu_ly, ho_ten, email
                            FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ma_nguoi_xu_ly') as $d) {
            $nguoi[] = [
                'id' => (int)$d['id'], 'ma' => $d['ma_nguoi_xu_ly'],
                'ma_chuan' => Util::chuanHoaMa($d['ma_nguoi_xu_ly']),
                'ho_ten' => $d['ho_ten'], 'email' => (string)$d['email'],
            ];
        }
        foreach (Db::tatCa('SELECT b.bi_danh, n.id, n.ma_nguoi_xu_ly, n.ho_ten
                            FROM bi_danh_nguoi_xu_ly b JOIN nguoi_xu_ly n ON n.id = b.id_nguoi_xu_ly
                            WHERE n.trang_thai = 1') as $d) {
            $nguoi[] = [
                'id' => (int)$d['id'], 'ma' => $d['ma_nguoi_xu_ly'],
                'ma_chuan' => Util::chuanHoaMa($d['bi_danh']),
                'ho_ten' => $d['ho_ten'], 'email' => '',
            ];
        }
        $vanBan = [];
        foreach (Db::tatCa('SELECT id, ma_van_ban, ma_chuan, ten_van_ban,
                                   IFNULL(id_nguoi_xu_ly_mac_dinh,0) nxl, tu_dong_tao
                            FROM van_ban WHERE trang_thai = 1 ORDER BY ma_van_ban') as $d) {
            $vanBan[] = [
                'id' => (int)$d['id'], 'ma' => $d['ma_van_ban'],
                'ma_chuan' => $d['ma_chuan'] ?: Util::chuanHoaMaSo($d['ma_van_ban']),
                'ten' => $d['ten_van_ban'], 'id_nguoi_mac_dinh' => (int)$d['nxl'],
                'tu_dong_tao' => (int)$d['tu_dong_tao'] === 1,
            ];
        }
        return ['truong' => $truong, 'nguoi_xu_ly' => $nguoi, 'van_ban' => $vanBan];
    }

    /** Tạo (hoặc chạm) một mã văn bản trong danh mục */
    public static function taoVanBanTuDong(string $ma): array
    {
        $ma = trim($ma);
        $maChuan = Util::chuanHoaMaSo($ma);
        if ($ma === '' || $maChuan === '') throw new InvalidArgumentException('Mã văn bản rỗng');

        $co = Db::mot('SELECT * FROM van_ban WHERE ma_van_ban = ? LIMIT 1', [$ma]);
        if ($co) {
            Db::chay('UPDATE van_ban SET lan_gap_cuoi = NOW(), so_lan_gap = so_lan_gap + 1 WHERE id = ?',
                     [$co['id']]);
        } else {
            Db::chen('van_ban', [
                'ma_van_ban'   => $ma,
                'ma_chuan'     => $maChuan,
                'ten_van_ban'  => 'Văn bản/công việc mã ' . $ma,
                'loai'         => 'khac',
                'ky_bao_cao'   => 'khong',
                'bat_buoc_nop' => 0,
                'pham_vi'      => 'tat_ca',
                'tu_dong_tao'  => 1,
                'trang_thai'   => 1,
                'mo_ta'        => 'Tự động tạo khi tiếp nhận mail - cần cập nhật tên chính thức',
                'lan_gap_dau'  => date('Y-m-d H:i:s'),
                'lan_gap_cuoi' => date('Y-m-d H:i:s'),
                'so_lan_gap'   => 1,
                'ngay_tao'     => date('Y-m-d H:i:s'),
                'ngay_cap_nhat'=> date('Y-m-d H:i:s'),
            ]);
            NhatKy::tin('them_van_ban', "Tự thêm mã văn bản mới '{$ma}' từ mail tiếp nhận", 'van_ban', $ma);
            $co = Db::mot('SELECT * FROM van_ban WHERE ma_van_ban = ? LIMIT 1', [$ma]);
        }
        return [
            'id' => (int)$co['id'], 'ma' => $co['ma_van_ban'], 'ma_chuan' => $co['ma_chuan'],
            'ten' => $co['ten_van_ban'], 'id_nguoi_mac_dinh' => (int)$co['id_nguoi_xu_ly_mac_dinh'],
        ];
    }

    // ==================================================================
    //  Tải tệp theo khối
    // ==================================================================
    public static function tepBatDau(string $hash, int $dungLuong, string $mime, string $tenTep): array
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) throw new InvalidArgumentException('Mã băm không hợp lệ');

        $gioiHan = (int)Ung::so('api.dung_luong_toi_da_mb', 25) * 1024 * 1024;
        if ($dungLuong > $gioiHan) {
            throw new RuntimeException('Tệp vượt giới hạn ' . Ung::so('api.dung_luong_toi_da_mb', 25) . ' MB');
        }

        $co = Db::mot('SELECT id, da_hoan_tat FROM tep_du_lieu WHERE hash_file = ?', [$hash]);
        if ($co && (int)$co['da_hoan_tat'] === 1) {
            return ['can_tai' => false, 'id_tep_du_lieu' => (int)$co['id']];
        }
        if ($co) Db::xoa('tep_du_lieu', 'id = ?', [$co['id']]);

        // Dọn các phiên tải dở quá 6 giờ
        Db::chay('DELETE FROM tai_len_tam WHERE ngay_tao < DATE_SUB(NOW(), INTERVAL 6 HOUR)');

        $id = Util::chuoiNgauNhien(12);
        Db::chen('tai_len_tam', [
            'id'         => $id,
            'hash_file'  => $hash,
            'dung_luong' => $dungLuong,
            'da_nhan'    => 0,
            'kieu_mime'  => mb_substr($mime, 0, 150),
            'ten_tep'    => mb_substr($tenTep, 0, 500),
            'du_lieu'    => '',
            'ngay_tao'   => date('Y-m-d H:i:s'),
        ]);
        return ['can_tai' => true, 'id_tai' => $id];
    }

    public static function tepKhoi(string $idTai, string $duLieuB64): int
    {
        $khoi = base64_decode($duLieuB64, true);
        if ($khoi === false) throw new InvalidArgumentException('Khối dữ liệu không hợp lệ');
        $n = Db::chay('UPDATE tai_len_tam SET du_lieu = CONCAT(du_lieu, ?), da_nhan = da_nhan + ?
                       WHERE id = ?', [$khoi, strlen($khoi), $idTai])->rowCount();
        if ($n === 0 && !Db::giaTri('SELECT COUNT(*) FROM tai_len_tam WHERE id = ?', [$idTai], 0)) {
            throw new RuntimeException('Phiên tải tệp không tồn tại hoặc đã hết hạn');
        }
        return (int)Db::giaTri('SELECT da_nhan FROM tai_len_tam WHERE id = ?', [$idTai], 0);
    }

    public static function tepKetThuc(string $idTai): array
    {
        $t = Db::mot('SELECT * FROM tai_len_tam WHERE id = ?', [$idTai]);
        if (!$t) throw new RuntimeException('Phiên tải tệp không tồn tại');

        $thuc = hash('sha256', $t['du_lieu']);
        if ($thuc !== $t['hash_file']) {
            Db::xoa('tai_len_tam', 'id = ?', [$idTai]);
            throw new RuntimeException('Nội dung tệp không khớp mã băm (truyền lỗi), hãy thử lại');
        }

        $co = Db::mot('SELECT id FROM tep_du_lieu WHERE hash_file = ?', [$t['hash_file']]);
        if ($co) {
            $idTep = (int)$co['id'];
            Db::chay('UPDATE tep_du_lieu SET da_hoan_tat = 1 WHERE id = ?', [$idTep]);
        } else {
            $idTep = (int)Db::chen('tep_du_lieu', [
                'hash_file'     => $t['hash_file'],
                'dung_luong'    => strlen($t['du_lieu']),
                'kieu_mime'     => $t['kieu_mime'],
                'noi_dung'      => $t['du_lieu'],
                'da_hoan_tat'   => 1,
                'so_tham_chieu' => 0,
                'ngay_tao'      => date('Y-m-d H:i:s'),
            ]);
        }
        Db::xoa('tai_len_tam', 'id = ?', [$idTai]);
        return ['id_tep_du_lieu' => $idTep, 'dung_luong' => strlen($t['du_lieu'])];
    }

    // ==================================================================
    //  Lưu email
    // ==================================================================
    public static function daLuu(string $gmailId): int
    {
        return (int)Db::giaTri('SELECT id FROM email WHERE gmail_message_id = ? LIMIT 1', [$gmailId], 0);
    }

    /**
     * Phát hiện trùng.
     * @return array [trang, id_email_goc, phien_ban, thong_diep]
     */
    public static function timTrung(string $hashNoiDung, string $hashTongHop): array
    {
        if (!Ung::bat('trung.bat', true) || $hashNoiDung === '') {
            return ['moi', 0, 1, ''];
        }
        $ngay = (int)Ung::so('trung.so_ngay_doi_chieu', 365);
        $ds = Db::tatCa(
            'SELECT id, IFNULL(hash_tong_hop, "") hth, phien_ban, IFNULL(id_email_goc, 0) goc
             FROM email
             WHERE hash_noi_dung = ? AND ngay_gui >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY id ASC LIMIT 200', [$hashNoiDung, $ngay]);
        if (!$ds) return ['moi', 0, 1, ''];

        $idGoc = 0;
        $phienMax = 1;
        foreach ($ds as $d) {
            $id = (int)$d['id'];
            $goc = (int)$d['goc'];
            if (!$idGoc) $idGoc = $goc > 0 ? $goc : $id;
            $phienMax = max($phienMax, (int)$d['phien_ban']);
            if ($d['hth'] === $hashTongHop) {
                return ['trung_lap', $goc > 0 ? $goc : $id, (int)$d['phien_ban'],
                        "Trùng hoàn toàn với email #{$id} (cùng nội dung và cùng tệp đính kèm)"];
            }
        }
        if (!Ung::bat('trung.tao_ban_moi_khi_tep_khac', true)) {
            return ['trung_lap', $idGoc, $phienMax,
                    "Trùng nội dung với email #{$idGoc} (bỏ qua theo thiết lập)"];
        }
        $pb = $phienMax + 1;
        return ['ban_moi', $idGoc, $pb,
                "Trùng nội dung với email #{$idGoc} nhưng tệp đính kèm khác (dung lượng/nội dung) "
                . "- ghi nhận là phiên bản {$pb}"];
    }

    /**
     * Lưu một email hoàn chỉnh kèm tệp và công việc.
     * $em: mảng dữ liệu email; $tep: danh sách tệp; $congViec: danh sách nhóm công việc.
     */
    public static function luu(array $em, array $tep, array $congViec): array
    {
        $gmailId = trim((string)($em['gmail_id'] ?? ''));
        if ($gmailId === '') throw new InvalidArgumentException('Thiếu mã thư Gmail');

        $idCu = self::daLuu($gmailId);
        if ($idCu) {
            return ['trang' => 'da_ton_tai', 'id_email' => $idCu, 'id_email_goc' => 0, 'phien_ban' => 1,
                    'so_cong_viec' => 0, 'so_tep' => 0, 'so_cho_phan_luong' => 0, 'so_byte_moi' => 0,
                    'thong_diep' => "Email đã có trong CSDL (#{$idCu})"];
        }

        [$trang, $idGoc, $phienBan, $thongDiep] =
            self::timTrung((string)($em['hash_noi_dung'] ?? ''), (string)($em['hash_tong_hop'] ?? ''));
        $boQuaCongViec = ($trang === 'trung_lap');

        $trangThaiEmail = 'da_phan_luong';
        if ($trang === 'trung_lap')    $trangThaiEmail = 'trung_lap';
        elseif ($trang === 'ban_moi')  $trangThaiEmail = 'ban_moi';
        else {
            $coCho = false;
            foreach ($congViec as $cv) if (empty($cv['du_thong_tin'])) $coCho = true;
            if ($coCho && count($congViec) === 1) $trangThaiEmail = 'cho_phan_luong';
        }

        $soByteMoi = 0;
        $idTepDuLieu = [];
        foreach ($tep as $i => $t) {
            $h = strtolower((string)($t['hash'] ?? ''));
            if (!preg_match('/^[0-9a-f]{64}$/', $h)) { $idTepDuLieu[$i] = null; continue; }
            $r = Db::mot('SELECT id, dung_luong, so_tham_chieu FROM tep_du_lieu
                          WHERE hash_file = ? AND da_hoan_tat = 1', [$h]);
            if ($r) {
                $idTepDuLieu[$i] = (int)$r['id'];
                Db::chay('UPDATE tep_du_lieu SET so_tham_chieu = so_tham_chieu + 1 WHERE id = ?', [$r['id']]);
            } else {
                $idTepDuLieu[$i] = null;   // tệp chưa được tải lên (bị bỏ qua hoặc lỗi)
            }
        }

        Db::batGiaoDich();
        try {
            $tongDungLuong = 0;
            foreach ($tep as $t) $tongDungLuong += (int)($t['dung_luong'] ?? 0);

            $idEmail = (int)Db::chen('email', [
                'gmail_message_id'  => $gmailId,
                'gmail_thread_id'   => mb_substr((string)($em['thread_id'] ?? ''), 0, 64),
                'message_id_header' => mb_substr((string)($em['message_id_header'] ?? ''), 0, 190),
                'hop_thu'           => mb_substr((string)($em['hop_thu'] ?? ''), 0, 190),
                'tieu_de'           => mb_substr((string)($em['tieu_de'] ?? ''), 0, 990),
                'tieu_de_chuan'     => mb_substr((string)($em['tieu_de_chuan'] ?? ''), 0, 490),
                'nguoi_gui'         => mb_substr((string)($em['nguoi_gui'] ?? ''), 0, 310),
                'ten_nguoi_gui'     => mb_substr((string)($em['ten_nguoi_gui'] ?? ''), 0, 250),
                'nguoi_nhan'        => mb_substr((string)($em['nguoi_nhan'] ?? ''), 0, 2000),
                'ngay_gui'          => self::ngay($em['ngay_gui'] ?? null),
                'ngay_nhan'         => date('Y-m-d H:i:s'),
                'doan_trich'        => mb_substr((string)($em['doan_trich'] ?? ''), 0, 990),
                'noi_dung_text'     => mb_substr((string)($em['noi_dung_text'] ?? ''), 0, 4000000),
                'noi_dung_html'     => mb_substr((string)($em['noi_dung_html'] ?? ''), 0, 4000000),
                'so_tep'            => count($tep),
                'tong_dung_luong'   => $tongDungLuong,
                'hash_noi_dung'     => (string)($em['hash_noi_dung'] ?? ''),
                'hash_tep'          => (string)($em['hash_tep'] ?? ''),
                'hash_tong_hop'     => (string)($em['hash_tong_hop'] ?? ''),
                'trang_thai'        => $trangThaiEmail,
                'id_email_goc'      => $idGoc ?: null,
                'phien_ban'         => $phienBan,
                'ly_do_trung'       => mb_substr($thongDiep, 0, 490),
                'nguon_phan_luong'  => self::nguon((string)($em['nguon_phan_luong'] ?? '')),
                'do_tin_cay'        => (float)($em['do_tin_cay'] ?? 0),
                'ghi_chu_ai'        => mb_substr((string)($em['ghi_chu_ai'] ?? ''), 0, 4000),
                'tu_spam'           => !empty($em['tu_spam']) ? 1 : 0,
                'ngay_tao'          => date('Y-m-d H:i:s'),
                'ngay_cap_nhat'     => date('Y-m-d H:i:s'),
            ]);

            $idTepDinhKem = [];
            foreach ($tep as $i => $t) {
                $ten = (string)($t['ten_tep'] ?? '');
                $ma = $t['ma'] ?? [];
                $idTepDinhKem[$i] = (int)Db::chen('tep_dinh_kem', [
                    'id_email'       => $idEmail,
                    'id_tep_du_lieu' => $idTepDuLieu[$i] ?? null,
                    'ten_tep'        => mb_substr($ten, 0, 490),
                    'ten_tep_chuan'  => mb_substr(Util::boDau($ten), 0, 490),
                    'phan_mo_rong'   => mb_substr(strtolower(pathinfo($ten, PATHINFO_EXTENSION) ?: ''), 0, 20),
                    'kieu_mime'      => mb_substr((string)($t['kieu_mime'] ?? ''), 0, 150),
                    'dung_luong'     => (int)($t['dung_luong'] ?? 0),
                    'hash_file'      => (string)($t['hash'] ?? ''),
                    'ma_truong'      => mb_substr((string)($ma['truong'] ?? ''), 0, 20),
                    'ma_van_ban'     => mb_substr((string)($ma['van_ban'] ?? ''), 0, 30),
                    'ma_nguoi_xu_ly' => mb_substr((string)($ma['nguoi'] ?? ''), 0, 20),
                    'doc_duoc_ma'    => !empty($t['doc_duoc_ma']) ? 1 : 0,
                    'thu_tu'         => (int)($t['thu_tu'] ?? $i),
                    'ngay_tao'       => date('Y-m-d H:i:s'),
                ]);
            }

            $soCongViec = 0;
            $soChoPhanLuong = 0;
            if (!$boQuaCongViec) {
                foreach ($congViec as $cv) {
                    $ma = $cv['ma'] ?? [];
                    $maT = (string)($ma['truong'] ?? '');
                    $maV = (string)($ma['van_ban'] ?? '');
                    $maN = (string)($ma['nguoi'] ?? '');
                    $maHoSo = ($maT !== '' ? $maT : '?') . '_' . ($maV !== '' ? $maV : '?')
                            . '_' . ($maN !== '' ? $maN : '?');
                    $du = !empty($cv['du_thong_tin']);
                    $chiSo = array_map('intval', (array)($cv['chi_so_tep'] ?? []));

                    $dl = 0;
                    foreach ($chiSo as $ci) if (isset($tep[$ci])) $dl += (int)($tep[$ci]['dung_luong'] ?? 0);

                    if ($trang === 'ban_moi' && $du) {
                        Db::chay('UPDATE cong_viec SET la_ban_moi_nhat = 0
                                  WHERE ma_ho_so = ? AND la_ban_moi_nhat = 1', [$maHoSo]);
                    }

                    $idCv = (int)Db::chen('cong_viec', [
                        'id_email'         => $idEmail,
                        'ma_ho_so'         => mb_substr($maHoSo, 0, 80),
                        'id_truong'        => ((int)($cv['id_truong'] ?? 0)) ?: null,
                        'id_van_ban'       => ((int)($cv['id_van_ban'] ?? 0)) ?: null,
                        'id_nguoi_xu_ly'   => ((int)($cv['id_nguoi'] ?? 0)) ?: null,
                        'ma_truong'        => mb_substr($maT, 0, 20),
                        'ma_van_ban'       => mb_substr($maV, 0, 30),
                        'ma_nguoi_xu_ly'   => mb_substr($maN, 0, 20),
                        'tieu_de'          => mb_substr((string)($em['tieu_de'] ?? ''), 0, 990),
                        'trich_yeu'        => mb_substr((string)($em['doan_trich'] ?? ''), 0, 2000),
                        'nguon_phan_luong' => self::nguon((string)($cv['nguon'] ?? '')),
                        'do_tin_cay'       => (float)($cv['do_tin_cay'] ?? 0),
                        'trang_thai'       => $du ? 'cho_xu_ly' : 'cho_phan_luong',
                        'muc_do'           => 'thuong',
                        'ngay_nhan'        => self::ngay($em['ngay_gui'] ?? null),
                        'han_xu_ly'        => self::hanXuLy($em['ngay_gui'] ?? null),
                        'phien_ban'        => $phienBan,
                        'la_ban_moi_nhat'  => 1,
                        'so_tep'           => count($chiSo),
                        'tong_dung_luong'  => $dl,
                        'ghi_chu'          => mb_substr((string)($cv['ghi_chu'] ?? ''), 0, 2000),
                        'ngay_tao'         => date('Y-m-d H:i:s'),
                        'ngay_cap_nhat'    => date('Y-m-d H:i:s'),
                    ]);
                    $soCongViec++;
                    if (!$du) $soChoPhanLuong++;

                    foreach ($chiSo as $ci) {
                        if (!isset($idTepDinhKem[$ci])) continue;
                        Db::chay('INSERT IGNORE INTO cong_viec_tep (id_cong_viec, id_tep_dinh_kem) VALUES (?,?)',
                                 [$idCv, $idTepDinhKem[$ci]]);
                    }
                }
            }

            Db::chotGiaoDich();
        } catch (Throwable $e) {
            Db::huyGiaoDich();
            throw $e;
        }

        return [
            'trang' => $trang === 'moi' ? 'moi' : $trang,
            'id_email' => $idEmail, 'id_email_goc' => $idGoc, 'phien_ban' => $phienBan,
            'so_cong_viec' => $soCongViec, 'so_tep' => count($tep),
            'so_cho_phan_luong' => $soChoPhanLuong, 'so_byte_moi' => $soByteMoi,
            'thong_diep' => $thongDiep,
        ];
    }

    // ------------------------------------------------------------------
    private static function ngay($v): ?string
    {
        if (!$v) return null;
        $t = is_numeric($v) ? (int)$v : strtotime((string)$v);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private static function hanXuLy($ngayGui): ?string
    {
        $n = (int)Ung::so('phanluong.han_xu_ly_ngay', 7);
        if ($n <= 0) return null;
        $t = $ngayGui ? (is_numeric($ngayGui) ? (int)$ngayGui : strtotime((string)$ngayGui)) : time();
        if (!$t) $t = time();
        return date('Y-m-d', $t + $n * 86400);
    }

    private static function nguon(string $n): string
    {
        $hopLe = ['ten_tep', 'tieu_de', 'ai', 'thu_cong', 'mac_dinh', 'nguoi_gui', 'khong_xac_dinh'];
        return in_array($n, $hopLe, true) ? $n : 'khong_xac_dinh';
    }

    // ==================================================================
    //  Phiên đồng bộ
    // ==================================================================
    public static function moPhien(string $hopThu, string $truyVan, string $mayChu): int
    {
        return (int)Db::chen('phien_dong_bo', [
            'bat_dau'    => date('Y-m-d H:i:s'),
            'may_chu'    => mb_substr($mayChu, 0, 100),
            'hop_thu'    => mb_substr($hopThu, 0, 190),
            'truy_van'   => mb_substr($truyVan, 0, 490),
            'trang_thai' => 'dang_chay',
        ]);
    }

    public static function dongPhien(int $id, array $tk, string $trangThai): void
    {
        if ($id <= 0) return;
        Db::capNhat('phien_dong_bo', [
            'ket_thuc'          => date('Y-m-d H:i:s'),
            'so_mail_quet'      => (int)($tk['so_mail_quet'] ?? 0),
            'so_mail_moi'       => (int)($tk['so_mail_moi'] ?? 0),
            'so_mail_trung'     => (int)($tk['so_mail_trung'] ?? 0),
            'so_mail_ban_moi'   => (int)($tk['so_mail_ban_moi'] ?? 0),
            'so_cong_viec'      => (int)($tk['so_cong_viec'] ?? 0),
            'so_tep'            => (int)($tk['so_tep'] ?? 0),
            'so_dung_ai'        => (int)($tk['so_dung_ai'] ?? 0),
            'so_cho_phan_luong' => (int)($tk['so_cho_phan_luong'] ?? 0),
            'so_loi'            => (int)($tk['so_loi'] ?? 0),
            'trang_thai'        => in_array($trangThai, ['dang_chay', 'hoan_tat', 'loi'], true) ? $trangThai : 'hoan_tat',
            'thong_diep'        => mb_substr((string)($tk['thong_diep'] ?? ''), 0, 60000),
        ], 'id = ?', [$id]);
    }
}
