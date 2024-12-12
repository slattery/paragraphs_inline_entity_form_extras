<?php

namespace Drupal\paragraphs_inline_entity_form_extras\Plugin\Filter;



use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\embed\DomHelperTrait;
use Drupal\entity_embed\Exception\EntityNotFoundException;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;



/**
 * Provides a filter to display embedded entities based on data attributes.
 *
 * @Filter(
 *   id = "embedded_paragraphs_collector",
 *   title = @Translation("Embedded Paragraphs Collector"),
 *   description = @Translation("NOT FOR RENDERING!  This filter harvests embedded paragraphs and populates parent fields to prevent orphaned status"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   weight = 100,
 * )
 */
class EmbeddedParagraphsCollector extends FilterBase implements ContainerFactoryPluginInterface {

  use DomHelperTrait;

  /**
   * The number of times this formatter allows collecting the same entity.
   *
   * @var int
   */
  const RECURSIVE_COLLECT_LIMIT = 2;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;



  /**
   * An array of counters for the recursive collecting protection.
   *
   * Each counter takes into account all the relevant information about the
   * field and the referenced entity that is being collected.
   *
   * @var array
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter::$recursiveCollectDepth
   */
  protected static $recursiveCollectDepth = [];

  /**
   * Constructs a EntityEmbedFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface
   *   The file URL generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode = 'en') {
    $result = new FilterProcessResult($text);
    $paragraph_uuids = [];

    if (strpos($text, 'data-entity-type') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      foreach ($xpath->query('//drupal-entity[@data-entity-type and @data-entity-uuid]') as $domnode) {
        /** @var \DOMElement $node */
        $entity_type = $domnode->getAttribute('data-entity-type');
        $entity = NULL;
        $entity_output = '';
        if ($entity_type == 'paragraph') {
          $entity = NULL;
          try {
            // Load the entity either by UUID (preferred) or ID.
            $id = NULL;
            if ($id = $domnode->getAttribute('data-entity-uuid')) {
              $entity = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $id]);
              $entity = current($entity);
            }
            else {
              $id = $domnode->getAttribute('data-entity-id');
              $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
            }
            if (!$entity instanceof \Drupal\paragraphs\Entity\Paragraph) {
              $maybelib = $this->entityTypeManager->getDefinition($entity_type)->getSingularLabel();
              throw new EntityNotFoundException(sprintf('Unable to load wanted %s got %s entity %s.', $entity_type, $maybelib, $id));
            }
          }
          catch (EntityNotFoundException $e) {
            $this->loggerFactory->get('entity')->error('Embedded paragraphs: %error_msg.', [
              '%error_msg' => $e,
            ]);
          }

          if ($entity instanceof \Drupal\paragraphs\Entity\Paragraph) {
            // If a UUID was not used, but is available, add it to the HTML.

            // Not sure this is going to work without t
            $recursive_collect_id = $entity->uuid();
            if (isset(static::$recursiveCollectDepth[$recursive_collect_id])) {
              static::$recursiveCollectDepth[$recursive_collect_id]++;
            }
            else {
              static::$recursiveCollectDepth[$recursive_collect_id] = 1;
            }

            // Protect ourselves from recursive collecting.
            if (static::$recursiveCollectDepth[$recursive_collect_id] > static::RECURSIVE_COLLECT_LIMIT) {
              $this->loggerFactory->get('entity')->error('Recursive collecting detected when collecting embedded entity %entity_type: %entity_id. Aborting collecting.', [
                '%entity_type' => $entity->getEntityTypeId(),
                '%entity_id' => $entity->id(),
              ]);
            }
            else {
              $paragraph_uuids[] = $entity->uuid();
            }
          }//if entity
        }//paragraphs_type_check
      }//foreach
    }//strpos

    return $paragraph_uuids;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>This filter harvests data-entity-uuid properties when we find data-entity-type="paragraph" in the embed tag:</p>');
    }
    else {
      return $this->t('This machine grabs paragraphs.');
    }
  }

}
