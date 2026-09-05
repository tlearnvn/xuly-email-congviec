<?php
/** Điều khiển: Tải hoặc xem trực tiếp tệp đính kèm */

$id = (int)Util::lay('id', 0);
$xemTrucTiep = (View::$trangHienTai === 'xem');

$f = Db::mot(
    'SELECT tdk.*, tdl.id AS id_du_lieu, tdl.dung_luong AS dl_thuc, tdl.da_hoan_tat
     FROM tep_dinh_kem tdk
     LEFT JOIN tep_du_lieu tdl ON tdl.id = tdk.id_tep_du_lieu
     WHERE tdk.id = ?', [$id]);

if (!$f || empty($f['id_du_lieu']) || (int)$f['da_hoan_tat'] !== 1) {
    http_response_code(404);
    exit('Không tìm thấy tệp hoặc nội dung tệp chưa được tải về máy chủ.');
}

// --------------------------- Kiểm tra quyền ---------------------------
if (!Auth::xemTatCa()) {
    $duocPhep = (int)Db::giaTri(
        'SELECT COUNT(*) FROM cong_viec cv
         LEFT JOIN cong_viec_tep cvt ON cvt.id_cong_viec = cv.id
         WHERE cv.id_nguoi_xu_ly = ? AND (cvt.id_tep_dinh_kem = ? OR cv.id_email = ?)',
        [Auth::id(), $id, $f['id_email']], 0);
    if (!$duocPhep) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập tệp này.');
    }
}

// --------------------------- Đọc nội dung ---------------------------
$noiDung = Db::giaTri('SELECT noi_dung FROM tep_du_lieu WHERE id = ?', [$f['id_du_lieu']], null);
if ($noiDung === null) {
    http_response_code(404);
    exit('Nội dung tệp không tồn tại.');
}
if (is_resource($noiDung)) $noiDung = stream_get_contents($noiDung);

NhatKy::tin($xemTrucTiep ? 'xem_tep' : 'tai_tep',
            ($xemTrucTiep ? 'Xem trực tiếp' : 'Tải về') . ' tệp: ' . $f['ten_tep'],
            'tep_dinh_kem', (string)$id);

// --------------------------- Trả về ---------------------------
$mime = (string)($f['kieu_mime'] ?: 'application/octet-stream');
if ($xemTrucTiep && !Util::xemTrucTiep($mime, $f['ten_tep'])) $xemTrucTiep = false;

// Không cho trình duyệt thực thi nội dung lạ khi xem trực tiếp
if ($xemTrucTiep && stripos($mime, 'text/html') !== false) $mime = 'text/plain; charset=utf-8';

$ten = preg_replace('/[\r\n"]+/', '', (string)$f['ten_tep']);
if ($ten === '') $ten = 'tep-dinh-kem';
$tenAscii = preg_replace('/[^\x20-\x7E]/', '_', Util::boDau($ten));

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($noiDung));
header('Content-Disposition: ' . ($xemTrucTiep ? 'inline' : 'attachment')
       . '; filename="' . $tenAscii . '"; filename*=UTF-8\'\'' . rawurlencode($ten));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:; style-src \'unsafe-inline\'');

echo $noiDung;
exit;
