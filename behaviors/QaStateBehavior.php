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
     * The different scenarios this behavior should track validation progress of
     * @var array
     */
    public $scenarios = array(
        'draft',
        'preview',
        'public',
        /* Example: tracking translation progress through language-specific validation scenarios? Add the scenarios through configuration:
        'translate_into_es',
        'translate_into_de',
        'translate_into_fr',
        'translate_into_sv',
        */
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
            $status = $this->scenarios[count($this->scenarios) - 1];
        }

        $this->qaAttributes = $this->scenarioSpecificAttributes($status);

        return $this->qaAttributes;
    }

    public function scenarioSpecificAttributes($scenario)
    {

        $attributes = array();

        foreach ($this->owner->validatorList as $validator) {
            if (!in_array($scenario, $validator->on)) {
                continue;
            }
            $attributes = array_merge($attributes, $validator->attributes);
        }

        return array_unique($attributes);

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
        if (is_null($this->owner->{$this->qaStateAttribute()})) {
            $this->initiateQaState($this->qaStateAttribute());
            if (!$this->owner->saveAttributes(array($this->qaStateAttribute() => $this->owner->{$this->qaStateAttribute()}))) {
                throw new CException("Save failed. Errors: " . print_r($this->owner->errors, true));
            }
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

        $this->initiateQaState($this->qaStateAttribute());

    }

    protected function initiateQaState($attribute)
    {

        if (!is_null($this->owner->$attribute)) {
            return;
        }

        $class = $this->qaStateClass();

        $qaState = new $class();
        if (!$qaState->save()) {
            throw new CException("Save failed");
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
        $attribute = $this->owner->tableName() . "_qa_state_id";

        $behaviors = $this->owner->behaviors();
        if (isset($behaviors['i18n-columns']['translationAttributes']) && in_array($attribute, $behaviors['i18n-columns']['translationAttributes'])) {
            $attribute .= "_" . Yii::app()->language;
        }

        return $attribute;
    }

    /**
     * Like calculateInvalidFields() but instead returns a
     * percentage 0-100 of the current validation
     * progress.
     * @param $scenario
     * @return float
     * @throws QaStateBehaviorNoAssociatedRulesException Thrown to prevent division with 0
     */
    public function calculateValidationProgress($scenario)
    {

        // Count fields
        $attributes = $this->scenarioSpecificAttributes($scenario);
        $totalFields = count($attributes);

        if ($totalFields == 0) {
            throw new QaStateBehaviorNoAssociatedRulesException("The scenario '$scenario' has no associated validation rules");
        }

        $invalidFields = $this->calculateInvalidFields($scenario);
        $validFields = $totalFields - $invalidFields;

        return round($validFields / $totalFields * 100);

    }

    /**
     * Steps through all of the fields that are validated in a specific
     * scenario and returns a percentage 0-100 of the current validation
     * progress
     * @param $scenario
     * @return int
     */
    public function calculateInvalidFields($scenario)
    {

        // Work on a clone to not interfere with existing attributes and validation errors
        $model = clone $this->owner;
        $model->scenario = $scenario;

        // Count fields
        $attributes = $this->scenarioSpecificAttributes($scenario);
        $invalidFields = 0;

        // Validate each field individually
        foreach ($attributes as $attribute) {
            $valid = $model->validate(array($attribute));
            if (!$valid) {
                $invalidFields++;
            }
        }

        return $invalidFields;

    }

    public function refreshQaState($lang = null)
    {

        // Set app language temporarily to whatever language we want to validate with
        if (!is_null($lang)) {
            $previousLang = Yii::app()->language;
            Yii::app()->language = $lang;
        }

        // Check validation progress
        foreach ($this->scenarios as $scenario) {
            $progress = $this->calculateValidationProgress($scenario);
            // Assign progress
            $attribute = "{$scenario}_validation_progress";
            $this->owner->qaState()->$attribute = $progress;

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

class QaStateBehaviorNoAssociatedRulesException extends CException
{

}