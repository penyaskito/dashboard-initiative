<?php
// @codingStandardsIgnoreStart

/**
 * Base tasks for setting up a module to test within a full Drupal environment.
 *
 * @class RoboFile
 * @codeCoverageIgnore
 */
class RoboFile extends \Robo\Tasks {

  /**
   * Command to build the environment
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobBuild() {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->copyConfigurationFiles());
    $collection->addTaskList($this->runComposer());
    return $collection->run();
  }

  /**
   * Command to run unit tests.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobUnitTests() {
    $collection = $this->collectionBuilder();
    // $collection->addTask($this->installDrupal());
    $collection->addTask($this->drushStatus());

    $collection->addTaskList($this->runUnitTests());
    return $collection->run();
  }

  /**
   * Command to generate a coverage report.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobCoverageReport() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->installDrupal());
    $collection->addTaskList($this->runCoverageReport());
    return $collection->run();
  }

  /**
   * Command to check drush status.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobDrushStatus() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->drushStatus());
    return $collection->run();
  }

  /**
   * Command to check for Drupal's Coding Standards.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobCodingStandards() {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runCodeSniffer());
    return $collection->run();
  }

  /**
   * Command to run behat tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobBehatTests()
  {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runBehatTests());
    return $collection->run();
  }

  /**
   * Command to run Cypress tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobCypressTests()
  {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runCypressTests());
    return $collection->run();
  }

  /**
   * Serve Drupal.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobServeDrupal()
  {
    $collection = $this->collectionBuilder();
    // $collection->addTaskList($this->importDatabase());
    // $collection->addTaskList($this->runUpdateDatabase());
    $collection->addTaskList($this->runServeDrupal());
    $collection->addTask($this->installDrupal());
    $collection->addTask($this->drushStatus());

    return $collection->run();
  }

  /**
   * Updates the database.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runUpdateDatabase() {
    $tasks = [];
    $tasks[] = $this->drush()
      ->args('updatedb')
      ->option('yes')
      ->option('verbose');
    $tasks[] = $this->drush()
      ->args('config:import')
      ->option('yes')
      ->option('verbose');
    $tasks[] = $this->drush()->args('cache:rebuild')->option('verbose');
    $tasks[] = $this->drush()->args('st');
    return $tasks;
  }

  /**
   * Run unit tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runUnitTests() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.github/config/phpunit.xml', 'web/core/phpunit.xml', $force);
    $tasks[] = $this->taskExecStack()
      ->dir('web')
      ->exec('../vendor/bin/phpunit -c core --debug --coverage-clover ../build/logs/clover.xml --verbose modules/contrib/dashboard');
    return $tasks;
  }

  /**
   * Generates a code coverage report.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCoverageReport() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.github/config/phpunit.xml', 'web/core/phpunit.xml', $force);
    $tasks[] = $this->taskExecStack()
      ->dir('web')
      ->exec('../vendor/bin/phpunit -c core --debug --verbose --coverage-html ../coverage modules/custom');
    return $tasks;
  }

  /**
   * Sets up and runs code sniffer.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCodeSniffer() {
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->mkdir('artifacts/phpcs');
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpcs --standard=Drupal --report=junit --report-junit=artifacts/phpcs/phpcs.xml web/modules/contrib/dashboard')
      ->exec('vendor/bin/phpcs --standard=DrupalPractice --report=junit --report-junit=artifacts/phpcs/phpcs.xml web/modules/contrib/dashboard');
    return $tasks;
  }

  /**
   * Serves Drupal.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  function runServeDrupal()
  {
    $tasks = [];
    $tasks[] = $this->taskExec('chown -R www-data:www-data ' . getenv('GITHUB_WORKSPACE'));
    // There is an existing installation from the docker container. For now let's just delete that
    // for avoiding confusion.
    $tasks[] = $this->taskExec('rm -rf /var/www/html');
    $tasks[] = $this->taskExec('ln -sf ' . getenv('GITHUB_WORKSPACE') . '/web /var/www/html');
    $tasks[] = $this->taskExec('chown -r www-data:www-data ' . getenv('GITHUB_WORKSPACE'));
    $tasks[] = $this->taskExec('echo "\nServerName localhost" >> /etc/apache2/apache2.conf');
    $tasks[] = $this->taskExec('service apache2 start');
    return $tasks;
  }

  /**
   * Runs Behat tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runBehatTests()
  {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.github/config/behat.yml', 'tests/behat.yml', $force);
    $tasks[] = $this->taskExec('sleep 30s');
    $tasks[] = $this->taskExec('vendor/bin/behat --verbose -c tests/behat.yml');
    return $tasks;
  }

  /**
   * Runs Cypress tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCypressTests()
  {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.cypress/cypress.json', 'cypress.json', $force)
      ->copy('.cypress/package.json', 'package.json', $force);
    $tasks[] = $this->taskExec('sleep 30s');
    $tasks[] = $this->taskExec('npm install cypress@9 --save-dev');
    $tasks[] = $this->taskExec('$(npm bin)/cypress run');
    return $tasks;
  }

  /**
   * Return drush with default arguments.
   *
   * @return \Robo\Task\Base\Exec
   *   A drush exec command.
   */
  protected function drush() {
    return $this->taskExec('vendor/bin/drush');
  }

  /**
   * Copies configuration files.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function copyConfigurationFiles() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.github/config/settings.local.php', 'web/sites/default/settings.local.php', $force)
      ->copy('.github/config/.env', '.env', $force);
    return $tasks;
  }

  /**
   * Runs composer commands.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runComposer() {
    $tasks = [];
    $tasks[] = $this->taskComposerValidate()->noCheckPublish();
    $tasks[] = $this->taskComposerInstall()
      ->noInteraction()
      ->envVars(['COMPOSER_ALLOW_SUPERUSER' => 1, 'COMPOSER_DISCARD_CHANGES' => 1] + getenv())
      ->optimizeAutoloader();
    return $tasks;
  }

  /**
   * Install Drupal.
   *
   * @return \Robo\Task\Base\Exec
   *   A task to install Drupal.
   */
  protected function installDrupal()
  {
    $task = $this->drush()
      ->args('site-install')
      ->option('verbose')
      ->option('yes');
    return $task;
  }

  /**
   * Drush status.
   *
   * @return \Robo\Task\Base\Exec
   *   A task to check drush status.
   */
  protected function drushStatus()
  {
    $task = $this->drush()
      ->args('status');
    return $task;
  }

  /**
   * Imports and updates the database.
   *
   * This task assumes that there is an environment variable $DB_DUMP_URL
   * that contains a URL to a database dump. Ideally, you should set up drush
   * site aliases and then replace this task by a drush sql-sync one. See the
   * README at lullabot/drupal9ci for further details.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function importDatabase()
  {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskExec('mysql -u root -proot -h mariadb -e "create database drupal"');
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.github/config/settings.local.php', 'web/sites/default/settings.local.php', $force);
    $tasks[] = $this->taskExec('wget -O dump.sql "' . getenv('DB_DUMP_URL') . '"');
    $tasks[] = $this->drush()->rawArg('sql-cli < dump.sql');
    return $tasks;
  }

}
