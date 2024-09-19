<?php

namespace Drupal\about_us\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a custom form.
 */
class AboutUsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_module_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'about_us.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config_name = 'about_us.settings';
    $config = $this->config($config_name);

    $num_groups = $form_state->get('num_groups') ?? 1;

    $form['actions']['remove_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#submit' => ['::removeCallback'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'groups-wrapper',
      ],
    ];

    $form['actions']['add_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => ['::addMore'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'groups-wrapper',
      ],
    ];

    $form['groups'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaderships'),
      '#prefix' => '<div id="groups-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['#tree'] = TRUE;

    for ($i = 0; $i < $num_groups; $i++) {
      $form['groups'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Leader @num', ['@num' => $i + 1]),
      ];

      $leader_name = $config->get('Leader_' . ($i + 1) . '_name');
      $designation = $config->get('Leader_' . ($i + 1) . '_designation');
      $linkedin_link = $config->get('Leader_' . ($i + 1) . '_linkedin_link');
      $profile_image = $config->get('Leader_' . ($i + 1) . '_profile_image');

      $form['groups'][$i]['leader_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $leader_name,
      ];

      $form['groups'][$i]['designation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Designation'),
        '#default_value' => $designation,
      ];

      $form['groups'][$i]['linkedin_link'] = [
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn Profile Link'),
        '#default_value' => $linkedin_link,
      ];

      $form['groups'][$i]['profile_image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Profile Image'),
        '#upload_location' => 'public://leaders_images/',
        '#upload_validators' => [
          'file_validate_extensions' => ['png', 'jpg', 'jpeg'],
        ],
        '#default_value' => $profile_image,
      ];
    }

    $form['anchor_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Best Anchor of the Week'),
      '#prefix' => '<div id="anchor-section-wrapper">',
      '#suffix' => '</div>',
    ];

    $anchor_ref = $config->get('anchor_reference');
    $default_anchor = NULL;

    if (!empty($anchor_ref)) {
      $default_anchor = $this->entityTypeManager->getStorage('user')->load($anchor_ref);
    }

    $form['anchor_section']['anchor_ref'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select a News Anchor'),
      '#target_type' => 'user',
      '#required' => TRUE,
      '#default_value' => $default_anchor,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'filter' => [
          'role' => ['news_anchor'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_name = 'about_us.settings';
    $config = $this->config($config_name);
    $num_groups = $form_state->get('num_groups') ?? 1;

    for ($i = 0; $i < $num_groups; $i++) {
      $leader_name = $form_state->getValue(['groups', $i, 'leader_name']);
      $designation = $form_state->getValue(['groups', $i, 'designation']);
      $linkedin_link = $form_state->getValue(['groups', $i, 'linkedin_link']);
      $profile_image = $form_state->getValue(['groups', $i, 'profile_image']);

      $config->set('Leader_' . ($i + 1) . '_name', $leader_name)
        ->set('Leader_' . ($i + 1) . '_designation', $designation)
        ->set('Leader_' . ($i + 1) . '_linkedin_link', $linkedin_link)
        ->set('Leader_' . ($i + 1) . '_profile_image', $profile_image);
    }

    $anchor_ref_value = $form_state->getValue(['anchor_section', 'anchor_ref']);
    $config->set('anchor_reference', $anchor_ref_value);

    $config->set('num_groups', $num_groups);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for adding more group fields.
   */
  public function addMore(array &$form, FormStateInterface $form_state) {
    $num_groups = $form_state->get('num_groups') ?? 1;
    $form_state->set('num_groups', $num_groups + 1);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for removing group fields.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $num_groups = $form_state->get('num_groups') ?? 1;
    if ($num_groups > 1) {
      $num_groups--;
      $form_state->set('num_groups', $num_groups);
      unset($form['groups'][$num_groups]);
      $form_state->setRebuild();
    }
  }

  /**
   * Ajax callback for rebuilding the form.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['groups'];
  }

}
