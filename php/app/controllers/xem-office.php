<?php
/**
 * Điều khiển: Xem trực tiếp tệp Word / Excel / PowerPoint ngay trên web.
 *
 * Trang này chỉ kiểm quyền rồi dựng khung; nội dung tệp do trình duyệt tự
 * tải qua "?t=tai&id=…" và tự dựng lại bằng assets/js/xem-office.js. Nhờ vậy
 * hồ sơ không phải đi qua máy chủ của Microsoft hay Google như khi nhúng
 * Office Online — và máy chủ web cũng không cần cài thêm gì.
 */

$id = (int)Util::lay('id', 0);

$f = Db::mot(
    'SELECT tdk.*, tdl.id AS id_du_lieu, tdl.da_hoan_tat
     FROM tep_dinh_kem tdk
     LEFT JOIN tep_du_lieu tdl ON tdl.id = tdk.id_tep_du_lieu
     WHERE tdk.id = ?', [$id]);

if (!$f || empty($f['id_du_lieu']) || (int)$f['da_hoan_tat'] !== 1) {
    http_response_code(404);
    View::$tieuDe = 'Không tìm thấy tệp';
    View::hien('loi-404');
    exit;
}

// Cùng luật quyền như trang tải về: không được xem tệp của việc người khác
if (!Auth::xemTatCa()) {
    $duocPhep = (int)Db::giaTri(
        'SELECT COUNT(*) FROM cong_viec cv
         LEFT JOIN cong_viec_tep cvt ON cvt.id_cong_viec = cv.id
         WHERE cv.id_nguoi_xu_ly = ? AND (cvt.id_tep_dinh_kem = ? OR cv.id_email = ?)',
        [Auth::id(), $id, $f['id_email']], 0);
    if (!$duocPhep) {
        http_response_code(403);
        Util::nhan('Bạn không có quyền truy cập tệp này.', 'loi');
        Util::chuyenHuong(Util::url('hop-viec'));
    }
}

$duoi = Util::duoiBoDocOffice($f['kieu_mime'], $f['ten_tep']);
if ($duoi === '') {
    Util::nhan('Tệp “' . $f['ten_tep'] . '” không xem trực tiếp được, hãy tải về máy để mở.', 'canh');
    Util::chuyenHuong(Util::url('tai', ['id' => $id]));
}

// Công việc để bấm "Quay lại" đúng chỗ
$idCv = (int)Util::lay('cv', 0);
if ($idCv > 0) {
    $hopLe = (int)Db::giaTri('SELECT COUNT(*) FROM cong_viec WHERE id = ? AND id_email = ?',
                             [$idCv, $f['id_email']], 0);
    if (!$hopLe) $idCv = 0;
}
if ($idCv === 0) {
    $idCv = (int)Db::giaTri(
        'SELECT cv.id FROM cong_viec cv
         LEFT JOIN cong_viec_tep cvt ON cvt.id_cong_viec = cv.id
         WHERE cvt.id_tep_dinh_kem = ? OR cv.id_email = ?
         ORDER BY (cvt.id_tep_dinh_kem = ?) DESC, cv.id LIMIT 1',
        [$id, $f['id_email'], $id], 0);
}

NhatKy::tin('xem_tep', 'Xem trực tiếp trên web tệp ' . strtoupper($duoi) . ': ' . $f['ten_tep'],
            'tep_dinh_kem', (string)$id);

View::$tieuDe = 'Xem ' . $f['ten_tep'];
View::hien('xem-office', compact('f', 'id', 'duoi', 'idCv'));
