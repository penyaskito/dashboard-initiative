<?php

namespace Drupal\dashboard_default_content;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a helper class for importing default content.
 *
 * @internal
 *   This code is only for use by the Dashboard demo: Content module.
 */
class InstallHelper implements ContainerInjectionInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Enabled languages.
   *
   * List of all enabled languages.
   *
   * @var array
   */
  protected $enabledLanguages;

  /**
   * Term ID map.
   *
   * Used to store term IDs created in the import process against
   * vocabulary and row in the source CSV files. This allows the created terms
   * to be cross referenced when creating articles and recipes.
   *
   * @var array
   */
  protected $termIdMap;

  /**
   * Media Image CSV ID map.
   *
   * Used to store media image CSV IDs created in the import process.
   * This allows the created media images to be cross referenced when creating
   * article, recipes and blocks.
   *
   * @var array
   */
  protected $mediaImageIdMap;

  /**
   * Node CSV ID map.
   *
   * Used to store node CSV IDs created in the import process. This allows the
   * created nodes to be cross referenced when creating blocks.
   *
   * @var array
   */
  protected $nodeIdMap;

  /**
   * The module's path.
   */
  protected string $module_path;

  /**
   * Constructs a new InstallHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler, StateInterface $state, FileSystemInterface $fileSystem) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
    $this->fileSystem = $fileSystem;
    $this->termIdMap = [];
    $this->mediaImageIdMap = [];
    $this->nodeIdMap = [];
    $this->enabledLanguages = array_keys(\Drupal::languageManager()->getLanguages());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('state'),
      $container->get('file_system')
    );
  }

  /**
   * Imports default contents.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function importContent() {
    $this->getModulePath()
      ->importContentFromFile('node', 'article')
      ->importContentFromFile('node', 'page');
  }

  /**
   * Set module_path variable.
   *
   * @return $this
   */
  protected function getModulePath() {
    $this->module_path = $this->moduleHandler->getModule('dashboard_default_content')->getPath();
    return $this;
  }

  /**
   * Read multilingual content.
   *
   * @param string $filename
   *   Filename to import.
   *
   * @return array
   *   An array of two items:
   *     1. All multilingual content that was read from the files.
   *     2. List of language codes that need to be imported.
   */
  protected function readMultilingualContent($filename) {
    $default_content_path = $this->module_path . "/default_content/languages/";

    // Get all enabled languages.
    $translated_languages = $this->enabledLanguages;

    // Load all the content from any CSV files that exist for enabled languages.
    foreach ($translated_languages as $language) {
      if (file_exists($default_content_path . "$language/$filename") &&
      ($handle = fopen($default_content_path . "$language/$filename", 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        $line_counter = 0;
        while (($content = fgetcsv($handle)) !== FALSE) {
          $keyed_content[$language][$line_counter] = array_combine($header, $content);
          $line_counter++;
        }
        fclose($handle);
      }
      else {
        // Language directory exists, but the file in this language was not found,
        // remove that language from list of languages to be translated.
        $key = array_search($language, $translated_languages);
        unset($translated_languages[$key]);
      }
    }
    return [$keyed_content, $translated_languages];
  }

  /**
   * Retrieves the node path of node CSV ID saved during the import process.
   *
   * @param string $langcode
   *   Current language code.
   * @param string $content_type
   *   Current content type.
   * @param string $node_csv_id
   *   The node's ID from the CSV file.
   *
   * @return string
   *   Node path, or 0 if node CSV ID could not be found.
   */
  protected function getNodePath($langcode, $content_type, $node_csv_id) {
    if (array_key_exists($langcode, $this->nodeIdMap) &&
        array_key_exists($content_type, $this->nodeIdMap[$langcode]) &&
        array_key_exists($node_csv_id, $this->nodeIdMap[$langcode][$content_type])) {
      return $this->nodeIdMap[$langcode][$content_type][$node_csv_id];
    }
    return 0;
  }

  /**
   * Saves a node CSV ID generated when saving content.
   *
   * @param string $langcode
   *   Current language code.
   * @param string $content_type
   *   Current content type.
   * @param string $node_csv_id
   *   The node's ID from the CSV file.
   * @param string $node_url
   *   Node's URL alias when saved in the Drupal database.
   */
  protected function saveNodePath($langcode, $content_type, $node_csv_id, $node_url) {
    $this->nodeIdMap[$langcode][$content_type][$node_csv_id] = $node_url;
  }

  /**
   * Process pages data into page node structure.
   *
   * @param array $data
   *   Data of line that was read from the file.
   * @param string $langcode
   *   Current language code.
   *
   * @return array
   *   Data structured as a page node.
   */
  protected function processPage(array $data, $langcode) {
    // Prepare content.
    $values = [
      'type' => 'page',
      'title' => $data['title'],
      'status' => $data['status'],
      'langcode' => 'en',
    ];
    // Fields mapping starts.
    // Set body field.
    if (!empty($data['body'])) {
      $values['body'] = [['value' => $data['body'], 'format' => 'basic_html']];
    }
    // Set node alias if exists.
    if (!empty($data['slug'])) {
      $values['path'] = [['alias' => '/' . $data['slug']]];
    }
    // Save node alias
    $this->saveNodePath($langcode, 'page', $data['id'], $data['slug']);

    // Set article author.
    if (!empty($data['author'])) {
      $values['uid'] = $this->getUser($data['author']);
    }
    return $values;
  }

  /**
   * Process article data into article node structure.
   *
   * @param array $data
   *   Data of line that was read from the file.
   * @param string $langcode
   *   Current language code.
   *
   * @return array
   *   Data structured as an article node.
   */
  protected function processArticle(array $data, $langcode) {
    // Prepare content.
    $values = [
      'type' => 'article',
      'title' => $data['title'],
      'status' => $data['status'],
      'langcode' => 'en',
    ];
    // Fields mapping starts.
    // Set body field.
    if (!empty($data['body'])) {
      $body_path = $this->module_path . '/default_content/languages/' . $langcode . '/article_body/' . $data['body'];
      $body = file_get_contents($body_path);
      if ($body !== FALSE) {
        $values['body'] = [['value' => $body, 'format' => 'basic_html']];
      }
    }

    // Set node alias if exists.
    if (!empty($data['slug'])) {
      $values['path'] = [['alias' => '/' . $data['slug']]];
    }
    // Save node alias
    $this->saveNodePath($langcode, 'article', $data['id'], $data['slug']);
    // Set article author.
    if (!empty($data['author'])) {
      $values['uid'] = $this->getUser($data['author']);
    }
    return $values;
  }

  /**
   * Process content into a structure that can be saved into Drupal.
   *
   * @param string $bundle_machine_name
   *   Current bundle's machine name.
   * @param array $content
   *   Current content array that needs to be structured.
   * @param string $langcode
   *   Current language code.
   *
   * @return array
   *   Structured content.
   */
  protected function processContent($bundle_machine_name, array $content, $langcode) {
    switch ($bundle_machine_name) {
      case 'article':
        $structured_content = $this->processArticle($content, $langcode);
        break;

      case 'page':
        $structured_content = $this->processPage($content, $langcode);
        break;

      default:
        break;
    }
    return $structured_content;
  }

  /**
   * Imports content.
   *
   * @param string $entity_type
   *   Entity type to be imported
   * @param string $bundle_machine_name
   *   Bundle machine name to be imported.
   *
   * @return $this
   */
  protected function importContentFromFile($entity_type, $bundle_machine_name) {
    $filename = $entity_type . '/' . $bundle_machine_name . '.csv';

    // Read all multilingual content from the file.
    [$all_content, $translated_languages] = $this->readMultilingualContent($filename);

    // English is no longer needed in the list of languages to translate.
    $key = array_search('en', $translated_languages);
    unset($translated_languages[$key]);

    // Start the loop with English (default) recipes.
    foreach ($all_content['en'] as $current_content) {
      // Process data into its relevant structure.
      $structured_content = $this->processContent($bundle_machine_name, $current_content, 'en');

      // Save Entity.
      $entity = $this->entityTypeManager->getStorage($entity_type)->create($structured_content);
      $entity->save();
      $this->storeCreatedContentUuids([$entity->uuid() => $entity_type]);

      // Go through all the languages that have translations.
      foreach ($translated_languages as $translated_language) {

        // Find the translated content ID that corresponds to original content.
        $translation_id = array_search($current_content['id'], array_column($all_content[$translated_language], 'id'));

        // Check if translation was found.
        if ($translation_id !== FALSE) {

          // Process that translation.
          $translated_entity = $all_content[$translated_language][$translation_id];
          $structured_content = $this->processContent($bundle_machine_name, $translated_entity, $translated_language);

          // Save entity's translation.
          $entity->addTranslation(
            $translated_language,
            $structured_content
          );
          $entity->save();
        }
      }
    }
    return $this;
  }

  /**
   * Deletes any content imported by this module.
   *
   * @return $this
   */
  public function deleteImportedContent() {
    $uuids = $this->state->get('demo_dashboard_content_uuids', []);
    $by_entity_type = array_reduce(array_keys($uuids), function ($carry, $uuid) use ($uuids) {
      $entity_type_id = $uuids[$uuid];
      $carry[$entity_type_id][] = $uuid;
      return $carry;
    }, []);
    foreach ($by_entity_type as $entity_type_id => $entity_uuids) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entities = $storage->loadByProperties(['uuid' => $entity_uuids]);
      $storage->delete($entities);
    }
    return $this;
  }

  /**
   * Looks up a user by name, if it is missing the user is created.
   *
   * @param string $name
   *   Username.
   *
   * @return int
   *   User ID.
   */
  protected function getUser($name) {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadByProperties(['name' => $name]);
    if (empty($users)) {
      // Creating user without any password.
      $user = $user_storage->create([
        'name' => $name,
        'status' => 1,
        'roles' => ['author'],
        'mail' => mb_strtolower(str_replace(' ', '.', $name)) . '@example.com',
      ]);
      $user->enforceIsNew();
      $user->save();
      $this->storeCreatedContentUuids([$user->uuid() => 'user']);
      return $user->id();
    }
    $user = reset($users);
    return $user->id();
  }

  /**
   * Stores record of content entities created by this import.
   *
   * @param array $uuids
   *   Array of UUIDs where the key is the UUID and the value is the entity
   *   type.
   */
  protected function storeCreatedContentUuids(array $uuids) {
    $uuids = $this->state->get('demo_dashboard_content_uuids', []) + $uuids;
    $this->state->set('demo_dashboard_content_uuids', $uuids);
  }

}
