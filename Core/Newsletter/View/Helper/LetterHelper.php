<?php
	/**
	* cant use Newsletter because of the model
	*/
	/**
	* Comment Template.
	*
	* @todo Implement .this needs to be sorted out.
	*
	* Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	*
	*
	*
	* @filesource
	* @copyright Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	* @link http://infinitas-cms.org
	* @package Core.Newsletter.Helper
	* @license http://www.opensource.org/licenses/mit-license.php The MIT License
	* @since 0.5a
	*/

	class LetterHelper extends AppHelper {
		public $helpers = array(
			// cake helpers
			'Html',
			// core helpers
			'Libs.Wysiwyg', 'Libs.Design', 'Libs.Image', 'Form'
		);

		public $allowedPreviews = array(
			'newsletters',
			'templates'
		);

		public $errors = array();

		public function toggle($id = null, $status = null, $method = 'toggleSend') {
			if (!$id) {
				$this->errors[] = 'No id passed for newsletter status';
				return false;
			}

			$params = array();

			switch($status) {
				case 0:
					return $link = $this->Html->link($this->Html->image(
							'core/icons/file_types/16/mail.png',
							array(
								'alt' => __('Pending'),
								'title' => __('Click to start sending'),
								'width' => '16px'
								)
							),
						array(
							'action' => $method,
							$id
							),
						$params + array(
							'escape' => false
							)
						);
					break;

				case 1:
					return $this->Html->image(
						'core/icons/actions/16/active.png',
						array(
							'alt' => __('Done'),
							'title' => __('Sending Complete'),
							'width' => '16px'
							)
						);
					break;
				default: ;
			} // switch
		}

		public function preview($id = null, $controller = null) {
			if (!$id || !$controller) {
				$this->errors[] = 'You need to pass the id and template|newsletter';
				return false;
			}

			if (!in_array($controller, $this->allowedPreviews)) {
				$this->errors[] = 'There is no preview for the controller';
				return false;
			}

			$url = $this->Html->url(
				array(
					'plugin' => 'newsletter',
					'controller' => $controller,
					'action' => 'preview',
					$id,
					'admin' => 'true'
					)
				);

			return '<iframe frameborder="0" width="100%" height="500px" name="preview"src="' . $url . '" style="border:1px dotted gray;"></iframe>';
		}
	}