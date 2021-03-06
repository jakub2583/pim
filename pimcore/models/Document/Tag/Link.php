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
 * @package    Document
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Document\Tag;

use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Logger;

/**
 * @method \Pimcore\Model\Document\Tag\Dao getDao()
 */
class Link extends Model\Document\Tag
{

    /**
     * Contains the data for the link
     *
     * @var array
     */
    public $data;

    /**
     * @see Document\Tag\TagInterface::getType
     * @return string
     */
    public function getType()
    {
        return "link";
    }

    /**
     * @see Document\Tag\TagInterface::getData
     * @return mixed
     */
    public function getData()
    {
        // update path if internal link
        $this->updatePathFromInternal(true);

        return $this->data;
    }

    /**
     * @see Document\Tag\TagInterface::frontend
     * @return string
     */
    public function frontend()
    {
        $url = $this->getHref();

        if (strlen($url) > 0) {
            // add attributes to link
            $attribs = [];
            if (is_array($this->options)) {
                foreach ($this->options as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $attribs[] = $key . '="' . $value . '"';
                    }
                }
            }
            // add attributes to link
            $allowedAttributes = ["charset", "coords", "hreflang", "name", "rel", "rev", "shape", "target", "accesskey", "class", "dir", "id", "lang", "style", "tabindex", "title", "xml:lang", "onblur", "onclick", "ondblclick", "onfocus", "onmousedown", "onmousemove", "onmouseout", "onmouseover", "onmouseup", "onkeydown", "onkeypress", "onkeyup"];
            $defaultAttributes = [];

            if (!is_array($this->options)) {
                $this->options = [];
            }
            if (!is_array($this->data)) {
                $this->data = [];
            }

            $availableAttribs = array_merge($defaultAttributes, $this->data, $this->options);

            foreach ($availableAttribs as $key => $value) {
                if ((is_string($value) || is_numeric($value)) && in_array($key, $allowedAttributes)) {
                    if (!empty($value)) {
                        $attribs[] = $key . '="' . $value . '"';
                    }
                }
            }

            $attribs = array_unique($attribs);

            if (array_key_exists("attributes", $this->data) && !empty($this->data["attributes"])) {
                $attribs[] = $this->data["attributes"];
            }

            return '<a href="' . $url . '" ' . implode(" ", $attribs) . '>' . htmlspecialchars($this->data["text"]) . '</a>';
        }

        return "";
    }

    /**
     * @return bool
     */
    public function checkValidity()
    {
        $sane = true;
        if (is_array($this->data) && $this->data["internal"]) {
            if ($this->data["internalType"] == "document") {
                $doc = Document::getById($this->data["internalId"]);
                if (!$doc) {
                    $sane = false;
                    Logger::notice("Detected insane relation, removing reference to non existent document with id [" . $this->getDocumentId() . "]");
                    $new = Document\Tag::factory($this->getType(), $this->getName(), $this->getDocumentId());
                    $this->data = $new->getData();
                }
            } elseif ($this->data["internalType"] == "asset") {
                $asset = Asset::getById($this->data["internalId"]);
                if (!$asset) {
                    $sane = false;
                    Logger::notice("Detected insane relation, removing reference to non existent asset with id [" . $this->getDocumentId() . "]");
                    $new = Document\Tag::factory($this->getType(), $this->getName(), $this->getDocumentId());
                    $this->data = $new->getData();
                }
            }
        }

        return $sane;
    }


    /**
     * @return string
     */
    public function getHref()
    {
        $this->updatePathFromInternal();

        $url = $this->data["path"];

        if (strlen($this->data["parameters"]) > 0) {
            $url .= "?" . str_replace("?", "", $this->getParameters());
        }

        if (strlen($this->data["anchor"]) > 0) {
            $anchor = $this->getAnchor();
            $anchor = str_replace('"', urlencode('"'), $anchor);
            $url .= "#" . str_replace("#", "", $anchor);
        }

        return $url;
    }

    /**
     * @param bool $realPath
     */
    protected function updatePathFromInternal($realPath = false)
    {
        $method = "getFullPath";
        if ($realPath) {
            $method = "getRealFullPath";
        }

        if (isset($this->data["internal"]) && $this->data["internal"]) {
            if ($this->data["internalType"] == "document") {
                if ($doc = Document::getById($this->data["internalId"])) {
                    if (!Document::doHideUnpublished() || $doc->isPublished()) {
                        $this->data["path"] = $doc->$method();
                    } else {
                        $this->data["path"] = "";
                    }
                }
            } elseif ($this->data["internalType"] == "asset") {
                if ($asset = Asset::getById($this->data["internalId"])) {
                    $this->data["path"] = $asset->$method();
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->data["text"];
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->data["text"] = $text;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->data["target"];
    }

    /**
     * @return string
     */
    public function getParameters()
    {
        return $this->data["parameters"];
    }

    /**
     * @return string
     */
    public function getAnchor()
    {
        return $this->data["anchor"];
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->data["title"];
    }

    /**
     * @return string
     */
    public function getRel()
    {
        return $this->data["rel"];
    }

    /**
     * @return string
     */
    public function getTabindex()
    {
        return $this->data["tabindex"];
    }

    /**
     * @return string
     */
    public function getAccesskey()
    {
        return $this->data["accesskey"];
    }


    /**
     * @see Document\Tag\TagInterface::setDataFromResource
     * @param mixed $data
     * @return $this
     */
    public function setDataFromResource($data)
    {
        $this->data = \Pimcore\Tool\Serialize::unserialize($data);
        if (!is_array($this->data)) {
            $this->data = [];
        }

        return $this;
    }

    /**
     * @see Document\Tag\TagInterface::setDataFromEditmode
     * @param mixed $data
     * @return $this
     */
    public function setDataFromEditmode($data)
    {
        if (!is_array($data)) {
            $data = [];
        }

        if ($doc = Document::getByPath($data["path"])) {
            if ($doc instanceof Document) {
                $data["internal"] = true;
                $data["internalId"] = $doc->getId();
                $data["internalType"] = "document";
            }
        }

        if (!$data["internal"]) {
            if ($asset = Asset::getByPath($data["path"])) {
                if ($asset instanceof Asset) {
                    $data["internal"] = true;
                    $data["internalId"] = $asset->getId();
                    $data["internalType"] = "asset";
                }
            }
        }

        $this->data = $data;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isEmpty()
    {
        return (strlen($this->getHref()) < 1);
    }

    /**
     * @return array
     */
    public function resolveDependencies()
    {
        $dependencies = [];

        if (is_array($this->data) && $this->data["internal"]) {
            if (intval($this->data["internalId"]) > 0) {
                if ($this->data["internalType"] == "document") {
                    if ($doc = Document::getById($this->data["internalId"])) {
                        $key = "document_" . $doc->getId();

                        $dependencies[$key] = [
                            "id" => $doc->getId(),
                            "type" => "document"
                        ];
                    }
                } elseif ($this->data["internalType"] == "asset") {
                    if ($asset = Asset::getById($this->data["internalId"])) {
                        $key = "asset_" . $asset->getId();

                        $dependencies[$key] = [
                            "id" => $asset->getId(),
                            "type" => "asset"
                        ];
                    }
                }
            }
        }

        return $dependencies;
    }

    /**
     * @param Model\Webservice\Data\Document\Element $wsElement
     * @param $document
     * @param mixed $params
     * @param null $idMapper
     * @throws \Exception
     */
    public function getFromWebserviceImport($wsElement, $document = null, $params = [], $idMapper = null)
    {
        if ($wsElement->value->data instanceof \stdClass) {
            $wsElement->value->data = (array) $wsElement->value->data;
        }

        if (empty($wsElement->value->data) or is_array($wsElement->value->data)) {
            $this->data = $wsElement->value->data;
            if ($this->data["internal"]) {
                if (intval($this->data["internalId"]) > 0) {
                    $id = $this->data["internalId"];

                    if ($this->data["internalType"] == "document") {
                        if ($idMapper) {
                            $id = $idMapper->getMappedId("document", $id);
                        }
                        $referencedDocument = Document::getById($id);
                        if (!$referencedDocument instanceof Document) {
                            if ($idMapper && $idMapper->ignoreMappingFailures()) {
                                $idMapper->recordMappingFailure("document", $this->getDocumentId(), $this->data["internalType"], $this->data["internalId"]);
                            } else {
                                throw new \Exception("cannot get values from web service import - link references unknown document with id [ " . $this->data["internalId"] . " ] ");
                            }
                        }
                    } elseif ($this->data["internalType"] == "asset") {
                        if ($idMapper) {
                            $id = $idMapper->getMappedId("document", $id);
                        }
                        $referencedAsset = Asset::getById($id);
                        if (!$referencedAsset instanceof Asset) {
                            if ($idMapper && $idMapper->ignoreMappingFailures()) {
                                $idMapper->recordMappingFailure("document", $this->getDocumentId(), $this->data["internalType"], $this->data["internalId"]);
                            } else {
                                throw new \Exception("cannot get values from web service import - link references unknown asset with id [ " . $this->data["internalId"] . " ] ");
                            }
                        }
                    }
                }
            }
        } else {
            throw new \Exception("cannot get values from web service import - invalid data");
        }
    }


    /**
     * Returns the current tag's data for web service export
     *
     * @param $document
     * @param mixed $params
     * @abstract
     * @return array
     */
    public function getForWebserviceExport($document = null, $params = [])
    {
        $el = parent::getForWebserviceExport($document, $params);
        if ($this->data["internal"]) {
            if (intval($this->data["internalId"]) > 0) {
                if ($this->data["internalType"] == "document") {
                    $referencedDocument = Document::getById($this->data["internalId"]);
                    if (!$referencedDocument instanceof Document) {
                        //detected broken link
                        $document = Document::getById($this->getDocumentId());
                    }
                } elseif ($this->data["internalType"] == "asset") {
                    $referencedAsset = Asset::getById($this->data["internalId"]);
                    if (!$referencedAsset instanceof Asset) {
                        //detected broken link
                        $document = Document::getById($this->getDocumentId());
                    }
                }
            }
        }

        $el->data = $this->data;

        return $el;
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
     * @param array $idMapping
     */
    public function rewriteIds($idMapping)
    {
        if ($this->data["internal"]) {
            $type = $this->data["internalType"];
            $id = (int)$this->data["internalId"];

            if (array_key_exists($type, $idMapping)) {
                if (array_key_exists($id, $idMapping[$type])) {
                    $this->data["internalId"] = $idMapping[$type][$id];
                    $this->getHref();
                }
            }
        }
    }
}
