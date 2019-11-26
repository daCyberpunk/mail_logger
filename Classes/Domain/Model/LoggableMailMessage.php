<?php

namespace Pluswerk\MailLogger\Domain\Model;

/***
 *
 * This file is part of an "+Pluswerk AG" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2018 Markus Hölzle <markus.hoelzle@pluswerk.ag>, +Pluswerk AG
 *
 ***/

use Pluswerk\MailLogger\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 */
class LoggableMailMessage extends DebuggableMailMessage
{

    /**
     * @var \Pluswerk\MailLogger\Domain\Model\MailLog
     */
    protected $mailLog;

    /**
     * send mail
     *
     * @return int the number of recipients who were accepted for delivery
     */
    public function send()
    {
        if (empty($this->getFrom())) {
            $this->setFrom(MailUtility::getSystemFrom());
        }
        $mailLogRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(MailLogRepository::class);

        // write mail to log before send
        $this->getMailLog(); //just for init mail log
        $this->assignMailLog();
        $mailLogRepository->add($this->mailLog);
        GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();

        if ($this->sendMessage()) {
            // send mail
            $result = parent::send();
            // write result to log after send
            $this->assignMailLog();
            $this->mailLog->setResult($result);
            $mailLogRepository->update($this->mailLog);
            return $result;
        }

       return 1;

    }

    /**
     * @return MailLog
     */
    public function getMailLog()
    {
        if ($this->mailLog === null) {
            $this->mailLog = GeneralUtility::makeInstance(MailLog::class);
        }
        return $this->mailLog;
    }

    /**
     * @return void
     */
    protected function assignMailLog()
    {
        $this->mailLog->setSubject($this->getSubject());
        if ($this->getBody() !== null) {
            $this->mailLog->setMessage($this->getBody());
        } else {
            $this->mailLog->setMessage($this->getBodiesOfChildren());
        }
        $this->mailLog->setMailFrom($this->getHeaders()->get('from') ? $this->getHeaders()->get('from')->getFieldBody() : '');
        $this->mailLog->setMailTo($this->getHeaders()->get('to') ? $this->getHeaders()->get('to')->getFieldBody() : '');
        $this->mailLog->setMailCopy($this->getHeaders()->get('cc') ? $this->getHeaders()->get('cc')->getFieldBody() : '');
        $this->mailLog->setMailBlindCopy($this->getHeaders()->get('bcc') ? $this->getHeaders()->get('bcc')->getFieldBody() : '');
        $this->mailLog->setHeaders($this->getHeaders()->toString());
    }

    protected function getBodiesOfChildren()
    {
        $string = '';
        if (!empty($this->getChildren())) {
            foreach ($this->getChildren() as $child) {
                $string .= $child->toString() . '<br><br><br><br>';
            }
        }
        return utf8_decode(utf8_encode(quoted_printable_decode($string)));
    }

    /**
     * Check if message is allowed to be forwarded.
     *
     * @param Swift_Mime_Message $message
     *
     * @return bool
     */
    protected function sendMessage()
    {
        $sendAsEmail = true;
        foreach ($this->getTo() as $key => $value) {
            $check = true;
            if (empty($value)) {
                $check = $this->isValidDebugEmailAddress($key);
            } else {
                $check = $this->isValidDebugEmailAddress($key);
            }
            if ($check === false) {
                return false;
            }
        }

        $bcc = $this->getBcc();
        $bcc = !is_array($bcc) ? [] : $bcc;
        foreach ($bcc as $key => $value) {
            $check = true;
            if (empty($value)) {
                $check = $this->isValidDebugEmailAddress($key);
            } else {
                $check = $this->isValidDebugEmailAddress($value);
            }
            if ($check === false) {
                return false;
            }
        }
        //var_dump($sendAsEmail);die;
        return $sendAsEmail;
    }

    /**
     * Check given email address against some patterns.
     *
     * @param string $email
     *
     * @return bool
     */
    protected function isValidDebugEmailAddress($email)
    {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['mail_logger']);

        if (!is_array($settings) || $settings['logAndSendAllMails']) {
            return true;
        }

        $allowedChecks = GeneralUtility::trimExplode(',', $settings['addresses'], true);

        foreach ($allowedChecks as $singleCheck) {
            if ($email === $singleCheck || ($singleCheck === substr($email, (-1) * strlen($singleCheck)))) {
                return true;
            }
        }

        return false;
    }
}
