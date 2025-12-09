<?php

namespace Drupal\social_open_graph\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\social_open_graph\UrlEmbed;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for social_open_graph routes.
 */
class SocialOpenGraphController extends ControllerBase {

  /**
   * Url Embed services.
   *
   * @var \Drupal\social_open_graph\UrlEmbed
   */
  protected UrlEmbed $urlEmbed;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected FloodInterface $flood;

  /**
   * The EmbedController constructor.
   *
   * @param \Drupal\social_open_graph\UrlEmbed $url_embed
   *   The url embed services.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   */
  public function __construct(UrlEmbed $url_embed, FloodInterface $flood) {
    $this->urlEmbed = $url_embed;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('social_open_graph'),
      $container->get('flood')
    );
  }

  /**
   * Generates embed content of a give URL.
   *
   * When the site-wide setting for consent is enabled, the links in posts and
   * nodes will be replaced with placeholder divs and a show content button.
   *
   * Once user clicks the button, it will send request to this controller along
   * with url of the content to embed and an uuid which differentiates each
   * link.
   *
   * See:
   * 1. SocialOpenGraphConvertUrlToEmbedFilter::convertUrls
   * 2. SocialOpenGraphUrlEmbedFilter::process
   * 3. EmbedConsentForm::buildForm
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function generateEmbed(Request $request) {
    // Get the requested URL of content to embed.
    $url = $request->query->get('url');
    // Get unique identifier for the button which was clicked.
    $uuid = $request->query->get('uuid');

    // Validate that both required parameters are present and valid.
    if ($url === NULL || $uuid === NULL || !Uuid::isValid($uuid)) {
      throw new NotFoundHttpException('Invalid or missing URL/UUID parameters.');
    }

    // The maximum number of times each user can do this event per time window.
    $retries = Settings::get('social_open_graph_flood_retries', 50);
    // Number of seconds in the time window for embed.
    $timeWindow = Settings::get('social_open_graph_flood_time_window', 300);

    // Only proceed if this is not a malicous request.
    if (!$this->flood->isAllowed('social_open_graph.generate_embed_flood_event', $retries, $timeWindow)) {
      throw new AccessDeniedHttpException();
    }
    // Register the flood event in system.
    $this->flood->register('social_open_graph.generate_embed_flood_event', $timeWindow);
    // Use uuid to set the selector to the specific div we need to replace.
    $selector = "#social-open-graph-iframe-$uuid";
    // If the content is embeddable then return the iFrame.
    $info = $this->urlEmbed->getUrlInfo($url);
    if ($info && !empty($iframe = $info['code'])) {
      $provider = strtolower($info['providerName']);
      $content = "<div id='social-open-graph-iframe-$uuid' class='social-open-graph-iframe-$provider'><p>$iframe</p></div>";
    }
    else {
      // Else return the link itself.
      $content = Link::fromTextAndUrl($url, Url::fromUri($url))->toString();
    }

    // Let's prepare the response.
    $response = new AjaxResponse();

    // And return the response which will replace the button
    // with embeddable content.
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

}
