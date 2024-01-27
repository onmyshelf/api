<?php

require_once('html.php');

abstract class AmazonImport extends HtmlImport
{
    protected $amzcategory;


    public function load()
    {
        if (isset($this->website)) {
            return true;
        }

        // get client language
        $lang = substr($GLOBALS['currentLanguage'], 0, 2);

        // choose Amazon localized website
        switch ($lang) {
            case "de":
            case "fr":
                $domain = $lang;
                break;

            default:
                $domain = "com";
                break;
        }

        $this->website = "https://www.amazon.".$domain;

        return true;
    }


    /**
     * Search item
     * @param  string $search
     * @return array
     */
    public function search($search)
    {
        $this->source = $this->website."/s?k=".preg_replace('/\s/', '+', $search);

        if (isset($this->amzcategory)) {
            $this->source .= "&i=".$this->amzcategory;
        }

        $this->loadHtml();

        $results = [];

        $products = $this->html->find('div.s-result-item');

        // ignore bad results
        if (!$products) {
            return false;
        }

        foreach ($products as $product) {
            $source = $this->getHtml('h2 a', $product, 'href');

            // ignore bad results
            if (!$source || substr($source, 0, 12) == '/sspa/click?') {
                continue;
            }

            $results[] = [
                'source' => $this->website.$source,
                'name' => $this->getText($this->getHtml('h2', $product)),
                'image' => $this->getImgSrc('div.s-product-image-container img', $product),
                'description' => $this->getText($this->getHtml('div.a-color-secondary', $product)),
            ];
        }

        return $results;
    }


    protected function getProductImage()
    {
        return $this->getImgSrc('#landingImage');
    }

    protected function getProductTitle()
    {
        return $this->getText($this->getHtml('#productTitle'));
    }

    protected function getProductSubtitle()
    {
        return $this->getText($this->getHtml('#productSubtitle'));
    }

    protected function getRpiAttribute($name)
    {
        return $this->getText($this->getHtml('#rpi-attribute-'.$name.' .rpi-attribute-value'));
    }
}
