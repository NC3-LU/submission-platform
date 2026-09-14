<?php

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || ! str_starts_with((string) getenv('DB_DATABASE'), '/tmp/submission-browser.')) {
    throw new RuntimeException('Use scripts/browser-server.sh with its isolated synthetic database.');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$owner = User::factory()->create(['name' => 'Audit Maintainer', 'email' => 'audit-admin@nc3.lu', 'role' => 'admin', 'password' => bcrypt('SyntheticAuditPassword123!')]);
$user = User::factory()->create(['name' => 'Audit Submitter', 'email' => 'audit-user@example.test', 'role' => 'user', 'password' => bcrypt('SyntheticAuditPassword123!')]);
$form = Form::factory()->published()->public()->create(['title' => 'Handover audit — public report', 'description' => 'Synthetic form for testing submission flow.', 'user_id' => $owner->id]);
$cat = $form->categories()->create(['name' => 'Contact details', 'description' => 'Please provide your contact information.', 'order' => 1]);
$f1 = $cat->fields()->create(['form_id' => $form->id, 'type' => 'text', 'label' => 'Your name', 'required' => true, 'order' => 1]);
$f2 = $cat->fields()->create(['form_id' => $form->id, 'type' => 'select', 'label' => 'Include details?', 'options' => 'Yes,No', 'required' => false, 'order' => 2]);
$f3 = $cat->fields()->create(['form_id' => $form->id, 'type' => 'text', 'label' => 'Extra details', 'required' => false, 'order' => 3, 'depends_on_field_id' => $f2->id, 'depends_on_value' => 'Yes']);
$cat2 = $form->categories()->create(['name' => 'Report', 'description' => 'Describe your report.', 'order' => 2]);
$cat2->fields()->create(['form_id' => $form->id, 'type' => 'textarea', 'label' => 'Report details', 'required' => true, 'order' => 1, 'char_limit' => 1000]);
$cat2->fields()->create(['form_id' => $form->id, 'type' => 'checkbox', 'label' => 'Categories', 'options' => 'Security,Usability', 'required' => true, 'order' => 2]);
$cat2->fields()->create(['form_id' => $form->id, 'type' => 'file', 'label' => 'Attachment', 'required' => false, 'order' => 3]);
$empty = Form::factory()->published()->public()->create(['title' => 'Empty published form', 'user_id' => $owner->id]);
$private = Form::factory()->published()->create(['title' => 'Private audit form', 'visibility' => 'private', 'user_id' => $owner->id]);
$builder = Form::factory()->draft()->public()->create(['title' => 'Editable builder fixture', 'user_id' => $owner->id]);
$section = $builder->categories()->create(['name' => 'First section', 'order' => 1]);
$section->fields()->create(['form_id' => $builder->id, 'label' => 'First question', 'type' => 'text', 'order' => 1]);
$builder->categories()->create(['name' => 'Second section', 'order' => 2]);
$draft = Submission::factory()->create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);
$draft->values()->create(['form_field_id' => $f1->id, 'value' => 'Saved draft name']);
ApiToken::create(['user_id' => $owner->id, 'name' => 'audit', 'token' => hash('sha256', 'browser-audit-token'), 'abilities' => ['forms:read']]);
echo json_encode(['builder' => $builder->id, 'form' => $form->id, 'name_field' => $f1->id, 'select_field' => $f2->id, 'conditional_field' => $f3->id, 'empty' => $empty->id, 'private' => $private->id]);
