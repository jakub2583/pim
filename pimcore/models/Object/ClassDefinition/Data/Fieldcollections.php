<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    Object|Class
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Object\ClassDefinition\Data;

use Pimcore\Model;
use Pimcore\Model\Webservice;
use Pimcore\Tool\Cast;
use Pimcore\Logger;

class Fieldcollections extends Model\Object\ClassDefinition\Data
{

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "fieldcollections";

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = "\\Pimcore\\Model\\Object\\Fieldcollection";

    /**
     * @var string
     */
    public $allowedTypes = [];

    /**
     * @var boolean
     */
    public $lazyLoading;

    /**
     * @var int
     */
    public $maxItems;

    /**
     * @var boolean
     */
    public $disallowAddRemove;

    /**
     * @var boolean
     */
    public $disallowReorder;

    /**
     * @var boolean
     */
    public $collapsed;

    /**
     * @var boolean
     */
    public $collapsible;

    /**
     * @return boolean
     */
    public function getLazyLoading()
    {
        return $this->lazyLoading;
    }

    /**
     * @param  $lazyLoading
     * @return $this
     */
    public function setLazyLoading($lazyLoading)
    {
        $this->lazyLoading = $lazyLoading;

        return $this;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataForEditmode
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        $editmodeData = [];
        $idx = -1;

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($data as $item) {
                $idx++;

                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                $collectionData = [];

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    if (!$fd instanceof CalculatedValue) {
                        $collectionData[$fd->getName()] = $fd->getDataForEditmode($item->{$fd->getName()}, $object, $params);
                    }
                }

                $calculatedChilds = [];
                self::collectCalculatedValueItems($collectionDef->getFieldDefinitions(), $calculatedChilds);

                if ($calculatedChilds) {
                    foreach ($calculatedChilds as $fd) {
                        $data = new \Pimcore\Model\Object\Data\CalculatedValue($fd->getName());
                        $data->setContextualData("fieldcollection", $this->getName(), $idx, null, null, null, $fd);
                        $data = $fd->getDataForEditmode($data, $object, $params);
                        $collectionData[$fd->getName()] = $data;
                    }
                }


                $editmodeData[] = [
                    "data" => $collectionData,
                    "type" => $item->getType(),
                    "oIndex" => $idx
                ];
            }
        }

        return $editmodeData;
    }

    /**
     * @see Model\Model\Object\ClassDefinition\Data::getDataFromEditmode
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        $values = [];
        $count = 0;

        if (is_array($data)) {
            foreach ($data as $collectionRaw) {
                $collectionData = [];
                $collectionKey = $collectionRaw["type"];

                $oIndex = $collectionRaw["oIndex"];

                $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($collectionKey);
                $fieldname = $this->getName();

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    if (array_key_exists($fd->getName(), $collectionRaw["data"])) {
                        $collectionData[$fd->getName()] = $fd->getDataFromEditmode($collectionRaw["data"][$fd->getName()], $object,
                            [
                                "context" => [
                                    "containerType" => "fieldcollection",
                                    "containerKey" => $collectionKey,
                                    "fieldname" => $fieldname,
                                    "index" => $count,
                                    "oIndex" => $oIndex
                                ]
                            ]
                        );
                    }
                }

                $collectionClass = "\\Pimcore\\Model\\Object\\Fieldcollection\\Data\\" . ucfirst($collectionRaw["type"]);
                $collection = new $collectionClass;
                $collection->setValues($collectionData);
                $collection->setIndex($count);
                $collection->setFieldname($this->getName());

                $values[] = $collection;

                $count++;
            }
        }

        $container = new \Pimcore\Model\Object\Fieldcollection($values, $this->getName());

        return $container;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getVersionPreview
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getVersionPreview($data, $object = null, $params = [])
    {
        return "FIELDCOLLECTIONS";
    }

    /**
     * converts object data to a simple string value or CSV Export
     * @abstract
     * @param Model\Object\AbstractObject $object
     * @param array $params
     * @return string
     */
    public function getForCsvExport($object, $params = [])
    {
        return "NOT SUPPORTED";
    }

    /**
     * @param string $importValue
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return null
     */
    public function getFromCsvImport($importValue, $object = null, $params = [])
    {
        return;
    }

    /**
     * @param $object
     * @param mixed $params
     * @return string
     */
    public function getDataForSearchIndex($object, $params = [])
    {
        $dataString = "";
        $fcData = $this->getDataFromObjectParam($object);
        if ($fcData instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($fcData as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    $dataString .= $fd->getDataForSearchIndex($item, $params) . " ";
                }
            }
        }

        return $dataString;
    }

    /**
     * @param $object
     * @param array $params
     * @throws \Exception
     */
    public function save($object, $params = [])
    {
        $container = $this->getDataFromObjectParam($object);

        if (is_null($container)) {
            $container = new \Pimcore\Model\Object\Fieldcollection();
            $container->setFieldname($this->getName());
        }

        if ($container instanceof \Pimcore\Model\Object\Fieldcollection) {
            $params = [
                "context" => [
                    "containerType" => "fieldcollection",
                    "fieldname" => $this->getName()
                ]
            ];

            $container->save($object, $params);
        } else {
            throw new \Exception("Invalid value for field \"" . $this->getName()."\" provided. You have to pass a Model\Object\\Fieldcollection or 'null'");
        }
    }

    /**
     * @param $object
     * @param array $params
     * @return null|Model\Object\Fieldcollection
     */
    public function load($object, $params = [])
    {
        if (!$this->getLazyLoading() || (isset($params["force"]) && $params["force"])) {
            $container = new \Pimcore\Model\Object\Fieldcollection(null, $this->getName());
            $container->load($object);

            if ($container->isEmpty()) {
                return null;
            }

            return $container;
        }

        return null;
    }

    /**
     * @param $object
     */
    public function delete($object)
    {
        $container = new \Pimcore\Model\Object\Fieldcollection(null, $this->getName());
        $container->delete($object);
    }

    /**
     * @return string
     */
    public function getAllowedTypes()
    {
        return $this->allowedTypes;
    }

    /**
     * @param $allowedTypes
     * @return $this
     */
    public function setAllowedTypes($allowedTypes)
    {
        if (is_string($allowedTypes)) {
            $allowedTypes = explode(",", $allowedTypes);
        }

        if (is_array($allowedTypes)) {
            for ($i = 0; $i < count($allowedTypes); $i++) {
                try {
                    \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($allowedTypes[$i]);
                } catch (\Exception $e) {
                    Logger::warn("Removed unknown allowed type [ $allowedTypes[$i] ] from allowed types of field collection");
                    unset($allowedTypes[$i]);
                }
            }
        }

        $this->allowedTypes = (array)$allowedTypes;
        $this->allowedTypes = array_values($this->allowedTypes); // get rid of indexed array (.join() doesnt work in JS)

        return $this;
    }

    /**
     * @param Model\Object\AbstractObject $object
     * @param mixed $params
     * @return mixed
     */
    public function getForWebserviceExport($object, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);
        $wsData = [];

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($data as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                $wsDataItem = new Webservice\Data\Object\Element();
                $wsDataItem->value = [];
                $wsDataItem->type = $item->getType();

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    $el = new Webservice\Data\Object\Element();
                    $el->name = $fd->getName();
                    $el->type = $fd->getFieldType();
                    $el->value = $fd->getForWebserviceExport($item, $params);
                    if ($el->value ==  null && self::$dropNullValues) {
                        continue;
                    }

                    $wsDataItem->value[] = $el;
                }

                $wsData[] = $wsDataItem;
            }
        }

        return $wsData;
    }

    /**
     * @param mixed $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return mixed|Model\Object\Fieldcollection
     * @param $idMapper
     * @throws \Exception
     */
    public function getFromWebserviceImport($data, $object = null, $params = [], $idMapper = null)
    {
        $values = [];
        $count = 0;

        if (is_array($data)) {
            foreach ($data as $collectionRaw) {
                if ($collectionRaw instanceof \stdClass) {
                    $collectionRaw = Cast::castToClass("\\Pimcore\\Model\\Webservice\\Data\\Object\\Element", $collectionRaw);
                }
                if (!$collectionRaw instanceof Webservice\Data\Object\Element) {
                    throw new \Exception("invalid data in fieldcollections [" . $this->getName() . "]");
                }

                $fieldcollection = $collectionRaw->type;
                $collectionData = [];
                $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($fieldcollection);

                if (!$collectionDef) {
                    throw new \Exception("Unknown fieldcollection in webservice import [" . $fieldcollection . "]");
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    foreach ($collectionRaw->value as $field) {
                        if ($field instanceof \stdClass) {
                            $field = Cast::castToClass("\\Pimcore\\Model\\Webservice\\Data\\Object\\Element", $field);
                        }
                        if (!$field instanceof Webservice\Data\Object\Element) {
                            throw new \Exception("invalid data in fieldcollections [" . $this->getName() . "]");
                        } elseif ($field->name == $fd->getName()) {
                            if ($field->type != $fd->getFieldType()) {
                                throw new \Exception("Type mismatch for fieldcollection field [" . $field->name . "]. Should be [" . $fd->getFieldType() . "] but is [" . $field->type . "]");
                            }
                            $collectionData[$fd->getName()] = $fd->getFromWebserviceImport($field->value, $object, [], $idMapper);
                            break;
                        }
                    }
                }

                $collectionClass = "\\Pimcore\\Model\\Object\\Fieldcollection\\Data\\" . ucfirst($fieldcollection);
                $collection = new $collectionClass;
                $collection->setValues($collectionData);
                $collection->setIndex($count);
                $collection->setFieldname($this->getName());

                $values[] = $collection;

                $count++;
            }
        }

        $container = new \Pimcore\Model\Object\Fieldcollection($values, $this->getName());

        return $container;
    }

    /**
     * @param mixed $data
     * @return array
     */
    public function resolveDependencies($data)
    {
        $dependencies = [];

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($data as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    $getter = "get" . ucfirst($fd->getName());
                    $dependencies = array_merge($dependencies, $fd->resolveDependencies($item->$getter()));
                }
            }
        }

        return $dependencies;
    }

    /**
     * This is a dummy and is mostly implemented by relation types
     *
     * @param mixed $data
     * @param array $tags
     * @return array
     */
    public function getCacheTags($data, $tags = [])
    {
        $tags = is_array($tags) ? $tags : [];

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($data as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    $getter = "get" . ucfirst($fd->getName());
                    $tags = $fd->getCacheTags($item->$getter(), $tags);
                }
            }
        }

        return $tags;
    }


    /**
     * Checks if data is valid for current data field
     *
     * @param mixed $data
     * @param boolean $omitMandatoryCheck
     * @throws \Exception
     */
    public function checkValidity($data, $omitMandatoryCheck = false)
    {
        if (!$omitMandatoryCheck) {
            if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
                foreach ($data as $item) {
                    if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                        continue;
                    }

                    try {
                        $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                    } catch (\Exception $e) {
                        continue;
                    }

                    foreach ($collectionDef->getFieldDefinitions() as $fd) {
                        $getter = "get" . ucfirst($fd->getName());
                        $fd->checkValidity($item->$getter());
                    }
                }
            }
        }
    }

    /**
     * @param $object
     * @param array $params
     * @return null|Model\Object\Fieldcollection
     * @throws \Exception
     */
    public function preGetData($object, $params = [])
    {
        if (!$object instanceof \Pimcore\Model\Object\Concrete) {
            throw new \Exception("Field Collections are only valid in Objects");
        }

        $data = $object->{$this->getName()};
        if ($this->getLazyLoading() and !in_array($this->getName(), $object->getO__loadedLazyFields())) {
            $data = $this->load($object, ["force" => true]);

            $setter = "set" . ucfirst($this->getName());
            if (method_exists($object, $setter)) {
                $object->$setter($data);
            }
        }

        return $data;
    }

    /**
     * @param $object
     * @param $data
     * @param array $params
     * @return array
     */
    public function preSetData($object, $data, $params = [])
    {
        if ($this->getLazyLoading() and !in_array($this->getName(), $object->getO__loadedLazyFields())) {
            $object->addO__loadedLazyField($this->getName());
        }

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            $data->setFieldname($this->getName());
        }

        return $data;
    }


    /**
     * @param $data
     * @param Model\Object\Concrete $object
     * @param mixed $params
     * @return string
     */
    public function getDataForGrid($data, $object = null, $params = [])
    {
        return "NOT SUPPORTED";
    }

    /**
     * @param $class
     * @return string
     */
    public function getGetterCode($class)
    {
        // getter, no inheritance here, that's the only difference

        $key = $this->getName();
        $code = "";

        $code .= '/**' . "\n";
        $code .= '* @return ' . $this->getPhpdocType() . "\n";
        $code .= '*/' . "\n";
        $code .= "public function get" . ucfirst($key) . " () {\n";

        // adds a hook preGetValue which can be defined in an extended class
        $code .= "\t" . '$preValue = $this->preGetValue("' . $key . '");' . " \n";
        $code .= "\t" . 'if($preValue !== null && !\Pimcore::inAdmin()) { return $preValue;}' . "\n";

        if (method_exists($this, "preGetData")) {
            $code .= "\t" . '$data = $this->getClass()->getFieldDefinition("' . $key . '")->preGetData($this);' . "\n";
        } else {
            $code .= "\t" . '$data = $this->' . $key . ";\n";
        }

        $code .= "\t return " . '$data' . ";\n";
        $code .= "}\n\n";

        return $code;
    }

    /**
     * @param $maxItems
     * @return $this
     */
    public function setMaxItems($maxItems)
    {
        $this->maxItems = $this->getAsIntegerCast($maxItems);

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxItems()
    {
        return $this->maxItems;
    }

    /** True if change is allowed in edit mode.
     * @param string $object
     * @param mixed $params
     * @return bool
     */
    public function isDiffChangeAllowed($object, $params = [])
    {
        return true;
    }


    /** Generates a pretty version preview (similar to getVersionPreview) can be either html or
     * a image URL. See the ObjectMerger plugin documentation for details
     * @param $data
     * @param null $object
     * @param mixed $params
     * @return array|string
     */
    public function getDiffVersionPreview($data, $object = null, $params = [])
    {
        $html = "";
        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            $html = "<table>";
            foreach ($data as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                $type = $item->getType();
                $html .= "<tr><th><b>" . $type . "</b></th><th>&nbsp;</th><th>&nbsp;</th></tr>";

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                $collectionData = [];

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    $title = !empty($fd->title) ? $fd->title : $fd->getName();
                    $html .= "<tr><td>&nbsp;</td><td>" . $title . "</td><td>";
                    $html .= $fd->getVersionPreview($item->{$fd->getName()}, $object, $params);
                    $html .= "</td></tr>";
                }
            }

            $html .= "</table>";
        }

        $value = [];
        $value["html"] = $html;
        $value["type"] = "html";

        return $value;
    }

    /**
     * @param $data
     * @param null $object
     * @param array $params
     * @return mixed
     */
    public function getDiffDataFromEditmode($data, $object = null, $params = [])
    {
        $result = parent::getDiffDataFromEditmode($data, $object, $params);
        Logger::debug("bla");

        return $result;
    }

    /**
     * Rewrites id from source to target, $idMapping contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     * @param mixed $object
     * @param array $idMapping
     * @param array $params
     * @return Model\Element\ElementInterface
     */
    public function rewriteIds($object, $idMapping, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);

        if ($data instanceof \Pimcore\Model\Object\Fieldcollection) {
            foreach ($data as $item) {
                if (!$item instanceof \Pimcore\Model\Object\Fieldcollection\Data\AbstractData) {
                    continue;
                }

                try {
                    $collectionDef = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($item->getType());
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($collectionDef->getFieldDefinitions() as $fd) {
                    if (method_exists($fd, "rewriteIds")) {
                        $d = $fd->rewriteIds($item, $idMapping, $params);
                        $setter = "set" . ucfirst($fd->getName());
                        $item->$setter($d);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param Model\Object\ClassDefinition\Data $masterDefinition
     */
    public function synchronizeWithMasterDefinition(\Pimcore\Model\Object\ClassDefinition\Data $masterDefinition)
    {
        $this->allowedTypes = $masterDefinition->allowedTypes;
        $this->lazyLoading = $masterDefinition->lazyLoading;
        $this->maxItems = $masterDefinition->maxItems;
    }

    /**
     * This method is called in Model\Object|Class::save() and is used to create the database table for the localized data
     * @param $class
     * @param array $params
     */
    public function classSaved($class, $params = [])
    {
        if (is_array($this->allowedTypes)) {
            foreach ($this->allowedTypes as $allowedType) {
                $definition = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($allowedType);
                if ($definition) {
                    $fieldDefinition = $definition->getFieldDefinitions();

                    foreach ($fieldDefinition as $fd) {
                        if (method_exists($fd, "classSaved")) {
                            if (!$fd instanceof Localizedfields) {
                                // defer creation
                                $fd->classSaved($class);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param boolean $disallowAddRemove
     */
    public function setDisallowAddRemove($disallowAddRemove)
    {
        $this->disallowAddRemove = $disallowAddRemove;
    }

    /**
     * @return boolean
     */
    public function getDisallowAddRemove()
    {
        return $this->disallowAddRemove;
    }

    /**
     * @param boolean $disallowReorder
     */
    public function setDisallowReorder($disallowReorder)
    {
        $this->disallowReorder = $disallowReorder;
    }

    /**
     * @return boolean
     */
    public function getDisallowReorder()
    {
        return $this->disallowReorder;
    }

    /**
     * @return boolean
     */
    public function isCollapsed()
    {
        return $this->collapsed;
    }

    /**
     * @param boolean $collapsed
     */
    public function setCollapsed($collapsed)
    {
        $this->collapsed = $collapsed;
    }

    /**
     * @return boolean
     */
    public function isCollapsible()
    {
        return $this->collapsible;
    }

    /**
     * @param boolean $collapsible
     */
    public function setCollapsible($collapsible)
    {
        $this->collapsible = $collapsible;
    }

    /**
     * @param $container
     * @param array $list
     */
    public static function collectCalculatedValueItems($container, &$list = [])
    {
        if (is_array($container)) {
            /** @var  $childDef Model\Object\ClassDefinition\Data */
            foreach ($container as $childDef) {
                if ($childDef instanceof Model\Object\ClassDefinition\Data\CalculatedValue) {
                    $list[] = $childDef;
                } else {
                    if (method_exists($childDef, "getFieldDefinitions")) {
                        self::collectCalculatedValueItems($childDef->getFieldDefinitions(), $list);
                    }
                }
            }
        }
    }
}
