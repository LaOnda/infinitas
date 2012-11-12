<?php
	/**
	 * Controller to manage reckord locking.
	 *
	 * This controller will unlock records that are locked.
	 *
	 * Copyright (c) 2010 Carl Sutton ( dogmatic69 )
	 *
	 * @filesource
	 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
	 * @link http://www.infinitas-cms.org
	 * @package Core.Locks.Controller
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.7a
	 *
	 * @author Carl Sutton ( dogmatic69 )
	 *
	 * Licensed under The MIT License
	 * Redistributions of files must retain the above copyright notice.
	 */

	class LocksController extends LocksAppController {
		public $notice = array(
			'deleted' => array(
				'message' => 'The selected records have been unlocked',
				'redirect' => true
			),
		);

		/**
		 *
		 */
		public function admin_index() {
			$this->Paginator->settings = array(
				'contain' => array(
					'Locker'
				)
			);
			$locks = $this->Paginator->paginate();
			$this->set(compact('locks'));
		}

		/**
		 * unlock the rows by deleting them.
		 */
		public function __massActionUnlock($ids = array()) {
			return $this->MassAction->__handleDeletes($ids);
		}

		/**
		 * Action to show when attemptying to edit a record that is locked.
		 */
		public function admin_locked() {
			$this->set('title_for_layout', __('This content is currently locked'));
		}
	}