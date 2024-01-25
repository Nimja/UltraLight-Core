<?php

namespace Core\Tool;

use Core\File\Email;

/**
 * Simple contact class.
 */
class Contact
{
    protected $_rules = [
        'email' => 'email',
        'subject' => 'empty',
        'text' => 'empty|10',
    ];
    public $title = 'Contact';
    /**
     * The email we send to.
     * @var string
     */
    protected $_emailTo;
    /**
     * The email we send from.
     * @var string
     */
    protected $_emailFrom;
    /**
     * Multiple subjects.
     * @var array
     */
    protected $_subjects;
    /**
     *
     * @var array
     */
    protected $_values;
    /**
     *
     * @var boolean
     */
    protected $_isValid = false;
    /**
     *
     * @var array
     */
    protected $_warnings = [];

    /**
     * Thank page url.
     *
     * @var string
     */
    protected $thanksPageUrl = '';

    /**
     * Thank page url.
     *
     * @var string
     */
    protected $extra = '';

    /**
     * Basic constructor.
     * @param string $emailTo
     * @param string $emailFrom
     * @param array $subjects
     */
    public function __construct($emailTo, $emailFrom, $subjects, $thanksPageUrl, $extra = '')
    {
        $this->_emailTo = $emailTo;
        $this->_emailFrom = $emailFrom;
        $this->_subjects = [];
        $this->thanksPageUrl = $thanksPageUrl;
        $this->extra = strval($extra);
        foreach ($subjects as $key => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }
            $this->_subjects[$key] = [
                'value' => getkey($values, 0, ''),
                'data' => [
                    'text' => getkey($values, 1, '')
                ],
                'isOption' => true,
            ];
        }
        $this->_values = \Request::getValues();
        // Check for posting.
        $this->_isValid = false;
        // Validate the content to check if we want to send it or not.
        if (\Request::isPost()  && !empty($this->_values)) {
            $validator = new \Core\Form\Validate();
            $this->_isValid = $validator->validate($this->_values, $this->_rules);
            $this->_warnings = $validator->warnings;
            $chosenSubject = getKey($this->_values, 'subject');
            # Only allow choosable subjects.
            if ($this->_isValid && (!array_key_exists($chosenSubject, $this->_subjects))) {
                $this->_isValid = false;
                $this->_warnings = ['Pick a subject'];
            }
        }
    }

    public function __toString()
    {
        return $this->_isValid ? $this->showThanks() : strval($this->getForm());
    }

    protected function getContent()
    {
        return \Sanitize::from_html_entities(getKey($this->_values, 'text'));
    }

    /**
     * Send email and redirect to the thank you page.
     *
     * @return string
     */
    protected function showThanks()
    {
        $replyTo = getKey($this->_values, 'email');
        $subject = \Config::system()->get('site', 'host', 'host');
        $chosenSubject = getKey($this->_values, 'subject');
        $subject .= ' - ' . $chosenSubject;
        $subject .= " from {$replyTo}";
        # The pretty subject text.
        $subjectText = $this->_subjects[$chosenSubject]['value'];

        $text = $this->getContent();
        # Check for spam, if so, we redirect without emailing.
        if (\Nimja\Spam::isSpam($replyTo, $text)) {
            \Request::redirect($this->thanksPageUrl . '#');
        }
        $ln = "\r\n";
        $extra = $this->extra;

        $content = "{$replyTo} wrote:{$ln}Subject: {$subjectText}{$ln}{$extra}Text: {$text}";

        $email = new Email('Contact');
        $email->setReplyTo($replyTo);
        $result = $email->sendTextEmail($subject, $content);

        if (!$result) {
            \Show::fatal("Error sending email?!?");
        }
        \Request::redirect($this->thanksPageUrl);
        return 'Thank you!';
    }

    /**
     * Get form object., used for the contact form.
     *
     * @return \Core\Form
     */
    protected function getForm()
    {
        $form = new \Core\Form(null, ['class' => 'form-horizontal']);
        $form->setWarnings($this->_warnings);
        $form->add(new \Core\Form\Field\Hidden('id', ['value' => getKey($this->_values, 'id')]));
        $form->add(new \Core\Form\Field\Input('email', ['label' => 'Email', 'type' => 'email', 'placeholder' => 'Your email (or anonymous@email.com)']));
        $form->add(
            new \Core\Form\Field\Select(
                'subject',
                ['values' => $this->_subjects, 'label' => 'Subject', 'id' => 'c_subject']
            )
        );
        $script = '';
        $scriptLines = [
            'var contactSubject = document.getElementById("c_subject");',
            'var contactText = document.getElementById("c_message");',
            'function contactUpdate() {',
            'contactText.setAttribute("placeholder", contactSubject.children.item(contactSubject.selectedIndex).dataset[\'text\']);',
            '}',
            'contactSubject.onchange = contactUpdate; contactUpdate();',
        ];
        $script = "<script>\n" . implode(PHP_EOL, $scriptLines) . "\n</script>";
        $form->add(
            new \Core\Form\Field\Text(
                'text',
                [
                    'label' => 'Text',
                    'id' => 'c_message',
                    'rows' => 10,
                ]
            )
        );
        $form->add(new \Core\Form\Field\Submit('submit', ['value' => 'Send', 'class' => 'btn-primary']));
        $form->add($script);
        return $form;
    }
}
