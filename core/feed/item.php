<?php

namespace Core\Feed;

/**
 * Easy class to generate RSS feeds with standardised values.
 *
 * Can export to RSS 2.0 or Atom.
 */
class Item extends Base
{
    public function setAsRss($asRss) {
        $this->asRss = $asRss;
    }

    /**
     * Map xml names to properties for items.
     *
     * This is slightly different from top level in some cases.
     *
     * @return array
     */
    protected function getPropertiesMap() {
        if ($this->asRss) {
            return [
                'title' => 'title',
                'link' => 'url',
                'guid' => 'url',
                'description' => 'description',
                'category' => 'category',
                'language' => 'language',
            ];
        } else {
            return [
                'title' => 'title',
                'id' => 'url',
                'summary' => 'description',
            ];
        }
    }

    protected function getBase()
    {
        if ($this->asRss) {
            return "<item>\n%s</item>";
        } else {
            return "<entry>\n%s</entry>";
        }
    }
}
