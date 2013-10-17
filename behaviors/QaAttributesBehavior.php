<?php

/**
 * QaAttributesBehavior
 *
 * @uses CActiveRecordBehavior
 * @license MIT
 * @author See https://github.com/neam/yii-qa-attributes/graphs/contributors
 */
class QaAttributesBehavior extends CActiveRecordBehavior
{

    /**
     * The different statuses the item can have during the qa process
     * @var array
     */
    public $statuses = array(
        'draft',
        'preview',
        'public'
    );

    /**
     * Additional flags that are to be manually tracked in the qa process.
     * Used to include attributes to track these flags in the schema.
     * @var array
     */
    public $manualFlags = array(
        'previewing_welcome',
        'candidate_for_public_status'
    );

    /**
     * The attributes that are part of the qa process
     * @var array
     */
    protected $qaAttributes = array();

    /**
     * Populate qaAttributes from validation rules
     * @return array
     */
    public function qaAttributes()
    {

        $finalStatus = $this->statuses[count($this->statuses) - 1];

        // those that are part of the final status will include all attributes
        $attributes = array();
        foreach ($this->owner->validatorList as $validator) {
            if (!in_array($finalStatus, $validator->on)) {
                continue;
            }
            $attributes = array_merge($attributes, $validator->attributes);
        }

        $this->qaAttributes = $attributes;

        return $this->qaAttributes;
    }

    /**
     * Expose behavior
     * @return array
     */
    public function & qaAttributesBehavior()
    {
        return $this;
    }

    /**
     * @param CActiveRecord $owner
     * @throws Exception
     */
    public function attach($owner)
    {
        parent::attach($owner);
        if (!($owner instanceof CActiveRecord)) {
            throw new Exception('Owner must be a CActiveRecord class');
        }
    }

    public function beforeSave($event)
    {

        $this->initiateQaStates();

    }

    protected function initiateQaStates()
    {

        $relationField = $this->owner->tableName() . "_qa_state_id";
        $this->initiateAuthoringState($relationField);

    }

    private function initiateQaState($attribute, $name)
    {

        if (!is_null($this->owner->$attribute)) {
            return;
        }

        // todo
        $id = null;

        // Store the qa state record id in the current item
        $this->owner->$attribute = $id;

    }

}
