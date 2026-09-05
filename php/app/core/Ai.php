<?php
/**
 * Ai.php - Trợ lý AI tương thích OpenAI (dùng cho gợi ý phân luồng tay)
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class Ai
{
    const MAX_TOKENS_TRAN = 64000;
    const TIMEOUT_TRAN    = 300;

    public static function bat(): bool
    {
        return Ung::bat('ai.bat', false)
            && trim((string)Ung::get('ai.url')) !== ''
            && trim((string)Ung::get('ai.model')) !== '';
    }

    public static function urlChat(?string $goc = null): string
    {
        $u = rtrim(trim((string)($goc ?? Ung::get('ai.url'))), '/');
        if ($u === '') return '';
        if (strpos($u, '/chat/completions') !== false) return $u;
        if (substr($u, -12) === '/completions') return $u;
        return $u . '/chat/completions';
    }

    public static function maxTokens(): int
    {
        $n = (int)Ung::so('ai.max_tokens', 4096);
        return max(1, min(self::MAX_TOKENS_TRAN, $n));
    }

    public static function timeout(): int
    {
        $n = (int)Ung::so('ai.timeout', 60);
        return max(1, min(self::TIMEOUT_TRAN, $n));
    }

    /**
     * Gọi API chat. Trả về [thành công, nội dung|thông báo lỗi, thông tin thêm]
     */
    public static function chat(array $tinNhan, array $ghiDe = []): array
    {
        $url = self::urlChat($ghiDe['url'] ?? null);
        if ($url === '') return [false, 'Chưa khai báo địa chỉ dịch vụ AI', []];

        $than = [
            'model'       => (string)($ghiDe['model'] ?? Ung::get('ai.model')),
            'messages'    => $tinNhan,
            'temperature' => (float)($ghiDe['temperature'] ?? Ung::get('ai.temperature', '0.1')),
            'max_tokens'  => (int)($ghiDe['max_tokens'] ?? self::maxTokens()),
            'stream'      => false,
        ];
        $khoa = (string)($ghiDe['api_key'] ?? Ung::get('ai.api_key'));
        $timeout = (int)($ghiDe['timeout'] ?? self::timeout());
        $timeout = max(1, min(self::TIMEOUT_TRAN, $timeout));

        $tieuDe = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];
        if ($khoa !== '') $tieuDe[] = 'Authorization: Bearer ' . $khoa;

        if (!function_exists('curl_init')) {
            return [false, 'Máy chủ web chưa bật phần mở rộng cURL của PHP', []];
        }

        $t0 = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($than, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $tieuDe,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(30, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'PhanLuongMail/1.0',
        ]);
        $traLoi = curl_exec($ch);
        $maHttp = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $loiCurl = curl_error($ch);
        curl_close($ch);
        $giay = round(microtime(true) - $t0, 2);

        if ($traLoi === false) return [false, 'Lỗi kết nối AI: ' . $loiCurl, ['giay' => $giay]];

        $j = json_decode($traLoi, true);
        if (!is_array($j)) {
            return [false, 'Dịch vụ AI trả về dữ liệu không hợp lệ: ' . mb_substr((string)$traLoi, 0, 300),
                    ['giay' => $giay]];
        }
        if (isset($j['error'])) {
            $m = is_array($j['error']) ? ($j['error']['message'] ?? json_encode($j['error'])) : $j['error'];
            return [false, 'AI báo lỗi: ' . $m, ['giay' => $giay]];
        }
        if ($maHttp < 200 || $maHttp >= 300) {
            return [false, "Dịch vụ AI trả về HTTP {$maHttp}: " . mb_substr((string)$traLoi, 0, 300),
                    ['giay' => $giay]];
        }
        $nd = $j['choices'][0]['message']['content'] ?? ($j['choices'][0]['text'] ?? '');
        if ($nd === '') return [false, 'AI trả về nội dung rỗng', ['giay' => $giay]];

        return [true, (string)$nd, ['giay' => $giay, 'token' => (int)($j['usage']['total_tokens'] ?? 0)]];
    }

    /** Kiểm tra kết nối */
    public static function kiemTra(array $ghiDe = []): array
    {
        [$ok, $nd, $tt] = self::chat(
            [['role' => 'user', 'content' => 'Trả lời đúng một từ: OK']],
            array_merge($ghiDe, ['max_tokens' => 16, 'temperature' => 0])
        );
        if (!$ok) return [false, $nd];
        return [true, sprintf('Kết nối AI thành công (%.1f giây). Mô hình trả lời: "%s"',
                              $tt['giay'] ?? 0, mb_substr(trim($nd), 0, 60))];
    }

    /**
     * Nhờ AI đề xuất mã cho một email (dùng ở màn hình phân luồng tay).
     * @return array [thành công, dữ liệu|thông báo lỗi]
     */
    public static function goiYPhanLuong(array $email, array $tenTep = []): array
    {
        if (!self::bat()) return [false, 'Chức năng AI đang tắt. Bật trong mục Cài đặt.'];

        $dm = LuuMail::danhMuc();
        $p = "DANH MỤC TRƯỜNG (mã | tên):\n";
        foreach (array_slice($dm['truong'], 0, 400) as $t) $p .= $t['ma'] . ' | ' . $t['ten'] . "\n";
        $p .= "\nDANH MỤC NGƯỜI XỬ LÝ (mã | họ tên):\n";
        foreach (array_slice($dm['nguoi_xu_ly'], 0, 200) as $t) $p .= $t['ma'] . ' | ' . $t['ho_ten'] . "\n";
        $p .= "\nDANH MỤC MÃ VĂN BẢN (mã | tên) - có thể chưa đầy đủ:\n";
        foreach (array_slice($dm['van_ban'], 0, 300) as $t) $p .= $t['ma'] . ' | ' . $t['ten'] . "\n";

        $p .= "\n===== THÔNG TIN EMAIL CẦN PHÂN LUỒNG =====\n";
        $p .= 'Người gửi: ' . ($email['ten_nguoi_gui'] ?? '') . ' <' . ($email['nguoi_gui'] ?? '') . ">\n";
        $p .= 'Tiêu đề: ' . ($email['tieu_de'] ?? '') . "\n";
        $p .= 'Thời gian: ' . Util::ngay($email['ngay_gui'] ?? null) . " (giờ Việt Nam)\n";
        $p .= "Tệp đính kèm:\n";
        if (!$tenTep) $p .= "  (không có)\n";
        foreach ($tenTep as $t) $p .= '  - ' . $t . "\n";
        $p .= "Trích nội dung:\n" . mb_substr(trim((string)($email['noi_dung_text'] ?: ($email['doan_trich'] ?? ''))), 0, 3000) . "\n";

        $p .= "\n===== YÊU CẦU =====\n"
            . "Quy ước đặt tên: <mã trường>_<mã văn bản>_<mã người xử lý>, ví dụ 001_001_TAT.\n"
            . "Hãy xác định 3 mã đó. Quy tắc:\n"
            . "1. Ưu tiên mã đọc được từ tên tệp, sau đó tới tiêu đề, cuối cùng mới suy luận từ nội dung.\n"
            . "2. ma_truong và ma_nguoi_xu_ly BẮT BUỘC nằm trong danh mục. Không chắc thì để chuỗi rỗng.\n"
            . "3. ma_van_ban có thể là mã mới - cứ ghi đúng mã đọc/suy được.\n"
            . "4. do_tin_cay là số thực 0..1.\n"
            . "CHỈ TRẢ LỜI bằng một đối tượng JSON duy nhất theo khuôn:\n"
            . '{"ma_truong":"","ma_van_ban":"","ma_nguoi_xu_ly":"","do_tin_cay":0.0,"ly_do":"giải thích ngắn bằng tiếng Việt"}';

        [$ok, $nd, $tt] = self::chat([
            ['role' => 'system', 'content' => (string)Ung::get('ai.nhac_he_thong',
                'Bạn là trợ lý văn thư của Sở Giáo dục và Đào tạo. Chỉ trả lời bằng JSON.')],
            ['role' => 'user', 'content' => $p],
        ]);
        if (!$ok) return [false, $nd];

        $json = self::bocJson($nd);
        $kq = json_decode($json, true);
        if (!is_array($kq)) return [false, 'Không đọc được JSON trong câu trả lời của AI: ' . mb_substr($nd, 0, 300)];

        $tin = (float)($kq['do_tin_cay'] ?? 0);
        if ($tin > 1) $tin /= 100;
        return [true, [
            'ma_truong'      => trim((string)($kq['ma_truong'] ?? '')),
            'ma_van_ban'     => trim((string)($kq['ma_van_ban'] ?? '')),
            'ma_nguoi_xu_ly' => strtoupper(trim((string)($kq['ma_nguoi_xu_ly'] ?? ''))),
            'do_tin_cay'     => max(0, min(1, $tin)),
            'ly_do'          => mb_substr(trim((string)($kq['ly_do'] ?? '')), 0, 2000),
            'giay'           => $tt['giay'] ?? 0,
        ]];
    }

    private static function bocJson(string $s): string
    {
        $t = trim($s);
        if (($f = strpos($t, '```')) !== false) {
            $bd = strpos($t, "\n", $f);
            $kt = strrpos($t, '```');
            if ($bd !== false && $kt !== false && $kt > $bd) $t = trim(substr($t, $bd + 1, $kt - $bd - 1));
        }
        $a = strpos($t, '{');
        $b = strrpos($t, '}');
        if ($a !== false && $b !== false && $b > $a) return substr($t, $a, $b - $a + 1);
        return $t;
    }
}
