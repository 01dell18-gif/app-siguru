<?php
// database/check_env.php - Helper untuk mendeteksi port bebas dan IP lokal sistem
$ports = [8080, 8081, 8082, 8083, 8000, 8888];
$chosenPort = 8080;

foreach ($ports as $p) {
    $fp = @fsockopen('localhost', $p, $errCode, $errStr, 0.15);
    if (!$fp) {
        $chosenPort = $p;
        break;
    }
    fclose($fp);
}

// Deteksi IP LAN aktif yang sebenarnya (bukan virtual adapter seperti VirtualBox 192.168.56.x)
function detectActiveLanIp() {
    // 1. Coba routing socket UDP (tidak mengirim paket keluar, hanya menanyakan routing OS)
    if (function_exists('socket_create') && function_exists('socket_connect') && function_exists('socket_getsockname')) {
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock && @socket_connect($sock, '8.8.8.8', 53)) {
            $sockIp = '';
            @socket_getsockname($sock, $sockIp);
            @socket_close($sock);
            if (!empty($sockIp) && $sockIp !== '127.0.0.1' && !str_starts_with($sockIp, '192.168.56.')) {
                return $sockIp;
            }
        }
    }

    // 2. Parse perintah ipconfig Windows: cari adaptor yang memiliki 'Default Gateway' aktif
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ipconfigOutput = @shell_exec('ipconfig');
        if ($ipconfigOutput) {
            $sections = preg_split('/\r?\n\r?\n/', $ipconfigOutput);
            foreach ($sections as $sec) {
                // Jangan gunakan VirtualBox Host-Only Network
                if (stripos($sec, 'VirtualBox') !== false || stripos($sec, 'VMware') !== false || stripos($sec, 'vEthernet') !== false) {
                    continue;
                }
                $ipMatch = null;
                $gwMatch = null;
                if (preg_match('/IPv4 Address[ .:]+:\s*([0-9.]+)/i', $sec, $m)) {
                    $ipMatch = trim($m[1]);
                }
                if (preg_match('/Default Gateway[ .:]+:\s*([0-9.]+)/i', $sec, $m)) {
                    $gwMatch = trim($m[1]);
                }
                if (!empty($ipMatch) && !empty($gwMatch) && $ipMatch !== '127.0.0.1' && !str_starts_with($ipMatch, '192.168.56.')) {
                    return $ipMatch;
                }
            }
        }
    }

    // 3. Fallback standar hostname
    $hostIp = gethostbyname(gethostname());
    if ($hostIp && $hostIp !== '127.0.0.1') {
        return $hostIp;
    }

    return '127.0.0.1';
}

$localIp = detectActiveLanIp();
echo $chosenPort . '|' . $localIp;

