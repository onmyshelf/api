<?php
/*
 * XML file import
 */

require_once('simplehtmldom/simple_html_dom.php');

class HtmlImport extends GlobalImport
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
        $this->html = file_get_html($url);

        parent::__construct($url, $options);
    }


    /**
     * Get data from the HTML DOM
     * @param  object $dom      DOM object
     * @param  string $selector
     * @return mixed
     */
    public function getData($dom, $selector='innertext')
    {
        $find = $this->html->find($dom);

        if (count($find) == 0) {
            return null;
        }

        return $find[0]->$selector;
    }
}
