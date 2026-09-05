<?php
/** Điều khiển: Danh mục trường / người xử lý / mã văn bản */

Auth::batBuocAdmin();

$loai = (string)Util::lay('loai', 'truong');
if (!in_array($loai, ['truong', 'nguoi', 'van_ban'], true)) $loai = 'truong';

// =====================================================================
//  Xử lý biểu mẫu
// =====================================================================
if (Util::laPost()) {
    Util::batBuocToken();
    $viec = Util::gui('viec');
    $id   = (int)Util::gui('id', 0);

    try {
        // ---------------------------- TRƯỜNG ----------------------------
        if ($loai === 'truong') {
            if ($viec === 'luu') {
                $ma = trim(Util::gui('ma_truong'));
                if ($ma === '') throw new InvalidArgumentException('Mã trường không được để trống.');
                $d = [
                    'ma_truong'     => mb_substr($ma, 0, 20),
                    'ma_chuan'      => Util::chuanHoaMaSo($ma),
                    'ten_truong'    => mb_substr(Util::gui('ten_truong'), 0, 255),
                    'ten_viet_tat'  => mb_substr(Util::gui('ten_viet_tat'), 0, 100),
                    'cap_hoc'       => mb_substr(Util::gui('cap_hoc'), 0, 50),
                    'dia_ban'       => mb_substr(Util::gui('dia_ban'), 0, 150),
                    'email'         => mb_substr(Util::gui('email'), 0, 191),
                    'dien_thoai'    => mb_substr(Util::gui('dien_thoai'), 0, 30),
                    'nguoi_dai_dien'=> mb_substr(Util::gui('nguoi_dai_dien'), 0, 150),
                    'thu_tu'        => (int)Util::gui('thu_tu', 0),
                    'trang_thai'    => Util::gui('trang_thai') === '1' ? 1 : 0,
                    'ghi_chu'       => mb_substr(Util::gui('ghi_chu'), 0, 2000),
                    'ngay_cap_nhat' => date('Y-m-d H:i:s'),
                ];
                if ($d['ten_truong'] === '') throw new InvalidArgumentException('Tên trường không được để trống.');
                if ($id) {
                    Db::capNhat('truong', $d, 'id = ?', [$id]);
                    NhatKy::tin('sua_danh_muc', "Sửa trường {$ma}", 'truong', (string)$id);
                    Util::nhan('Đã cập nhật thông tin trường.', 'ok');
                } else {
                    $d['ngay_tao'] = date('Y-m-d H:i:s');
                    $id = (int)Db::chen('truong', $d);
                    NhatKy::tin('them_danh_muc', "Thêm trường {$ma}", 'truong', (string)$id);
                    Util::nhan('Đã thêm trường mới.', 'ok');
                }
            } elseif ($viec === 'xoa' && $id) {
                $co = (int)Db::giaTri('SELECT COUNT(*) FROM cong_viec WHERE id_truong = ?', [$id], 0);
                if ($co) {
                    Db::capNhat('truong', ['trang_thai' => 0, 'ngay_cap_nhat' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
                    Util::nhan("Trường đang có {$co} văn bản nên chỉ được chuyển sang trạng thái ngưng hoạt động.", 'canh');
                } else {
                    Db::xoa('truong', 'id = ?', [$id]);
                    Util::nhan('Đã xoá trường khỏi danh mục.', 'ok');
                }
                NhatKy::canhBao('xoa_danh_muc', 'Xoá/ngưng trường', 'truong', (string)$id);
            } elseif ($viec === 'nhap_hang_loat') {
                $so = 0;
                foreach (preg_split('/\r\n|\r|\n/', (string)Util::gui('du_lieu')) as $dong) {
                    $dong = trim($dong);
                    if ($dong === '') continue;
                    $c = array_map('trim', preg_split('/\t|\s*[;|]\s*/', $dong));
                    if (count($c) < 2 || $c[0] === '') continue;
                    Db::chay(
                        'INSERT INTO truong (ma_truong, ma_chuan, ten_truong, cap_hoc, dia_ban, email,
                                             thu_tu, trang_thai, ngay_tao, ngay_cap_nhat)
                         VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())
                         ON DUPLICATE KEY UPDATE ten_truong = VALUES(ten_truong),
                             cap_hoc = VALUES(cap_hoc), dia_ban = VALUES(dia_ban),
                             email = VALUES(email), ngay_cap_nhat = NOW()',
                        [mb_substr($c[0], 0, 20), Util::chuanHoaMaSo($c[0]), mb_substr($c[1], 0, 255),
                         mb_substr($c[2] ?? '', 0, 50), mb_substr($c[3] ?? '', 0, 150),
                         mb_substr($c[4] ?? '', 0, 191), $so]);
                    $so++;
                }
                NhatKy::tin('nhap_danh_muc', "Nhập hàng loạt {$so} trường", 'truong');
                Util::nhan("Đã nhập/cập nhật {$so} trường.", 'ok');
            }

        // ------------------------- NGƯỜI XỬ LÝ -------------------------
        } elseif ($loai === 'nguoi') {
            if ($viec === 'luu') {
                $ma  = strtoupper(trim(Util::gui('ma_nguoi_xu_ly')));
                $tdn = strtolower(trim(Util::gui('ten_dang_nhap')));
                if ($ma === '' || $tdn === '') throw new InvalidArgumentException('Mã và tên đăng nhập không được để trống.');
                if (!preg_match('/^[a-z0-9._\-]{3,64}$/', $tdn)) {
                    throw new InvalidArgumentException('Tên đăng nhập chỉ gồm chữ thường, số, dấu chấm, gạch dưới, gạch ngang (3-64 ký tự).');
                }
                $d = [
                    'ma_nguoi_xu_ly' => mb_substr($ma, 0, 20),
                    'ho_ten'         => mb_substr(Util::gui('ho_ten'), 0, 150),
                    'ten_dang_nhap'  => $tdn,
                    'email'          => mb_substr(Util::gui('email'), 0, 191),
                    'dien_thoai'     => mb_substr(Util::gui('dien_thoai'), 0, 30),
                    'chuc_vu'        => mb_substr(Util::gui('chuc_vu'), 0, 150),
                    'phong_ban'      => mb_substr(Util::gui('phong_ban'), 0, 150),
                    'vai_tro'        => in_array(Util::gui('vai_tro'), ['admin', 'lanh_dao', 'nguoi_xu_ly'], true)
                                        ? Util::gui('vai_tro') : 'nguoi_xu_ly',
                    'nhan_tat_ca'    => Util::gui('nhan_tat_ca') === '1' ? 1 : 0,
                    'trang_thai'     => Util::gui('trang_thai') === '1' ? 1 : 0,
                    'ghi_chu'        => mb_substr(Util::gui('ghi_chu'), 0, 2000),
                    'ngay_cap_nhat'  => date('Y-m-d H:i:s'),
                ];
                if ($d['ho_ten'] === '') throw new InvalidArgumentException('Họ tên không được để trống.');
                $mk = (string)($_POST['mat_khau'] ?? '');
                if ($mk !== '') {
                    if (mb_strlen($mk) < 6) throw new InvalidArgumentException('Mật khẩu phải từ 6 ký tự trở lên.');
                    $d['mat_khau'] = password_hash($mk, PASSWORD_DEFAULT);
                    $d['doi_mat_khau'] = Util::gui('buoc_doi_mk') === '1' ? 1 : 0;
                }
                if ($id) {
                    if ($id === Auth::id() && $d['vai_tro'] !== 'admin') {
                        throw new InvalidArgumentException('Không thể tự hạ quyền quản trị của chính mình.');
                    }
                    Db::capNhat('nguoi_xu_ly', $d, 'id = ?', [$id]);
                    NhatKy::tin('sua_danh_muc', "Sửa người xử lý {$ma}", 'nguoi_xu_ly', (string)$id);
                    Util::nhan('Đã cập nhật người xử lý.', 'ok');
                } else {
                    if ($mk === '') throw new InvalidArgumentException('Phải đặt mật khẩu cho tài khoản mới.');
                    $d['ngay_tao'] = date('Y-m-d H:i:s');
                    $d['so_lan_dang_nhap'] = 0;
                    $id = (int)Db::chen('nguoi_xu_ly', $d);
                    NhatKy::tin('them_danh_muc', "Thêm người xử lý {$ma}", 'nguoi_xu_ly', (string)$id);
                    Util::nhan('Đã thêm người xử lý mới.', 'ok');
                }
                // Bí danh
                $biDanh = array_filter(array_map('trim', explode(',', (string)Util::gui('bi_danh'))));
                Db::xoa('bi_danh_nguoi_xu_ly', 'id_nguoi_xu_ly = ?', [$id]);
                foreach ($biDanh as $b) {
                    $b = Util::chuanHoaMa($b);
                    if ($b === '' || $b === Util::chuanHoaMa($ma)) continue;
                    try {
                        Db::chen('bi_danh_nguoi_xu_ly',
                                 ['id_nguoi_xu_ly' => $id, 'bi_danh' => mb_substr($b, 0, 30),
                                  'ngay_tao' => date('Y-m-d H:i:s')]);
                    } catch (Throwable $e) { /* bí danh trùng - bỏ qua */ }
                }
            } elseif ($viec === 'xoa' && $id) {
                if ($id === Auth::id()) throw new InvalidArgumentException('Không thể xoá tài khoản đang đăng nhập.');
                $co = (int)Db::giaTri('SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ?', [$id], 0);
                if ($co) {
                    Db::capNhat('nguoi_xu_ly', ['trang_thai' => 0, 'ngay_cap_nhat' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
                    Util::nhan("Người này đang giữ {$co} văn bản nên chỉ được chuyển sang trạng thái ngưng.", 'canh');
                } else {
                    Db::xoa('nguoi_xu_ly', 'id = ?', [$id]);
                    Util::nhan('Đã xoá người xử lý khỏi danh mục.', 'ok');
                }
                NhatKy::canhBao('xoa_danh_muc', 'Xoá/ngưng người xử lý', 'nguoi_xu_ly', (string)$id);
            }

        // -------------------------- MÃ VĂN BẢN --------------------------
        } else {
            if ($viec === 'luu') {
                $ma = trim(Util::gui('ma_van_ban'));
                if ($ma === '') throw new InvalidArgumentException('Mã văn bản không được để trống.');
                $d = [
                    'ma_van_ban'   => mb_substr($ma, 0, 30),
                    'ma_chuan'     => Util::chuanHoaMaSo($ma),
                    'ten_van_ban'  => mb_substr(Util::gui('ten_van_ban'), 0, 255),
                    'loai'         => in_array(Util::gui('loai_vb'), ['bao_cao', 'cong_viec', 'khac'], true)
                                      ? Util::gui('loai_vb') : 'khac',
                    'ky_bao_cao'   => in_array(Util::gui('ky_bao_cao'),
                                      ['khong','ngay','tuan','thang','quy','hoc_ky','nam','dot'], true)
                                      ? Util::gui('ky_bao_cao') : 'khong',
                    'han_nop'      => Util::gui('han_nop') ?: null,
                    'bat_buoc_nop' => Util::gui('bat_buoc_nop') === '1' ? 1 : 0,
                    'pham_vi'      => Util::gui('pham_vi') === 'chon_loc' ? 'chon_loc' : 'tat_ca',
                    'id_nguoi_xu_ly_mac_dinh' => ((int)Util::gui('id_nxl', 0)) ?: null,
                    'tu_dong_tao'  => 0,
                    'trang_thai'   => Util::gui('trang_thai') === '1' ? 1 : 0,
                    'mo_ta'        => mb_substr(Util::gui('mo_ta'), 0, 2000),
                    'ngay_cap_nhat'=> date('Y-m-d H:i:s'),
                ];
                if ($d['ten_van_ban'] === '') throw new InvalidArgumentException('Tên văn bản không được để trống.');
                if ($id) {
                    Db::capNhat('van_ban', $d, 'id = ?', [$id]);
                    NhatKy::tin('sua_danh_muc', "Sửa mã văn bản {$ma}", 'van_ban', (string)$id);
                    Util::nhan('Đã cập nhật mã văn bản.', 'ok');
                } else {
                    $d['ngay_tao'] = date('Y-m-d H:i:s');
                    $id = (int)Db::chen('van_ban', $d);
                    NhatKy::tin('them_danh_muc', "Thêm mã văn bản {$ma}", 'van_ban', (string)$id);
                    Util::nhan('Đã thêm mã văn bản mới.', 'ok');
                }
                // Phạm vi trường
                Db::xoa('van_ban_truong', 'id_van_ban = ?', [$id]);
                if ($d['pham_vi'] === 'chon_loc') {
                    foreach (array_map('intval', (array)($_POST['truong_ap_dung'] ?? [])) as $idT) {
                        if ($idT > 0) {
                            Db::chay('INSERT IGNORE INTO van_ban_truong (id_van_ban, id_truong) VALUES (?,?)',
                                     [$id, $idT]);
                        }
                    }
                }
            } elseif ($viec === 'xoa' && $id) {
                $co = (int)Db::giaTri('SELECT COUNT(*) FROM cong_viec WHERE id_van_ban = ?', [$id], 0);
                if ($co) {
                    Db::capNhat('van_ban', ['trang_thai' => 0, 'ngay_cap_nhat' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
                    Util::nhan("Mã này đang gắn với {$co} văn bản nên chỉ được chuyển sang ngưng sử dụng.", 'canh');
                } else {
                    Db::xoa('van_ban', 'id = ?', [$id]);
                    Util::nhan('Đã xoá mã văn bản.', 'ok');
                }
                NhatKy::canhBao('xoa_danh_muc', 'Xoá/ngưng mã văn bản', 'van_ban', (string)$id);
            }
        }
    } catch (Throwable $e) {
        Util::nhan($e->getMessage(), 'loi');
        Util::chuyenHuong(Util::urlGiu(['sua' => $id ?: null]));
    }
    Util::chuyenHuong(Util::url('danh-muc', ['loai' => $loai]));
}

// =====================================================================
//  Dữ liệu hiển thị
// =====================================================================
$tuKhoa = (string)Util::lay('q');
$idSua  = (int)Util::lay('sua', 0);
$sua    = null;

$moiTrang = 100;
$trangSo  = max(1, (int)Util::lay('trang', 1));
$boQua    = ($trangSo - 1) * $moiTrang;

if ($loai === 'truong') {
    $dk = $tuKhoa !== '' ? 'WHERE ma_truong LIKE ? OR ten_truong LIKE ? OR dia_ban LIKE ?' : '';
    $ts = $tuKhoa !== '' ? ['%' . $tuKhoa . '%', '%' . $tuKhoa . '%', '%' . $tuKhoa . '%'] : [];
    $tong = (int)Db::giaTri("SELECT COUNT(*) FROM truong $dk", $ts, 0);
    $ds = Db::tatCa("SELECT t.*, (SELECT COUNT(*) FROM cong_viec cv WHERE cv.id_truong = t.id) so_vb
                     FROM truong t $dk ORDER BY t.thu_tu, t.ma_truong LIMIT $moiTrang OFFSET $boQua", $ts);
    if ($idSua) $sua = Db::mot('SELECT * FROM truong WHERE id = ?', [$idSua]);
} elseif ($loai === 'nguoi') {
    $dk = $tuKhoa !== '' ? 'WHERE ma_nguoi_xu_ly LIKE ? OR ho_ten LIKE ? OR ten_dang_nhap LIKE ?' : '';
    $ts = $tuKhoa !== '' ? ['%' . $tuKhoa . '%', '%' . $tuKhoa . '%', '%' . $tuKhoa . '%'] : [];
    $tong = (int)Db::giaTri("SELECT COUNT(*) FROM nguoi_xu_ly $dk", $ts, 0);
    $ds = Db::tatCa("SELECT n.*,
                       (SELECT COUNT(*) FROM cong_viec cv WHERE cv.id_nguoi_xu_ly = n.id AND cv.la_ban_moi_nhat = 1) so_vb,
                       (SELECT GROUP_CONCAT(b.bi_danh SEPARATOR ', ') FROM bi_danh_nguoi_xu_ly b
                        WHERE b.id_nguoi_xu_ly = n.id) bi_danh
                     FROM nguoi_xu_ly n $dk ORDER BY n.ho_ten LIMIT $moiTrang OFFSET $boQua", $ts);
    if ($idSua) {
        $sua = Db::mot('SELECT * FROM nguoi_xu_ly WHERE id = ?', [$idSua]);
        if ($sua) {
            $sua['bi_danh'] = implode(', ', Db::cot(
                'SELECT bi_danh FROM bi_danh_nguoi_xu_ly WHERE id_nguoi_xu_ly = ?', [$idSua]));
        }
    }
} else {
    $dk = $tuKhoa !== '' ? 'WHERE v.ma_van_ban LIKE ? OR v.ten_van_ban LIKE ?' : '';
    $ts = $tuKhoa !== '' ? ['%' . $tuKhoa . '%', '%' . $tuKhoa . '%'] : [];
    $tong = (int)Db::giaTri("SELECT COUNT(*) FROM van_ban v $dk", $ts, 0);
    $ds = Db::tatCa("SELECT v.*, n.ho_ten AS ten_nxl,
                       (SELECT COUNT(*) FROM cong_viec cv WHERE cv.id_van_ban = v.id) so_vb
                     FROM van_ban v LEFT JOIN nguoi_xu_ly n ON n.id = v.id_nguoi_xu_ly_mac_dinh
                     $dk ORDER BY v.tu_dong_tao DESC, v.ma_van_ban LIMIT $moiTrang OFFSET $boQua", $ts);
    if ($idSua) {
        $sua = Db::mot('SELECT * FROM van_ban WHERE id = ?', [$idSua]);
        if ($sua) $sua['truong_ap_dung'] = Db::cot('SELECT id_truong FROM van_ban_truong WHERE id_van_ban = ?', [$idSua]);
    }
}

$dsNguoiChon = Db::tatCa('SELECT id, ma_nguoi_xu_ly, ho_ten FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ho_ten');
$dsTruongChon = ($loai === 'van_ban')
    ? Db::tatCa('SELECT id, ma_truong, ten_truong FROM truong WHERE trang_thai = 1 ORDER BY thu_tu, ma_truong')
    : [];

$tenLoai = ['truong' => 'Danh mục trường', 'nguoi' => 'Danh mục người xử lý', 'van_ban' => 'Danh mục mã văn bản'];
View::$tieuDe = $tenLoai[$loai];
View::hien('danh-muc', compact('loai', 'ds', 'tong', 'trangSo', 'moiTrang', 'sua', 'tuKhoa',
                               'dsNguoiChon', 'dsTruongChon'));
