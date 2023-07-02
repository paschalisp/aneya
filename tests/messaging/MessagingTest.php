<?php

use aneya\Messaging\Message;
use aneya\Messaging\MessageOptions;
use aneya\Security\User;
use PHPUnit\Framework\TestCase;

require_once (__DIR__ . '/../../aneya.php');

class MessagingTest extends TestCase {
	public function testStart () {
		/** @var User $user */
		$user = User::load (1);

		// Use a test e-mail address if sadmin has no e-mail set
		if (strlen ($user->email) == 0) {
			$user->email = 'debug@aneyacms.com';
		}

		$options = new MessageOptions ();
		$options->method = Message::SendByEmailAndInternally;
		$status = $user->messaging ()->send ($user->id, 'Self-test', 'This is a <strong>self-test</strong> message', $options);
		$this->assertTrue ($status->isOK ());

		$cnt = $user->messaging ()->inbox ()->count (Message::FlagUnread);
		$this->assertTrue ($cnt > 0);

		$msg = $user->messaging ()->inbox ()->first (Message::FlagUnread);
		$this->assertTrue ($msg instanceof Message);
	}
}
