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
        'reviewable',
        'publishable',
        /* Example: tracking translation progress through language-specific validation scenarios? Add the scenarios through configuration:
        'translate_into_es',
        'translate_into_de',
        'translate_into_fr',
        'translate_into_sv',
        */
    );

    /**
     * The different statuses this behavior should be aware of.
     *
     * The configuration format is an array with the status reference (as saved in
     * the database) as the key and an array with the following options as the value:
     *
     *   label     - the label for the UI
     *   scenarios - array of scenarios that have to validate in order to set a certain status automatically.
     *               an empty array means that nothing needs to be validated for this status, and is useful for
     *               default statuses
     *   type      - automatic|manual determines if the status is automatically calculated and set or set manually
     *
     * The status will be set to the last status that validates it's scenarios, and will
     * be calculated and set automatically unless the current status is a manually set status.
     *
     * @var array
     */
    public $statuses = array(
        'temporary' => array(
            'label' => 'Temporary',
            'scenarios' => array(),
            'type' => self::STATUS_AUTOMATIC,
        ),
        'draft' => array(
            'label' => 'Draft',
            'scenarios' => array('draft'),
            'type' => self::STATUS_AUTOMATIC,
        ),
        'reviewable' => array(
            'label' => 'Reviewable',
            'scenarios' => array('reviewable', 'status_reviewable'),
            'type' => self::STATUS_AUTOMATIC,
        ),
        'publishable' => array(
            'label' => 'Publishable',
            'scenarios' => array('publishable', 'status_publishable'),
            'type' => self::STATUS_AUTOMATIC,
        ),
        'replaced' => array(
            'label' => 'Replaced',
            'type' => self::STATUS_MANUAL,
        ),
        'removed' => array(
            'label' => 'Removed',
            'type' => self::STATUS_MANUAL,
        ),
    );

    const STATUS_AUTOMATIC = 'automatic';
    const STATUS_MANUAL = 'manual';

    /**
     * Additional flags that are to be manually tracked in the qa process.
     * Used to include attributes to track these flags in the schema.
     * @var array
     */
    public $manualFlags = array(
        'allow_review',
        'allow_publish'
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
    public function qaAttributes($scenario = null)
    {
        // reset before populating anew
        $this->qaAttributes = array();

        // include all attributes
        if (is_null($scenario)) {
            foreach ($this->scenarios as $scenario) {
                $this->qaAttributes = array_unique(array_merge($this->qaAttributes, $this->scenarioSpecificAttributes($scenario)));
            }
        } else {
            $this->qaAttributes = $this->scenarioSpecificAttributes($scenario);
        }

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

    public function determineAutomaticStatus()
    {
        $lastValidStatus = null;
        foreach ($this->statuses as $status => $options) {
            if ($options['type'] == self::STATUS_MANUAL || !isset($options['scenarios'])) {
                continue;
            }
            Yii::log("\$this->validStatus($status):" . (int) $this->validStatus($status), 'flow', __METHOD__);
            if ($this->validStatus($status)) {
                $lastValidStatus = $status;
            } else {
                return $lastValidStatus;
            }
        }
        return $lastValidStatus;
    }

    public function validStatus($status)
    {
        $validates = true;
        $options = $this->statuses[$status];
        foreach ($options['scenarios'] as $scenario) {
            Yii::log("\$this->calculateValidationProgress($scenario):" . (int) $this->calculateValidationProgress($scenario), 'flow', __METHOD__);
            $progress = $this->calculateValidationProgress($scenario);
            if ($progress < 100) {
                $validates = false;
                break;
            }
        }
        return $validates;
    }

    public function setAutomaticStatus()
    {
        $currentStatus = $this->owner->qaState()->status;
        if (is_null($currentStatus) || $this->statuses[$currentStatus]['type'] == self::STATUS_AUTOMATIC) {
            $this->owner->qaState()->status = $this->determineAutomaticStatus();
        }
    }

    public function getStatusLabel()
    {
        if (is_null($this->owner->qaState()->status) || !isset($this->statuses[$this->owner->qaState()->status])) {
            return null;
        }
        return $this->statuses[$this->owner->qaState()->status]['label'];
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

        // Set status
        $this->setAutomaticStatus();

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