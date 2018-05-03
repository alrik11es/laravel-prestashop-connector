<?php
namespace Alr\Laravel\Prestashop\Models;

use Alr\Laravel\Prestashop\PrestaShopWebservice;

class BaseModel
{
    protected $model_name;
    protected $model_name_plural;
    /** @var PrestaShopWebservice ws */
    protected $ws;
    private $attributes;

    protected $unwritable = [];

    public function __construct()
    {
        $arr = explode('\\', strtolower(get_called_class()));
        $this->model_name = last($arr);
        $this->model_name_plural = str_plural(last($arr));
        $this->ws = new PrestaShopWebservice(getenv('PRESTASHOP_URL'), getenv('PRESTASHOP_TOKEN'), false);
        $this->unwritable = array_merge($this->unwritable, ['unwritable', 'model_name', 'model_name_plural', 'ws', 'level_depth', 'nb_products_recursive']);
    }

    public static function find($id)
    {
        $instance = new static;
        $xml = $instance->ws->get(['resource' => $instance->model_name_plural, 'id' => $id]);

//        echo json_encode($xml, JSON_PRETTY_PRINT);
        $attr = $instance->generateStructure($xml);

        $instance->attributes = $attr->{$instance->model_name} ?? $instance->attributes;

        $r = $instance->hydrate($instance->attributes);

        return $r;
    }

    public function generateStructure($element)
    {
        $object = new \stdClass();
        /** @var \SimpleXMLElement $item */
        foreach($element as $item) {
            if($item->count() > 0){
                $object->{$item->getName()} =  $this->generateStructure($item);
            } else {
                $object->{$item->getName()} = new \stdClass();
                if($item->attributes()) {
                    $attr = (object) json_decode(json_encode($item->attributes()), true);
                    $object->{$item->getName()}->{'@attributes'} = (object) $attr->{'@attributes'};
                }
                $object->{$item->getName()}->value = (string) $item;
            }
        }
        return $object;
    }

    public function hydrate($result)
    {
        foreach($result as $item => $value){
            $this->$item = $value;
        }
        return $this;
    }

    public function save()
    {
        $xml = new \SimpleXMLElement('<prestashop/>');
        $child = $xml->addChild($this->model_name_plural);

        $this->addChilren($child, $this->attributes);

        $opt['resource'] = $this->model_name_plural;
        $opt['putXml'] = $xml->asXML();
        $opt['id'] = $this->id->value;

        $xml = $this->ws->edit($opt);

        return $xml;
    }

    /**
     * @param $xml \SimpleXMLElement
     * @param $elements
     */
    public function addChilren($xml, $elements)
    {
        foreach(get_object_vars($elements) as $item => $value){
            if(!in_array($item, $this->unwritable)) {
                $child = $xml->addChild($item, '');
                if (!is_object($value)) {
                    $child[0] = $value;
                } else {
                    if (isset($value->{'@attributes'})) {
                        $child[0] = $value->value;
                        foreach ($value->{'@attributes'} as $attr => $val) {
                            $child->addAttribute($attr, $val);
                        }
                    } else if(isset($value->value)) {
                        $child[0] = $value->value;
                    } else {
                        $this->addChilren($child, $value);
                    }
                }
            }
        }
    }

    public function __get($name)
    {
        $result = null;
        if(in_array($name, (array) $this->attributes)) {
            $result = $this->attributes->$name->value;
        } else {
            $result = $this->$name;
        }
        return $result;
    }

    function __set($name, $value)
    {
        if(in_array($name, (array) $this->attributes)) {
            $this->attributes->$name->value = $value;
        } else {
            $this->$name = $value;
        }
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }
}