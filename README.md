Yii Extension: QaState
==========================

Tools (a behavior and a console command) to track proofreading and approval progress on a field-by-field basis.
Used in content management systems to help track the creation and translation progress of the created content.

Features
--------

 * Supplies a convenient place to store the approval and proofreading flags for attributes that are part of the content creation process
 * Methods to calculate and read the current validation, approval and proofreading progress
 * Transparent qa state records creation
 * Leverages Yii validation logic as the fundamental method of validating the fields
 * Leverages Gii code generation to provide CRUD operations to set and change flags
 * (Optional) Leverages Yii extension [i18n-columns](http://www.yiiframework.com/extension/i18n-columns/) to provide individual quality assurance states for each translation language
 * Console command automatically creates migrations for the necessary database changes

Requirements
------------------

 * Yii 1.1 or above
 * Use of Yii console
 * Use of Gii (preferably [Gtc](https://github.com/schmunk42/gii-template-collection/)) and/or [giic](http://www.yiiframework.com/extension/giic/)
 * MySQL 5.1.10+, SQL Server 2012 or similarly recent database (For the console command. The behavior itself works with any Yii-supported database)

Setup
-----

### Download and install

Ensure that you have the following in your composer.json:

    "repositories":[
        {
            "type": "vcs",
            "url": "https://github.com/neam/yii-qa-state"
        },
        ...
    ],
    "require":{
        "neam/yii-qa-state":"dev-develop",
        ...
    },

Then install through composer:

    php composer.php update neam/yii-qa-state

If you don't use composer, clone or download this project into /path/to/your/app/vendor/neam/yii-qa-state

### Add Alias to both main.php and console.php
    'aliases' => array(
        ...
        'vendor'  => dirname(__FILE__) . '/../../vendor',
        'qa-state' => 'vendor.neam.yii-qa-state',
        ...
    ),

### Import the behavior in main.php

    'import' => array(
        ...
        'qa-state.behaviors.QaStateBehavior',
        ...
    ),


### Reference the qa-state command in console.php

    'commandMap' => array(
        ...
        'qa-state'    => array(
            'class' => 'qa-state.commands.QaStateCommand',
        ),
        ...
    ),


### Configure models to be part of the qa process

#### 1. Set up validation rules in your model, stating what fields are required for draft, preview and public scenarios.

Example:

    // Chapter.php

    class Chapter extends BaseChapter
    {
        ...
        public function rules()
        {
            return array_merge(
                parent::rules(), array(
                    array('title, slug', 'required', 'on' => 'draft,preview,public'),
                    array('thumbnail, about, video, teachers_guide, exercises, snapshots, credits', 'required', 'on' => 'public'),
                )
            );
        }
        ...
    }

This example only uses the validation rule "required", but of course any validation rules can be used.
The attributes can be ordinary model attributes, relations or virtual attributes that use custom validation rules etc.
The important part is that they are applied on the scenarios that the behavior is configured to use (default: draft,preview,public).

#### 2. Add the behavior to the models that you want to track qa state and progress of

    public function behaviors()
    {
        return array(
            'qa-state' => array(
                 'class' => 'QaStateBehavior',
            ),
        );
    }

#### 3. Generate the necessary schema migration using the included console command:

`./yiic qa-state`

Run with `--verbose` to see more details.

#### 4. Apply the generated migration:

`./yiic migrate`

This will add the relevant tables, relations and fields to be able to track the qa state of the configured models.

The schema has this general structure:

    {table}_qa_state
        id
        foreach scenario: {scenario}_validation_progress
        approval_progress
        proofing_progress
        foreach manualFlag: {manualFlag}
        foreach attribute: {attribute}_approved - boolean (with null)
        foreach attribute: {attribute}_proofed - boolean (with null)
        created
        modified

Each progress field are integers between 0 and 100, reflecting percentage of total progress.
Total progress is measured as all attributes under validation either validates, are approved, proofread or translated respectively.

Sample migration file:

    <?php
    class m131017_120644_qa_attributes extends CDbMigration
    {
        public function up()
        {
            $this->createTable('chapter_qa_state', array(
                        'id' => 'BIGINT NOT NULL AUTO_INCREMENT',
                        'PRIMARY KEY (`id`)',
                    ));
            $this->addColumn('chapter', 'chapter_qa_state_id', 'BIGINT NULL');
            $this->addForeignKey('chapter_qa_state_id_fk', 'chapter', 'chapter_qa_state_id', 'chapter_qa_state', 'id', 'SET NULL', 'SET NULL');
            $this->addColumn('chapter_qa_state', 'draft_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'preview_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'public_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'approval_progress', 'INT NULL');
            $this->addColumn('chapter_qa_state', 'proofing_progress', 'INT NULL');
            $this->addColumn('chapter_qa_state', 'previewing_welcome', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'candidate_for_public_status', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'title_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'slug_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'thumbnail_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'about_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'video_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'teachers_guide_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'exercises_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'snapshots_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'credits_approved', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'title_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'slug_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'thumbnail_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'about_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'video_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'teachers_guide_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'exercises_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'snapshots_proofed', 'BOOLEAN NULL');
            $this->addColumn('chapter_qa_state', 'credits_proofed', 'BOOLEAN NULL');
            // {...example file shortened...}
            $this->createTable('video_file_qa_state', array(
                        'id' => 'BIGINT NOT NULL AUTO_INCREMENT',
                        'PRIMARY KEY (`id`)',
                    ));
            $this->addColumn('video_file', 'video_file_qa_state_id', 'BIGINT NULL');
            $this->addForeignKey('video_file_qa_state_id_fk', 'video_file', 'video_file_qa_state_id', 'video_file_qa_state', 'id', 'SET NULL', 'SET NULL');
            $this->addColumn('video_file_qa_state', 'draft_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'preview_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'public_validation_progress', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'approval_progress', 'INT NULL');
            $this->addColumn('video_file_qa_state', 'proofing_progress', 'INT NULL');
            $this->addColumn('video_file_qa_state', 'previewing_welcome', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'candidate_for_public_status', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'title_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'slug_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'clip_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'about_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'thumbnail_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'subtitles_approved', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'title_proofed', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'slug_proofed', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'clip_proofed', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'about_proofed', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'thumbnail_proofed', 'BOOLEAN NULL');
            $this->addColumn('video_file_qa_state', 'subtitles_proofed', 'BOOLEAN NULL');
        }

        public function down()
        {
          $this->dropForeignKey('chapter_qa_state_id_fk', 'chapter');
          $this->dropColumn('chapter', 'chapter_qa_state_id');
          $this->dropTable('chapter_qa_state');
          $this->dropColumn('chapter_qa_state', 'draft_validation_progress');
          $this->dropColumn('chapter_qa_state', 'preview_validation_progress');
          $this->dropColumn('chapter_qa_state', 'public_validation_progress');
          $this->dropColumn('chapter_qa_state', 'approval_progress');
          $this->dropColumn('chapter_qa_state', 'proofing_progress');
          $this->dropColumn('chapter_qa_state', 'previewing_welcome');
          $this->dropColumn('chapter_qa_state', 'candidate_for_public_status');
          $this->dropColumn('chapter_qa_state', 'title_approved');
          $this->dropColumn('chapter_qa_state', 'slug_approved');
          $this->dropColumn('chapter_qa_state', 'thumbnail_approved');
          $this->dropColumn('chapter_qa_state', 'about_approved');
          $this->dropColumn('chapter_qa_state', 'video_approved');
          $this->dropColumn('chapter_qa_state', 'teachers_guide_approved');
          $this->dropColumn('chapter_qa_state', 'exercises_approved');
          $this->dropColumn('chapter_qa_state', 'snapshots_approved');
          $this->dropColumn('chapter_qa_state', 'credits_approved');
          $this->dropColumn('chapter_qa_state', 'title_proofed');
          $this->dropColumn('chapter_qa_state', 'slug_proofed');
          $this->dropColumn('chapter_qa_state', 'thumbnail_proofed');
          $this->dropColumn('chapter_qa_state', 'about_proofed');
          $this->dropColumn('chapter_qa_state', 'video_proofed');
          $this->dropColumn('chapter_qa_state', 'teachers_guide_proofed');
          $this->dropColumn('chapter_qa_state', 'exercises_proofed');
          $this->dropColumn('chapter_qa_state', 'snapshots_proofed');
          $this->dropColumn('chapter_qa_state', 'credits_proofed');
          // {...example file shortened...}
          $this->dropForeignKey('video_file_qa_state_id_fk', 'video_file');
          $this->dropColumn('video_file', 'video_file_qa_state_id');
          $this->dropTable('video_file_qa_state');
          $this->dropColumn('video_file_qa_state', 'draft_validation_progress');
          $this->dropColumn('video_file_qa_state', 'preview_validation_progress');
          $this->dropColumn('video_file_qa_state', 'public_validation_progress');
          $this->dropColumn('video_file_qa_state', 'approval_progress');
          $this->dropColumn('video_file_qa_state', 'proofing_progress');
          $this->dropColumn('video_file_qa_state', 'previewing_welcome');
          $this->dropColumn('video_file_qa_state', 'candidate_for_public_status');
          $this->dropColumn('video_file_qa_state', 'title_approved');
          $this->dropColumn('video_file_qa_state', 'slug_approved');
          $this->dropColumn('video_file_qa_state', 'clip_approved');
          $this->dropColumn('video_file_qa_state', 'about_approved');
          $this->dropColumn('video_file_qa_state', 'thumbnail_approved');
          $this->dropColumn('video_file_qa_state', 'subtitles_approved');
          $this->dropColumn('video_file_qa_state', 'title_proofed');
          $this->dropColumn('video_file_qa_state', 'slug_proofed');
          $this->dropColumn('video_file_qa_state', 'clip_proofed');
          $this->dropColumn('video_file_qa_state', 'about_proofed');
          $this->dropColumn('video_file_qa_state', 'thumbnail_proofed');
          $this->dropColumn('video_file_qa_state', 'subtitles_proofed');
        }
    }

#### 4. Re-generate models and crud

Use Gii as per the official documentation. After this, you have a convenient place to store the approval and proofreading flags for attributes that are part of the content creation process.

Usage
-----

The manual flags as well as the approval and proofreading flags are meant to be altered by relevant users/editors in your application.
The behavior updates the total validation, approval and proofreading progress on afterSave.

Changelog
---------

### 0.1.0

- Initial release
- Supplies a convenient place to store the approval and proofreading flags for attributes that are part of the content creation process
- Methods to calculate and read the current validation, approval and proofreading progress
- Transparent qa state records creation
- Leverages Yii validation logic as the fundamental method of validating the fields
- Leverages Gii code generation to provide CRUD operations to set and change flags
- Leverages Yii extension [i18n-columns](http://www.yiiframework.com/extension/i18n-columns/) to provide individual quality assurance states for each translation language
- Console command automatically creates migrations for the necessary database changes

FAQ
---

### How do I update the schema after having updated my datamodel or the list of attributes that are part of the qa process?

1. Generate the necessary schema migration using the command ./yiic authoringstate schema
2. Apply the migration using ./yiic migrate
3. Generate crud for the new schema

Before generating the crud you might want to remove any fields that are no longer configured to be
part of the qa process. This is however optional since the behavior will only take into consideration the currently configured attributes.