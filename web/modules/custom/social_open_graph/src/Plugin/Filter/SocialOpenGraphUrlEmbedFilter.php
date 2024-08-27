<?php

namespace Drupal\social_open_graph\Plugin\Filter;

use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\social_open_graph\Service\SocialOpenGraphHelper;
use Drupal\url_embed\Plugin\Filter\UrlEmbedFilter;
use Drupal\url_embed\UrlEmbedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to display embedded URLs based on data attributes.
 *
 * @Filter(
 *   id = "social_open_graph_url_embed",
 *   title = @Translation("Display embedded URLs Open Graph content"),
 *   description = @Translation("Embeds URLs using data attribute: data-embed-url."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class SocialOpenGraphUrlEmbedFilter extends UrlEmbedFilter {

  /**
   * Uuid services.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * The config factory services.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected ConfigFactory $configFactory;

  /**
   * The social embed helper services.
   *
   * @var \Drupal\social_open_graph\Service\SocialOpenGraphHelper
   */
  protected SocialOpenGraphHelper $embedHelper;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Constructs a SocialOpenGraphUrlEmbedFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\url_embed\UrlEmbedInterface $url_embed
   *   The URL embed service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid services.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory services.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer services.
   * @param \Drupal\social_open_graph\Service\SocialOpenGraphHelper $embed_helper
   *   The social embed helper class object.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UrlEmbedInterface $url_embed,
    UuidInterface $uuid,
    ConfigFactory $config_factory,
    Renderer $renderer,
    SocialOpenGraphHelper $embed_helper,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $url_embed);
    $this->uuid = $uuid;
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
    $this->embedHelper = $embed_helper;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('social_open_graph'),
      $container->get('uuid'),
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('social_open_graph.helper_service'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);
    if (strpos($text, 'data-embed-url') !== FALSE) {
      $dom = Html::load($text);
      /** @var \DOMXPath $xpath */
      $xpath = new \DOMXPath($dom);
      /** @var \DOMNode[] $matching_nodes */
      $matching_nodes = $xpath->query('//drupal-url[@data-embed-url]');
      foreach ($matching_nodes as $node) {
        /** @var \DOMElement $node */
        $url = $node->getAttribute('data-embed-url');

        // Has this link been posted before?
        $query = \Drupal::entityQuery('social_open_graph_url')
          ->condition('url.value', $url);
        $nids = $query->execute();

        $providerName = NULL;
        $title = NULL;
        $description = NULL;
        $file = NULL;

        if (!empty($nids)) {
          $id = array_pop($nids);
          $info = \Drupal::entityTypeManager()
            ->getStorage('social_open_graph_url')
            ->load($id)
            ->toArray();

          $providerName = $info['provider_name'][0]['value'];
          $title = $info['title'][0]['value'];
          $providerName = $info['provider_name'][0]['value'];
          $description = $info['description'][0]['value'];
          $file = File::load($info['image'][0]['target_id']);
        }
        else {
          // Create new Open Graph entity.
          $info = $this->urlEmbed->getUrlInfo($url);

          $data = file_get_contents($info['image']);
          $filename = 'public://' . uniqid() . basename($info['image']);

          // Clean filename.
          $path = explode("?", $filename);
          $filename = $path[0];

          $path = explode("%", $filename);
          $filename = $path[0];

          $file = file_save_data($data, $filename);

          $title = $info['title'];
          $providerName = $info['providerName'];
          $description = $info['description'];

          $urlOpenGraph = \Drupal::entityTypeManager()
            ->getStorage('social_open_graph_url')
            ->create([
              'url' => $url,
              'title' => $title,
              'image' => [
                'target_id' => $file->id(),
                'alt'       => 'Article image',
              ],
              'provider_name' => $providerName,
              'description' => $description,
            ]);

          $urlOpenGraph->save();
        }

        // Render Open Graph entity.
        $uri = $file->getFileUri();
        $renderable = [
          '#theme' => 'open_graph',
          '#url' => $url,
          '#image' => $uri,
          '#providerName' => $providerName,
          '#title' => $title,
          '#description' => $description,
        ];
        $url_output = $this->renderer->renderPlain($renderable);
        $this->replaceNodeContent($node, $url_output);
      }

      $result->setProcessedText(Html::serialize($dom));
    }
    // Add the required dependencies and cache tags.
    return $this->embedHelper->addDependencies($result, 'social_open_graph:filter.url_embed');
  }

}
