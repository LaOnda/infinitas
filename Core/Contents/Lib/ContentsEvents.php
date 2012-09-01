<?php
/**
 * @brief ContentsEvents plugin events.
 *
 * The events for the Contents plugin for setting up cache and the general
 * configuration of the plugin.
 *
 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
 * @link http://www.infinitas-cms.org
 * @package Infinitas.Contents
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @since 0.8a
 *
 * @author dogmatic69
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 */

final class ContentsEvents extends AppEvents {
	public function onPluginRollCall() {
		return array(
			'name' => 'Content',
			'description' => 'Mange the way content works inside Infinitas',
			'icon' => '/contents/img/icon.png',
			'author' => 'Infinitas',
			'dashboard' => array('plugin' => 'contents', 'controller' => 'global_contents', 'action' => 'dashboard')
		);
	}

	public function onAdminMenu() {
		$menu['main'] = array(
			'Dashboard' => array('plugin' => 'contents', 'controller' => 'global_contents', 'action' => 'dashboard'),
			'Layouts' => array('plugin' => 'contents', 'controller' => 'global_layouts', 'action' => 'index'),
			'Contents' => array('plugin' => 'contents', 'controller' => 'global_contents', 'action' => 'index'),
			'Categories' => array('plugin' => 'contents', 'controller' => 'global_categories', 'action' => 'index'),
			'Tags' => array('plugin' => 'contents', 'controller' => 'global_tags', 'action' => 'index')
		);

		return $menu;
	}

	public function onAttachBehaviors($event = null) {
		if($event->Handler->shouldAutoAttachBehavior()) {
			if (isset($event->Handler->contentable) && $event->Handler->contentable && !$event->Handler->Behaviors->enabled('Contents.Contentable')) {
				$event->Handler->Behaviors->attach('Contents.Contentable');
			}

			if (array_key_exists('category_id', $event->Handler->schema())  && !$event->Handler->Behaviors->enabled('Contents.Categorisable')) {
				$event->Handler->Behaviors->attach('Contents.Categorisable');
			}
		}
	}

	public function onRequireComponentsToLoad($event = null) {
		return array(
			'Contents.GlobalContents'
		);
	}

	public function onRequireHelpersToLoad($event = null) {
		return array(
			'Contents.TagCloud',
			'Contents.GlobalContents'
		);
	}

	public function onRequireJavascriptToLoad($event, $data = null) {
		return array(
			'Contents.jq-tags',
			'Contents.tags'
		);
	}

	public function onRequireCssToLoad($event, $data = null) {
		return array(
			'Contents.tags'
		);
	}

	public function onSiteMapRebuild($event) {
		$Category = ClassRegistry::init('Contents.GlobalCategory');
		$newest = $Category->getNewestRow();
		$frequency = $Category->getChangeFrequency();

		$return = array();
		$return[] = array(
			'url' => Router::url(
				array(
					'plugin' => 'contents',
					'controller' => 'categories',
					'action' => 'index',
					'admin' => false,
					'prefix' => false
				),
				true
			),
			'last_modified' => $newest,
			'change_frequency' => $frequency
		);

		$categories = ClassRegistry::init('Contents.GlobalContent')->find(
			'list',
			array(
				'fields' => array(
					'GlobalContent.foreign_key',
					'GlobalContent.slug'
				),
				'conditions' => array(
					'GlobalContent.model' => 'Contents.GlobalCategory'
				)
			)
		);
		foreach($categories as $category) {
			$return[] = array(
				'url' => Router::url(
					array(
						'plugin' => 'contents',
						'controller' => 'categories',
						'action' => 'view',
						'slug' => $category,
						'admin' => false,
						'prefix' => false
					),
					true
				),
				'last_modified' => $newest,
				'change_frequency' => $frequency
			);
		}

		return $return;
	}

	public function onSetupRoutes($event, $data = null) {
		InfinitasRouter::connect(
			'/admin/contents',
			array(
				'plugin' => 'contents',
				'controller' => 'global_contents',
				'action' => 'dashboard',
				'admin' => true,
				'prefix' => 'admin'
			)
		);
	}

	public function onSlugUrl($event, $data = null, $type = null) {
		if(empty($data['type'])) {
			$data['type'] = 'category';
		}

		if(!empty($data['model'])) {
			$data = array('type' => $data['model'], 'data' => array('GlobalCategory' => $data));
		}

		if(!empty($data['GlobalCategory'])) {
			$data = array('type' => 'category', 'data' => array('GlobalCategory' => $data));
		}

		switch($data['type']) {
			case 'Contents.GlobalCategory':
				$data['type'] = 'category';
				break;
		}

		return parent::onSlugUrl($event, $data['data'], $data['type']);
	}

	public function onRouteParse($event, $data) {
		$return = null;

		if(!empty($data['slug'])) {
			$return = ClassRegistry::init('Contents.GlobalContent')->find(
				'count',
				array(
					'conditions' => array(
						'GlobalContent.slug' => $data['slug']
					)
				)
			);

			if($return > 0) {
				return $data;
			}

			return false;
		}

		return $data;
	}
}