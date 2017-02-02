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

use MongoDB\BSON\ObjectID;
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
     * @inheritdoc
     */
    public function getAssignments($userId)
    {
        if(empty($userId))
            return [];

        if (is_numeric($userId))
            return parent::getAssignments($userId);

        if(($userId = $this->userIdByMongo($userId)) !== null)
            return parent::getAssignments($userId);

        return [];
    }

    /**
     * @param \yii\rbac\Role $role
     * @param int|string $userId
     * @return null|\yii\rbac\Assignment
     */
    public function assign($role, $userId)
    {
        if(is_numeric($userId))
            return parent::assign($role, $userId);

        if(($userId = $this->userIdByMongo($userId)) !== null)
            return parent::assign($role, $userId);

        return null;
    }

    /**
     * @param $value
     * @return mixed|null
     */
    private function userIdByMongo($value)
    {
        if($value instanceof ObjectID)
            return (string) $value;

        try
        {
            $mongoId = new \MongoDB\BSON\ObjectID($value);

            /** @var \yii\web\IdentityInterface|\yii\mongodb\ActiveRecord $class */
            $class = \Yii::$app->user->identityClass;

            /** @var \yii\db\ActiveRecord|\yii\mongodb\ActiveRecord $user */
            $user = $class::findOne($mongoId);

            if ($user !== null)
                return (string) ($user->_id);

        } catch (\Exception $e) {}
        return null;
    }

    /**
     * Returns both roles and permissions assigned to user.
     *
     * @param  integer|string|ObjectID $userId
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
