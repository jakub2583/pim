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
 * @package    Element
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Element\Tag;

use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Tag;

/**
 * @property \Pimcore\Model\Element\Tag $model
 */
class Dao extends Model\Dao\AbstractDao
{

    /**
     * @param $id
     * @throws \Exception
     */
    public function getById($id)
    {
        $data = $this->db->fetchRow("SELECT * FROM tags WHERE id = ?", $id);
        if (!$data["id"]) {
            throw new \Exception("Tag item with id " . $id . " not found");
        }
        $this->assignVariablesToModel($data);
    }

    /**
     * Save object to database
     *
     * @return boolean|null
     * @throws \Exception
     *
     * @todo: not all save methods return a boolean, why this one?
     */
    public function save()
    {
        $this->db->beginTransaction();
        try {
            $dataAttributes = get_object_vars($this->model);

            $originalIdPath = null;
            if ($this->model->getId()) {
                $originalIdPath = $this->db->fetchOne('SELECT idPath FROM tags WHERE id = ?', $this->model->getId());
            }

            $data = [];
            foreach ($dataAttributes as $key => $value) {
                if (in_array($key, $this->getValidTableColumns("tags"))) {
                    $data[$key] = $value;
                }
            }

            $this->db->insertOrUpdate("tags", $data);

            $lastInsertId = $this->db->lastInsertId();
            if (!$this->model->getId() && $lastInsertId) {
                $this->model->setId($lastInsertId);
            }


            //check for id-path and update it, if path has changed -> update all other tags that have idPath == idPath/id
            if ($originalIdPath && $originalIdPath != $this->model->getIdPath()) {
                $this->db->query("UPDATE tags SET idPath = REPLACE(idPath, ?, ?)  WHERE idPath LIKE ?;", [$originalIdPath, $this->model->getIdPath(), $originalIdPath . $this->model->getId() . "/%"]);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Deletes object from database
     *
     * @throws \Exception
     */
    public function delete()
    {
        $this->db->beginTransaction();
        try {
            $this->db->delete("tags_assignment", $this->db->quoteInto("tagid = ?", $this->model->getId()));
            $this->db->delete("tags_assignment", $this->db->quoteInto("tagid IN (SELECT id FROM tags WHERE idPath LIKE ?)", $this->model->getIdPath() . $this->model->getId() . "/%"));

            $this->db->delete("tags", $this->db->quoteInto("id = ?", $this->model->getId()));
            $this->db->delete("tags", $this->db->quoteInto("idPath LIKE ?", $this->model->getIdPath() . $this->model->getId() . "/%"));

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    /**
     * @param $cType
     * @param $cId
     * @return Model\Element\Tag[]
     */
    public function getTagsForElement($cType, $cId)
    {
        $tags = [];
        $tagIds = $this->db->fetchCol("SELECT tagid FROM tags_assignment WHERE cid = ? AND ctype = ?", [$cId, $cType]);

        foreach ($tagIds as $tagId) {
            $tags[] = Model\Element\Tag::getById($tagId);
        }

        $tags = array_filter($tags);
        @usort($tags, function ($left, $right) {
            return strcmp($left->getNamePath(), $right->getNamePath());
        });

        return $tags;
    }

    /**
     * @param $cType
     * @param $cId
     */
    public function addTagToElement($cType, $cId)
    {
        $this->doAddTagToElement($this->model->getId(), $cType, $cId);
    }

    /**
     * @param $tagId
     * @param $cType
     * @param $cId
     */
    protected function doAddTagToElement($tagId, $cType, $cId)
    {
        $data = [
            "tagid" => $tagId,
            "ctype" => $cType,
            "cid" => $cId
        ];
        $this->db->insertOrUpdate("tags_assignment", $data);
    }

    /**
     * @param $cType
     * @param $cId
     */
    public function removeTagFromElement($cType, $cId)
    {
        $this->db->delete("tags_assignment",
            "tagid = " . $this->db->quote($this->model->getId()) . " AND ctype = " . $this->db->quote($cType) . " AND cid = " . $this->db->quote($cId));
    }

    /**
     * @param $cType
     * @param $cId
     * @param array $tags
     * @throws \Exception
     */
    public function setTagsForElement($cType, $cId, array $tags)
    {
        $this->db->beginTransaction();
        try {
            $this->db->delete("tags_assignment", "ctype = " . $this->db->quote($cType) . " AND cid = " . $this->db->quote($cId));

            foreach ($tags as $tag) {
                $this->doAddTagToElement($tag->getId(), $cType, $cId);
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param $cType
     * @param array $cIds
     * @param array $tagIds
     * @param $replace
     */
    public function batchAssignTagsToElement($cType, array $cIds, array $tagIds, $replace)
    {
        if ($replace) {
            $this->db->delete("tags_assignment", "ctype = " . $this->db->quote($cType) . " AND cid IN (" . implode(",", $cIds) . ")");
        }

        foreach ($tagIds as $tagId) {
            foreach ($cIds as $cId) {
                $this->doAddTagToElement($tagId, $cType, $cId);
            }
        }
    }

    /**
     * Retrieves all elements that have a specific tag or one of its child tags assigned
     *
     * @param Tag    $tag               The tag to search for
     * @param string $type              The type of elements to search for: 'document', 'asset' or 'object'
     * @param array  $subtypes          Filter by subtypes, eg. page, object, email, folder etc.
     * @param array  $classNames        For objects only: filter by classnames
     * @param bool   $considerChildTags Look for elements having one of $tag's children assigned
     *
     * @return array
     */
    public function getElementsForTag(
        Tag $tag, $type, array $subtypes = [], array $classNames = [], $considerChildTags = false
    ) {
        $elements = [];

        $map = [
            'document' => ['documents', 'id', 'type', '\Pimcore\Model\Document'],
            'asset'    => ['assets', 'id', 'type', '\Pimcore\Model\Asset'],
            'object'   => ['objects', 'o_id', 'o_type', '\Pimcore\Model\Object\AbstractObject'],
        ];

        $select = $this->db->select()
                           ->from('tags_assignment', [])
                           ->where('tags_assignment.ctype = ?', $type);

        if (true === $considerChildTags) {
            $select->joinInner('tags', 'tags.id = tags_assignment.tagid', ['tags_id' => 'id']);
            $select->where('(' .
                $this->db->quoteInto('tags_assignment.tagid = ?', $tag->getId()) . ' OR ' .
                $this->db->quoteInto('tags.idPath LIKE ?', $tag->getFullIdPath() . "%") . ')'
            );
        } else {
            $select->where('tags_assignment.tagid = ?', $tag->getId());
        }

        $select->joinInner(
            ['el' => $map[$type][0]], 'tags_assignment.cId = el.' . $map[$type][1],
            ['el_id' => $map[$type][1]]
        );

        if (! empty($subtypes)) {
            $select->where($map[$type][2] . ' IN (?)', $subtypes);
        }

        if ('object' === $type && ! empty($classNames)) {
            $select->where('o_className IN (?)', $classNames);
        }

        $res = $this->db->query($select);

        while ($row = $res->fetch()) {
            $el = $map[$type][3]::getById($row['el_id']);
            if ($el) {
                $elements[] = $el;
            }
        }

        return $elements;
    }
}
