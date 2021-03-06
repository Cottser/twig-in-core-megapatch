<?php

/**
 * @file
 * Contains \Drupal\field_sql_storage\Entity\QueryFactory.
 */

namespace Drupal\field_sql_storage\Entity;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManager;

/**
 * Factory class creating entity query objects for the SQL backend.
 *
 * @see \Drupal\field_sql_storage\Entity\Query
 * @see \Drupal\field_sql_storage\Entity\QueryAggregate
 */
class QueryFactory {

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection used by the entity query.
   */
  function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Constructs a entity query for a certain entity type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\field_sql_storage\Entity\Query
   *   The factored query.
   */
  function get($entity_type, $conjunction, EntityManager $entity_manager) {
    return new Query($entity_type, $entity_manager, $conjunction, $this->connection);
  }

  /**
   * Constructs a entity aggregation query for a certain entity type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\field_sql_storage\Entity\QueryAggregate
   *   The factored aggregation query.
   */
  function getAggregate($entity_type, $conjunction, EntityManager $entity_manager) {
    return new QueryAggregate($entity_type, $entity_manager, $conjunction, $this->connection);
  }

}
