<?php
/**
 * Created by PhpStorm.
 * User: st1gz
 * Date: 20.03.15
 * Time: 10:16
 */

namespace consultnn\treeView;

use yii\base\Behavior;

class TreeViewBehavior extends Behavior
{
    /**
     * @param string|null $parentId
     * @param int $position
     * @return bool
     */
    public function move($parentId = null, $position = null)
    {
        if ($parentId) {
            $parentId = new \MongoId($parentId);
        } else {
            $parentId = null;
        }

        if ($parentId == $this->owner->id) {
            return false;
        }

        $this->owner->parent_id = $parentId;
        $this->calculatePosition($position);

        return $this->owner->save();
    }

    /**
     * @param int $position
     * @return float|int|mixed
     */
    public function calculatePosition($position = null)
    {
        if (!empty($this->owner->position) && $position === null) {
            return $this->owner->position;
        } elseif (empty($position)) {
            return $this->setMinPosition();
        }

        $condition['parent_id'] = $this->owner->parent_id;
        if ($this->owner->id) {
            $condition['_id'] = ['$ne' => $this->owner->_id];
        }

        $owner = $this->owner;
        $brothers = $owner::find()->where($condition)->orderBy('position')->offset($position - 1)->limit(2)->all();
        switch (count($brothers)) {
            case 0:
                $this->setMinPosition();
                break;
            case 1:
                $this->owner->position = ceil($brothers[0]->position + 1);
                break;
            case 2:
                list($prev, $next) = $brothers;
                $rise = ($next->position - $prev->position) / 2;
                if ($rise == 0) {
                    self::updatePositions($this->owner->parent_id);
                    $this->calculatePosition($position);
                } else {
                    $this->owner->position = $prev->position + $rise;
                }
                break;
        }

        return $this->owner->position;
    }

    /**
     * @return float|int
     */
    private function setMinPosition()
    {
        $owner = $this->owner;
        $min = $owner::find()->where(['parent_id' => $this->owner->parent_id])->min('position');
        $minPosition = $min / 2;

        if (!$min) {
            $this->owner->position = 1;
        } elseif ($minPosition != 0) {
            $this->owner->position = $minPosition;
        } else {
            $this->updatePositions($this->owner->parent_id);
            $this->setMinPosition();
        }

        return $this->owner->position;
    }

    /**
     * @param $parentId
     */
    private function updatePositions($parentId)
    {
        if (!is_null($parentId)) {
            $parentId = new \MongoId($parentId);
        }

        $position = 0;

        /** @var self[] $rubrics */
        $rubrics = $this->owner->find()->where(['parent_id' => $parentId])->orderBy('position')->all();
        foreach ($rubrics as $rubric) {
            $rubric->updateAttributes(['position' => ++$position]);
        }
    }
}
