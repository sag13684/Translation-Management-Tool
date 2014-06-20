<?php

/**
 * @file
 * Contains \Drupal\tmgmt\JobItemUiCart.
 */

namespace Drupal\tmgmt;

use Drupal\tmgmt\Entity\JobItem;

/**
 * Represents a job item cart.
 *
 * @ingroup tmgmt_ui_cart
 */
class JobItemUiCart {

  /**
   * Singleton instance of cart.
   */
  protected static $instance;

  /**
   * Array key to store the contents of $this->cart into the $_SESSION variable.
   *
   * @var string
   */
  protected $session_key = 'tmgmt_cart';

  /**
   * An array to hold and manipulate the contents of the job item cart.
   *
   * @var array
   */
  protected $cart;

  /**
   * Set up a new JobItemUiCart instance.
   *
   * Will load the cart from the session or initialize a new one if nothing has
   * been stored yet.
   */
  protected function __construct() {
    if (!isset($_SESSION[$this->session_key])) {
      $_SESSION[$this->session_key] = array();
    }
    $this->cart = &$_SESSION[$this->session_key];
  }

  /**
   * Returns the singleton cart.
   *
   * @return JobItemUiCart
   *   An instance of the cart.
   */
  public static function getInstance() {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Adds existing job items into the cart.
   *
   * @param JobItem[] $items
   *   Job items to be added.
   */
  public function addExistingJobItems(array $items) {
    foreach ($items as $item) {
      if (!$this->isSourceItemAdded($item->getPlugin(), $item->getItemType(), $item->getItemId())) {
        $this->cart[] = $item->id();
      }
    }
  }

  /**
   * Creates a job item and adds it into the cart.
   *
   * @param string $plugin
   *   The source plugin.
   * @param string $item_type
   *   The source item type.
   * @param $item_id
   *   The source item id.
   *
   * @return JobItem|null
   *   Added job item. If the item exists NULL is returned.
   */
  public function addJobItem($plugin, $item_type, $item_id) {
    if ($this->isSourceItemAdded($plugin, $item_type, $item_id)) {
      return NULL;
    }

    $job_item = tmgmt_job_item_create($plugin, $item_type, $item_id);
    $job_item->save();
    $this->cart[] = $job_item->id();
    return $job_item;
  }

  /**
   * Checks if the source item has been added into the cart.
   *
   * @param string $plugin
   *   The source plugin.
   * @param string $item_type
   *   The source type.
   * @param int $source_id
   *   The source id.
   *
   * @return bool
   *   If the source item is in the cart.
   */
  public function isSourceItemAdded($plugin, $item_type, $source_id) {
    foreach ($this->getJobItemsFromCart() as $job_item) {
      if ($job_item->getItemId() == $source_id && $job_item->getItemType() == $item_type && $job_item->getPlugin() == $plugin) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Remove job items from the cart.
   *
   * @param array $job_item_ids
   *   Job items to be removed.
   */
  public function removeJobItems(array $job_item_ids) {
    $this->cart = array_diff($this->cart, $job_item_ids);
  }

  /**
   * Gets job items in the cart.
   *
   * @return \Drupal\tmgmt\Entity\JobItem[] $items
   *   Job items in the cart.
   */
  public function getJobItemsFromCart() {
    return entity_load_multiple('tmgmt_job_item', $this->cart);
  }

  /**
   * Gets count of items in the cart.
   *
   * @return int
   *   Number of items in the cart.
   */
  public function count() {
    return count($this->cart);
  }

  /**
   * Remove all contents from the cart.
   */
  public function emptyCart() {
    $this->cart = array();
  }
}