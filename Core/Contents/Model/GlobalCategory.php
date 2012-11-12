<?php
	/**
	 * Category model handles the CRUD for categories.
	 *
	 * Saving and editing categories are done here.
	 *
	 * @copyright Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	 * @link http://infinitas-cms.org
	 * @package Infinitas.Contents.models
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.7a
	 * 
	 *
	 *
	 */

	class GlobalCategory extends ContentsAppModel {
		public $contentable = true;

		/**
		 * lockable enables the internal row locking while a row is being modified
		 * preventing anyone from accessing that row.
		 *
		 * @var bool
		 * @access public
		 */
		public $lockable = true;

		/**
		 * default order of the model is set to lft as its an mptt table
		 *
		 * @var array
		 * @access public
		 */
		public $order = array(
		);

		/**
		 * the relations for the category model
		 *
		 * @var array
		 * @access public
		 */
		public $belongsTo = array(
			//'Parent' => array(
			//	'className' => 'Categories.Category',
			//	'counterCache' => true
			//),
			'Group' => array(
				'className' => 'Users.Group'
			)
		);

		public $hasOne = array(
			'GlobalContent' => array(
				'className' => 'Contents.GlobalContent',
				'foreignKey' => 'foreign_key',
				'conditions' => array(
					'GlobalContent.foreign_key = GlobalCategory.id',
					'GlobalContent.model' => 'Contents.GlobalCategory'
				)
			)
		);

		/** 
		 * @copydoc AppModel::__construct()
		 */
		public function  __construct($id = false, $table = null, $ds = null) {
			parent::__construct($id, $table, $ds);

			$this->order = array(
				$this->alias . '.lft' => 'asc'
			);

			$this->findMethods['getCategory'] = true;
			$this->findMethods['categoryList'] = true;
		}

		public function beforeValidate($options = array()) {
			if(empty($this->data[$this->alias]['parent_id'])) {
				$this->data[$this->alias]['parent_id'] = 0;
			}
			return parent::beforeValidate($options);
		}

		/**
		 * get active ids of the categories for use in other finds where you only
		 * want the active rows according to what categories are active.
		 *
		 * @access public 
		 * 
		 * @return array list of ids for categories that are active
		 */
		public function getActiveIds() {
			$ids = $this->find(
				'list',
				array(
					'fields' => array(
						$this->alias . '.id', $this->alias . '.id'
					),
					'conditions' => array(
						$this->alias . '.active' => 1
					)
				)
			);

			return $ids;
		}

		/**
		 * overwrite childern method to allow finding by slug or name
		 * 
		 * @param mixed $id the id of the parent
		 * @param bool $direct direct children only or all like grandchildren
		 * @access public
		 *
		 * @todo seems like a bug here with uuid's
		 * 
		 * @return TreeBehavior::children
		 */
		public function children($id = null, $direct = false) {
			if(!$id || is_int($id)) {
				return parent::children($id, $direct);
			}

			$id = $this->find(
				'first',
				array(
					'conditions' => array(
						'or' => array(
							'GlobalCategory.slug' => $id,
							'GlobalCategory.title' => $id
						),
					)
				)
			);

			if(isset($id['GlobalCategory']['id']) && !empty($id['GlobalCategory']['id'])) {
				$id = $id['GlobalCategory']['id'];
			}

			return parent::children($id, $direct);
		}

		public function _findGetCategory($state, $query, $results = array()) {
			if ($state === 'before') {
				$query['limit'] = 1;

				$query['fields'] = array_merge(
					(array)$query['fields'],
					array(
						'ParentCategory.*',
						'ParentCategoryData.id',
						'ParentCategoryData.model',
						'ParentCategoryData.foreign_key',
						'ParentCategoryData.title',
						'ParentCategoryData.slug',
						'ParentCategoryData.introduction',
						'ParentCategoryData.canonical_url',
						'ParentCategoryData.global_category_id'
					)
				);

				$query['joins'][] = array(
					'table' => 'global_categories',
					'alias' => 'ParentCategory',
					'type' => 'LEFT',
					'foreignKey' => false,
					'conditions' => array(
						'ParentCategory.id = GlobalCategory.parent_id'
					)
				);
				$query['joins'][] = array(
					'table' => 'global_contents',
					'alias' => 'ParentCategoryData',
					'type' => 'LEFT',
					'foreignKey' => false,
					'conditions' => array(
						'ParentCategoryData.foreign_key = ParentCategory.id'
					)
				);
				return $query;
			}

			$results = current($results);

			if(!empty($results[$this->alias][$this->primaryKey])) {
				if(!empty($results['ParentCategory'][$this->primaryKey])) {
					$results['ParentCategory']['title'] = $results['ParentCategoryData']['title'];
					$results['ParentCategory']['slug'] = $results['ParentCategoryData']['slug'];
					$results['ParentCategory']['canonical_url'] = $results['ParentCategoryData']['canonical_url'];
					unset($results['ParentCategoryData']);
				}
				
				$results['CategoryContent'] = $this->GlobalContent->find('getRelationsCategory', $results[$this->alias][$this->primaryKey]);
			}

			return $results;
		}
		
		/**
		 * generate a category drop down tree
		 * 
		 * @param string $state
		 * @param array $query
		 * @param array $results
		 * 
		 * @return array category list with active / inactive rows
		 */
		public function _findCategoryList($state, $query, $results = array()) {
			if ($state === 'before') {
				$query['fields'] = array_merge(
					(array)$query['fields'],
					array(
						'GlobalCategory.id',
					)
				);
				return $query;
			}
			$_active = __d('contents', 'Active');
			$_inactive = __d('contents', 'Inactive');
			
			$return = array($_active => array(), $_inactive => array());
			foreach($results as $result) {
				$title = $result['GlobalCategory']['title'];
				if($result['GlobalCategory']['path_depth']) {
					$title = sprintf('%s %s', str_repeat('-', $result['GlobalCategory']['path_depth']), $title);
				}
				
				if($result['GlobalCategory']['active']) {
					$return[$_active][$result['GlobalCategory']['id']] = $title;
					continue;
				}
				
				$return[$_inactive][$result['GlobalCategory']['id']] = $title;
			}

			return $return;
		}
		
		public function afterSave($created) {
			$this->saveField(
				'path_depth', 
				count($this->getPath($this->id)) - 1,
				array(
					'callbacks' => false
				)
			);
			
			return parent::afterSave($created);
		}
	}