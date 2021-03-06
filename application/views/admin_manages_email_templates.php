<?php

require_once BASEPATH . 'autoload.php';

echo userHTML();

$symbDelete = ' <i class="fa fa-trash"></i>';

$templates = getEmailTemplates();

$html = '<form method="post" action="">';
$html .= '<select name="id"> 
    <option disabled selected>Add a new template</option>';

$templateDict = [];
foreach ($templates as $template) {
    $templateDict[$template['id']] = $template;
    $html .= '<option name="id" value="' . $template['id'] . '">'
        . $template['id'] . '</option>';
}

$html .= '</select>';
$html .= '<button type="submit">Submit</button>';
$html .= '</form>';
echo $html;

$id = __get__($_POST, 'id', '');

echo '<h3>Add/Edit a new template</h3>';

// If existing id is selected then edit, else add.
$todo = 'add';
$defaults = ['id' => ''];
if ($id) {
    $defaults = $templateDict[$id];
    $todo = 'update';
}

echo '<form method="post" action="' . site_url('admin/templates_task') . '">';

if ('update' == $todo) {
    $editable = ['recipients', 'cc', 'when_to_send', 'description'];
} else {
    $editable = ['id', 'recipients', 'cc', 'when_to_send', 'description'];
}

echo dbTableToHTMLTable('email_templates', $defaults, $editable, $todo);
echo '<button onclick="AreYouSure( this, \'delete\' )" 
    name="response" title="Delete this entry">' . $symbDelete
    . '</button>';
echo '</form>';

echo goBackToPageLink('admin/home');
