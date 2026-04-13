<?php
date_default_timezone_set('Asia/Jakarta');

	/**
	 * Returns UNIX timestamp from a NTP server (RFC 5905)
	 *
	 * @param  string  $host    Server host (default is pool.ntp.org)
	 * @param  integer $timeout Timeout  in seconds (default is 10 seconds)
	 * @return integer Number of seconds since January 1st 1970
	 */
	function getTimeFromNTP($host = 'pool.ntp.org', $timeout = 10)
	{
		$socket = stream_socket_client('udp://' . $host . ':123', $errno, $errstr, (int)$timeout);

		$msg = "\010" . str_repeat("\0", 47);
		fwrite($socket, $msg);

		$response = fread($socket, 48);
		fclose($socket);

		// unpack to unsigned long
		$data = unpack('N12', $response);

		// 9 =  Receive Timestamp (rec): Time at the server when the request arrived
   		// from the client, in NTP timestamp format.
		$timestamp = sprintf('%u', $data[9]);

		// NTP = number of seconds since January 1st, 1900
		// Unix time = seconds since January 1st, 1970
		// remove 70 years in seconds to get unix timestamp from NTP time
		$timestamp -= 2208988800;

		return $timestamp;
	}

$timestamp = getTimeFromNTP($host, $timeout);
$timejava = date('Y,m-1,d,H,i,s', $timestamp);

echo "var servertimeOBJ = new Date($timejava);var TimeZone = 'WIB';";
