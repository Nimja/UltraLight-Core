<?php

namespace Core\File;

/**
 * Email class for some simple structure, will default to certain values from config.
 */
class Email
{
    private $fromEmail;
    private $fromName;
    private $replyTo;

    public function __construct($fromName, $fromEmail = null)
    {
        $this->fromName = $fromName;
        $this->fromEmail = $this->checkEmail(
            $fromEmail ?: \Config::system()->get('contact', 'emailFrom')
        );
        $this->replyTo = $this->fromEmail;
    }

    public function setReplyTo($replyTo)
    {
        $this->replyTo = $this->checkEmail($replyTo);
    }

    private function checkEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw \Exception("$email is not valid!");
        }
        return $email;
    }

    public function formatEmail($name, $email)
    {
        return "$name <{$email}>";
    }

    public function sendTextEmail($subject, $body, $emailTo = null)
    {
        $emailTo = $this->checkEmail(
            $emailTo ?: \Config::system()->get('contact', 'emailTo')
        );
        $headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/plain; charset=iso-8859-1',
            'From' => $this->formatEmail($this->fromName, $this->fromEmail),
            'X-Mailer' => 'PHP/' . phpversion(),
        ];
        if ($this->fromEmail != $this->replyTo && !empty($this->replyTo)) {
            $headers['Reply-To'] = $this->replyTo;
        }
        $result = mail($emailTo, $subject, $body, $headers);
        return $result;
    }
}
