<?php
	/**
	 * User Model.
	 *
	 * Model for managing users
	 *

	 *
	 * @filesource
	 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
	 * @link http://www.infinitas-cms.org
	 * @package Infinitas.Users.Model
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.7alpha
	 *
	 * @author Carl Sutton (dogmatic69)
	 *
	 *
	 *
	 */
	App::uses('UsersAppModel', 'Users.Model');

	class User extends UsersAppModel{
		public $useTable = 'users';

		public $displayField = 'username';

		public $actsAs = array(
			//'Acl' => 'requester',
			'Libs.Ticketable'
		);

		public $belongsTo = array(
			'Users.Group'
		);

		public function __construct($id = false, $table = null, $ds = null) {
			parent::__construct($id, $table, $ds);

			$message = Configure::read('Website.password_validation');

			$this->validate = array(
				'username' => array(
					'notEmpty' => array(
						'rule' => 'notEmpty',
						'message' => __d('users', 'Please enter your username')
					),
					'isUnique' => array(
						'rule' => 'isUnique',
						'message' => __d('users', 'That username is taken, sorry')
					)
				),
				'email' => array(
					'notEmpty' => array(
						'rule' => 'notEmpty',
						'message' => __d('users', 'Please enter your email address')
					),
					'email' => array(
						'rule' => array('email', true),
						'message' => __d('users', 'That email address does not seem to be valid')
					),
					'isUnique' => array(
						'rule' => 'isUnique',
						'message' => __d('users', 'It seems you are already registered, please use the forgot password option')
					)
				),
				'confirm_email' => array(
					'validateCompareFields' => array(
						'rule' => array('validateCompareFields', array('email', 'confirm_email')),
						'message' => __d('users', 'Your email address does not match')
					)
				),
				'password' => array(
					'notEmpty' => array(
						'rule' => 'notEmpty',
						'message' => __d('users', 'Please enter a password')
					)
				),
				'confirm_password' => array(
					'notEmpty' => array(
						'rule' => 'notEmpty',
						'message' => __d('users', 'Please re-enter your password')
					),
					'validPassword' => array(
						'rule' => 'validPassword',
						'message' => (!empty($message) ? $message : __d('users', 'Please enter a stronger password'))
					),
					'validateCompareFields' => array(
						'rule' => array('validateCompareFields', array('password', 'confirm_password')),
						'message' => __d('users', 'The passwords entered do not match')
					)
				),
				'time_zone' => array(
					'notEmpty' => array(
						'rule' => 'notEmpty',
						'message' => __d('users', 'Please select a time zone')
					)
				),
			);
		}

		/**
		 * auto hash passwords when other plugins use the model with a different alias
		 *
		 * Auth does not auto has the pw field when the alias is not User, so we
		 * have to do it here so that it seems auto for other plugins.
		 *
		 * @param array $options see parent::beforeValidate
		 * @return parent::beforeValidate
		 */
		public function beforeValidate($options) {
			if(!empty($this->data[$this->alias]['confirm_password'])) {
				$this->data[$this->alias]['password'] = Security::hash($this->data[$this->alias]['password'], null, true);
			}

			return parent::beforeValidate($options);
		}

		/**
		 * Valid password.
		 *
		 * This method uses the regex saved in the config to check that the
		 * password is secure. The user can update this and the message in the
		 * backend by changing the config value "Website.password_regex".
		 *
		 * @params array $field the array $field => $value from the form
		 * @return bool true if password matches the regex and false if not
		 */
		public function validPassword($field = null) {
			return preg_match('/'.Configure::read('Website.password_regex').'/', $field['confirm_password']);
		}

		/**
		 * Get last login details.
		 *
		 * Gets the details of the last login of the user so we can show the last
		 * login and ipaddress to them.
		 *
		 * @param int $userId the users id.
		 * @return array the data from the last login.
		 */
		public function getLastLogon($userId = null) {
			if (!$userId) {
				return false;
			}

			return $this->find(
				'first',
				array(
					'fields' => array(
						$this->alias . '.ip_address',
						$this->alias . '.last_login',
						$this->alias . '.country',
						$this->alias . '.city'
					),
					'conditions' => array(
						$this->alias . '.' . $this->primaryKey => $userId
					)
				)
			);
		}

		public function loggedInUserCount() {
			$Session = ClassRegistry::init('Session');
			return $Session->find('count');
		}

		public function latestUsers($limit = 10) {
			$Session = ClassRegistry::init('Session');
			$sessions = $Session->find('all');

			foreach($sessions as &$session) {
				$session['User'] = explode('Auth|', $session['Session']['data']);

				if(isset($session['User'][1])) {
					$session['User'] = unserialize($session['User'][1]);
					if (isset($session['User']['User'])) {
						$session['User'] = $session['User']['User'];
					}
					else {
						$session['User'] = '';
					}
				}
				else {
					$session['User'] = '';
				}
			}

			$users = Set::extract('/User/id', $sessions);

			$this->User->recursive = 0;
			$users = $this->find(
				'all',
				array(
					'conditions' => array(
						'User.id' => $users
					),
					'limit' => $limit
				)
			);

			return $users;
		}

		public function parentNode() {
			if (!$this->id && empty($this->data)) {
				return null;
			}

			$data = $this->data;
			if (empty($this->data)) {
				$data = $this->read();
			}

			if (!isset($data['User']['group_id']) || !$data['User']['group_id']) {
				return null;
			}

			else {
				return array('Group' => array('id' => $data['User']['group_id']));
			}
		}

		/**
		 * After save callback
		 *
		 * Update the aro for the user.
		 *
		 * @access public
		 * @return void
		 */
		public function afterSave($created) {
			if (!$created && is_a('Model', $this->Aro)) {
				$parent = $this->node($this->parentNode());
				$node = $this->node();
				$aro = $node[0];
				$aro['Aro']['parent_id'] = $parent[0]['Aro']['id'];
				$this->Aro->save($aro);
			}
		}

		public function getSiteRelatedList() {
			return $this->find(
				'list',
				array(
					'conditions' => array(
						'User.group_id' => 1
					)
				)
			);
		}

		/**
		 * check that the given user id is a valid user.
		 *
		 * @access public
		 *
		 * @param mixed $userId user id to check
		 *
		 * @return bool, true if valid, false if not
		 */
		public function validUserId($userId) {
			if(!$userId) {
				return false;
			}

			return (bool)$this->find(
				'count',
				array(
					'conditions' => array(
						$this->alias . '.id' => $userId
					)
				)
			);
		}

		public function getAdmins($fields = array()) {
			if(!$fields) {
				$fields = array(
					$this->alias . '.username',
					$this->alias . '.email'
				);
			}

			return $this->find(
				'list',
				array(
					'fields' => $fields,
					'conditions' => array(
						$this->alias . '.group_id' => 1
					)
				)
			);
		}

		/**
		 * get a count of registrations per month for the last two years
		 *
		 * @access public
		 *
		 * @return array, list of (year_month => count)
		 */
		public function getRegistrationChartData() {
			$this->virtualFields['join_date'] = 'CONCAT_WS("/", YEAR(`' . $this->alias . '`.`created`), LPAD(MONTH(`' . $this->alias . '`.`created`), 2, 0))';
			$this->virtualFields['count_joins'] = 'COUNT(`' . $this->alias . '`.`id`)';

			$i = - 24;
			$dates = array();
			while($i <= 0) {
				$dates[date('Y/m', mktime(0, 0, 0, date('m') + $i, 1, date('Y')))] = null;
				$i++;
			}

			$data = $this->find(
				'list',
				array(
					'fields' => array(
						'join_date',
						'count_joins',
					),
					'conditions' => array(
						$this->alias . '.created >= ' => date('Y-m-d H:i:s', mktime(0, 0, 0, date('m') - 24, date('d'), date('Y')))
					),
					'group' => array(
						'join_date'
					)
				)
			);

			return array_merge($dates, $data);
		}
	}