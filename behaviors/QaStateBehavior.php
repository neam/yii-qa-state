<?php

/**
 * QaStateBehavior
 *
 * @uses CActiveRecordBehavior
 * @license MIT
 * @author See https://github.com/neam/yii-qa-state/graphs/contributors
 */
class QaStateBehavior extends CActiveRecordBehavior
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
    public function qaAttributes($status = null)
    {

        if (is_null($status)) {
            // those that are part of the final status will include all attributes
            $status = $this->statuses[count($this->statuses) - 1];
        }

        $attributes = array();
        foreach ($this->owner->validatorList as $validator) {
            if (!in_array($status, $validator->on)) {
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
    public function & qaStateBehavior()
    {
        return $this;
    }

    /**
     * Expose and ensure qa state object relation
     */
    public function qaState()
    {

        $class = $this->qaStateClass();
        $relation = lcfirst($class);

        // Ensure initiated qa state object relation
        if (is_null($this->owner->{$relation})) {
            $this->initiateQaState($this->qaStateAttribute());
            $this->owner->save();
            $this->owner->refresh();
        }

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

        $relationField = $this->qaStateAttribute();

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

    protected function initiateQaState($attribute)
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

    protected function qaStateAttribute()
    {

        return $this->owner->tableName() . "_qa_state_id";

    }

    public function calculateValidationProgress($status)
    {

        // Work on a clone to not interfere with existing attributes and validation errors
        $model = clone $this->owner;
        $model->scenario = $status;

        // Count fields
        $attributes = $this->qaAttributes($status);
        $totalFields = count($attributes);
        $validFields = 0;

        // Validate each field individually
        foreach ($attributes as $attribute) {
            $valid = $model->validate(array($attribute));
            if ($valid) {
                $validFields++;
            }
        }

        // Assign progress
        $attribute = "{$status}_validation_progress";
        $model->qaState()->$attribute = round($validFields / $totalFields * 100);

    }

    public function refreshQaState($lang = null)
    {

        // Set app language temporarily to whatever language we want to validate with
        if (!is_null($lang)) {
            $previousLang = Yii::app()->language;
            Yii::app()->language = $lang;
        }

        // Check validation progress
        foreach ($this->statuses as $status) {
            $this->calculateValidationProgress($status);
        }

        // Save qa state
        if (!$this->owner->qaState()->save()) {
            throw new CException("Could not save qa state");
        }

        // Revert to original app language
        if (!is_null($lang)) {
            Yii::app()->language = $previousLang;
        }

    }


}
