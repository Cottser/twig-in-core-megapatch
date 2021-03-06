<?php

/**
 * @file
 * Contains \Drupal\system\Form\DateFormatDeleteForm.
 */

namespace Drupal\system\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\ControllerInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a form to delete a date format.
 */
class DateFormatDeleteForm extends ConfirmFormBase implements ControllerInterface {

  /**
   * The date format data to be deleted.
   *
   * @var array
   */
  protected $format;

  /**
   * The ID of the date format to be deleted.
   *
   * @var string
   */
  protected $formatID;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a DateFormatDeleteForm object.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'system_date_delete_format_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getQuestion() {
    return t('Are you sure you want to remove the format %name : %format?', array(
      '%name' => $this->format['name'],
      '%format' => format_date(REQUEST_TIME, $this->formatID))
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfirmText() {
    return t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCancelPath() {
    return 'admin/config/regional/date-time/formats';
  }

  /**
   * {@inheritdoc}
   *
   * @param string $format_id
   *   The date format ID.
   */
  public function buildForm(array $form, array &$form_state, $format_id = NULL) {
    // We don't get the format ID in the returned format array.
    $this->formatID = $format_id;
    $this->format = $this->configFactory->get('system.date')->get("formats.$format_id");

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    system_date_format_delete($this->formatID);
    drupal_set_message(t('Removed date format %format.', array('%format' => $this->format['name'])));

    $form_state['redirect'] = 'admin/config/regional/date-time/formats';
  }

}
