<?php
namespace Elgg\Database;

use Elgg\Database;
use Elgg\Database\EntityTable;
use Elgg\Cache\PluginSettingsCache;

/**
 * Private settings for entities
 *
 * Private settings provide metadata like storage of settings for plugins
 * and users.
 *
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * @access private
 * @since 2.0.0
 */
class PrivateSettingsTable {

	/**
	 * @var Database
	 */
	protected $db;

	/**
	 * @var EntityTable
	 */
	protected $entities;

	/**
	 * @var string Name of the database table
	 */
	protected $table;

	/**
	 * @var PluginSettingsCache cache for settings
	 */
	protected $cache;

	/**
	 * Constructor
	 *
	 * @param Database            $db       The database
	 * @param EntityTable         $entities Entities table
	 * @param PluginSettingsCache $cache    Settings cache
	 */
	public function __construct(Database $db, EntityTable $entities, PluginSettingsCache $cache) {
		$this->db = $db;
		$this->entities = $entities;
		$this->cache = $cache;
		$this->table = $this->db->prefix . 'private_settings';
	}

	/**
	 * Returns entities based upon private settings
	 *
	 * Also accepts all options available to elgg_get_entities(). Supports
	 * the singular option shortcut.
	 *
	 * @param array $options Array in format:
	 *
	 *  private_setting_names => null|ARR private setting names
	 *
	 *  private_setting_values => null|ARR metadata values
	 *
	 *  private_setting_name_value_pairs => null|ARR (
	 *                                       name => 'name',
	 *                                       value => 'value',
	 *                                       'operand' => '=',
	 *                                      )
	 *                               Currently if multiple values are sent via
	 *                               an array (value => array('value1', 'value2')
	 *                               the pair's operand will be forced to "IN".
	 *
	 *  private_setting_name_value_pairs_operator => null|STR The operator to
	 *                                 use for combining
	 *                                 (name = value) OPERATOR (name = value);
	 *                                 default AND
	 *
	 *  private_setting_name_prefix => STR A prefix to apply to all private
	 *                                 settings. Used to namespace plugin user
	 *                                 settings or by plugins to namespace their
	 *                                 own settings.
	 *
	 * @return mixed int If count, int. If not count, array. false on errors.
	 */
	public function getEntities(array $options = []) {
		$defaults = [
			'private_setting_names'                     => ELGG_ENTITIES_ANY_VALUE,
			'private_setting_values'                    => ELGG_ENTITIES_ANY_VALUE,
			'private_setting_name_value_pairs'          => ELGG_ENTITIES_ANY_VALUE,
			'private_setting_name_value_pairs_operator' => 'AND',
			'private_setting_name_prefix'               => '',
		];

		$options = array_merge($defaults, $options);

		$singulars = [
			'private_setting_name',
			'private_setting_value',
			'private_setting_name_value_pair',
		];

		$options = _elgg_normalize_plural_options_array($options, $singulars);

		$clauses = $this->getWhereSql('e',
			$options['private_setting_names'],
			$options['private_setting_values'],
			$options['private_setting_name_value_pairs'],
			$options['private_setting_name_value_pairs_operator'],
			$options['private_setting_name_prefix']);

		if ($clauses) {
			// merge wheres to pass to get_entities()
			if (isset($options['wheres']) && !is_array($options['wheres'])) {
				$options['wheres'] = [$options['wheres']];
			} elseif (!isset($options['wheres'])) {
				$options['wheres'] = [];
			}

			$options['wheres'] = array_merge($options['wheres'], $clauses['wheres']);

			// merge joins to pass to get_entities()
			if (isset($options['joins']) && !is_array($options['joins'])) {
				$options['joins'] = [$options['joins']];
			} elseif (!isset($options['joins'])) {
				$options['joins'] = [];
			}

			$options['joins'] = array_merge($options['joins'], $clauses['joins']);
		}

		return $this->entities->getEntities($options);
	}

	/**
	 * Returns private setting name and value SQL where/join clauses for entities
	 *
	 * @param string     $table         Entities table name
	 * @param array|null $names         Array of names
	 * @param array|null $values        Array of values
	 * @param array|null $pairs         Array of names / values / operands
	 * @param string     $pair_operator Operator for joining pairs where clauses
	 * @param string     $name_prefix   A string to prefix all names with
	 * @return array
	 */
	private function getWhereSql($table, $names = null, $values = null,
		$pairs = null, $pair_operator = 'AND', $name_prefix = '') {

		// @todo short circuit test

		$return =  [
			'joins' =>  [],
			'wheres' => [],
		];

		$return['joins'][] = "JOIN {$this->table} ps on
			{$table}.guid = ps.entity_guid";

		$wheres = [];

		// get names wheres
		$names_where = '';
		if ($names !== null) {
			if (!is_array($names)) {
				$names = [$names];
			}

			$sanitised_names = [];
			foreach ($names as $name) {
				$name = $name_prefix . $name;
				$sanitised_names[] = '\'' . $this->db->sanitizeString($name) . '\'';
			}

			$names_str = implode(',', $sanitised_names);
			if ($names_str) {
				$names_where = "(ps.name IN ($names_str))";
			}
		}

		// get values wheres
		$values_where = '';
		if ($values !== null) {
			if (!is_array($values)) {
				$values = [$values];
			}

			$sanitised_values = [];
			foreach ($values as $value) {
				// normalize to 0
				if (!$value) {
					$value = 0;
				}
				$sanitised_values[] = '\'' . $this->db->sanitizeString($value) . '\'';
			}

			$values_str = implode(',', $sanitised_values);
			if ($values_str) {
				$values_where = "(ps.value IN ($values_str))";
			}
		}

		if ($names_where && $values_where) {
			$wheres[] = "($names_where AND $values_where)";
		} elseif ($names_where) {
			$wheres[] = "($names_where)";
		} elseif ($values_where) {
			$wheres[] = "($values_where)";
		}

		// add pairs which must be in arrays.
		if (is_array($pairs)) {
			// join counter for incremental joins in pairs
			$i = 1;

			// check if this is an array of pairs or just a single pair.
			if (isset($pairs['name']) || isset($pairs['value'])) {
				$pairs = [$pairs];
			}

			$pair_wheres = [];

			foreach ($pairs as $index => $pair) {
				// @todo move this elsewhere?
				// support shortcut 'n' => 'v' method.
				if (!is_array($pair)) {
					$pair = [
						'name' => $index,
						'value' => $pair
					];
				}

				// must have at least a name and value
				if (!isset($pair['name']) || !isset($pair['value'])) {
					// @todo should probably return false.
					continue;
				}

				if (isset($pair['operand'])) {
					$operand = $this->db->sanitizeString($pair['operand']);
				} else {
					$operand = ' = ';
				}

				// for comparing
				$trimmed_operand = trim(strtolower($operand));

				// if the value is an int, don't quote it because str '15' < str '5'
				// if the operand is IN don't quote it because quoting should be done already.
				if (is_numeric($pair['value'])) {
					$value = $this->db->sanitizeString($pair['value']);
				} else if (is_array($pair['value'])) {
					$values_array = [];

					foreach ($pair['value'] as $pair_value) {
						if (is_numeric($pair_value)) {
							$values_array[] = $this->db->sanitizeString($pair_value);
						} else {
							$values_array[] = "'" . $this->db->sanitizeString($pair_value) . "'";
						}
					}

					if ($values_array) {
						$value = '(' . implode(', ', $values_array) . ')';
					}

					// @todo allow support for non IN operands with array of values.
					// will have to do more silly joins.
					$operand = 'IN';
				} else if ($trimmed_operand == 'in') {
					$value = "({$pair['value']})";
				} else {
					$value = "'" . $this->db->sanitizeString($pair['value']) . "'";
				}

				$name = $this->db->sanitizeString($name_prefix . $pair['name']);

				// @todo The multiple joins are only needed when the operator is AND
				$return['joins'][] = "JOIN {$this->table} ps{$i}
					on {$table}.guid = ps{$i}.entity_guid";

				$pair_wheres[] = "(ps{$i}.name = '$name' AND ps{$i}.value
					$operand $value)";

				$i++;
			}

			$where = implode(" $pair_operator ", $pair_wheres);
			if ($where) {
				$wheres[] = "($where)";
			}
		}

		$where = implode(' AND ', $wheres);
		if ($where) {
			$return['wheres'][] = "($where)";
		}

		return $return;
	}

	/**
	 * Gets a private setting for an entity
	 *
	 * Plugin authors can set private data on entities. By default private
	 * data will not be searched or exported.
	 *
	 * @param int    $entity_guid The entity GUID
	 * @param string $name        The name of the setting
	 *
	 * @return mixed The setting value, or null if does not exist
	 */
	public function get($entity_guid, $name) {

		$values = $this->cache->getAll($entity_guid);
		if (isset($values[$name])) {
			return $values[$name];
		}

		if (!$this->entities->exists($entity_guid)) {
			return false;
		}

		$query = "
			SELECT value FROM {$this->table}
			WHERE name = :name
			AND entity_guid = :entity_guid
		";
		$params = [
			':entity_guid' => (int) $entity_guid,
			':name' => (string) $name,
		];

		$setting = $this->db->getDataRow($query, null, $params);

		if ($setting) {
			return $setting->value;
		}

		return null;
	}

	/**
	 * Return an array of all private settings.
	 *
	 * @param int $entity_guid The entity GUID
	 *
	 * @return string[] empty array if no settings
	 */
	public function getAll($entity_guid) {
		if (!$this->entities->exists($entity_guid)) {
			return [];
		}

		$query = "
			SELECT * FROM {$this->table}
			WHERE entity_guid = :entity_guid
		";
		$params = [
			':entity_guid' => (int) $entity_guid,
		];

		$result = $this->db->getData($query, null, $params);

		$return = [];

		if ($result) {
			foreach ($result as $r) {
				$return[$r->name] = $r->value;
			}
		}

		return $return;
	}

	/**
	 * Sets a private setting for an entity.
	 *
	 * @param int    $entity_guid The entity GUID
	 * @param string $name        The name of the setting
	 * @param string $value       The value of the setting
	 * @return bool
	 */
	public function set($entity_guid, $name, $value) {
		$this->cache->clear($entity_guid);
		_elgg_services()->boot->invalidateCache();

		if (!$this->entities->exists($entity_guid)) {
			return false;
		}

		$query = "
			INSERT into {$this->table}
			(entity_guid, name, value) VALUES
			(:entity_guid, :name, :value)
			ON DUPLICATE KEY UPDATE value = :value
		";
		$params = [
			':entity_guid' => (int) $entity_guid,
			':name' => (string) $name,
			':value' => (string) $value,
		];

		$result = $this->db->insertData($query, $params);

		return $result !== false;
	}

	/**
	 * Deletes a private setting for an entity.
	 *
	 * @param int    $entity_guid The Entity GUID
	 * @param string $name        The name of the setting
	 * @return bool
	 */
	public function remove($entity_guid, $name) {
		$this->cache->clear($entity_guid);
		_elgg_services()->boot->invalidateCache();

		$query = "
			DELETE FROM {$this->table}
			WHERE name = :name
			AND entity_guid = :entity_guid
		";
		$params = [
			':entity_guid' => (int) $entity_guid,
			':name' => (string) $name,
		];
		
		return $this->db->deleteData($query, $params);
	}

	/**
	 * Deletes all private settings for an entity
	 *
	 * @param int $entity_guid The Entity GUID
	 * @return bool
	 */
	public function removeAllForEntity($entity_guid) {
		$this->cache->clear($entity_guid);
		_elgg_services()->boot->invalidateCache();

		$query = "
			DELETE FROM {$this->table}
			WHERE entity_guid = :entity_guid
		";
		$params = [
			':entity_guid' => (int) $entity_guid,
		];

		return $this->db->deleteData($query, $params);
	}

}
