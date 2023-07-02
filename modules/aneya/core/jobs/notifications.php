<?php
require_once '../../../aneya.php';

use aneya\Core\CMS;
use aneya\Core\Environment\Net;
use aneya\Messaging\Message;
use aneya\Security\User;
use aneya\Snippets\Snippet;

$db = CMS::db();

$sql = "SELECT T1.recipient_id, T2.email, T2.default_language, count(*) AS cnt
		FROM cms_messaging T1
		JOIN cms_users T2 ON T2.user_id=T1.recipient_id
		WHERE T1.date_sent>=date_sub(now(),INTERVAL 1 HOUR) AND T1.status=:status AND T1.priority=:priority AND T2.status IN (:enabled, :locked)
		GROUP BY T1.recipient_id, T2.email, T2.default_language";
$rows = $db->fetchAll ($sql, array (':status' => Message::StatusUnread, ':priority' => Message::PriorityNotify, ':enabled' => User::StatusActive, ':locked' => User::StatusLocked));
if ($rows) {
	foreach ($rows as $row) {
		$lang = $row['default_language'];
		CMS::translator()->setCurrentLanguage($lang);

		$s = new Snippet();
		$s->loadContentFromDb ('system-email-notification');
		$s->params->num = $row['cnt'];
		$subject = $s->title;
		$subject = str_replace ('{num}', $row['cnt'], $subject);

		$body = $s->compile ();

		Net::sendMail (CMS::app()->systemEmail, $row['email'], $subject, $body);
	}
}

echo "Sent " . count($rows) . " e-mail notifications for the past hour.";
