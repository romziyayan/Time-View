<?php
date_default_timezone_set("Asia/Jakarta");
session_start();
$please_wait = "";
$last = time();
if (isset($_SESSION["last"])) {
	$last = $_SESSION["last"];
} else {
	$_SESSION["last"] = $last;
}
//wrap the whole thing in a test for the last hit-time on the page, to avoid abusing NTP servers
if (time() - $last < 0) {
	$wait = time() - $last;
	$please_wait = "Request limit exceeded, please wait " . (0 - $wait) . " s.";
	$server = $vn_response = $mode_response = $stratum_response = $remote_originate = $remote_received = $remote_received = $remote_transmitted = $delay_ms = $ntp_time_formatted = $server_time_formatted = $please_wait;
} else {
	$_SESSION["last"] = time();

	$bit_max = 4294967296;
	$epoch_convert = 2208988800;
	$vn = 3;

	$servers = ['galleon-systems.co.uk','0.uk.pool.ntp.org','1.uk.pool.ntp.org','2.uk.pool.ntp.org','3.uk.pool.ntp.org'];
	$server_count = count($servers);

	//see rfc5905, page 20
	//first byte
	//LI (leap indicator), a 2-bit integer. 00 for 'no warning'
	$header = "00";
	//VN (version number), a 3-bit integer.  011 for version 3
	$header .= sprintf("%03d", decbin($vn));
	//Mode (association mode), a 3-bit integer. 011 for 'client'
	$header .= "011";

	//echo bindec($header);

	//construct the packet header, byte 1
	$request_packet = chr(bindec($header));

	//we'll use a for loop to try additional servers should one fail to respond
	$i = 0;
	for ($i; $i < $server_count; $i++) {
		$socket = @fsockopen(
			"udp://" . $servers[$i],
			123,
			$err_no,
			$err_str,
			1
		);
		if ($socket) {
			//add nulls to position 11 (the transmit timestamp, later to be returned as originate)
			//10 lots of 32 bits
			for ($j = 1; $j < 40; $j++) {
				$request_packet .= chr(0x0);
			}

			//the time our packet is sent from our server (returns a string in the form 'msec sec')
			$local_sent_explode = explode(" ", microtime());
			$local_sent = $local_sent_explode[1] + $local_sent_explode[0];

			//add 70 years to convert unix to ntp epoch
			$originate_seconds = $local_sent_explode[1] + $epoch_convert;

			//convert the float given by microtime to a fraction of 32 bits
			$originate_fractional = round($local_sent_explode[0] * $bit_max);

			//pad fractional seconds to 32-bit length
			$originate_fractional = sprintf("%010d", $originate_fractional);

			//pack to big endian binary string
			$packed_seconds = pack("N", $originate_seconds);
			$packed_fractional = pack("N", $originate_fractional);

			//add the packed transmit timestamp
			$request_packet .= $packed_seconds;
			$request_packet .= $packed_fractional;

			if (fwrite($socket, $request_packet)) {
				$data = null;
				stream_set_timeout($socket, 1);

				$response = fread($socket, 48);

				//the time the response was received
				$local_received = microtime(true);

				//echo 'response was: '.strlen($response).$response;
			}
			fclose($socket);

			if (strlen($response) == 48) {
				//the response was of the right length, assume it's valid and break out of the loop
				break;
			} else {
				if ($i == $server_count - 1) {
					//this was the last server on the list, so give up
					die("unable to establish a connection");
				}
			}
		} else {
			if ($i == $server_count - 1) {
				//this was the last server on the list, so give up
				die("unable to establish a connection");
			}
		}
	}

	//unpack the response to unsiged lonng for calculations
	$unpack0 = unpack("N12", $response);
	//print_r($unpack0);

	//present as a decimal number
	$remote_originate_seconds = sprintf("%u", $unpack0[7]) - $epoch_convert;
	$remote_received_seconds = sprintf("%u", $unpack0[9]) - $epoch_convert;
	$remote_transmitted_seconds = sprintf("%u", $unpack0[11]) - $epoch_convert;

	$remote_originate_fraction = sprintf("%u", $unpack0[8]) / $bit_max;
	$remote_received_fraction = sprintf("%u", $unpack0[10]) / $bit_max;
	$remote_transmitted_fraction = sprintf("%u", $unpack0[12]) / $bit_max;

	$remote_originate = $remote_originate_seconds + $remote_originate_fraction;
	$remote_received = $remote_received_seconds + $remote_received_fraction;
	$remote_transmitted =
		$remote_transmitted_seconds + $remote_transmitted_fraction;

	//unpack to ascii characters for the header response
	$unpack1 = unpack("C12", $response);
	//print_r($unpack1);

	//echo 'byte 1: ' . $unpack1[1] . ' | ';

	//the header response in binary (base 2)
	$header_response = base_convert($unpack1[1], 10, 2);

	//pad with zeros to 1 byte (8 bits)
	$header_response = sprintf("%08d", $header_response);

	//Mode (the last 3 bits of the first byte), converting to decimal for humans;
	$mode_response = bindec(substr($header_response, -3));

	//VN
	$vn_response = bindec(substr($header_response, -6, 3));

	//the header stratum response in binary (base 2)
	$stratum_response = base_convert($unpack1[2], 10, 2);
	$stratum_response = bindec($stratum_response);
	//echo 'stratum: ' . bindec($stratum_response);

	//calculations assume a symmetrical delay, fixed point would give more accuracy
	$delay =
		($local_received - $local_sent) / 2 -
		($remote_transmitted - $remote_received);
	$delay_ms = round($delay * 1000) . " ms";
	//echo 'delay: ' . $delay * 1000 . 'ms';

	$server = $servers[$i];

	$ntp_time = $remote_transmitted - $delay;
	$ntp_time_explode = explode(".", $ntp_time);

	$ntp_time_formatted =
		date("Y-m-d H:i:s", $ntp_time_explode[0]) . "," . $ntp_time_explode[1];
	$ntp_time_java = date("Y,m-1,d,H,i,s", $ntp_time_explode[0]);

	//compare with the current server time
	$server_time = microtime();
	$server_time_explode = explode(" ", $server_time);
	$server_time_micro = round($server_time_explode[0], 4);

	$server_time_formatted =
		date("Y-m-d H:i:s", time()) . "," . substr($server_time_micro, 2);
}
echo "var servertimeOBJ = new Date($ntp_time_java);var TimeZone = 'WIB';";
?>
