<?php

namespace Drupal\qb\Form;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Drupal\views\Plugin\views\wizard\WizardException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure qb settings for this site.
 */
class SettingsForm extends FormBase {

  /**
   * The wizard plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager
   */
  protected $wizardManager;

  /**
   * Constructs a new ViewAddForm object.
   *
   * @param \Drupal\views\Plugin\ViewsPluginManager $wizard_manager
   *   The wizard plugin manager.
   */
  public function __construct(ViewsPluginManager $wizard_manager) {
    $this->wizardManager = $wizard_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.views.wizard')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qb_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['qb.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = [
      'node_type' => $this->t('Content'),
      'user' => $this->t('Users'),
      'taxonomy_vocabulary' => $this->t('Taxonomy')
    ];

    $form['query_builder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Query Builder'),
      '#prefix' => '<div id="qb-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['query_builder']['entity'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Entity'),
      '#options' => $options,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'qb-fieldset-wrapper',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];
    // render bundles by type of entity selected in the dropdown above.
    if (!empty($form_state->getValue('entity'))) {
      $node_type = [];
      $types = \Drupal::entityTypeManager()
        ->getStorage($form_state->getValue('entity'))
        ->loadMultiple();
      foreach ($types as $type) {
        $node_type[$type->id()] = $type->label();
      }
      $form['query_builder']['bundle'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => $this->t('Bundle'),
        '#options' => $node_type,
      ];
    }
    // render fields by type of entity selected in the dropdown above.
//    $types = ['integer', 'string', 'text', 'text_long', 'text_with_summary', 'boolean', 'datetime', 'decimal', 'email', 'float', 'image', 'link', 'list_string', 'list_float', 'list_integer', 'list_boolean', 'map', 'uri', 'timestamp'];
    $types = [
      'insert' => $this->t('Insert'),
      'update' => $this->t('Update'),
      'delete' => $this->t('Delete'),
      'select' => $this->t('Select'),
      'create_view'=> $this->t('CREATE VIEW'),
    ];
    $form['query_builder']['type_query'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Type Query'),
      '#options' => $types,
    ];
    $form['query_builder']['group_by'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group By'),
      '#description' => $this->t('Group By'),
    ];

    $form['query_builder']['limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Limit'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'qb-fieldset-wrapper',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];
    if (!empty($form_state->getValue('limit'))) {
      $form['query_builder']['limit_value'] = [
        '#type' => 'number',
        '#title' => $this->t('Limit Value'),
        '#description' => $this->t('Limit Value'),
      ];
    }
    // submit button
    $form['query_builder']['actions']['submit'] = [
      '#type' => 'submit',
      '#submit' => ['::settingsAjaxSubmit'],
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => [$this, 'ajaxcallback'],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    if ($form_state->getValue('example') != 'example') {
//      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
//    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsAjaxSubmit(array &$form, FormStateInterface $form_state) {
    $getValues = $form_state->getValues();
    // Query by filtering on the ID as this is more efficient than filtering
    // on the entity_type property directly.
    $ids = \Drupal::entityQuery('field_config')
      ->condition('entity_type', 'node')
      ->condition('bundle', $getValues['bundle'])
      ->execute();

    $items = [];
    // Fetch all fields and key them by field name.
    $field_configs = FieldConfig::loadMultiple($ids);
    $result = [];
    foreach ($field_configs as $field_instance) {
      $result['getName'] = $field_instance->getName();
      $result['getSettings'] = $field_instance->getSettings();
      $items[] = $result;
    }
    $markup = '<pre>' . print_r($items, TRUE) . '</pre>';
    foreach ($items as $item) {
      // markup
      $markup = '<div class="form-item form-type-checkbox form-item-qb-field-' . $item['getName'] . '">
        <input type="checkbox" id="edit-qb-field-' . $item['getName'] . '" name="qb_field[' . $item['getName'] . ']" value="' . $item['getName'] . '" class="form-checkbox">
        <label class="option" for="edit-qb-field-' . $item['getName'] . '">' . $item['getName'] . '</label>"';

    }
    $form['query_builder']['ssss'] = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
      // ADD MASSGE TO THE FORM
    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
  }

  /**
   * Ajax callback.
   */
  public function ajaxCallback($form, FormStateInterface $form_state) {
    return $form['query_builder'];
  }
}
