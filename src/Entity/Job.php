<?php

/*
 * @file
 * Contains Drupal\tmgmt\Plugin\Core\Entity\Job.
 */

namespace Drupal\tmgmt\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Entity class for the tmgmt_job entity.
 *
 * @ContentEntityType(
 *   id = "tmgmt_job",
 *   label = @Translation("Translation Job"),
 *   module = "tmgmt",
 *   controllers = {
 *     "access" = "Drupal\tmgmt\Entity\Controller\JobAccessController",
 *     "form" = {
 *       "edit" = "Drupal\tmgmt\Form\JobForm",
 *       "abort" = "Drupal\tmgmt\Form\JobAbortForm",
 *       "resubmit" = "Drupal\tmgmt\Form\JobResubmitForm",
 *     }
 *   },
 *   base_table = "tmgmt_job",
 *   entity_keys = {
 *     "id" = "tjid",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "tmgmt.job_entity",
 *     "abort-form" = "tmgmt.job_entity_abort",
 *     "resubmit-form" = "tmgmt.job_entity_resubmi",
 *   }
 * )
 *
 * @ingroup tmgmt_job
 */
class Job extends ContentEntityBase implements EntityOwnerInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['tjid'] = FieldDefinition::create('integer')
      ->setLabel(t('Node ID'))
      ->setDescription(t('The Job ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = FieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The node UUID.'))
      ->setReadOnly(TRUE);

    $fields['source_language'] = FieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The source language.'))
      ->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED);

    $fields['target_language'] = FieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The target language.'))
      ->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED);

    $fields['label'] = FieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The label of this job.'))
      ->setDefaultValue('')
      ->setSettings(array(
        'max_length' => 255,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = FieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that is the job owner.'))
      ->setSettings(array(
        'target_type' => 'user',
      ))
      ->setDefaultValue(0);

    $fields['translator'] = FieldDefinition::create('entity_reference')
      ->setLabel(t('Translator'))
      ->setDescription(t('The selected translator'))
      ->setSettings(array(
        'target_type' => 'tmgmt_translator',
      ));

    $fields['settings'] = FieldDefinition::create('map')
      ->setLabel(t('Settings'))
      ->setDescription(t('Translator specific configuration and context information for this job.'))
      ->setDefaultValue(array());

    $fields['reference'] = FieldDefinition::create('string')
      ->setLabel(t('Reference'))
      ->setDescription(t('Remote reference of this job'))
      ->setDefaultValue('')
      ->setSettings(array(
        'max_length' => 255,
      ));
    $fields['state'] = FieldDefinition::create('integer')
      ->setLabel(t('Job state'))
      ->setDescription(t('The job state.'))
      ->setDefaultValue(TMGMT_JOB_STATE_UNPROCESSED);

    $fields['created'] = FieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the job was created.'));

    $fields['changed'] = FieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the job was last edited.'));
    return $fields;
  }

  /**
   * Returns the target language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The target language.
   */
  function getTargetLanguage() {
    return $this->get('target_language')->language;
  }

  /**
   * Returns the target language code.
   *
   * @return string
   *   The target language code
   */
  function getTargetLangcode() {
    return $this->get('target_language')->value;
  }

  /**
   * Returns the source language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The source language.
   */
  function getSourceLanguage() {
    return $this->get('source_language')->language;
  }

  /**
   * Returns the source language code.
   *
   * @return string
   *   The source language code
   */
  function getSourceLangcode() {
    return $this->get('source_language')->value;
  }

  /**
   * Returns the created time.
   *
   * @return int
   *   The time when the job was last changed.
   */
  function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * Returns the created time.
   *
   * @return int
   *   The time when the job was last changed.
   */
  function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Returns the reference.
   *
   * @return string
   *   The reference set by the translator.
   */
  function getReference() {
    return $this->get('reference')->value;
  }

  /**
   * Clones job as unprocessed.
   */
  public function cloneAsUnprocessed() {
    $clone = $this->createDuplicate();
    $clone->uid->value = 0;
    $clone->reference->value = '';
    $clone->created->value = REQUEST_TIME;
    $clone->state->value = TMGMT_JOB_STATE_UNPROCESSED;
    return $clone;
  }


  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage_controller, array $entities) {
    // Since we are deleting one or multiple jobs here we also need to delete
    // the attached job items and messages.
    $tjiids = \Drupal::entityQuery('tmgmt_job_item')
      ->condition('tjid', array_keys($entities))
      ->execute();
    if (!empty($tjiids)) {
      entity_delete_multiple('tmgmt_job_item', $tjiids);
    }

    $mids = \Drupal::entityQuery('tmgmt_message')
      ->condition('tjid', array_keys($entities))
      ->execute();
    if (!empty($mids)) {
      entity_delete_multiple('tmgmt_message', $mids);
    }

    $trids = \Drupal::entityQuery('tmgmt_remote')
      ->condition('tjid', array_keys($entities))
      ->execute();
    if (!empty($trids)) {
      entity_delete_multiple('tmgmt_remote', $trids);
    }
  }

  /**
   * {inheritdoc}
   */
  public function label($langcode = NULL) {
    // In some cases we might have a user-defined label.
    if (!empty($this->get('label')->value)) {
      return $this->get('label')->value;
    }

    $items = $this->getItems();
    $count = count($items);
    if ($count > 0) {
      $source_label = reset($items)->getSourceLabel();
      $t_args = array('!title' => $source_label, '!more' => $count - 1);
      $label = format_plural($count, '!title', '!title and !more more', $t_args);

      // If the label length exceeds maximum allowed then cut off exceeding
      // characters from the title and use it to recreate the label.
      if (strlen($label) > TMGMT_JOB_LABEL_MAX_LENGTH) {
        $max_length = strlen($source_label) - (strlen($label) - TMGMT_JOB_LABEL_MAX_LENGTH);
        $source_label = truncate_utf8($source_label, $max_length, TRUE);
        $t_args['!title'] = $source_label;
        $label = format_plural($count, '!title', '!title and !more more', $t_args);
      }
    }
    else {
      $source = $this->getSourceLanguage() ? $this->getSourceLanguage()->getName() : '?';
      $target = $this->getTargetLanguage() ? $this->getTargetLanguage()->getName() : '?';
      $label = t('From !source to !target', array('!source' => $source, '!target' => $target));
    }

    return $label;
  }

  /**
   * Adds an item to the translation job.
   *
   * @param $plugin
   *   The plugin name.
   * @param $item_type
   *   The source item type.
   * @param $item_id
   *   The source item id.
   *
   * @return JobItem
   *   The job item that was added to the job or FALSE if it couldn't be saved.
   * @throws \Drupal\tmgmt\TMGMTException
   *   On zero item word count.
   */
  public function addItem($plugin, $item_type, $item_id) {

    $transaction = db_transaction();
    $is_new = FALSE;

    if ($this->isNew()) {
      $this->save();
      $is_new = TRUE;
    }

    $item = tmgmt_job_item_create($plugin, $item_type, $item_id, array('tjid' => $this->id()));
    $item->save();

    if ($item->getWordCount() == 0) {
      $transaction->rollback();

      // In case we got word count 0 for the first job item, NULL tjid so that
      // if there is another addItem() call the rolled back job object will get
      // persisted.
      if ($is_new) {
        $this->tjid = NULL;
      }

      throw new TMGMTException('Job item @label (@type) has no translatable content.',
        array('@label' => $item->label(), '@type' => $item->getSourceType()));
    }

    return $item;
  }

  /**
   * Add a givenJobItem to this job.
   *
   * @param \Drupal\tmgmt\Entity\JobItem $item
   *   The job item to add.
   */
  function addExistingItem(JobItem $item) {
    $item->tjid = $this->id();
    $item->save();
  }

  /**
   * Add a log message for this job.
   *
   * @param $message
   *   The message to store in the log. Keep $message translatable by not
   *   concatenating dynamic values into it! Variables in the message should be
   *   added by using placeholder strings alongside the variables argument to
   *   declare the value of the placeholders. See t() for documentation on how
   *   $message and $variables interact.
   * @param $variables
   *   (Optional) An array of variables to replace in the message on display.
   * @param $type
   *   (Optional) The type of the message. Can be one of 'status', 'error',
   *   'warning' or 'debug'. Messages of the type 'debug' will not get printed
   *   to the screen.
   */
  public function addMessage($message, $variables = array(), $type = 'status') {
    // Save the job if it hasn't yet been saved.
    if (!$this->isNew() || $this->save()) {
      $message = tmgmt_message_create($message, $variables, array('tjid' => $this->id(), 'type' => $type));
      if ($message->save()) {
        return $message;
      }
    }
    return FALSE;
  }

  /**
   * Returns all job items attached to this job.
   *
   * @param array $conditions
   *   Additional conditions.
   *
   * @return \Drupal\tmgmt\Entity\JobItem[]
   *   An array of translation job items.
   */
  public function getItems($conditions = array()) {
    $query = \Drupal::entityQuery('tmgmt_job_item')
      ->condition('tjid', $this->id());
    foreach ($conditions as $key => $condition) {
      if (is_array($condition)) {
        $operator = isset($condition['operator']) ? $condition['operator'] : '=';
        $query->condition($key, $condition['value'], $operator);
      }
      else {
        $query->condition($key, $condition);
      }
    }
    $results = $query->execute();
    if (!empty($results)) {
      return entity_load_multiple('tmgmt_job_item', $results);
    }
    return array();
  }

  /**
   * Returns all job messages attached to this job.
   *
   * @param array $conditions
   *   Additional conditions.
   *
   * @return \Drupal\tmgmt\Entity\Message[]
   *   An array of translation job messages.
   */
  public function getMessages($conditions = array()) {
    $query = \Drupal::entityQuery('tmgmt_message')
      ->condition('tjid', $this->id());
    foreach ($conditions as $key => $condition) {
      if (is_array($condition)) {
        $operator = isset($condition['operator']) ? $condition['operator'] : '=';
        $query->condition($key, $condition['value'], $operator);
      }
      else {
        $query->condition($key, $condition);
      }
    }
    $results = $query->execute();
    if (!empty($results)) {
      return entity_load_multiple('tmgmt_message', $results);
    }
    return array();
  }

  /**
   * Returns all job messages attached to this job with timestamp newer than
   * $time.
   *
   * @param int $time
   *   (Optional) Messages need to have a newer timestamp than $time. Defaults
   *   to REQUEST_TIME.
   *
   * @return \Drupal\tmgmt\Entity\Message[]
   *   An array of translation job messages.
   */
  public function getMessagesSince($time = NULL) {
    $time = isset($time) ? $time : REQUEST_TIME;
    $conditions = array('created' => array('value' => $time, 'operator' => '>='));
    return $this->getMessages($conditions);
  }

  /**
   * Retrieves a setting value from the job settings. Pulls the default values
   * (if defined) from the plugin controller.
   *
   * @param string $name
   *   The name of the setting.
   *
   * @return
   *   The setting value or $default if the setting value is not set. Returns
   *   NULL if the setting does not exist at all.
   */
  public function getSetting($name) {
    if (isset($this->settings->$name)) {
      return $this->settings->$name;
    }
    // The translator might provide default settings.
    if ($translator = $this->getTranslator()) {
      if (($setting = $translator->getSetting($name)) !== NULL) {
        return $setting;
      }
    }
    if ($controller = $this->getTranslatorController()) {
      $defaults = $controller->defaultSettings();
      if (isset($defaults[$name])) {
        return $defaults[$name];
      }
    }
  }

  /**
   * Returns the translator ID for this job.
   *
   * @return int
   *   The translator ID or NULL if there is none.
   */
  public function getTranslatorId() {
    return $this->get('translator')->target_id;
  }

  /**
   * Returns the translator for this job.
   *
   * @return Translator
   *   The translator entity or NULL if there is none.
   */
  public function getTranslator() {
    if ($this->translator->target_id) {
      return $this->translator->entity;
    }
    return FALSE;
  }

  /**
   * Returns the state of the job. Can be one of the job state constants.
   *
   * @return integer
   *   The state of the job or NULL if it hasn't been set yet.
   */
  public function getState() {
    return $this->get('state')->value;
  }

  /**
   * Updates the state of the job.
   *
   * @param int $state
   *   The new state of the job. Has to be one of the job state constants.
   * @param string $message
   *   (optional) The log message to be saved along with the state change.
   * @param array $variables
   *   (optional) An array of variables to replace in the message on display.
   * @param string $type
   *   (optional) The message type.
   *
   * @return int
   *   The updated state of the job if it could be set.
   *
   * @see Job::addMessage()
   */
  public function setState($state, $message = NULL, $variables = array(), $type = 'debug') {
    // Return TRUE if the state could be set. Return FALSE otherwise.
    if (array_key_exists($state, tmgmt_job_states())) {
      $this->state = $state;
      $this->save();
      // If a message is attached to this state change add it now.
      if (!empty($message)) {
        $this->addMessage($message, $variables, $type);
      }
    }
    return $this->getState();
  }

  /**
   * Checks whether the passed value matches the current state.
   *
   * @param int $state
   *   The value to check the current state against.
   *
   * @return bool
   *   TRUE if the passed state matches the current state, FALSE otherwise.
   */
  public function isState($state) {
    return $this->getState() == $state;
  }

  /**
   * Checks whether the user described by $account is the author of this job.
   *
   * @param AccountInterface $account
   *   (Optional) A user object. Defaults to the currently logged in user.
   *
   * @return bool
   *   TRUE if the passed account is the job owner.
   */
  public function isAuthor(AccountInterface $account = NULL) {
    $account = isset($account) ? $account : \Drupal::currentUser();
    return $this->getOwnerId() == $account->id();
  }

  /**
   * Returns whether the state of this job is 'unprocessed'.
   *
   * @return bool
   *   TRUE if the state is 'unprocessed', FALSE otherwise.
   */
  public function isUnprocessed() {
    return $this->isState(TMGMT_JOB_STATE_UNPROCESSED);
  }

  /**
   * Returns whether the state of this job is 'aborted'.
   *
   * @return bool
   *   TRUE if the state is 'aborted', FALSE otherwise.
   */
  public function isAborted() {
    return $this->isState(TMGMT_JOB_STATE_ABORTED);
  }

  /**
   * Returns whether the state of this job is 'active'.
   *
   * @return bool
   *   TRUE if the state is 'active', FALSE otherwise.
   */
  public function isActive() {
    return $this->isState(TMGMT_JOB_STATE_ACTIVE);
  }

  /**
   * Returns whether the state of this job is 'rejected'.
   *
   * @return bool
   *   TRUE if the state is 'rejected', FALSE otherwise.
   */
  public function isRejected() {
    return $this->isState(TMGMT_JOB_STATE_REJECTED);
  }

  /**
   * Returns whether the state of this jon is 'finished'.
   *
   * @return bool
   *   TRUE if the state is 'finished', FALSE otherwise.
   */
  public function isFinished() {
    return $this->isState(TMGMT_JOB_STATE_FINISHED);
  }

  /**
   * Checks whether a job is translatable.
   *
   * @return boolean
   *   TRUE if the job can be translated, FALSE otherwise.
   */
  public function canRequestTranslation() {
    if ($translator = $this->getTranslator()) {
      if ($translator->canTranslate($this)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether a job is abortable.
   *
   * @return bool
   *   TRUE if the job can be aborted, FALSE otherwise.
   */
  public function isAbortable() {
    // Only non-submitted translation jobs can be aborted.
    return $this->isActive();
  }

  /**
   * Checks whether a job is submittable.
   *
   * @return bool
   *   TRUE if the job can be submitted, FALSE otherwise.
   */
  public function isSubmittable() {
    return $this->isUnprocessed() || $this->isRejected();
  }

  /**
   * Checks whether a job is deletable.
   *
   * @return bool
   *   TRUE if the job can be deleted, FALSE otherwise.
   */
  public function isDeletable() {
    return !$this->isActive();
  }

  /**
   * Set the state of the job to 'submitted'.
   *
   * @param string $message
   *   (optional) The log message to be saved along with the state change.
   * @param array $variables
   *   (optional) An array of variables to replace in the message on display.
   * @param string $type
   *   (optional) The message type.
   *
   * @return \Drupal\tmgmt\Entity\Job
   *   The job entity.
   *
   * @see Job::addMessage()
   */
  public function submitted($message = NULL, $variables = array(), $type = 'status') {
    if (!isset($message)) {
      $message = 'The translation job has been submitted.';
    }
    $this->setState(TMGMT_JOB_STATE_ACTIVE, $message, $variables, $type);
  }

  /**
   * Set the state of the job to 'finished'.
   *
   * @param $message
   *   The log message to be saved along with the state change.
   * @param $variables
   *   (Optional) An array of variables to replace in the message on display.
   *
   * @return \Drupal\tmgmt\Entity\Job
   *   The job entity.
   *
   * @see Job::addMessage()
   */
  public function finished($message = NULL, $variables = array(), $type = 'status') {
    if (!isset($message)) {
      $message = 'The translation job has been finished.';
    }
    return $this->setState(TMGMT_JOB_STATE_FINISHED, $message, $variables, $type);
  }

  /**
   * Sets the state of the job to 'aborted'.
   *
   * @param $message
   *   The log message to be saved along with the state change.
   * @param $variables
   *   (Optional) An array of variables to replace in the message on display.
   *
   * Use Job::abortTranslation() to abort a translation.
   *
   * @return \Drupal\tmgmt\Entity\Job
   *   The job entity.
   *
   * @see Job::addMessage()
   */
  public function aborted($message = NULL, $variables = array(), $type = 'status') {
    if (!isset($message)) {
      $message = 'The translation job has been aborted.';
    }
    /** @var JobItem $item */
    foreach ($this->getItems() as $item) {
      $item->setState(TMGMT_JOB_ITEM_STATE_ABORTED);
    }
    return $this->setState(TMGMT_JOB_STATE_ABORTED, $message, $variables, $type);
  }

  /**
   * Sets the state of the job to 'rejected'.
   *
   * @param $message
   *   The log message to be saved along with the state change.
   * @param $variables
   *   (Optional) An array of variables to replace in the message on display.
   *
   * @return \Drupal\tmgmt\Entity\Job
   *   The job entity.
   *
   * @see Job::addMessage()
   */
  public function rejected($message = NULL, $variables = array(), $type = 'error') {
    if (!isset($message)) {
      $message = 'The translation job has been rejected by the translation provider.';
    }
    return $this->setState(TMGMT_JOB_STATE_REJECTED, $message, $variables, $type);
  }

  /**
   * Request the translation of a job from the translator.
   *
   * @return integer
   *   The updated job status.
   */
  public function requestTranslation() {
    if (!$this->canRequestTranslation() || !$controller = $this->getTranslatorController()) {
      return FALSE;
    }
    // We don't know if the translator plugin already processed our
    // translation request after this point. That means that the plugin has to
    // set the 'submitted', 'needs review', etc. states on its own.
    $controller->requestTranslation($this);
  }

  /**
   * Attempts to abort the translation job. Already accepted jobs can not be
   * aborted, submitted jobs only if supported by the translator plugin.
   * Always use this method if you want to abort a translation job.
   *
   * @return boolean
   *   TRUE if the translation job was aborted, FALSE otherwise.
   */
  public function abortTranslation() {
    if (!$this->isAbortable() || !$controller = $this->getTranslatorController()) {
      return FALSE;
    }
    // We don't know if the translator plugin was able to abort the translation
    // job after this point. That means that the plugin has to set the
    // 'aborted' state on its own.
    if (method_exists($controller, 'cancelTranslation')) {
      // We keep the compatibility with previous API method cancelTranslation().
      // This compatibility check will be removed in 8.x-1.x.
      return $controller->cancelTranslation($this);
    }
    return $controller->abortTranslation($this);
  }

  /**
   * Returns the translator plugin controller of the translator of this job.
   *
   * @return \Drupal\tmgmt\TranslatorPluginInterface
   *   The controller of the translator plugin.
   */
  public function getTranslatorController() {
    if ($translator = $this->getTranslator($this)) {
      return $translator->getController();
    }
    return FALSE;
  }

  /**
   * Returns the source data of all job items.
   *
   * @param $key
   *   If present, only the subarray identified by key is returned.
   * @param $index
   *   Optional index of an attribute below $key.
   * @return array
   *   A nested array with the source data where the most upper key is the job
   *   item id.
   */
  public function getData(array $key = array(), $index = NULL) {
    $data = array();
    if (!empty($key)) {
      $tjiid = array_shift($key);
      $item = entity_load('tmgmt_job_item', $tjiid);
      if ($item) {
        $data[$tjiid] = $item->getData($key, $index);
        // If not set, use the job item label as the data label.
        if (!isset($data[$tjiid]['#label'])) {
          $data[$tjiid]['#label'] = $item->getSourceLabel();
        }
      }
    }
    else {
      foreach ($this->getItems() as $tjiid => $item) {
        $data[$tjiid] = $item->getData();
        // If not set, use the job item label as the data label.
        if (!isset($data[$tjiid]['#label'])) {
          $data[$tjiid]['#label'] = $item->getSourceLabel();
        }
      }
    }
    return $data;
  }

  /**
   * Sums up all pending counts of this jobs job items.
   *
   * @return
   *   The sum of all pending counts
   */
  public function getCountPending() {
    return tmgmt_job_statistic($this, 'count_pending');
  }

  /**
   * Sums up all translated counts of this jobs job items.
   *
   * @return
   *   The sum of all translated counts
   */
  public function getCountTranslated() {
    return tmgmt_job_statistic($this, 'count_translated');
  }

  /**
   * Sums up all accepted counts of this jobs job items.
   *
   * @return
   *   The sum of all accepted data items.
   */
  public function getCountAccepted() {
    return tmgmt_job_statistic($this, 'count_accepted');
  }

  /**
   * Sums up all accepted counts of this jobs job items.
   *
   * @return
   *   The sum of all accepted data items.
   */
  public function getCountReviewed() {
    return tmgmt_job_statistic($this, 'count_reviewed');
  }

  /**
   * Sums up all word counts of this jobs job items.
   *
   * @return
   *   The total word count of this job.
   */
  public function getWordCount() {
    return tmgmt_job_statistic($this, 'word_count');
  }

  /**
   * Store translated data back into the items.
   *
   * @param $data
   *   Partially or complete translated data, the most upper key needs to be
   *   the translation job item id.
   * @param $key
   *   (Optional) Either a flattened key (a 'key1][key2][key3' string) or a nested
   *   one, e.g. array('key1', 'key2', 'key2'). Defaults to an empty array which
   *   means that it will replace the whole translated data array. The most
   *   upper key entry needs to be the job id (tjiid).
   */
  public function addTranslatedData($data, $key = NULL) {
    $key = tmgmt_ensure_keys_array($key);
    $items = $this->getItems();
    // If there is a key, get the specific item and forward the call.
    if (!empty($key)) {
      $item_id = array_shift($key);
      if (isset($items[$item_id])) {
        $items[$item_id]->addTranslatedData($data, $key);
      }
    }
    else {
      foreach ($data as $key => $value) {
        if (isset($items[$key])) {
          $items[$key]->addTranslatedData($value);
        }
      }
    }
  }

  /**
   * Propagates the returned job item translations to the sources.
   *
   * @return boolean
   *   TRUE if we were able to propagate the translated data, FALSE otherwise.
   */
  public function acceptTranslation() {
    foreach ($this->getItems() as $item) {
      $item->acceptTranslation();
    }
  }

  /**
   * Gets remote mappings for current job.
   *
   * @return array
   *   List of TMGMTRemote entities.
   */
  public function getRemoteMappings() {
    $trids = \Drupal::entityQuery('tmgmt_remote')
      ->condition('tjid', $this->id())
      ->execute();

    if (!empty($trids)) {
      return entity_load_multiple('tmgmt_remote', $trids);
    }

    return array();
  }

  /**
   * Invoke the hook 'hook_tmgmt_source_suggestions' to get all suggestions.
   *
   * @param array $conditions
   *   Conditions to pass only some and not all items to the hook.
   *
   * @return array
   *   An array with all additional translation suggestions.
   *   - job_item: AJobItem instance.
   *   - referenced: A string which indicates where this suggestion comes from.
   *   - from_job: The mainJob-ID which suggests this translation.
   */
  public function getSuggestions(array $conditions = array()) {
    $suggestions = module_invoke_all('tmgmt_source_suggestions', $this->getItems($conditions), $this);

    // EachJob needs a job id to be able to count the words, because the
    // source-language is stored in the job and not the item.
    foreach ($suggestions as &$suggestion) {
      $jobItem = $suggestion['job_item'];
      $jobItem->tjid = $this->id();
      $jobItem->recalculateStatistics();
    }
    return $suggestions;
  }

  /**
   * Removes all suggestions from the given list which should not be processed.
   *
   * This function removes all suggestions from the given list which are already
   * assigned to a translation job or which should not be processed because
   * there are no words, no translation is needed, ...
   *
   * @param array &$suggestions
   *   Associative array of translation suggestions. It must contain at least:
   *   - tmgmt_job: An instance of aJobItem.
   */
  public function cleanSuggestionsList(array &$suggestions) {
    foreach ($suggestions as $k => $suggestion) {
      if (is_array($suggestion) && isset($suggestion['job_item']) && ($suggestion['job_item'] instanceof JobItem)) {
        $jobItem = $suggestion['job_item'];

        // Items with no words to translate should not be presented.
        if ($jobItem->getWordCount() <= 0) {
          unset($suggestions[$k]);
          continue;
        }

        // Check if there already exists a translation job for this item in the
        // current language.
        $items = tmgmt_job_item_load_all_latest($jobItem->getPlugin(), $jobItem->getItemType(), $jobItem->getItemId(), $this->getSourceLangcode());
        if ($items && isset($items[$this->getTargetLangcode()])) {
          unset($suggestions[$k]);
          continue;
        }
      } else {
        unset($suggestions[$k]);
        continue;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable() {
    // Translation jobs themself can not be translated.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function language() {
    return new Language(array('id' => Language::LANGCODE_NOT_SPECIFIED));
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

}