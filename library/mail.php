<?php

//Loading an image file.
class Library_Mail
{

    /**
     * Easy way to send a mail with an array of options.
     * 
     * @param array $options
     * <b>Required values</b><br />
     * * to - email address to send to<br />
     * * message - message to send.
     * 
     * <b>Optional values</b><br />
     * * subject - Defaults to 'Automated message.'<br />
     * * from - Defaults to 'no-reply@host'<br />
     * * headers - Array with additional headers, with key => value
     */
    public static function send($options)
    {
        if (empty($options['to']) || empty($options['message'])) {
            return false;
        }

        //Set to and subject.
        $to = trim($options['to']);
        $message = trim($options['message']);
        $subject = !empty($options['subject']) ? trim($options['subject']) : 'Automated message.';

        //Build "from"
        $from = !empty($options['from']) ? $options['from'] : null;
        if (empty($from)) {
            $base_url = $GLOBALS['config']['base_url'];
            $host = parse_url($base_url, PHP_URL_HOST);
            $from = 'no-reply@' . $host;
        }

        //build headers.
        $headers = !empty($options['headers']) ? $options['headers'] : array();
        $headers = array_merge($headers, array(
            'From' => $from,
            'Reply-To' => $from,
            'X-Mailer' => 'UltraLight',
                ));

        $full_headers = '';
        $divider = '';
        foreach ($headers as $type => $val) {
            $full_headers .= $divider . ucwords($type) . ': ' . $val;
            $divider = "\r\n";
        }

        if (!empty($options['trace'])) {
            $message .= "\n\n" . implode("\n", debug_simple());
        }

        return mail($to, $subject, $message, $full_headers);
    }

}