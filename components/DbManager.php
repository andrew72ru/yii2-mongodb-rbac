<?php

/*
 * This file is part of the Dektrium project.
 *
 * (c) Dektrium project <http://github.com/dektrium>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace andrew72ru\rbac\components;

use yii\mongodb\Query;
use yii\mongodb\rbac\MongoDbManager;

/**
 * This Auth manager changes visibility and signature of some methods from \yii\rbac\DbManager.
 *
 * @author Dmitry Erofeev <dmeroff@gmail.com>
 */
class DbManager extends MongoDbManager implements ManagerInterface
{
    /**
     * @param  int|null $type If null will return all auth items.
     * @param  array $excludeItems Items that should be excluded from result array.
     * @return array
     */
    public function getItems($type = null, $excludeItems = [])
    {
        /** @var Query $query */
        $query = (new Query())
            ->from($this->itemCollection);

        $notInArr = [];
        foreach ($excludeItems as $name)
        {
            $notInArr[] = $name;
//            $query->andWhere('name != :item', ['item' => $name]);
        }
        if(!empty($notInArr))
            $query->where(['name' => ['$nin' => $notInArr]]);

        if ($type !== null)
        {
            $query->andWhere(['type' => $type]);
        } else
        {
            $query->orderBy('type');
        }

        $items = [];

        foreach ($query->all($this->db) as $row)
        {
            if(array_key_exists('name', $row))
                $items[$row['name']] = $this->populateItem($row);
        }

        return $items;
    }

    /**
     * Returns both roles and permissions assigned to user.
     *
     * @param  integer $userId
     * @return array
     */
    public function getItemsByUser($userId)
    {
        if (empty($userId))
        {
            return [];
        }
        $assignmentsQuery = new Query();
        $assignments = $assignmentsQuery->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId])
            ->indexBy('item_name')
            ->all();

        if(empty($assignments))
            return [];

        $itemQuery = new Query();
        $items = $itemQuery->from($this->itemCollection)
            ->where(['name' => array_keys($assignments)])
            ->all();

        $roles = [];
        foreach ($items as $row)
        {
            if(array_key_exists('name', $row))
                $roles[$row['name']] = $this->populateItem($row);
        }
        return $roles;
    }

    /** @inheritdoc */
    public function getItem($name)
    {
        return parent::getItem($name);
    }
}
