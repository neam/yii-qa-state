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
     * Expose qa state object relation
     */
    public function qaState()
    {

        $class = $this->qaStateClass();
        $relation = lcfirst($class);
        return $this->owner->{$relation};

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

        $behaviors = $this->owner->behaviors();

        // Check i18n-columns if multilingual attribute
        if (isset($behaviors['i18n-columns']['translationAttributes'][$relationField])) {

            // One for each language
            foreach (Yii::app()->langHandler->languages as $lang) {

                $attribute = $relationField . $lang;
                $this->initiateQaState($attribute);

            }

        } else {

            $this->initiateQaState($relationField);

        }

    }

    private function initiateQaState($attribute)
    {

        if (!is_null($this->owner->$attribute)) {
            return;
        }

        $class = $this->qaStateClass();

        $qaState = new $class();
        if (!$qaState->save()) {
            throw new SaveException();
        }

        $id = $qaState->id;

        // Store the qa state record id in the current item
        $this->owner->$attribute = $id;

    }

    protected function qaStateClass()
    {

        return get_class($this->owner) . "QaState";

    }

    public function recalculateProgress()
    {

        // Set app language temporarily to whatever language we want to validate with
        // Revert to original app language

        $model =& $this->owner;

        // Check draft progress
        $model->scenario = 'draft';
        $model->validate();

        // Check preview progress
        $model->scenario = 'preview';
        $model->validate();

        // Check publish progress
        $model->scenario = 'publish';
        $model->validate();

    }

    public function refreshQaState()
    {
    }


}
