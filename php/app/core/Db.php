<?php
/**
 * Db.php - Lớp truy cập cơ sở dữ liệu (PDO MySQL)
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class Db
{
    /** @var PDO|null */
    private static $pdo = null;
    private static $cauHinh = [];
    private static $soTruyVan = 0;

    public static function khoiTao(array $ch): void
    {
        self::$cauHinh = $ch;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $ch = self::$cauHinh;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $ch['may_chu'] ?? 'localhost',
            (int)($ch['cong'] ?? 3306),
            $ch['co_so_du_lieu'] ?? '',
            $ch['bang_ma'] ?? 'utf8mb4'
        );
        try {
            self::$pdo = new PDO($dsn, $ch['nguoi_dung'] ?? '', $ch['mat_khau'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            // Luôn làm việc theo giờ Việt Nam
            self::$pdo->exec("SET time_zone = '+07:00'");
            self::$pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            throw new RuntimeException('Không kết nối được cơ sở dữ liệu: ' . $e->getMessage(), 0, $e);
        }
        return self::$pdo;
    }

    public static function daKetNoi(): bool
    {
        return self::$pdo instanceof PDO;
    }

    /** Thực thi câu lệnh có tham số, trả về PDOStatement */
    public static function chay(string $sql, array $ts = []): PDOStatement
    {
        self::$soTruyVan++;
        $st = self::pdo()->prepare($sql);
        $st->execute($ts);
        return $st;
    }

    /** Lấy tất cả các dòng */
    public static function tatCa(string $sql, array $ts = []): array
    {
        return self::chay($sql, $ts)->fetchAll();
    }

    /** Lấy một dòng, không có thì trả về null */
    public static function mot(string $sql, array $ts = []): ?array
    {
        $d = self::chay($sql, $ts)->fetch();
        return $d === false ? null : $d;
    }

    /** Lấy một giá trị của cột đầu tiên */
    public static function giaTri(string $sql, array $ts = [], $macDinh = null)
    {
        $d = self::chay($sql, $ts)->fetch(PDO::FETCH_NUM);
        return ($d === false || !isset($d[0])) ? $macDinh : $d[0];
    }

    /** Lấy mảng một chiều từ cột đầu tiên */
    public static function cot(string $sql, array $ts = []): array
    {
        return self::chay($sql, $ts)->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /** Lấy mảng khoá => giá trị từ hai cột đầu */
    public static function capKhoa(string $sql, array $ts = []): array
    {
        $r = [];
        foreach (self::chay($sql, $ts)->fetchAll(PDO::FETCH_NUM) as $d) {
            $r[$d[0]] = $d[1] ?? null;
        }
        return $r;
    }

    /** Chèn một dòng, trả về ID vừa tạo */
    public static function chen(string $bang, array $duLieu)
    {
        $cot = array_keys($duLieu);
        $sql = 'INSERT INTO `' . $bang . '` (`' . implode('`,`', $cot) . '`) VALUES ('
             . implode(',', array_fill(0, count($cot), '?')) . ')';
        self::chay($sql, array_values($duLieu));
        return self::pdo()->lastInsertId();
    }

    /** Cập nhật theo điều kiện, trả về số dòng ảnh hưởng */
    public static function capNhat(string $bang, array $duLieu, string $dieuKien, array $tsDk = []): int
    {
        if (!$duLieu) return 0;
        $dat = [];
        foreach (array_keys($duLieu) as $c) $dat[] = "`$c` = ?";
        $sql = 'UPDATE `' . $bang . '` SET ' . implode(', ', $dat) . ' WHERE ' . $dieuKien;
        return self::chay($sql, array_merge(array_values($duLieu), $tsDk))->rowCount();
    }

    public static function xoa(string $bang, string $dieuKien, array $ts = []): int
    {
        return self::chay('DELETE FROM `' . $bang . '` WHERE ' . $dieuKien, $ts)->rowCount();
    }

    public static function batGiaoDich(): void  { self::pdo()->beginTransaction(); }
    public static function chotGiaoDich(): void { if (self::pdo()->inTransaction()) self::pdo()->commit(); }
    public static function huyGiaoDich(): void  { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }

    public static function soTruyVan(): int { return self::$soTruyVan; }

    /** Kiểm tra một bảng đã tồn tại chưa */
    public static function coBang(string $bang): bool
    {
        try {
            self::chay('SELECT 1 FROM `' . $bang . '` LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
