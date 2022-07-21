<?php

require_once('simplehtmldom/simple_html_dom.php');

abstract class HtmlImport extends GlobalImport
{
    protected $html;

    /**
     * Load HTML from URL
     * @param  string $url
     * @return boolean Success
     */
    public function loadHtml()
    {
        $this->html = file_get_html($this->source);

        return !is_null($this->html);
    }


    /**
     * Get data from the HTML DOM
     * @param  object $dom      DOM object
     * @param  string $search
     * @param  string $selector
     * @return mixed
     */
    protected function getHtml($search, $dom = null, $selector = null)
    {
        if (is_null($dom)) {
            $dom = $this->html;
        }

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
    protected function getText($dom, $selector = 'innertext')
    {
        if (!$dom) {
            return null;
        }

        return trim(strip_tags($dom->innertext));
    }
}
