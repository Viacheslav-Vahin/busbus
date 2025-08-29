<?php
// app/Support/WordPressHasher.php
namespace App\Support;

class WordPressHasher
{
    // Порівняти пароль юзера з WP-хешем
    public function check(string $password, string $wpHash): bool
    {
        if ($wpHash === '' || $password === '') return false;

        // Нові WP можуть мігрувати на PHP password_*, але в більшості — phpass $P$/$H$
        if (\str_starts_with($wpHash, '$P$') || \str_starts_with($wpHash, '$H$')) {
            return $this->phpassCheck($password, $wpHash);
        }

        // На всяк випадок — якщо це password_hash()
        if (\preg_match('/^\$2y\$/', $wpHash)) {
            return password_verify($password, $wpHash);
        }

        return false;
    }

    private function phpassCheck(string $password, string $storedHash): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        $countLog2 = strpos($itoa64, $storedHash[3]);
        if ($countLog2 < 7 || $countLog2 > 30) return false;
        $salt = substr($storedHash, 4, 8);
        if (strlen($salt) !== 8) return false;

        $count = 1 << $countLog2;
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        $output = substr($storedHash, 0, 12) . $this->encode64($hash, 16, $itoa64);
        return hash_equals($storedHash, $output);
    }

    private function encode64(string $input, int $count, string $itoa64): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];
            if ($i < $count) $value |= ord($input[$i]) << 8;
            $output .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) break;
            if ($i < $count) $value |= ord($input[$i]) << 16;
            $output .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) break;
            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
