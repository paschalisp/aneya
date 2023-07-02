<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * -----------------------------------------------------------------------------
 * The Sole Developer of the Original Code is Paschalis Ch. Pagonidis
 * Portions created by Paschalis Ch. Pagonidis are Copyright (c) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Environment;

use aneya\Core\ApplicationError;
use aneya\Core\CMS;
use aneya\Core\Encrypt;
use aneya\Core\Status;
use aneya\Messaging\Attachment;
use aneya\Messaging\AttachmentCollection;
use aneya\Messaging\Recipients;
use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;

final class Net {
	#region Properties
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#region Host related methods
	/** Returns the client's IP address */
	public static function getIpAddress(): string {
		foreach (array('REMOTE_ADDR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED') as $source) {
			if (array_key_exists($source, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$source]) as $ipAddress) {
					$ipAddress = trim($ipAddress);

					if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
						return $ipAddress;
					}
				}
			}
		}

		// If first method fails, try to remove the extra filters
		foreach (array('REMOTE_ADDR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED') as $source) {
			if (array_key_exists($source, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$source]) as $ipAddress) {
					$ipAddress = trim($ipAddress);

					if (filter_var($ipAddress) !== false) {
						return $ipAddress;
					}
				}
			}
		}

		return '';
	}
	#endregion

	#region Mail methods
	/**
	 * Sends an e-mail
	 *
	 * @param string            $from			The From e-mail address
	 * @param string|Recipients $recipients		List of recipients or the recipient's e-mail address, if string is provided
	 * @param string            $subject		The e-mail's subject
	 * @param string            $body			The e-mail's message
	 * @param bool              $isHtml			(default true) If true will send with text/html content type
	 * @param $attachments		$attachments	Attachment|AttachmentCollection|Attachment[] Files to attach along with the e-mail
	 * @param ?string			$fromName		The name of the sender
	 * @param ?string            $replyTo		The Reply-To e-mail address (equals to "From:" if omitted)
	 * @param bool              $validateEmail	(default false) If true will check DNS records corresponding to the e-mail's domain name
	 * @return Status true if the e-mail was sent successfully
	 */
	public static function sendMail(string $from, $recipients, string $subject, string $body, bool $isHtml = true, $attachments = null, string $fromName = null, string $replyTo = null, bool $validateEmail = false): Status {
		#region Get environment configuration and initialize
		if ($from == null || mb_strlen((trim($from))) == 0) {
			$from = CMS::cfg()->env->email;
		}

		if ($recipients == null || (is_string($recipients) && mb_strlen((trim($recipients))) == 0)) {
			$recipients = CMS::cfg()->env->email;
		}

		$mail_host = CMS::cfg()->env->mail->host;
		$mail_port = CMS::cfg()->env->mail->port;
		$mail_ssec = CMS::cfg()->env->mail->security;
		$mail_user = CMS::cfg()->env->mail->username;
		$mail_pass = CMS::cfg()->env->mail->password;
		#endregion

		$mail = new PHPMailer();
		$mail->CharSet = 'UTF-8';
		$mail->isHTML($isHtml);

		#region Setup mailer
		if (strlen($mail_host) > 0) {
			$mail->isSMTP();
			$mail->Host = $mail_host;
			$mail->Port = $mail_port;
			$mail->SMTPSecure = (in_array($mail_ssec, ['tls', 'ssl'])) ? $mail_ssec : '';

			if (strlen($mail_user) > 0) {
				$mail->SMTPAuth = true;
				$mail->Username = $mail_user;
				try {
					$mail->Password = Encrypt::decrypt($mail_pass);
				} catch (\Exception $e) {
					CMS::app()->log($e, Logger::ERROR);
					return new Status(false, sprintf('Error sending e-mail: %s', $e->getMessage()));
				}
			}
		} else {
			$mail->isSendmail();
		}
		#endregion

		#region Configure recipients
		if ($recipients instanceof Recipients) {
			$recipients = $recipients->toEmails(true);

			foreach ($recipients->to as $to) {
				$mail->addAddress($to);
			}

			foreach ($recipients->cc as $cc) {
				$mail->addCC($cc);
			}

			foreach ($recipients->bcc as $bcc) {
				$mail->addBCC($bcc);
			}

			$to = '<multiple addresses>';
		} elseif (is_string($recipients) && self::validateEmail($recipients, $validateEmail)) {
			$to = $recipients;
			$mail->addAddress($to);
		} else {
			CMS::logger()->warning("Invalid recipients argument provided [$recipients]");
			return new Status (false, 'Invalid recipients');
		}
		#endregion

		#region Configure e-mail message
		$mail->From = $from;
		$mail->FromName = $fromName ?? $from;
		if (strlen($replyTo) > 0)
			$mail->addReplyTo($replyTo, $replyTo);
		else
			$mail->addReplyTo($from, $fromName ?? $from);

		$mail->Subject = $subject;
		$mail->Body = $body;
		#endregion

		#region Configure attachments
		if ($attachments instanceof Attachment)
			$attachments = [$attachments];

		if ($attachments instanceof AttachmentCollection) {
			foreach ($attachments->all() as $attachment) {
				try {
					$mail->addAttachment(CMS::filesystem()->normalize($attachment->fileUrl), $attachment->fileName, 'base64', $attachment->contentType());
				} catch (\Exception $e) {
					CMS::app()->log($e, Logger::NOTICE);
				}
			}
		} elseif (is_array($attachments)) {
			foreach ($attachments as $attachment) {
				if ($attachment instanceof Attachment) {
					try {
						$mail->addAttachment(CMS::filesystem()->normalize($attachment->fileUrl), $attachment->fileName, 'base64', $attachment->contentType());
					} catch (\Exception $e) {
						CMS::app()->log($e, Logger::NOTICE);
					}
				}
			}
		}
		#endregion

		try {
			if ($mail->send()) {
				$status = new Status(true, 'Message has been sent successfully');
				CMS::logger()->info("An e-mail sent from $from to the address $to");
			} else {
				$status = new Status(false, $mail->ErrorInfo, -1, $mail->ErrorInfo);
				CMS::app()->log(new ApplicationError("Error while trying to send e-mail from $from to the address $to [$mail->ErrorInfo]"), ApplicationError::SeverityInfo);
			}
		} catch (\Exception $e) {
			$status = new Status(false, $e->getMessage(), -2, $e->getTraceAsString());
			CMS::app()->log(new ApplicationError("Error while trying to send e-mail from $from to the address $to"), ApplicationError::SeverityInfo);
		}

		return $status;
	}

	/**
	 * Checks if an e-mail address has valid format.
	 * If the second argument is true, it additionally checks whether the e-mail address's domain name has MX record set.
	 *
	 * @param string $email The e-mail address to check
	 * @param bool $checkDNS (false) Indicates whether to do deeper validation by checking the DNS
	 * @return boolean true if the given e-mail is valid; otherwise false
	 */
	public static function validateEmail(string $email, bool $checkDNS = false): bool {
		$ret = (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
		if ($ret === false)
			return false;

		if ($checkDNS) {
			$domain = substr($email, strpos($email, '@') + 1);
			return checkdnsrr($domain, 'MX');
		}

		return true;
	}

	/** Checks if a web address has valid format */
	public static function validateWebAddress(string $url): bool {
		return (self::validateUrl($url) && strpos(parse_url($url, PHP_URL_HOST), '.') > 0);
	}

	/** Checks if a URL has valid format */
	public static function validateUrl(string $url): bool {
		return (in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https')) && filter_var($url, FILTER_VALIDATE_URL) !== false);
	}
	#endregion
	#endregion
}
