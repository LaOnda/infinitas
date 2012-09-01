<?php
	final class ManagementEvents extends AppEvents {
		public function onPluginRollCall() {
		}

		public function onSetupCache($event, $data = null) {
			return array(
				'name' => 'core',
				'config' => array(
					'prefix' => 'core.',
				)
			);
		}

		public function onAdminMenu() {
			$menu['main'] = array(
				'Configuration' => array('plugin' => 'management', 'controller' => 'management', 'action' => 'site')
			);

			return $menu;
		}

		public function onSlugUrl($event, $data = null, $type = null) {
			switch($data['type']) {
				case 'comments':
					return array(
						'plugin' => 'management',
						'controller' => 'infinitas_comments',
						'action' => $data['data']['action'],
						'id' => $data['data']['id'],
						'category' => 'user-feedback'
					);
					break;
			} // switch
		}

		public function onSetupRoutes($event, $data = null) {
			InfinitasRouter::connect(
				'/admin',
				array(
					'plugin' => 'management',
					'controller' => 'management',
					'action' => 'dashboard',
					'prefix' => 'admin',
					'admin' => 1,
				)
			);

			InfinitasRouter::connect(
				'/admin/management',
				array(
					'plugin' => 'management',
					'controller' => 'management',
					'action' => 'site',
					'admin' => true,
					'prefix' => 'admin'
				)
			);
		}

		public function onGetRequiredFixtures($event) {
			return array(
				'Management.Ticket',
				'Management.Session'
			);
		}
	}