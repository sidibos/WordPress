<?php
/**
 * Verify IPs against whitelisted ranges
 *
 * Validate IP addresses of remote users against whitelists of IPs
 * and ranges known to Assanka (clients, clients' suppliers and
 * Assanka staff, for example)
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\KnownHost;

class KnownHostV1 {

	private $_knownips;

	/**
	 * Add a predefined whitelist to the set of hosts that should be considered known
	 *
	 * This function can be edited as we need to add or remove addresses.  It is placed above the constuctor for easy access.
	 *
	 * @param string $clientcode A string specifying the whitelist to add
	 * @return void
	 */
	private function _addKnownIPs($clientcode) {
		switch ($clientcode) {

			case "ECON":
				$this->_knownips[] = "81.151.94.228";
				$this->_knownips[] = "81.136.219.134";
				$this->_knownips[] = "194.129.56.0/21";
				$this->_knownips[] = "63.118.206.4";
				$this->_knownips[] = "194.129.61.10";
				$this->_knownips[] = "194.129.60.10";

				break;

			case "FTCO":
				$this->_knownips[] = "62.173.203.142"; 		// OSB
				$this->_knownips[] = "212.62.14.234"; 		// Park Royal
				$this->_knownips[] = "62.25.72.212"; 		// IWW
				$this->_knownips[] = "208.47.205.14"; 		// New York
				$this->_knownips[] = "207.87.35.150"; 		// Washington
				$this->_knownips[] = "65.113.44.190"; 		// San Francisco
				$this->_knownips[] = "210.176.66.206"; 		// Hong Kong
				$this->_knownips[] = "203.190.72.0/24"; 	// Manilla

				break;

			case "ASSK":

				// External
				$this->_knownips[] = "178.238.155.160/28";	// 50 Brook Green A&A ISP ADSL
				$this->_knownips[] = "90.155.50.254";		// 50 Brook Green A&A ISP ADSL
				$this->_knownips[] = "79.135.113.176/28";	// Office static IP range (Gradwell)
				$this->_knownips[] = "82.136.45.0/26";		// Office static IP range (Burstfire fibre)
				$this->_knownips[] = "92.48.84.72/29";		// BS1 linknet
				$this->_knownips[] = "92.48.79.64/27";		// BS1 production range
				$this->_knownips[] = "213.229.126.120/29";	// BS1 43D linknet
				$this->_knownips[] = "213.229.126.192/27";	// BS1 43D production range
				$this->_knownips[] = "94.76.241.224/27";	// Another BlueSquare range
				$this->_knownips[] = "87.127.122.195";		// Rowan static IP (Aquiss / BT Infinity)
				$this->_knownips[] = "46.65.96.72";		// Andrew static IP (Be Broadband)
				$this->_knownips[] = "46.65.83.29";		// Rob static IP (Be Broadband)
				$this->_knownips[] = "87.194.32.147";		// Will static IP (Be Broadband)
				$this->_knownips[] = "188.220.47.114";		// Adam static IP (Be Broadband)
				$this->_knownips[] = "206.155.115.128/26";	// US1 production range
				$this->_knownips[] = "206.155.117.70";		// US1 NAT external IP

				// Internal
				$this->_knownips[] = "192.168.32.0/24";		// Office LAN
				$this->_knownips[] = "192.168.33.0/24";		// Office LAN
				$this->_knownips[] = "192.168.1.0/24";		// Rob's home
				$this->_knownips[] = "10.1.1.0/24";		// Internal VLAN range for infrastructure in BS1
				$this->_knownips[] = "10.1.2.0/24";		// Internal VLAN range for FTCO in BS1
				$this->_knownips[] = "10.1.3.0/24";		// Internal VLAN range for TLPR in BS1
				$this->_knownips[] = "10.1.4.0/24";		// Internal VLAN range for BS1 restricted Assanka servers
				$this->_knownips[] = "10.1.5.0/24";		// Internal VLAN range for BS1 public Assanka servers
				$this->_knownips[] = "10.1.7.0/24";		// VPN VLAN
				$this->_knownips[] = "10.11.185.0/24";		// Andrew's home
				$this->_knownips[] = "10.89.63.0/24";		// US1 front end range
				$this->_knownips[] = "10.89.65.0/24";		// US1 back end range

				// Development Pools (dev01-etc)
				$this->_knownips[] = "10.119.160.0/22";		// FT Labs Production Pool
				$this->_knownips[] = "10.119.135.0/24";		// FT Labs Development Pool
				$this->_knownips[] = "10.119.133.0/24";		// FT Labs Office Pool
				$this->_knownips[] = "10.119.145.0/24";		// FT Labs UK2 Development Pool
				break;

			// Testing FT's app.ft.com in June 2011
			case "Testers-NGS":
				$this->_knownips[] = "88.151.219.1/24";
				$this->_knownips[] = "195.95.131.0/24";
				$this->_knownips[] = "195.11.80.16/29";
				$this->_knownips[] = "194.217.29.192/27";
				$this->_knownips[] = "82.108.42.192/26";
				break;

			default:
				return false;
		}
		return true;
	}

	/**
	 * Create a KnownHosts object
	 *
	 * @param mixed $clientcodes Either a string specifying the whitelist to use, or an array of such strings
	 */
	public function __construct($clientcodes = "ASSK") {
		$this->_knownips = array();
		if (is_string($clientcodes)) $clientcodes = array($clientcodes);
		foreach ($clientcodes as $clientcode) {
			$this->_addKnownIPs(strtoupper($clientcode));
		}
	}

	/**
	 * Return true if the REMOTE_ADDR set in the current PHP environment is in the active list of known hosts
	 *
	 * Currently any valid IPv6 address will cause this method to return true.  This is because at time of writing, Assanka's servers see only internal IPv6 traffic, so any IPv6 host will be known.
	 *
	 * @return boolean
	 */
	public function isKnownHost() {

		$ip = false;
		$ip = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:$ip;
		$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']))?$_SERVER['HTTP_X_FORWARDED_FOR']:$ip;

		// If no IP is found, then this is not a known host (this typically happens for scripts run from the command line):
		if (!$ip) return false;

		// Allow through anyone on IPv6 automatically
		// TODO:WV:20110617:Implement IPv6 support
		if ($this->getIPVersion($ip) == 6) return true;

		return ($this->isInIPRange($ip, $this->_knownips));
	}

	/**
	 * Return true if a specified IP is in a specified range.
	 *
	 * Accepts either a single range, or an array of ranges.  Each range can be either a single IP address or an IP range expressed in CIDR notation.  Based on technique from http://matthias.leisi.net/archives/173-IP-CIDR-calculation-in-MySQL,-PHP-and-JavaScript.html
	 *
	 * @param string $IPToCheck An IP address (IPv4 only)
	 * @param mixed  $IPrange   Either a string representation of an IP address or IP range in CIPR notation, or, an array of such strings
	 * @return boolean
	 */
	public function isInIPRange($IPToCheck, $IPrange) {

		// If an array of IP ranges was supplied, check if the supplied
		// IPToCheck matches any of them
		if (is_array($IPrange)) {
			foreach ($IPrange as $IP) {
				if ($this->isInIPRange($IPToCheck, $IP)) return true;
			}
			return false;

		// If only one IP was supplied, check if the supplied IPToCheck
		// matches it
		} else {

			// CIDR-format IP range (firstIP/mask)
			if (strpos($IPrange, "/") !== false) {
				list($firstIP, $mask) = explode("/", $IPrange);

			// Single IP address
			} else {
				$firstIP = $IPrange;
				$mask = 32;
			}

			// Check the IDToCheck against the supplied range
			$firstIP = ip2long($firstIP);
			$lastIP = $firstIP + pow(2, (32 - $mask)) - 1;
			$IPToCheck = ip2long($IPToCheck);
			return ($IPToCheck >= $firstIP and $IPToCheck <= $lastIP);
		}
	}

	/**
	 * Determine the version of a specified IP address
	 *
	 * Returns 4 for IPv4 addresses, and 6 for IPv6, false if the address format is not recognised.
	 * Adapted from // From http://uk.php.net/manual/en/function.ip2long.php
	 *
	 * @param string $ip An IPv4 or IPv6 IP address
	 * @return integer
	 */
	private function getIPVersion($ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
			return 4;
		}
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			return 6;
		}
		return false;
	}
}
