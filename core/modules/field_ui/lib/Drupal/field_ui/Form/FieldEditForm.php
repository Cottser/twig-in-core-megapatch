<?php

/**
 * @file
 * Contains \Drupal\field_ui\Form\FieldEditForm.
 */

namespace Drupal\field_ui\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\field\Plugin\Core\Entity\FieldInstance;
use Drupal\field\Field;

/**
 * Provides a form for the field settings edit page.
 */
class FieldEditForm implements FormInterface {

  /**
   * The field instance being edited.
   *
   * @var \Drupal\field\Plugin\Core\Entity\FieldInstance
   */
  protected $instance;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'field_ui_field_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, FieldInstance $field_instance = NULL) {
    $this->instance = $form_state['instance'] = $field_instance;
    $field = $this->instance->getField();
    $form['#field'] = $field;

    drupal_set_title($this->instance->label());

    $description = '<p>' . t('These settings apply to the %field field everywhere it is used. These settings impact the way that data is stored in the database and cannot be changed once data has been created.', array('%field' => $this->instance->label())) . '</p>';

    // Create a form structure for the field values.
    $form['field'] = array(
      '#prefix' => $description,
      '#tree' => TRUE,
    );

    // See if data already exists for this field.
    // If so, prevent changes to the field settings.
    $has_data = field_has_data($field);
    if ($has_data) {
      $form['field']['#prefix'] = '<div class="messages messages--error">' . t('There is data for this field in the database. The field settings can no longer be changed.') . '</div>' . $form['field']['#prefix'];
    }

    // Build the configurable field values.
    $cardinality = $field['cardinality'];
    $form['field']['cardinality_container'] = array(
      // We can't use the container element because it doesn't support the title
      // or description properties.
      '#type' => 'item',
      // Reset #parents to 'field', so the additional container does not appear.
      '#parents' => array('field'),
      '#field_prefix' => '<div class="container-inline">',
      '#field_suffix' => '</div>',
      '#title' => t('Allowed number of values'),
    );
    $form['field']['cardinality_container']['cardinality'] = array(
      '#type' => 'select',
      '#title' => t('Allowed number of values'),
      '#title_display' => 'invisible',
      '#options' => array(
        'number' => t('Limited'),
        FIELD_CARDINALITY_UNLIMITED => t('Unlimited'),
      ),
      '#default_value' => ($cardinality == FIELD_CARDINALITY_UNLIMITED) ? FIELD_CARDINALITY_UNLIMITED : 'number',
    );
    $form['field']['cardinality_container']['cardinality_number'] = array(
      '#type' => 'number',
      '#default_value' => $cardinality != FIELD_CARDINALITY_UNLIMITED ? $cardinality : 1,
      '#min' => 1,
      '#title' => t('Limit'),
      '#title_display' => 'invisible',
      '#size' => 2,
      '#states' => array(
        'visible' => array(
         ':input[name="field[cardinality]"]' => array('value' => 'number'),
        ),
      ),
    );

    // Build the non-configurable field values.
    $form['field']['field_name'] = array('#type' => 'value', '#value' => $field['field_name']);
    $form['field']['type'] = array('#type' => 'value', '#value' => $field['type']);
    $form['field']['module'] = array('#type' => 'value', '#value' => $field['module']);
    $form['field']['active'] = array('#type' => 'value', '#value' => $field['active']);

    // Add settings provided by the field module. The field module is
    // responsible for not returning settings that cannot be changed if
    // the field already has data.
    $form['field']['settings'] = array(
      '#weight' => 10,
    );
    $additions = \Drupal::moduleHandler()->invoke($field['module'], 'field_settings_form', array($field, $this->instance, $has_data));
    if (is_array($additions)) {
      $form['field']['settings'] += $additions;
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save field settings'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    // Validate field cardinality.
    $cardinality = $form_state['values']['field']['cardinality'];
    $cardinality_number = $form_state['values']['field']['cardinality_number'];
    if ($cardinality === 'number' && empty($cardinality_number)) {
      form_error($form['field']['cardinality_container']['cardinality_number'], t('Number of values is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    form_load_include($form_state, 'inc', 'field_ui', 'field_ui.admin');
    $form_values = $form_state['values'];
    $field_values = $form_values['field'];

    // Save field cardinality.
    $cardinality = $field_values['cardinality'];
    $cardinality_number = $field_values['cardinality_number'];
    if ($cardinality === 'number') {
      $cardinality = $cardinality_number;
    }
    $field_values['cardinality'] = $cardinality;
    unset($field_values['container']);

    // Merge incoming form values into the existing field.
    $field = Field::fieldInfo()->getField($field_values['field_name']);
    foreach ($field_values as $key => $value) {
      $field[$key] = $value;
    }

    // Update the field.
    try {
      $field->save();
      drupal_set_message(t('Updated field %label field settings.', array('%label' => $this->instance->label())));
      $form_state['redirect'] = field_ui_next_destination($this->instance->entity_type, $this->instance->bundle);
    }
    catch (Exception $e) {
      drupal_set_message(t('Attempt to update field %label failed: %message.', array('%label' => $this->instance->label(), '%message' => $e->getMessage())), 'error');
    }
  }

}
