<?php

/**
 * QaStateCommand
 *
 * Generates qa state table schemas
 *
 * @uses CConsoleCommand
 * @license MIT
 * @author See https://github.com/neam/yii-qa-state/graphs/contributors
 */

class QaStateCommand extends CConsoleCommand
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
     * Source language
     *
     * @var string
     */
    public $sourceLanguage;

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

            $qaAttributes = $model->qaStateBehavior()->qaAttributes();

            // Do not activate for models without any attributes to include in the qa process
            if (empty($qaAttributes)) {
                $this->d("\t\tNote: $modelName has no attributes to include in the qa process - skipping...\n");
                continue;
            }

            // Ensure there is a table
            $tables = Yii::app()->db->schema->getTables();
            if (!isset($tables[$relationTable])) {

                $this->up[] = '$this->createTable(\'' . $relationTable . '\', array(
                    \'id\' => \'BIGINT NOT NULL AUTO_INCREMENT\',
                    \'PRIMARY KEY (`id`)\',
                ));';

            }

            // Ensure there is a field for the qa_state table fk
            // The column may be supplied by i18n-columns behavior.
            $columnExists = $this->_checkTableAndColumnExists($model->tableName(), $relationField);
            $i18nColumnExists = isset($behaviors['i18n-columns']) && in_array($relationField, $behaviors['i18n-columns']['translationAttributes']);
            if (!$columnExists && !$i18nColumnExists) {

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

            // add status column
            if (!$this->_checkTableAndColumnExists($relationTable, 'status')) {

                $this->up[] = '$this->addColumn(\'' . $relationTable . '\', \'' . 'status'
                    . '\', \'VARCHAR(255) NULL\');';
                $this->down[] = '$this->dropColumn(\'' . $relationTable . '\', \'' . 'status' . '\');';

            }

            // progress fields
            foreach ($model->qaStateBehavior()->statuses as $status) {
                $this->ensureColumn($relationTable, $status . '_validation_progress', 'BOOLEAN NULL');
            }
            $this->ensureColumn($relationTable, 'approval_progress', 'INT NULL');
            $this->ensureColumn($relationTable, 'proofing_progress', 'INT NULL');

            // translations progress fields
            foreach ($model->qaStateBehavior()->statuses as $status) {
                $this->ensureColumn($relationTable, 'translations_' . $status . '_validation_progress', 'INT NULL');
            }
            $this->ensureColumn($relationTable, 'translations_approval_progress', 'INT NULL');
            $this->ensureColumn($relationTable, 'translations_proofing_progress', 'INT NULL');

            // add flags
            foreach ($model->qaStateBehavior()->manualFlags as $manualFlag) {
                $this->ensureColumn($relationTable, $manualFlag, 'BOOLEAN NULL');
            }

            // add attribute approval fields
            foreach ($qaAttributes as $attribute) {
                $this->ensureColumn($relationTable, $attribute . '_approved', 'BOOLEAN NULL');
            }

            // add attribute proof fields
            foreach ($qaAttributes as $attribute) {
                $this->ensureColumn($relationTable, $attribute . '_proofed', 'BOOLEAN NULL');
            }

            // todo - check for fields added by earlier versions of this command
        }

        $this->_createMigrationFile();
    }

    protected function ensureColumn($table, $column, $type)
    {

        if (!$this->_checkTableAndColumnExists($table, $column)) {

            $this->up[] = '$this->addColumn(\'' . $table . '\', \'' . $column
                . '\', \'' . $type . '\');';
            $this->down[] = '$this->dropColumn(\'' . $table . '\', \'' . $column . '\');';

        }

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
     * @return bool
     */
    protected function _checkTableAndColumnExists($table, $column)
    {
        $tables = Yii::app()->db->schema->getTables();
        // The column does not exist if the table does not exist
        return isset($tables[$table]) && (isset($tables[$table]->columns[$column]));
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
     * Load languages from main config.
     *
     * @access protected
     */
    protected function _loadLanguages()
    {
        // Load main.php config file
        $file = realpath(Yii::app()->basePath) . '/config/main.php';
        if (!file_exists($file)) {
            print("Config not found\n");
            exit("Error loading config file $file.\n");
        } else {
            $config = require($file);
            $this->d("Config loaded\n");
        }

        if (!isset($config['language'])) {
            exit("Please, define a default language in the config file.\n");
        }

        $this->sourceLanguage = $config['language'];
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
                            if (isset($behaviors['qa-state']) && strpos(
                                    $behaviors['qa-state']['class'],
                                    'QaStateBehavior'
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
