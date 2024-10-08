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
    private $multiple;
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
     * Get values
     * @param  string $search (optionnal)
     * @return array  Values
     */
    public function getValues($search=null)
    {
        return (new Database)->getPropertyValues($this->collectionId, $this->name, $search);
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
            // remove non allowed data
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }

            // check values
            switch ($key) {
                case 'type':
                    if (!in_array($data[$key], Property::getTypes())) {
                        Logger::debug("Update property error: bad $key: ".$data[$key]);
                        return false;
                    }
                    break;
                case 'visibility':
                    if (!Visibility::validateLevel($data[$key])) {
                        Logger::debug("Update property error: bad $key: ".$data[$key]);
                        return false;
                    }
                    break;
            }
        }

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
        // default type is text
        // default label in english is the name with first 
        $config = [
            "type" => "text",
            "label" => [
                "en_US" => ucfirst(preg_replace('/_/', ' ', $name)),
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
                $config['multiple'] = ($plural == "s");
                break;

            case 'category':
                $config['label']["fr_FR"] = "Catégorie";
                $config['filterable'] = true;
                break;

            case 'categorie': //(s)
                $config['label']["fr_FR"] = "Catégories";
                $config['filterable'] = true;
                $config['multiple'] = true;
                break;
            
            case 'collection':
                $config['filterable'] = true;
                $config['multiple'] = ($plural == 's');
                break;

            case 'color':
            case 'colour':
                $config['type'] = "color";
                $config['label'] = [
                    "en_US" => "Color$plural",
                    "fr_FR" => "Couleur$plural",
                ];
                $config['multiple'] = ($plural == 's');
                break;

            case 'cover':
                $config['type'] = 'image';
                $config['label']['fr_FR'] = "Couverture$plural";
                // do not consider multiple covers as the main cover
                $config['isCover'] = ($plural == '');
                break;

            case 'date':
                $config['sortable'] = ($plural == '');
                $config['multiple'] = ($plural == 's');
                break;

            case 'editor':
                $config['label']['fr_FR'] = "Éditeur$plural";
                $config['filterable'] = true;
                $config['multiple'] = ($plural == "s");
                break;

            case 'format':
            case 'genre':
            case 'type':
                $config['filterable'] = true;
                $config['multiple'] = ($plural == "s");
                break;

            case 'id':
                // do not consider idS as an id
                $config['isId'] = ($plural == '');
                $config['multiple'] = ($plural == "s");
                break;

            case 'image':
            case 'poster':
                $config['type'] = 'image';
                // do not consider multiple images as the main image
                $config['isCover'] = ($plural == '');
                $config['multiple'] = ($plural == "s");
                break;

            case 'language':
                $config['label']['fr_FR'] = "Langue$plural";
                $config['filterable'] = true;
                $config['multiple'] = ($plural == "s");
                break;

            case 'name':
                $config['label']['fr_FR'] = "Nom$plural";
                $config['isTitle'] = true;
                break;

            case 'picture':
                $config['type'] = 'image';
                $config['label']['fr_FR'] = "Image$plural";
                // do not consider pictures as the main image
                $config['isCover'] = ($plural == '');
                $config['multiple'] = ($plural == "s");
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
            
            case 'ref':
            case 'reference':
                $config['label'] = [
                    "en_US" => "Reference$plural",
                    'fr_FR' => "Référence$plural"
                ];
                // do not consider references as an id
                $config['isId'] = ($plural == '');
                $config['multiple'] = ($plural == "s");
                break;

            case 'serie':
                $config['label']['fr_FR'] = "Série$plural";
                $config['filterable'] = true;
                break;

            case 'source':
                $config['type'] = 'url';
                break;

            case 'subtitle':
                $config['label']['fr_FR'] = "Sous-titre$plural";
                // do not consider subtitleS as the main sub-title
                $config['isSubTitle'] = ($plural == '');
                break;

            case 'summary':
                $config['type'] = 'longtext';
                $config['label']['fr_FR'] = "Résumé$plural";
                break;

            case 'tag':
                $config['filterable'] = true;
                $config['multiple'] = ($plural == "s");
                break;

            case 'title':
                $config['label']['fr_FR'] = "Titre$plural";
                $config['isTitle'] = true;
                break;

            case 'year':
                $config['label']['fr_FR'] = "Année$plural";
                $config['sortable'] = ($plural == '');
                $config['multiple'] = ($plural == 's');
                break;
        }

        return $config;
    }
}
