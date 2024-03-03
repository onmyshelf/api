<?php

class Property
{
    private $collectionId;
    private $name;
    private $label;
    private $description;
    private $type;
    private $suffix;
    private $default;
    private $authorizedValues;
    private $visibility;
    private $required;
    private $hideLabel;
    private $isId;
    private $isTitle;
    private $isSubTitle;
    private $isCover;
    private $preview;
    private $filterable;
    private $searchable;
    private $sortable;
    private $order;
    private $hidden;


    public function __construct($params=null)
    {
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($params[$p])) {
                $this->$p = $params[$p];
            }
        }
    }


    /**
     * Update property
     * @param  array $data
     * @return bool
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['collection']);
        unset($allowed['name']);

        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        // create in database
        return (new Database)->setProperty($this->collectionId, $this->name, $data);
    }


    /**
     * Delete property
     * @return bool
     */
    public function delete()
    {
        return (new Database)->deleteProperty($this->collectionId, $this->name);
    }


    /*
     *  Static functions
     */

    /**
     * Get property by name
     * @param  int    $collectionId
     * @param  string $name
     * @return object Property object
     */
    public static function getByName($collectionId, $name)
    {
        if (is_null($collectionId)) {
            Logger::error("Called Property::getByName(null,$name)");
            return false;
        }

        $data = (new Database)->getProperty($collectionId, $name);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Get property types
     * @return array
     */
    public static function getTypes()
    {
        return [
            'text',
            'image',
            'number',
            'date',
            'rating',
            'yesno',
            'longtext',
            'datetime',
            'url',
            'file',
            'color',
        ];
    }


    /**
     * Guess property config from its name
     *
     * @param  string $name
     * @return array  Property config
     */
    public static function guessConfigFromName($name)
    {
        $config = [
            "type" => "text",
            "label" => [
                "en_US" => ucfirst($name),
            ],
        ];

        $plural = "";

        // remove plurals
        if (substr($name, -1, 1) == 's') {
            $name = substr($name, 0, -1);
            $plural = "s";
        }

        switch ($name) {
            case 'author':
                $config['label']['fr_FR'] = "Auteur$plural";
                $config['filterable'] = true;
                break;

            case 'color':
            case 'colour':
                $config['type'] = "color";
                $config['label'] = [
                    "en_US" => "Color$plural",
                    "fr_FR" => "Couleur$plural",
                ];
                break;

            case 'cover':
                $config['type'] = 'image';
                $config['label'] = [
                    "en_US" => "Cover$plural",
                    "fr_FR" => "Couverture$plural",
                ];
                // do not consider coverS as a cover
                $config['isCover'] = ($plural == '');
                break;
            
            case 'editor':
                $config['label']['fr_FR'] = "Éditeur$plural";
                break;

            case 'genre':
                $config['filterable'] = true;
                break;

            case 'id':
                // do not consider idS as an id
                $config['isId'] = ($plural == '');
                break;

            case 'image':
            case 'poster':
                $config['type'] = 'image';
                // do not consider images as a main image
                $config['isCover'] = ($plural == '');
                break;

            case 'language':
                $config['label']['fr_FR'] = "Langue$plural";
                break;

            case 'picture':
                $config['type'] = 'image';
                $config['label']['fr_FR'] = "Image$plural";
                // do not consider pictures as a main image
                $config['isCover'] = ($plural == '');
                break;

            case 'comment':
            case 'description':
            case 'synopsi': //(s)
                $config['type'] = 'longtext';
                break;

            case 'rating':
                $config['type'] = 'rating';
                $config['label']['fr_FR'] = "Note$plural";
                $config['sortable'] = true;
                break;

            case 'serie':
                $config['label']['fr_FR'] = "Série$plural";
                break;

            case 'source':
                $config['type'] = 'url';
                break;

            case 'subtitle':
                $config['label'] = [
                    "en_US" => "Subtitle$plural",
                    "fr_FR" => "Sous-titre$plural",
                ];
                // do not consider subtitleS as a subtitle
                $config['isSubTitle'] = ($plural == '');
                break;

            case 'summary':
                $config['type'] = 'longtext';
                $config['label']['fr_FR'] = "Résumé$plural";
                break;

            case 'tag':
                $config['filterable'] = true;
                break;

            case 'title':
                $config['label'] = [
                    "en_US" => "Title$plural",
                    "fr_FR" => "Titre$plural",
                ];
                $config['isTitle'] = true;
                break;

            case 'trailer':
                $config['type'] = 'video';
                break;
            
            case 'year':
                $config['label']['fr_FR'] = "Année$plural";
                $config['sortable'] = true;
                break;
        }

        return $config;
    }
}
