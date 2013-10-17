<?php

/**
 * AuthoringStateCommand
 *
 * Generates authoring state table schemas for
 *
 * @uses CConsoleCommand
 * @license MIT
 * @author See https://github.com/neam/yii-i18n-columns/graphs/contributors
 */

class QaAttributesCommand extends CConsoleCommand
{
    /**
     * @var string
     */
    public $migrationPath = 'application.migrations';

    /**
     * @var array
     */
    public $models = array();

    /**
     * @var array
     */
    public $up = array();

    /**
     * @var array
     */
    public $down = array();

    /**
     * If we should be verbose
     *
     * @var bool
     */
    private $_verbose = false;

    /**
     * Write a string to standard output if we're verbose
     *
     * @param $string
     */
    public function d($string)
    {
        if ($this->_verbose) {
            print "\033[37m" . $string . "\033[30m";
        }
    }

    /**
     * Execute the command
     *
     * @param array $args
     * @return bool|int
     */
    public function run($args)
    {
        if (in_array('--verbose', $args)) {
            $this->_verbose = true;
        }

        // Sqlite check
        if ((Yii::app()->db->schema instanceof CSqliteSchema) !== false) {
            throw new CException("Sqlite does not support adding foreign keys, renaming columns or even add new columns that have a NOT NULL constraint, so this command can not support sqlite. Sorry.");
        }

        $this->models = $this->_getModels();

        if (sizeof($this->models) > 0) {
            $this->_createMigration();
        } else {
            throw new CException("Found no models with QaAttributes behavior attached");
        }
    }

    /**
     * Create the migration files
     *
     * Target schema - {table}_qa_status:
     *  id
     *  status - varchar values based on statuses: draft,preview,public
     *  foreach scenario: {scenario}_validation_progress
     *  approval_progress
     *  proofing_progress
     *  foreach scenario: translations_{scenario}_validation_progress
     *  translations_approval_progress
     *  translations_proofing_progress
     *  foreach flag: {flag}
     *  foreach attribute: {attribute}_approved - boolean (with null)
     *  foreach attribute: {attribute}_proofed - boolean (with null)
     */
    protected function _createMigration()
    {
        $this->d("Creating the migration...\n");
        foreach ($this->models as $modelName => $model) {
            $this->d("\t...$modelName: \n");
            $behaviors = $model->behaviors();

            $relationTable = $model->tableName() . "_qa_state";
            $relationField = $relationTable . "_id";

            // Ensure there is a table
            $tables = Yii::app()->db->schema->getTables();
            if (!isset($tables[$relationTable])) {

                $this->up[] = '$this->createTable(\'' . $relationTable . '\', array(
                    \'id\' => \'BIGINT NOT NULL AUTO_INCREMENT\',
                    \'PRIMARY KEY (`id`)\',
                ));';

            }

            // Ensure there is a field for the qa_state table fk
            if (!$this->_checkColumnExists($model, $relationField)) {

                $this->up[] = '$this->addColumn(\'' . $model->tableName() . '\', \'' . $relationField
                    . '\', \'BIGINT NULL\');';
                $this->up[] = '$this->addForeignKey(\'' . $relationField . '_fk'
                    . '\', \'' . $model->tableName()
                    . '\', \'' . $relationField
                    . '\', \'' . $relationTable
                    . '\', \'' . 'id'
                    . '\', \'' . 'SET NULL'
                    . '\', \'' . 'SET NULL' . '\');';

                $this->down[] = '$this->dropForeignKey(\'' . $relationField . '_fk'
                    . '\', \'' . $model->tableName() . '\');';
                $this->down[] = '$this->dropColumn(\'' . $model->tableName() . '\', \'' . $relationField . '\');';

            }

            if (!isset($tables[$relationTable])) {

                $this->down[] = '$this->dropTable(\'' . $relationTable . '\');';

            }

            // todo - add status
            // todo - add progress fields
            // todo - add flags
            // todo - add attribute approval fields
            // todo - add attribute proof fields

            /*
            foreach ($model->qaAttributes() as $attribute) {

                foreach ($this->languages as $lang) {
                    $this->d("\t\t$lang: ");
                    $this->_processAttribute($lang, $model, $attribute);
                }
                $this->d("\n");
            }
            */
        }

        $this->_createMigrationFile();
    }

    /**
     * @param $lang
     * @param $model
     */
    protected function _processAttribute($lang, $model, $attribute)
    {

        // todo

    }

    /**
     * @param $model
     * @param $column
     * @return bool
     */
    protected function _checkColumnExists($model, $column)
    {
        return isset($model->metaData->columns[$column]);
    }

    /**
     * @param $model
     * @param $column
     * @return string
     */
    protected function _getColumnDbType($model, $column)
    {
        $data = $model->metaData->columns[$column];
        $isNull = $data->allowNull ? "null" : "not null";

        return $data->dbType . ' ' . $isNull;
    }

    /**
     * Create migration file
     */
    protected function _createMigrationFile()
    {
        if (count($this->up) == 0) {
            exit("Database up to date\n");
        }

        $migrationName = 'm' . gmdate('ymd_His') . '_qa_attributes';

        $phpCode = '<?php
class ' . $migrationName . ' extends CDbMigration
{
    public function up()
    {
        ' . implode("\n        ", $this->up) . '
    }

    public function down()
    {
      ' . implode("\n      ", $this->down) . '
    }
}' . "\n";

        $migrationsDir = Yii::getPathOfAlias($this->migrationPath);
        if (!realpath($migrationsDir)) {
            die(sprintf('Please create migration directory %s first', $migrationsDir));
        }

        $migrationFile = $migrationsDir . '/' . $migrationName . '.php';
        $f = fopen($migrationFile, 'w') or die("Can't open file");
        fwrite($f, $phpCode);
        fclose($f);

        print "Migration successfully created.\n";
        print "See $migrationName\n";
        print "To apply migration enter: ./yiic migrate\n";
    }

    // Adapted from gii-template-collection / fullCrud / FullCrudCode.php
    protected function _getModels()
    {
        $models = array();
        $aliases = array();
        $aliases[] = 'application.models';
        foreach (Yii::app()->getModules() as $moduleName => $config) {
            if ($moduleName != 'gii') {
                $aliases[] = $moduleName . ".models";
            }
        }

        foreach ($aliases as $alias) {
            if (!is_dir(Yii::getPathOfAlias($alias))) {
                continue;
            }
            $files = scandir(Yii::getPathOfAlias($alias));
            Yii::import($alias . ".*");
            foreach ($files as $file) {
                if ($fileClassName = $this->_checkFile($file, $alias)) {
                    $classname = sprintf('%s.%s', $alias, $fileClassName);
                    Yii::import($classname);
                    try {
                        $model = new $fileClassName;
                        if (method_exists($model, 'behaviors')) {
                            $behaviors = $model->behaviors();
                            if (isset($behaviors['qa-attributes']) && strpos(
                                    $behaviors['qa-attributes']['class'],
                                    'QaAttributesBehavior'
                                ) !== false
                            ) {
                                $models[$classname] = $model;
                            }
                        }
                    } catch (ErrorException $e) {
                        $this->d("\tErrorException: " . $e->getMessage());
                        break;
                    } catch (CDbException $e) {
                        $this->d("\CDbException: " . $e->getMessage());
                        break;
                    } catch (Exception $e) {
                        $this->d("\Exception: " . $e->getMessage());
                        break;
                    }
                }
            }
        }

        return $models;
    }

    // Imported from gii-template-collection / fullCrud / FullCrudCode.php
    protected function _checkFile($file, $alias = '')
    {
        if (substr($file, 0, 1) !== '.' && substr($file, 0, 2) !== '..' && substr(
                $file,
                0,
                4
            ) !== 'Base' && $file != 'GActiveRecord' && strtolower(substr($file, -4)) === '.php'
        ) {
            $fileClassName = substr($file, 0, strpos($file, '.'));
            if (class_exists($fileClassName) && is_subclass_of($fileClassName, 'CActiveRecord')) {
                $fileClass = new ReflectionClass($fileClassName);
                if ($fileClass->isAbstract()) {
                    return null;
                } else {
                    return $models[] = $fileClassName;
                }
            }
        }
    }

}
