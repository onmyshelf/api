<?php

require_once('inc/import/global/amazon.php');

class Import extends AmazonImport
{
    public function load()
    {
        $this->amzcategory = 'stripbooks';

        $this->properties = [
            'source',
            'title',
            'subtitle',
            'author',
            'isbn',
            'cover',
            'summary',
            'date',
            'weight',
            'pages',
            'language',
            'editor',
        ];

        return parent::load();
    }


    /**
     * Get item properties from Amazon product page
     * @return array
     */
    public function getData()
    {
        $this->loadHtml();
        
        return [
            'source' => $this->source,
            'title' => $this->getProductTitle(),
            'subtitle' => $this->getProductSubtitle(),
            'author' => $this->getText($this->getHtml('#byLineInfo')),
            'isbn' => $this->getISBN(),
            'cover' => $this->getProductImage(),
            'summary' => $this->getText($this->getHtml('#bookDescription_feature_div')),
            'date' => $this->getRpiAttribute('book_details-publication_date'),
            'pages' => $this->getPages(),
            'language' => $this->getRpiAttribute('language'),
            'editor' => $this->getRpiAttribute('book_details-publisher'),
        ];
    }


    protected function getProductImage()
    {
        $image = $this->getHtml('#imgBlkFront', null, 'src');
        if (!$image) {
            $image = $this->getHtml('#ebooksImgBlkFront', null, 'src');
        }

        return $image;
    }


    private function getISBN()
    {
        // try to get the ISBN-13
        $isbn = $this->getRpiAttribute('book_details-isbn13');

        if (!$isbn) {
            // or else, try to get the ISBN-10
            $isbn = $this->getRpiAttribute('book_details-isbn10');
        }

        return $isbn;
    }


    private function getPages()
    {
        $pages = $this->getRpiAttribute('book_details-fiona_pages');

        if (!$pages) {
            return null;
        }

        return str_replace(' pages', '', $pages);
    }
}
