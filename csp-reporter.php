<?php
// Configuration -->
$recipients = [
	'csp@example.org',
];

$subject = 'CSP Violation on %s';

// Blacklist of domains to not report to you.
$blacklist = [
	'img-src' => [
	],
	'style-src' => [
	],
	'script-src' => [
	],
	'connect-src' => [
	],
	'font-src' => [
	],
	'default-src' => [
	],
	'frame-src' => [
	],
	'extensions' => [
		'safari-extension',
		'chrome-extension',
		'moz-extension://',
	],
];
// <-- Configuration

// Actual Code -->
$inputData = file_get_contents('php://input');
$jsonData  = json_decode($inputData, true);

if (!is_array($jsonData))
{
	return;
}

// Detect violated-directive 
$explode           = explode(' ', $jsonData['csp-report']['violated-directive']);
$violatedDirective = $explode[0] ? $explode[0] : 'none';
$blockedUri        = $jsonData['csp-report']['blocked-uri'];
$blockedUri        = str_replace('https://www.', '', $blockedUri);
$blockedUri        = str_replace('http://www.', '', $blockedUri);
$blockedUri        = str_replace('https://', '', $blockedUri);
$blockedUri        = str_replace('http://', '', $blockedUri);
$blockeddomain     = explode('/', $blockedUri);
$ip                = explode(':', $blockeddomain[0]);

// Some Browser Plugin missuse our csp by setting "violated-directive": "script-src 'none'"
if (substr($jsonData['csp-report']['violated-directive'], 0, 17) === "script-src 'none'")
{
	return;
}

// Return in case we have a IP as this is invalid anyway
if (filter_var($ip[0], FILTER_VALIDATE_IP) !== false)
{
	return;
}

// Check that the current report is not on the blacklist for sending mails else send mail
if (!in_array($blockeddomain[0], $blacklist[$violatedDirective]) && !in_array(substr($jsonData['csp-report']['blocked-uri'], 0, 16), $blacklist['extensions']))
{
	$mailData = json_encode(
		$jsonData,
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);

	// Add UserAgent, Blocked Domain and Blocked Uri value String
	$mailData .= "\n\n" . 'UserAgent: ' . $_SERVER['HTTP_USER_AGENT'];
	$mailData .= "\n\n" . 'Violated Directive: ' . $violatedDirective;
	$mailData .= "\n\n" . 'Blocked Domain: ' . $blockeddomain[0];
	$mailData .= "\n\n" . 'Blocked Uri: ' . $jsonData['csp-report']['blocked-uri'];
	$mailData .= "\n\n" . 'IP: ' . $ip[0];

	$website = ($jsonData['csp-report']['document-uri'] ? $jsonData['csp-report']['document-uri'] : 'Unknown Website');

	// Loop over all recipients
	foreach ($recipients as $recipient)
	{
		// Mail the report to the recipient.
		mail($recipient, sprintf($subject, $website), $mailData);
	}
}
