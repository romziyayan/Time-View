<?php
function beliefmedia_ntp_time($host) {
    // See source for full implementation details
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_connect($sock, $host, 123);
    $msg = "\010" . str_repeat("\000", 47);
    socket_send($sock, $msg, strlen($msg), 0);
    socket_recv($sock, $recv, 48, MSG_WAITALL);
    socket_close($sock);

    $data = unpack('N12', $recv);
    $timestamp = sprintf('%u', $data[9]);
    $timestamp -= 2208988800; // Adjust for NTP epoch difference

    return $timestamp;
}

// Usage
$host = 'pool.ntp.org';
$timestamp = beliefmedia_ntp_time($host);
$time = date('F j, Y, g:i a', $timestamp);

echo "Timestamp from NTP server: " . $timestamp . "<br>";
echo "Formatted time: " . $time;
?>
