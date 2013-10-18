Yii Extension: QaState
==========================

What is it for:
Tools to help tracks the progress of entering, approval and proofreading of one or many attributes, as is necessary for
a high degree of quality assurance.

How to set-up:
1. Set up validation rules in your model, stating what fields are required for draft, preview and public scenarios
2. Attach this behavior to your models, together with a list of attributes that are authored in that model. These can be
   model attributes, relations or virtual attributes that needs custom validation
3. Generate the necessary schema migration using the command ./yiic authoringstate schema
4. Apply the migration using ./yiic migrate
5. Generate crud for the new schema

How to use:
This behavior updates the total entering, approval and proofreading progress. The previewable, candidate, approval
and proofreading flags are meant to be altered by relevant users/editors in your application.

How to update the schema after you have update your datamodel or list of attributes that are authored:
1. Generate the necessary schema migration using the command ./yiic authoringstate schema
2. Apply the migration using ./yiic migrate
3. Generate crud for the new schema
4. Run ./yiic authoringstate recalculate

The schema:

{table}_qa_status
    id
    status - varchar values based on statuses: draft,preview,public
    foreach scenario: {scenario}_validation_progress
    approval_progress
    proofing_progress
    foreach scenario: translations_{scenario}_validation_progress
    translations_approval_progress
    translations_proofing_progress
    foreach flag: {flag}
    foreach attribute: {attribute}_approved - boolean (with null)
    foreach attribute: {attribute}_proofed - boolean (with null)
    created
    modified

