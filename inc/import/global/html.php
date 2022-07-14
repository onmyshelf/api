<?php

require_once('simplehtmldom/simple_html_dom.php');

abstract class HtmlImport extends GlobalImport
{
    protected $html;
    protected $url;

    /**
     * Class constructor
     * @param string $url     The URL to scan
     * @param array  $options Import options
     */
    public function __construct($url, $options=[])
    {
        $this->url = $url;

        parent::__construct($url, $options);
    }


    /**
     * Load HTML from URL
     * @param  string $url
     * @return boolean Success
     */
    public function loadHtml($url=null)
    {
        if ($url) {
            $this->url = $url;
        }
        
        $this->html = file_get_html($this->url);

        return (!is_null($this->html));
    }


    /**
     * Get data from the HTML DOM
     * @param  object $dom      DOM object
     * @param  string $selector
     * @return mixed
     */
    protected function getHtml($dom, $selector='innertext')
    {
        $find = $this->html->find($dom, 0);

        if (!$find) {
            return null;
        }

        return trim($find->$selector);
    }


    /**
     * Get data from the HTML DOM
     * @param  object $dom      DOM object
     * @param  string $search
     * @param  string $selector
     * @return mixed
     */
    protected function getDom($dom, $search, $selector='innertext')
    {
        $find = $dom->find($search, 0);

        if (!$find) {
            return null;
        }

        if (is_null($selector)) {
            return $find;
        }

        return $find->$selector;
    }


    /**
     * Get data from the HTML DOM
     * @param  object $dom      DOM object
     * @param  string $search
     * @param  string $selector
     * @return mixed
     */
    protected function getText($dom, $search)
    {
        $find = $dom->find($search, 0);

        if (!$find) {
            return null;
        }

        return trim(strip_tags($find->innertext));
    }
}
