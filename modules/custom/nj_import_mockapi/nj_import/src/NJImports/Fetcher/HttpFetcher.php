<?php

namespace Drupal\nj_import\NJImport\Fetcher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nj_import\Exception\EmptyImportException;
use Drupal\nj_import\ImportInterface;
use Drupal\nj_import\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines an HTTP fetcher.
 */
class HttpFetcher extends PluginBase implements ClearableInterface, FetcherInterface, ContainerFactoryPluginInterface {

  /**
   * The Guzzle client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Drupal file system helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs an UploadFetcher object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The Drupal file system helper.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ClientInterface $client, CacheBackendInterface $cache, FileSystemInterface $file_system) {
    $this->client = $client;
    $this->cache = $cache;
    $this->fileSystem = $file_system;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(ImportInterface $import, StateInterface $state) {
    $sink = $this->fileSystem->tempnam('temporary://', 'nj_import_http_fetcher');
    $sink = $this->fileSystem->realpath($sink);

    // Get cache key if caching is enabled.
    $cache_key = $this->useCache() ? $this->getCacheKey($import) : FALSE;

    $response = $this->get($import->getSource(), $sink, $cache_key);
    // @todo Handle redirects.
    // @codingStandardsIgnoreStart
    // $import->setSource($response->getEffectiveUrl());
    // @codingStandardsIgnoreEnd

    // 304, nothing to see here.
    if ($response->getStatusCode() == Response::HTTP_NOT_MODIFIED) {
      $state->setMessage($this->t('The import has not been updated.'));
      throw new EmptyImportException();
    }

    return new HttpFetcherResult($sink, $response->getHeaders());
  }

  /**
   * Performs a GET request.
   *
   * @param string $url
   *   The URL to GET.
   * @param string $sink
   *   The location where the downloaded content will be saved. This can be a
   *   resource, path or a StreamInterface object.
   *
   * @return \Guzzle\Http\Message\Response
   *   A Guzzle response.
   *
   * @throws \RuntimeException
   *   Thrown if the GET request failed.
   *
   * @see \GuzzleHttp\RequestOptions
   */
  protected function get($url, $sink) {
    $url = Import::translateSchemes($url);

    $options = [RequestOptions::SINK => $sink];


    try {
      $response = $this->client->get($url, $options);
    }
    catch (RequestException $e) {
      $args = ['%site' => $url, '%error' => $e->getMessage()];
      throw new \RuntimeException($this->t('The import from %site seems to be broken because of error "%error".', $args));
    }

    return $response;
  }


}
