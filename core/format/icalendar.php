<?php
namespace Core\Format;
/**
 * iCalendar format, version 2.0
 *
 * This will easily allow you to get a calendar event people can save.
 */

class Icalendar
{
    /**
     * The product ID of this event.
     *
     * @var string
     */
    private $prodId;
    /**
     * Unique ID.
     *
     * @var string
     */
    private $uid;

    /**
     * Description/summary of the event.
     *
     * @var string
     */
    private $summary;
    /**
     * Creation unix timestamp.
     *
     * @var int
     */
    private $tsCreated;
    /**
     * Start unix timestamp.
     *
     * @var int
     */
    private $tsStart;
    /**
     * End unix timestamp.
     *
     * @var int
     */
    private $tsEnd;

    /**
     * Create easy/minimal icalendar event.
     *
     * @param string $domain The domain name for this event, used for ids.
     * @param string $summary The summary/description of this event.
     * @param int $tsCreated Creation timestamp.
     * @param int $tsStart Event start unix timestamp.
     * @param int $tsEnd Event end unix timestamp.
     */
    public function __construct($domain, $summary, $tsCreated, $tsStart, $tsEnd)
    {
        $this->summary = $summary;
        $this->tsCreated = $tsCreated;
        $this->tsStart = $tsStart;
        $this->tsEnd = $tsEnd;
        // Generate unique and product ID of this event, based on domain and summary.
        $formattedStart = $this->getFormattedTs($tsStart);
        $this->uid =  "{$formattedStart}@{$domain}";
        $this->prodId = "-//{$domain}/{$formattedStart}//{$summary}//EN";
    }

    /**
     * Return formatted date as UTC time, to avoid timezone shenanigans.
     *
     * @param int $ts
     * @return string
     */
    private function getFormattedTs($ts)
    {
        return gmdate("Ymd", $ts) . "T" . gmdate("His", $ts) . "Z";
    }

    // Get correct mime type for ical.
    public function getMimeType() {
        return "text/calendar";
    }

    /**
     * Create a minimal ical formatted document.
     *
     * They expect CRLF at the end of each block.
     *
     * @return string
     */
    public function __toString()
    {
        $lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:{$this->prodId}",
            "BEGIN:VEVENT",
            "UID: {$this->uid}",
        ];

        $lines[] = "DTSTAMP:" . $this->getFormattedTs($this->tsCreated);
        $lines[] = "DTSTART:" . $this->getFormattedTs($this->tsStart);
        $lines[] = "DTEND:" . $this->getFormattedTs($this->tsEnd);
        $lines[] = "SUMMARY:{$this->summary}";
        $lines[] = "END:VEVENT";
        $lines[] = "END:VCALENDAR";
        return implode("\r\n", $lines);
    }
}