<?php

require_once('simplehtmldom/simple_html_dom.php');

abstract class HtmlImport extends GlobalImport
{
    protected $html;
    protected $website;


    /**
     * Load HTML from URL
     * @param  string $url
     * @return boolean Success
     */
    protected function loadHtml()
    {
        // Note: We use curl (not file_get_html) in silent mode with user agent defined to cover every cases.
        // e.g. Amazon module needs this to read properly a product page.
        $content = shell_exec("curl -sS --compressed -H \"User-Agent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/605.1.15 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/605.1.15'\" \"".$this->source.'"');
        
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
     * @param  object $dom DOM object
     * @param  string $search
     * @param  string $selector
     * @return mixed
     */
    protected function getText($dom, $selector = 'innertext')
    {
        if (!$dom) {
            return null;
        }

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($dom->innertext)));
    }


    /**
     * Get a link
     *
     * @param string $search
     * @param object $dom
     * @return void
     */
    protected function getLink($search, $dom = null)
    {
        return $this->getHtml($search, $dom, 'href');
    }


    /**
     * Get an image source URL from the HTML DOM
     *
     * @param string $search
     * @param object $dom
     * @return void
     */
    protected function getImgSrc($search, $dom = null)
    {
        return $this->urlFullPath($this->getHtml($search, $dom, 'src'));
    }


    /**
     * Get text from a ul list and return it as an array
     *
     * @param object $dom
     * @return array Array of strings
     */
    protected function getList($dom)
    {
        if (!$dom) {
            return null;
        }

        $items = [];
        foreach ($dom->find('li') as $list) {
            $items[] = $this->getText($list);
        }

        return $items;
    }


    /**
     * Download a file from the web
     *
     * @param string $url
     * @param bool   $ignore_errors
     * @return void
     */
    protected function download($url, $ignore_errors = true)
    {
        # download from complete path and ignore errors (will keep the origin url)
        return parent::download($this->urlFullPath($url), true);
    }


    /**
     * Transforms an partial url in a complete one
     *
     * @param  string $url
     * @return string Full url
     */
    protected function urlFullPath($url)
    {
        # returns nothing if bad url
        if (!is_string($url)) {
            return '';
        }

        # if http(s) prefix is missing, add it
        if (substr($url, 0, 2) == '//') {
            # get http or https from website variable if set
            if (isset($this->website) && substr($this->website, 0, 4) == 'http') {
                $url = explode(':', $this->website)[0].":$url";
            } else {
                # or we assume 99% of websites are in https
                $url = "https:$url";
            }
        } elseif (substr($url, 0, 1) == '/') {
            # url starts with /... => add website url
            if (isset($this->website)) {
                $url = $this->website.$url;
            }
        }

        return $url;
    }
}
