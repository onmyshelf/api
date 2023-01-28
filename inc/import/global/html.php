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
        // Note: We use curl (not file_get_html) with user agent defined to cover every cases.
        // e.g. Amazon module needs this to read properly a product page.
        $content = shell_exec("curl --compressed -H \"User-Agent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/605.1.15 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/605.1.15'\" \"".$this->source.'"');
        
        // load page in a simplehtmldom object
        $this->html = str_get_html($content);

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

        return html_entity_decode(trim(strip_tags($dom->innertext)));
    }
}
