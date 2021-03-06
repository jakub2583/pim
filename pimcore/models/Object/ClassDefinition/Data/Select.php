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

class Select extends Model\Object\ClassDefinition\Data
{

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "select";

    /**
     * Available options to select
     *
     * @var array
     */
    public $options;

    /**
     * @var integer
     */
    public $width;

    /**
     * @var string
     */
    public $defaultValue;


    /**
     * Type for the column to query
     *
     * @var string
     */
    public $queryColumnType = "varchar";

    /**
     * Type for the column
     *
     * @var string
     */
    public $columnType = "varchar";

    /**
     * Column length
     *
     * @var integer
     */
    public $columnLength = 190;

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = "string";


    /**
     * @return integer
     */
    public function getColumnLength()
    {
        return $this->columnLength;
    }

    /**
     * @param $columnLength
     * @return $this
     */
    public function setColumnLength($columnLength)
    {
        if ($columnLength) {
            $this->columnLength = $columnLength;
        }

        return $this;
    }

    /**
     * Correct old column definitions (e.g varchar(255)) to the new format
     * @param $type
     */
    protected function correctColumnDefinition($type)
    {
        preg_match("/(.*)\((\d+)\)/i", $this->$type, $matches);
        if ($matches[2]) {
            $this->{"set" . ucfirst($type)}($matches[1]);
            if ($matches[2] > 190) {
                $matches[2] = 190;
            }
            $this->setColumnLength($matches[2] <= 190 ? $matches[2] : 190);
        }
    }


    /**
     * @return string
     */
    public function getColumnType()
    {
        $this->correctColumnDefinition('columnType');

        return $this->columnType . "(" . $this->getColumnLength() . ")";
    }

    /**
     * @return string
     */
    public function getQueryColumnType()
    {
        $this->correctColumnDefinition('queryColumnType');

        return $this->queryColumnType . "(" . $this->getColumnLength() . ")";
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return integer
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param $width
     * @return $this
     */
    public function setWidth($width)
    {
        $this->width = $this->getAsIntegerCast($width);

        return $this;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataForResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForResource($data, $object = null, $params = [])
    {
        return $data;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        return $data;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataForQueryResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForQueryResource($data, $object = null, $params = [])
    {
        return $data;
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
        return $this->getDataForResource($data, $object, $params);
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromEditmode
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        return $this->getDataFromResource($data, $object, $params);
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
        return $data;
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

    /** See parent class.
     * @param mixed $data
     * @param null $object
     * @param mixed $params
     * @return array|null
     */
    public function getDiffDataForEditMode($data, $object = null, $params = [])
    {
        $result = [];

        $diffdata = [];
        $diffdata["data"] = $data;
        $diffdata["disabled"] = false;
        $diffdata["field"] = $this->getName();
        $diffdata["key"] = $this->getName();
        $diffdata["type"] = $this->fieldtype;

        $value = "";
        foreach ($this->options as $option) {
            if ($option->value == $data) {
                $value = $option->key;
                break;
            }
        }

        $diffdata["value"] = $value;
        $diffdata["title"] = !empty($this->title) ? $this->title : $this->name;

        $result[] = $diffdata;

        return $result;
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
        if (!$omitMandatoryCheck && $this->getMandatory() && $this->isEmpty($data)) {
            throw new Model\Element\ValidationException("Empty mandatory field [ " . $this->getName() . " ]");
        }
    }

    /**
     * @param $data
     * @return bool
     */
    public function isEmpty($data)
    {
        return (strlen($data) < 1);
    }

    /**
     * @param Model\Object\ClassDefinition\Data $masterDefinition
     */
    public function synchronizeWithMasterDefinition(\Pimcore\Model\Object\ClassDefinition\Data $masterDefinition)
    {
        $this->options = $masterDefinition->options;
        $this->columnLength = $masterDefinition->columnLength;
    }

    /**
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string $defaultValue
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }
}
