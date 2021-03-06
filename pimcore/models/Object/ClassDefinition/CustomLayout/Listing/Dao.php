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

namespace Pimcore\Model\Object\ClassDefinition\CustomLayout\Listing;

use Pimcore\Model;

/**
 * @property \Pimcore\Model\Object\ClassDefinition\CustomLayout\Listing $model
 */
class Dao extends Model\Listing\Dao\AbstractDao
{

    /**
     * Loads a list of custom layouts for the specified parameters, returns an array of Model\Object\ClassDefinition\CustomLayout elements
     *
     * @return array
     */
    public function load()
    {
        $layouts = [];

        $layoutsRaw = $this->db->fetchCol("SELECT id FROM custom_layouts" . $this->getCondition() . $this->getOrder() . $this->getOffsetLimit(), $this->model->getConditionVariables());

        foreach ($layoutsRaw as $classRaw) {
            $layouts[] = Model\Object\ClassDefinition\CustomLayout::getById($classRaw);
        }

        $this->model->setLayoutDefinitions($layouts);

        return $layouts;
    }

    /**
     * @return int
     *
     * @todo: $amount could not be defined, so this could cause an issue
     */
    public function getTotalCount()
    {
        try {
            $amount = (int) $this->db->fetchOne("SELECT COUNT(*) as amount FROM custom_layouts " . $this->getCondition(), $this->model->getConditionVariables());
        } catch (\Exception $e) {
        }

        return $amount;
    }
}
