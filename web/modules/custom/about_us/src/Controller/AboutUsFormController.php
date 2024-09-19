<?php

namespace Drupal\about_us\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom controller for displaying the About Us page.
 */
class AboutUsFormController extends ControllerBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a CustomFormController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Creates an instance of the controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Display the About Us page with configuration data.
   *
   * @return array
   *   A render array for the About Us page.
   */
  public function aboutUsPage() {
    $config = $this->configFactory->get('about_us.settings');
    $num_groups = $config->get('num_groups');

    $content = [];
    for ($i = 0; $i < $num_groups; $i++) {
      $leader_key = "Leader_" . ($i + 1);
      $profile_image = $config->get($leader_key . '_profile_image');

      $profile_image_url = '';
      if (!empty($profile_image)) {
        $file = $this->entityTypeManager->getStorage('file')->load($profile_image[0]);
        if ($file) {
          $profile_image_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
        }
      }

      $content[] = [
        'leaderName' => $config->get($leader_key . '_name'),
        'designation' => $config->get($leader_key . '_designation'),
        'linkedinLink' => $config->get($leader_key . '_linkedin_link'),
        'profileImage' => $profile_image_url,
      ];
    }

    $anchor_ref = $config->get('anchor_reference');
    $anchorReference = '';
    $field_description = '';
    $latest_news = [];

    if (!empty($anchor_ref)) {
      $anchorUser = $this->entityTypeManager->getStorage('user')->load($anchor_ref);
      if ($anchorUser) {
        $anchorReference = $anchorUser->getAccountName();

        if ($anchorUser->hasField('field_description')) {
          $field_description = $anchorUser->get('field_description')->value;
        }

        $news_articles = $this->entityTypeManager->getStorage('node')->loadByProperties([
          'type' => 'news',
          'status' => 1,
          'uid' => $anchor_ref,
        ]);

        if (!empty($news_articles)) {
          usort($news_articles, function (Node $a, Node $b) {
            return $b->getCreatedTime() <=> $a->getCreatedTime();
          });

          $latest_news = array_slice($news_articles, 0, 3);

          $latest_news_data = [];
          foreach ($latest_news as $article) {
            $latest_news_data[] = [
              'title' => $article->getTitle(),
              'url' => $article->toUrl()->toString(),
            ];
          }
          $latest_news = $latest_news_data;
        }
      }
    }

    return [
      '#theme' => 'about_us_data',
      '#content' => $content,
      '#anchorReference' => $anchorReference,
      '#description' => $field_description,
      '#latestNews' => $latest_news,
      '#cache' => [
        'tags' => ['config:about_us.settings'],
      ],
    ];
  }

}
