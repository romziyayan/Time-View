<?php
date_default_timezone_set('Asia/Makassar');

function get_ntp_time($host = '0.uk.pool.ntp.org') {
	// Attempt to open a UDP connection to the NTP server on port 123
	// We use @ to suppress errors if the host strictly blocks the port
	$sock = @fsockopen("udp://$host", 123, $err_no, $err_str, 5);
	
	if ($sock) {
		// Send the NTP request packet
		$msg = "\010" . str_repeat("\000", 47);
		fwrite($sock, $msg);

		// Read the 48-byte response
		stream_set_timeout($sock, 3); // 3-second timeout for reading
		$response = fread($sock, 48);
		fclose($sock);

		if (strlen($response) == 48) {
			// Unpack the binary response and calculate the timestamp
			$data = unpack('N12', $response);
			$timestamp = sprintf('%u', $data[9]);
			$timestamp -= 2208988800; // Adjust for the 70-year difference in epochs
			
			return $timestamp;
		}
	}

	// FALLBACK: If the UDP port is blocked or times out, use the server's built-in time
	return time();
}

// 1. Fetch the timestamp
$host = '0.uk.pool.ntp.org';
$timestamp = get_ntp_time($host);

// 2. Format it for the JavaScript Date object (Note: JS months are 0-11, hence m-1)
$timejava = date('Y,m-1,d,H,i,s', $timestamp);

// 3. Output the exact JavaScript variable string you requested
echo "let servertimeOBJ = new Date($timejava); let TimeZone = 'WIB';";
?>
