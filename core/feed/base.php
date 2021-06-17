<?php

namespace Core\Feed;

/**
 * Easy class to generate RSS feeds with standardised values.
 *
 * Can export to RSS 2.0 or Atom.
 */
class Base
{
    protected $asRss;
    protected $values;

    /**
     * Basic instantiation.
     *
     * @param array $values
     * @param boolean $asRss
     */
    public function __construct(array $values, bool $asRss = true)
    {
        $this->asRss = $asRss;
        $this->values = $values;
        $this->items = [];
    }

    /**
     * Add item to this property.
     *
     * @param Item $item
     * @return void
     */
    public function addItem(Item $item)
    {
        $item->setAsRss($this->asRss);
        $this->items[] = $item;
    }

    /**
     * Very simple XML line.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    protected function xmlLine(string $name, string $value)
    {
        return "<{$name}>{$value}</{$name}>\n";
    }

    /**
     * Map xml names to properties.
     *
     * @return array
     */
    protected function getPropertiesMap()
    {
        if ($this->asRss) {
            return [
                'title' => 'title',
                'link' => 'url',
                'description' => 'description',
                'category' => 'category',
                'language' => 'language',
            ];
        } else {
            return [
                'title' => 'title',
                'id' => 'url',
            ];
        }
    }

    protected function mapPropertiesToXml()
    {
        $properties = $this->getPropertiesMap();

        $result = [];
        foreach ($properties as $name => $prop) {
            $val = getKey($this->values, $prop);
            if (!empty($val)) {
                $result[] = $this->xmlLine($name, $val);
            }
        }
        return implode("", $result);
    }

    protected function getDateString($timestamp)
    {
        if ($this->asRss) {
            return date('r', $timestamp);
        } else {
            return date('c', $timestamp);
        }
    }

    protected function getAdditionalLines()
    {
        $result = $this->asRss ? $this->getAdditionalRss() : $this->getAdditionalAtom();
        return implode("", $result);
    }

    protected function getAdditionalRss()
    {
        $result = [];
        if (array_key_exists('date', $this->values)) {
            $result[] = $this->xmlLine('pubDate', $this->getDateString($this->values['date']));
        }
        if (array_key_exists('icon', $this->values)) {
            $image = [
                $this->xmlLine('url', $this->values['icon']),
                $this->xmlLine('title', $this->values['title']),
                $this->xmlLine('link', $this->values['url']),
            ];
            $result[] = $this->xmlLine('image', implode("", $image));
        }
        return $result;
    }

    protected function getAdditionalAtom()
    {
        $result = [];
        if (array_key_exists('author', $this->values)) {
            $result[] = $this->xmlLine('author', $this->xmlLine('name', $this->values['author']));
        }
        if (array_key_exists('icon', $this->values)) {
            $result[] = $this->xmlLine('icon', $this->values['icon']);
        }
        if (array_key_exists('url', $this->values)) {
            $result[] = "<link href=\"{$this->values['url']}\"/>";
        }
        if (array_key_exists('date', $this->values)) {
            $xmlname = $this instanceof Item ? 'updated' : 'updated';
            $result[] = $this->xmlLine($xmlname, $this->getDateString($this->values['date']));
        }
        return $result;
    }

    protected function getBase()
    {
        $result = ['<?xml version="1.0" encoding="utf-8" ?>'];
        if ($this->asRss) {
            $result[] = '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
            $result[] = '<channel>';
            $result[] = sprintf('<atom:link href="%s" rel="self" type="application/rss+xml" />', \Core::$requestUrl);
            $result[] = '%s';
            $result[] = '</channel>';
            $result[] = '</rss>';
        } else {
            $result[] = '<feed xmlns="http://www.w3.org/2005/Atom">';
            $result[] = sprintf('<link href="%s" rel="self"/>', \Core::$requestUrl);
            $result[] = '%s';
            $result[] = '</feed>';
        }
        return implode("\n", $result);
    }

    public function __toString()
    {
        $details = $this->mapPropertiesToXml();
        $details .= $this->getAdditionalLines();
        $details .= implode("\n", $this->items);
        return sprintf($this->getBase(), $details);
    }
}
