<?php
/** Điều khiển: Thống kê nộp báo cáo theo trường */

$idVb   = (int)Util::lay('vb', 0);
$tuNgay = (string)Util::lay('tu', '');
$denNgay= (string)Util::lay('den', '');
$diaBan = (string)Util::lay('dia_ban', '');

// Danh sách mã văn bản để chọn
$dsVanBan = Db::tatCa(
    'SELECT id, ma_van_ban, ten_van_ban, ky_bao_cao, han_nop, bat_buoc_nop, pham_vi
     FROM van_ban WHERE trang_thai = 1
     ORDER BY bat_buoc_nop DESC, ma_van_ban');

if (!$idVb) {
    $dau = Db::mot('SELECT id FROM van_ban WHERE bat_buoc_nop = 1 AND trang_thai = 1
                    ORDER BY (han_nop IS NULL), han_nop DESC, ma_van_ban LIMIT 1');
    $idVb = $dau ? (int)$dau['id'] : 0;
}

$vb = $idVb ? Db::mot('SELECT * FROM van_ban WHERE id = ?', [$idVb]) : null;

$dsDiaBan = Db::cot("SELECT DISTINCT dia_ban FROM truong WHERE trang_thai = 1 AND dia_ban <> '' ORDER BY dia_ban");

// --------------------------- Bảng nộp / chưa nộp ---------------------------
$bang = [];
$daNop = 0;
$chuaNop = 0;

if ($vb) {
    // Phạm vi trường phải nộp
    if ($vb['pham_vi'] === 'chon_loc') {
        $sqlTruong = 'SELECT t.* FROM truong t
                      JOIN van_ban_truong vt ON vt.id_truong = t.id AND vt.id_van_ban = ?
                      WHERE t.trang_thai = 1';
        $tsTruong = [$idVb];
    } else {
        $sqlTruong = 'SELECT t.* FROM truong t WHERE t.trang_thai = 1';
        $tsTruong = [];
    }
    if ($diaBan !== '') { $sqlTruong .= ' AND t.dia_ban = ?'; $tsTruong[] = $diaBan; }
    $sqlTruong .= ' ORDER BY t.thu_tu, t.ma_truong';
    $truongs = Db::tatCa($sqlTruong, $tsTruong);

    // Bản ghi nộp theo trường
    $dkNgay = '';
    $tsNop = [$idVb];
    if ($tuNgay !== '')  { $dkNgay .= ' AND cv.ngay_nhan >= ?'; $tsNop[] = $tuNgay . ' 00:00:00'; }
    if ($denNgay !== '') { $dkNgay .= ' AND cv.ngay_nhan <= ?'; $tsNop[] = $denNgay . ' 23:59:59'; }

    $nop = [];
    foreach (Db::tatCa(
        "SELECT cv.id_truong, COUNT(*) so_lan, MAX(cv.ngay_nhan) lan_cuoi,
                MAX(cv.phien_ban) phien_ban, MAX(cv.id) id_moi_nhat,
                SUM(cv.so_tep) tong_tep
         FROM cong_viec cv
         WHERE cv.id_van_ban = ? AND cv.id_truong IS NOT NULL
               AND cv.trang_thai <> 'trung_lap' $dkNgay
         GROUP BY cv.id_truong", $tsNop) as $r) {
        $nop[(int)$r['id_truong']] = $r;
    }

    foreach ($truongs as $t) {
        $r = $nop[(int)$t['id']] ?? null;
        $bang[] = [
            'truong'    => $t,
            'da_nop'    => (bool)$r,
            'so_lan'    => $r ? (int)$r['so_lan'] : 0,
            'lan_cuoi'  => $r['lan_cuoi'] ?? null,
            'phien_ban' => $r ? (int)$r['phien_ban'] : 0,
            'so_tep'    => $r ? (int)$r['tong_tep'] : 0,
            'id_cv'     => $r ? (int)$r['id_moi_nhat'] : 0,
            'tre_han'   => ($r && $vb['han_nop'] && substr($r['lan_cuoi'], 0, 10) > $vb['han_nop']),
        ];
        if ($r) $daNop++; else $chuaNop++;
    }
}

// --------------------------- Thống kê tổng hợp ---------------------------
$tongHop = Db::tatCa(
    "SELECT vb.id, vb.ma_van_ban, vb.ten_van_ban, vb.bat_buoc_nop, vb.han_nop, vb.ky_bao_cao,
            COUNT(DISTINCT cv.id_truong) so_truong_nop, COUNT(cv.id) so_van_ban
     FROM van_ban vb
     LEFT JOIN cong_viec cv ON cv.id_van_ban = vb.id AND cv.trang_thai <> 'trung_lap'
     WHERE vb.trang_thai = 1
     GROUP BY vb.id
     ORDER BY vb.bat_buoc_nop DESC, so_van_ban DESC, vb.ma_van_ban
     LIMIT 40");

$tongTruongHd = (int)Db::giaTri('SELECT COUNT(*) FROM truong WHERE trang_thai = 1', [], 0);

// --------------------------- Xuất CSV ---------------------------
if (Util::lay('xuat') === 'csv' && $vb) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="thong-ke-nop-'
           . preg_replace('/[^A-Za-z0-9_\-]/', '', $vb['ma_van_ban']) . '-'
           . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";  // BOM để Excel đọc đúng tiếng Việt
    $out = fopen('php://output', 'w');
    // Tham số $escape truyền tường minh để tương thích PHP 7.4 -> 8.4
    fputcsv($out, ['STT', 'Mã trường', 'Tên trường', 'Địa bàn', 'Tình trạng',
                   'Số lần nộp', 'Lần nộp gần nhất', 'Số tệp', 'Trễ hạn'], ',', '"', '');
    $i = 1;
    foreach ($bang as $r) {
        fputcsv($out, [
            $i++, $r['truong']['ma_truong'], $r['truong']['ten_truong'], $r['truong']['dia_ban'],
            $r['da_nop'] ? 'Đã nộp' : 'Chưa nộp', $r['so_lan'],
            $r['lan_cuoi'] ? Util::ngay($r['lan_cuoi']) : '', $r['so_tep'],
            $r['tre_han'] ? 'Trễ hạn' : '',
        ], ',', '"', '');
    }
    fclose($out);
    NhatKy::tin('xuat_csv', 'Xuất thống kê nộp báo cáo mã ' . $vb['ma_van_ban'], 'van_ban', (string)$idVb);
    exit;
}

View::$tieuDe = 'Thống kê nộp báo cáo';
View::hien('thong-ke', compact('dsVanBan', 'vb', 'bang', 'daNop', 'chuaNop', 'tongHop',
                               'tongTruongHd', 'tuNgay', 'denNgay', 'diaBan', 'dsDiaBan', 'idVb'));
