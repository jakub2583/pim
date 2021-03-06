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
 * @package    Object\Fieldcollection
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Object\Fieldcollection;

use Pimcore\Model;

/**
 * @property \Pimcore\Model\Object\Fieldcollection $model
 */
class Dao extends Model\Dao\AbstractDao
{

    /**
     * @param Model\Object\Concrete $object
     */
    public function save(\Pimcore\Model\Object\Concrete $object)
    {
        $this->delete($object);
    }

    /**
     * @param Model\Object\Concrete $object
     * @return array
     */
    public function load(\Pimcore\Model\Object\Concrete $object)
    {
        $fieldDef = $object->getClass()->getFieldDefinition($this->model->getFieldname());
        $values = [];


        foreach ($fieldDef->getAllowedTypes() as $type) {
            try {
                $definition = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($type);
            } catch (\Exception $e) {
                continue;
            }

            $tableName = $definition->getTableName($object->getClass());

            try {
                $results = $this->db->fetchAll("SELECT * FROM " . $tableName . " WHERE o_id = ? AND fieldname = ? ORDER BY `index` ASC", [$object->getId(), $this->model->getFieldname()]);
            } catch (\Exception $e) {
                $results = [];
            }

            $fieldDefinitions = $definition->getFieldDefinitions();
            $collectionClass = "\\Pimcore\\Model\\Object\\Fieldcollection\\Data\\" . ucfirst($type);

            foreach ($results as $result) {
                $collection = new $collectionClass();
                $collection->setIndex($result["index"]);
                $collection->setFieldname($result["fieldname"]);
                $collection->setObject($object);

                foreach ($fieldDefinitions as $key => $fd) {
                    if (method_exists($fd, "load")) {
                        // datafield has it's own loader
                        $value = $fd->load($collection,
                            [
                                "context" => [
                                    "object" => $object,
                                    "containerType" => "fieldcollection",
                                    "containerKey" => $type,
                                    "fieldname" =>  $this->model->getFieldname(),
                                    "index" => $result["index"]
                            ]]);
                        if ($value === 0 || !empty($value)) {
                            $collection->setValue($key, $value);
                        }
                    } else {
                        if (is_array($fd->getColumnType())) {
                            $multidata = [];
                            foreach ($fd->getColumnType() as $fkey => $fvalue) {
                                $multidata[$key . "__" . $fkey] = $result[$key . "__" . $fkey];
                            }
                            $collection->setValue($key, $fd->getDataFromResource($multidata));
                        } else {
                            $collection->setValue($key, $fd->getDataFromResource($result[$key]));
                        }
                    }
                }

                $values[] = $collection;
            }
        }

        $orderedValues = [];
        foreach ($values as $value) {
            $orderedValues[$value->getIndex()] = $value;
        }

        ksort($orderedValues);

        $this->model->setItems($orderedValues);

        return $orderedValues;
    }

    /**
     * @param Model\Object\Concrete $object
     */
    public function delete(\Pimcore\Model\Object\Concrete $object)
    {
        // empty or create all relevant tables
        $fieldDef = $object->getClass()->getFieldDefinition($this->model->getFieldname());

        foreach ($fieldDef->getAllowedTypes() as $type) {
            try {
                /** @var  $definition Definition */
                $definition = \Pimcore\Model\Object\Fieldcollection\Definition::getByKey($type);
            } catch (\Exception $e) {
                continue;
            }

            $tableName = $definition->getTableName($object->getClass());

            try {
                $this->db->delete($tableName, $this->db->quoteInto("o_id = ?", $object->getId()) . " AND " . $this->db->quoteInto("fieldname = ?", $this->model->getFieldname()));
            } catch (\Exception $e) {
                // create definition if it does not exist
                $definition->createUpdateTable($object->getClass());
            }

            if ($definition->getFieldDefinition("localizedfields")) {
                $tableName = $definition->getLocalizedTableName($object->getClass());

                try {
                    $this->db->delete($tableName, $this->db->quoteInto("ooo_id = ?", $object->getId()) . " AND " . $this->db->quoteInto("fieldname = ?", $this->model->getFieldname()));
                } catch (\Exception $e) {
                    \Logger::error($e);
                }
            }

            $childDefinitions = $definition->getFielddefinitions();

            if (is_array($childDefinitions)) {
                foreach ($childDefinitions as $fd) {
                    if (method_exists($fd, "delete")) {
                        $fd->delete($object, [
                                "context" => [
                                    "containerType" => "fieldcollection",
                                    "containerKey" => $type,
                                    "fieldname" =>  $this->model->getFieldname()
                                ]
                            ]
                        );
                    }
                }
            }
        }

        // empty relation table
        $this->db->delete("object_relations_" . $object->getClassId(),
            "(ownertype = 'fieldcollection' AND " . $this->db->quoteInto("ownername = ?", $this->model->getFieldname()) . " AND " . $this->db->quoteInto("src_id = ?", $object->getId()) . ")"
            . " OR (ownertype = 'localizedfield' AND " . $this->db->quoteInto("ownername LIKE ?", "/fieldcollection~" . $this->model->getFieldname() . "/%") . " AND " . $this->db->quoteInto("src_id = ?", $object->getId()). ")"
        );
    }
}
