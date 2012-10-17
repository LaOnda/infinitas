<?php
	/**
	 * @page AppHelper AppHelper
	 *
	 * @section app_helper-overview What is it
	 *
	 * AppHelper is the base helper class that other helpers may extend to inherit
	 * some methods and functionality. If for some reason you do not want to
	 * inherit from this class just extend Helper.
	 *
	 * @section app_helper-usage How to use it
	 *
	 * Usage is simple, extend your SomethingHelper from this class Example below:
	 *
	 * @code
	 *	// in APP/plugins/my_plugin/views/helpers/something.php create
	 *	class SomethingHelper extends AppHelper{
	 *
	 *	}
	 * @endcode
	 *
	 * After that you will be able to directly access the public methods that
	 * are available from this class as if they were in your helper. There are
	 * two different ways that the methods can be accessed
	 *
	 * @code
	 *	// from within the Something helper
	 *	$this->someMethod();
	 *
	 *	// from a view file
	 *	$this->Something->someMethod();
	 * @endcode
	 *
	 * @section app_helper-see-also Also see
	 * @li InfinitasHelper
	 */


	/**
	 * @brief AppHelper is the base helper class that other helpers can extend
	 *
	 * @copyright Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	 * @link http://infinitas-cms.org
	 * @package Infinitas
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.5a
	 *
	 * Url Caching
	 * Copyright (c) 2009 Matt Curry
	 * @link http://github.com/mcurry/url_cache
	 * @link http://www.pseudocoder.com/archives/how-to-save-half-a-second-on-every-cakephp-requestand-maintain-reverse-routing
	 * @author      Matt Curry <matt@pseudocoder.com>
	 * @since 0.7a
	 *
	 * Licensed under The MIT License
	 * Redistributions of files must retain the above copyright notice.
	 */
	App::uses('Helper', 'View');
	App::uses('CakeSession', 'Model/Datasource');

	class AppHelper extends Helper {
		/**
		 * Internal counter of the row number to do zebra striped tables
		 *
		 * @var int
		 * @access public
		 */
		public $rowClassCounter = 0;

		/**
		 * The pagination string
		 *
		 * @var string
		 * @access public
		 */
		public $paginationCounterFormat = 'Page %page% of %pages%.';

		/**
		 * array of errors for debugging
		 *
		 * To keep track of what errors are happening you can add them to the
		 * error stack from your helpers. Then you can use pr() to see what
		 * error have happend up until that point.
		 *
		 * @code
		 *	// add errors from the helper
		 *	$this->errors[] = 'something bad happend ' . __LINE__;
		 *
		 *	// see errors in the helper
		 *	pr($this->errors);
		 *
		 *	// see errors in the view
		 *	pr($this->Something->errors);
		 *
		 * @var array
		 * @access public
		 */
		public $errors = array();

		/**
		 * @deprecated
		 *
		 * @todo need to make a wysiwyg engine like ChartsHelper
		 *
		 * @var string
		 */
		public $wysiwyg = 'fck';

		/**
		 * Additional helpers to load
		 *
		 * @var array
		 * @access public
		 */
		public $helpers = array(
			'Html', 'Form',
			'Libs.Wysiwyg', 'Libs.Image'
		);

		/**
		 * @brief create some bread crumbs
		 *
		 * This is used in the admin backend to generate bread crumbs of where
		 * the user is in the site. Its no very smart so some of the links will
		 * be wrong if you dont have what is expected.
		 *
		 * @param array $here is $this from the view.
		 * @access public
		 *
		 * @return string the markup for the bread crumbs
		 */
		public function breadcrumbs($seperator = ' :: ') {
			$action = $this->request->params['action'];
			if(strstr($this->request->params['action'], 'mass') === false) {
				$action = $this->_stripText($this->request->params['action'], 'admin_');
			}

			$pluginName = Inflector::camelize($this->request->params['plugin']);
			$dashboard = current(EventCore::trigger($this, $pluginName . '.pluginRollCall'));
			if(!empty($dashboard[$pluginName]['dashboard'])) {
				$dashboard = $dashboard[$pluginName]['dashboard'];
			} else {
				$dashboard = array(
					'plugin' => $this->request->params['plugin'],
					'controller' => false,
					'action' => false
				);
			}

			$breadcrumbs = array(
				$this->Html->link(__d($this->request->params['plugin'], $this->request->params['plugin']), $dashboard),
				$this->Html->link(
					__d($this->request->params['plugin'], strtolower(prettyName($this->stripPluginName($this->request->params['controller'])))),
					array(
						'plugin' => $this->request->params['plugin'],
						'controller' => $this->request->params['controller'],
						'action' => 'index'
					)
				),
				$action
			);

			$_prefix = isset($this->request->params['prefix']) ? $this->request->params['prefix'] : false;

			if ($_prefix !== false) {
				$breadcrumbs = array_merge(
					(array)$this->Html->link(
						__d($this->request->params['plugin'], $_prefix),
						'/' . $_prefix
					),
					$breadcrumbs
				);
			}

			return implode($seperator, $breadcrumbs);
		}

		/**
		 * @brief get the current url with no params
		 *
		 * This will give you an array or url of the current page with no params.
		 * Good for resetting search fields and filters.
		 *
		 * @param bool $array return array (true) or string (false)
		 * @access public
		 *
		 * @return mixed the clean url
		 */
		public function cleanCurrentUrl($array = true) {
			$params = array(
				'prefix' => $this->request->params['prefix'],
				'plugin' => $this->request->params['plugin'],
				'controller' => $this->request->params['controller'],
				'action' => $this->request->params['action']
			);

			if($array) {
				return $params;
			}

			return Ruter::url($params);
		}

		/**
		 * @brief switch the class for table rows
		 *
		 * Used to make the zebra striping in the admin backend. This should be
		 * removed from admin in favour of CSS3 pesudo selectors but the
		 * method can remain for frontend use.
		 *
		 * @todo remove usage from admin backend
		 *
		 * @param string $class1 class 1 highlight
		 * @param string $class2 class 2 highlight
		 * @access public
		 *
		 * @return string the class
		 */
		public function rowClass($class1 = 'bg', $class2 = '') {
			return (($this->rowClassCounter++ % 2) ? $class1 : $class2);
		}

		/**
		 * @brief Admin page heading
		 *
		 * Generates a heading based on the controller and adds a bread crumb
		 *
		 * @access public
		 *
		 * @return string the markup for the page header
		 */
		public function adminPageHead() {
			$name = prettyName(self::stripPluginName($this->request->params['controller']));
			return '<h1>' . sprintf(__('%s Manager'), $name) . '<small>' . $this->breadcrumbs() . '</small></h1>';
		}

		/**
		 * @brief strip the plugin name from the start of the text
		 *
		 * @code 
		 * 	// $this->request->params['plugin'] = 'foo';
		 *  $this->YourHelper->stripPluginName('foo_bar'); // return 'bar'
		 * @endcode
		 * 
		 * @param string $text the text to be manipulated
		 * 
		 * @return string
		 */
		public function stripPluginName($text) {
			if(empty($this->request->params['plugin'])) {
				return $text;
			}

			return self::_stripText($text, $this->request->params['plugin'] . '_');
		}

		/**
		 * @brief strip text from a string
		 * 
		 * @param string $text the text to be manipulated
		 * @param string $remove the text to be removed
		 * @param integer $position the possition to look / remove from
		 * 
		 * @return string
		 */
		protected function _stripText($text, $remove, $position = 0) {
			if(empty($remove)) {
				return $text;
			}
			
			if(strpos($text, $remove) === $position) {
				$text = substr_replace($text, '', $position, strlen($remove));
			}

			return $text;
		}

		/**
		 * @brief Creates table headers for admin.
		 *
		 * If the format is just array( 'head1', 'head2' ... ) it will output a
		 * normal table with TH that have no classes/styles applied.  you can
		 * also pass things like array ( 'head1' => array( 'class' => 'something' ) )
		 * to get out put like <th class="something">head1</th>
		 *
		 * @param array $data an array of items for the head.
		 * @param bool $footer if you want to show the table footer or not.
		 * @access public
		 *
		 * @return string the thead and tfoot html
		 */
		public function adminTableHeader($data, $footer = true) {
			$out = '';
			foreach($data as $field => $params) {
				$atributes = '';

				if (is_int($field) && !is_array($params)) {
					$field = $params;
					$params = '';
				}

				else {
					foreach($params as $type => $param) {
						$atributes = '';
						$atributes .= $type . '="' . $param . '" ';
					}

					$atributes .= $atributes;
				}

				if ($atributes != '') {
					$params = $atributes;
				}

				$out .= '<th ' . $params . '>' . $field . '</th>';
			}
			return '<thead>'.$out.'<thead>'. (($footer) ? '<tfoot>'.$out.'</tfoot>' : '');
		}

		/**
		 * @brief lazy way to create the admin index page headers
		 *
		 * @param array $filterOptions the filters to show
		 * @param array $massActions the mass actions to show
		 * @access public
		 *
		 * @return string the markup generated
		 */
		public function adminIndexHead($filterOptions = array(), $massActions = null) {
			if(!class_exists('FilterHelper')) {
				App::uses('FilterHelper', 'Filter.View/Helper');
			}

			if(is_array($massActions)) {
				$massActions = $this->massActionButtons($massActions);
			}

			return sprintf(
				'<div class="adminTopBar">%s%s</div><div class="filters">%s</div>',
				$this->adminPageHead(),
				$massActions,
				FilterHelper::form('Post', $filterOptions) . FilterHelper::clear($filterOptions)
			);
		}

		/**
		 * @brief lazy page for general admin pages with no mass actions
		 *
		 * @param array $massActions the mass actions as generated by
		 * @access public
		 *
		 * @return string the markup for the page
		 */
		public function adminOtherHead($massActions = null) {
			if(is_array($massActions)) {
				$massActions = $this->massActionButtons($massActions);
			}

			return $this->Html->tag(
				'div',
				$this->adminPageHead() . $massActions,
				array('class' => 'adminTopBar')

			);
		}

		/**
		 * @brief lazy method to create the admin head for editing pages
		 *
		 * @param array $actions the actions to create buttons for
		 * @access public
		 *
		 * @return string the markup for the page
		 */
		public function adminEditHead($actions = array('save', 'cancel')) {
	        return $this->adminOtherHead(
				$this->massActionButtons($actions)
	        );
		}

		/**
		 * @brief generate links with little code
		 *
		 * Generate a default edit link for use insde admin with no routing. just
		 * pass the array like $row['Model'] to this method and if you want something
		 * other than action => edit (maybe a different controller) pass that also
		 *
		 * @code
		 *	// for the current model
		 *	$this->Html->adminQuickLink($user['User']);
		 *
		 *	// for related data
		 *	$this->Html->adminQuickLink($user['Group'], array(), 'Group');
		 *
		 *	// to a different page
		 *	$this->Html->adminQuickLink($user['User'], array('action' => 'view'));
		 * @endcode
		 *
		 * @access public
		 *
		 * @param array $row the row $row['Model'] data
		 * @param mixed $url normal cake url array/string
		 * @param array $models if you want to link to a related model
		 * @param bool $urlOnly if you just want the url back
		 *
		 * @return string Undefined on error, html link when all is good
		 */
		public function adminQuickLink($row = array(), $url = array(), $model = '', $urlOnly = false) {
			$id = $text = null;

			if(is_array($url)) {
				$url = array_merge(array('action' => 'edit'), $url);
			}


			$id   = isset($row['id']) ? $row['id'] : null;
			$text = isset($row['name']) ? $row['name'] : null;
			$text = (!$text && isset($row['title'])) ? $row['title'] : null;

			$model = !empty($model) ? $model : current(array_keys($this->request->params['models']));

			if(!$id) {
				$text = $row[ClassRegistry::init($model)->displayField];
			}

			if(!$text) {
				$text = $row[ClassRegistry::init($model)->displayField];
			}

			if(!$id) {
				return __d($this->request->params['plugin'], 'Undefined');
			}

			if(is_array($url)) {
				$url = array_merge($url, array(0 => $id));
			}
			else{
				$url .= '/' . $id;
			}

			if($urlOnly) {
				return $url;
			}

			$link = $text;
			if(!$text) {
				$link = $id;
			}

			return $this->Html->link($link, $url);
		}

		/**
		 * @brief generate links for ordering normal tables
		 *
		 * creates links to the mass actions for ordering rows. This is for
		 * models that use the SequenceBehavior.
		 *
		 * @see AppHelper::treeOrdering()
		 *
		 * @param string $id the id of the row
		 * @param int $currentPosition the current order
		 * @param string $modelName the model
		 * @param array $results the row being ordered
		 * @access public
		 *
		 * @return string markup for the links to order them
		 */
		public function ordering($id = null, $currentPosition = null, $modelName = null, $results = null, $url = array()) {
			if (!$id) {
				$this->errors[] = 'How will i know what to move?';
			}

			if (!$currentPosition) {
				$this->errors[] = 'The new order was not passed';
			}

			if($results != null && $modelName) {
				$maxPosition = max(Set::extract('/' . $modelName . '/ordering', $results));
			}

			if(!is_array($url)) {
				$url = array();
			}
			$url['action'] = 'reorder';
			$url = array_merge($this->urlExtras, (array)$url);

			$out = '';

			if ($currentPosition > 1) {
				$out .= $this->Html->link(
					$this->Image->image(
						'actions',
						'arrow-up',
						array(
							'alt' => __('Up'),
							'title' => __('Move up'),
							'width' => '16px',
							'class' => 'arrow-up'
						)
					),
					$url + array('position' => $currentPosition - 1, $id),
					array(
						'escape' => false,
					)
				);
			}

			if($results == null || $currentPosition < $maxPosition) {
				$out .= $this->Html->link(
					$this->Image->image(
						'actions',
						'arrow-down',
						array(
							'alt' => __('Down'),
							'title' => __('Move down'),
							'width' => '16px',
							'class' => 'arrow-down'
						)
					),
					$url + array('position' => $currentPosition + 1, $id),
					array(
						'escape' => false,
					)
				);
			}

			return $out;
		}

		/**
		 * @brief generate icons and links for ordering mptt tables
		 *
		 * Generates links for ordering mptt rows with the TreeBehavior
		 *
		 * options:
		 * - firstChild: Pass true if this node is the first child
		 * - lastChild: Pass true if this node is the last child
		 *
		 * @see AppHelper::ordering()
		 *
		 * @param array $data the row being ordered
		 * @param array $options see above
		 * @access public
		 *
		 * @return string the html markup for icons to order the rows
		 */
		public function treeOrdering($data = null, $options = array()) {
			$options = array_merge(array('firstChild' => false, 'lastChild' => false), $options);

			if (!$data) {
				$this->errors[] = 'There is no data to build reorder links';
				return false;
			}

			$out = '';

			if(!$options['firstChild']) {
				$out .= $this->Html->link(
					$this->Image->image(
						'actions',
						'arrow-up',
						array(
							'alt' => __('Up'),
							'title' => __('Move up'),
							'width' => '16px',
							'class' => 'arrow-up'
						)
					),
					array(
						'action' => 'reorder',
						'direction' => 'up',
						$data['id']
					),
					array(
						'escape' => false,
					)
				);
			}

			if(!$options['lastChild']) {
				$out .= $this->Html->link(
					$this->Image->image(
						'actions',
						'arrow-down',
						array(
							'alt' => __('Down'),
							'title' => __('Move down'),
							'width' => '16px',
							'class' => 'arrow-down'
						)
					),
					array(
						'action' => 'reorder',
						'direction' => 'down',
						$data['id']
					),
					array(
						'escape' => false,
					)
				);
			}

			return $out;
		}

		/**
		 * @brief return the pagination counter text as set in the format
		 *
		 * @param object $pagintion the pagination helper object
		 * @access public
		 *
		 * @return string the markup
		 */
		public function paginationCounter($pagintion) {
			if (empty($pagintion)) {
				$this->errors[] = 'You did not pass the pagination object.';
				return false;
			}

			return $pagintion->counter(array('format' => __($this->paginationCounterFormat)));
		}

		/**
		 * @deprecated
		 *
		 * create a wysiwyg editor for the field that is passed in. If wysiwyg
		 * is disabled or not installed it will render a textarea.
		 *
		 * @param string $id the field to create a wysiwyg editor for
		 * @param array $config some settings for the editor
		 * @access public
		 *
		 * @return string markup for the editor
		 */
		public function wysiwyg($id = null, $config = array('toolbar' => 'Full')) {
			if (!$id) {
				$this->errors[] = 'No field specified for the wysiwyg editor';
				return false;
			}

			if (!Configure::read('Wysiwyg.editor')) {
				$this->errors[] = 'There is no editor configured';
			}

			$editor = 'text';

			$_editor = Configure::read('Wysiwyg.editor');
			if (!empty($_editor)) {
				$editor = $_editor;
			}

			return $this->Wysiwyg->load($editor, $id, $config);
		}

		/**
		 * @deprecated
		 *
		 * show a gravitar
		 *
		 * @todo currently only supports gravitars, see the ChartsHelper to make it
		 * more usable
		 *
		 * @param string $email email address
		 * @param array $options the options for the gravitar
		 * @access public
		 *
		 * @return string the markup of the gravitar
		 */
		public function gravatar($email = null, $options = array()) {
			if (!$email) {
				$this->errors[] = 'no email specified for the gravatar.';
				return false;
			}

			return $this->Gravatar->image($email, $options);
		}

		/**
		 * @brief create some mass action buttons like add, edit, delete etc.
		 *
		 * @param array $buttons the buttons to create
		 * @param array $name The name of the current controller used for AppHelper::niceTitleText()
		 * @access public
		 *
		 * @return string the markup for the buttons
		 */
		public function massActionButtons($buttons = null, $name = array()) {
			if (!$buttons) {
				$this->errors[] = 'No buttons set';
				return false;
			}

			$out = array();
			foreach($buttons as $button) {
				$out[] = $this->Form->button(
					 $this->Html->tag(
						'span',
						$this->Image->image('actions', $button, array('width' => '32')) . '<br/>' . __(Inflector::humanize($button))
					),
					array(
						'value' => strtolower(str_replace(array('-', ' '), '_', $button)),
						'name' => 'action',
						'title' => $this->niceTitleText($button, $name),
						'div' => false
					)
				);
			}

			return $this->Html->tag(
				'div',
				$this->Html->tag('div', implode('', $out), array('class' => 'wrapper')),
				array('class' => 'massActions')
			);
		}

		/**
		 * @brief Generate preview links
		 *
		 * create a preview link to a record, expects there to be a preview($id)
		 * method and will use the thickbox plugin if available, or open in a new
		 * window so you can see exactly how the coneten looks without making it active
		 *
		 * uses AppHelper::adminQuickLink to create the url and you must use array() urls
		 *
		 * @param array $row the row to make a preview of
		 * @param array $url some params you want to add to the url
		 * @param string $model if its not the main model
		 * @access public
		 *
		 * @return string some html for the preview link
		 */
		public function adminPreview($row = array(), $url = array(), $model = '') {
			if(empty($url)) {
				$url = array();
			}
			if(!is_array($url)) {
				return false;
			}

			return $this->Html->link(
				$this->Image->image(
					'actions',
					'new-window',
					array(
						'title' => __('Preview'),
						'alt' => __('Preview')
					)
				),
				array_merge(
					$this->adminQuickLink($row, $url, $model, true),
					array(
						'action' => 'preview',
						'?' => 'TB_iframe=true&width=1000'
					)
				),
				array(
					'target' => '_blank',
					'class' => 'new-window thickbox',
					'escape' => false,
					'title' => __('Preview of the entry')
				)
			);
		}

		/**
		 * @brief Generate nice title text.
		 *
		 * This method is used to generate nice looking information title text
		 * depending on what is displayed to the user.
		 *
		 * @param string $switch this is the title that is passed in
		 * @param array|string $name the name of the controller used to generate the text
		 *	- string: will use this name for singular and Inflector::pluralize() for plural
		 *  - array: keys singluar and plural
		 * @access public
		 *
		 * @return string the text for the title.
		 */
		public function niceTitleText($switch = null, $name = array()) {
			if(!is_array($name)) {
				$name = array('singular' => $name);
			}

			$name = array_merge(array('singular' => null, 'plural' => null), $name);
			if(empty($name['singular'])) {
				$name['singular'] = __(Inflector::singularize($this->request->params['controller']));
				$name['singular'] = str_replace(array('global', $this->request->params['plugin']), '', $name['singular']);
				$name['singular'] = str_replace('_', ' ', $name['singular']);
			}

			if(empty($name['plural'])) {
				$name['plural'] = Inflector::pluralize($name['singular']);
			}

			switch(strtolower($switch)) {
				case 'add':
					$heading = sprintf('%s %s', __('Create a'), $name['singular']);
					$text = __('Click here to create a new %s. You do not need to tick any checkboxes <br/>to create a new %s.', $name['singular'], $name['singular']);
					break;

				case 'edit':
					$heading = sprintf('%s %s', __('Edit a'), $name['singular']);
					$text = __('Tick the checkbox next to the %s you want to edit then click here.<br/>Currently you may only edit one %s at a time.', $name['singular'], $name['singular']);
					break;

				case 'copy':
					$heading = sprintf('%s %s', __('Copy some'), $name['plural']);
					$text = __('Tick the checkboxes next to the %s you want to copy then click here.<br/>You may copy as many %s as you like.', $name['singular'], $name['singular']);
					break;

				case 'toggle':
					$heading = sprintf('%s %s', __('Toggle some'), $name['plural']);
					$text = __('Tick the checkboxes next to the %s you want to toggle then click here.<br/>Inactive %s will become active, and active %s will become inactive', $name['singular'], $name['singular'], $name['singular']);
					break;

				case 'delete':
					$heading = sprintf('%s %s', __('Delete some'), $name['plural']);
					$text = __('Tick the checkboxes next to the %s you want to delete then click here.<br/>If possible the %s will be moved to the trash can. If not they will be deleted permanently.', $name['singular'], $name['singular']);
					if($this->request->params['action'] == 'admin_index' && $this->request->params['plugin'] == 'trash') {
						$heading = __('Delete records');
						$text = __('Deleting these records can not be undone, <br/>please make sure you check the correct records');
					}
					break;

				case 'disabled':
					$heading = sprintf('%s %s', __('Activate some'), $name['plural']);
					$text = __('This %s currently disabled, to enable it tick the check to the left and <br/>click toggle.', $name['singular']);
					break;

				case 'active':
					$heading = sprintf('%s %s', __('Disable some'), $name['plural']);
					$text = __('This %s currently active, to disable it tick the check to the left and <br/>click toggle.', $name['singular']);
					break;

				case 'save':
					$heading = sprintf('%s %s', __('Save the'), $name['singular']);
					$text = __('Click here to save your %s. This will save your current changes and take <br/>you back to the index list.', $name['singular']);
					break;

				case 'cancel':
					$heading = sprintf('%s', __('Discard your changes'));
					$text = __('Click here to return to the index page without saving the changes you <br/>have made to the %s.', $name['singular']);
					break;

				case 'move':
					$heading = sprintf('%s %s', __('Move some'), $name['plural']);
					$text = __('Tick the checkboxes next to the %s you want to move then click here. <br/>You will be prompted with a page, asking how you would like to move the %s', $name['singular'], $name['singular']);
					break;

				case 'preview':
					$heading = sprintf('%s %s', __('Preview a'), $name['singular']);
					$text = __('Tick the checkbox next to the %s you want to preview then click here. <br/>This will normally open in a popup and not affect your view counts', $name['singular'], $name['singular']);
					break;

				case 'restore':
					$heading = sprintf('%s %s', __('Restore records'), $name['singular']);
					$text = __('Tick the checkboxes next to the rows you would like to restore then click here.');
					break;

				default:
					$heading = $switch;
					$text = '';
					if(Configure::read('debug')) {
						$text = 'todo: Need to add an option for ' . $switch;
					}
			}

			return sprintf('%s :: %s', $heading, $text);
		}

		/**
		 * @todo implement this method or remove it
		 *
		 * nothing to see, move along
		 * @access public
		 */
		public function niceAltText($text) {
			return $text;
		}

		/**
		 * @brief Generate a date picker with the built in jquery datepicker widget.
		 *
		 * @param array $classes
		 * @param string $model the model the picker is for
		 * @param $time show a time picker (or datetime fields)
		 * @access public
		 *
		 * @return string the markup for the picker
		 */
		public function datePicker($classes, $model = null, $time = false) {
			$model = (!$model) ? Inflector::classify($this->request->params['controller']) : $model;

			$out = '<div class="datePicker">';
			foreach((array)$classes as $class) {
				$out .= sprintf(
					'<div class="date"><label>%s</label><div id="%sDatePicker%s"></div>%s</div>',
					Inflector::humanize($class),
					$model,
					ucfirst(Inflector::classify($class)),
					$this->Form->hidden($model.'.'.$class, array('type' => 'text'))
				);

				if($time === true) {
					$out .= '<div class="time">';
					$out .= $this->Form->input($model.'.'.str_replace('date', 'time', $class), array('type' => 'time', 'class' => 'timePicker'));
					$out .= '</div>';
				}

				else if(is_array($time)) {
					foreach($time as $t) {
						$out .= $this->Form->input($model.'.'.$t, array('type' => 'time', 'class' => 'timePicker'));
					}
				}
				$out .= "\n";
			}

			return $out . '</div>';
		}

		/**
		 * @var array
		 */
		public $urlCache = array();

		/**
		 * @var string
		 */
		public $urlKey = '';

		/**
		 * @var array
		 */
		public $urlExtras = array();

		/**
		 * @var array
		 */
		public $urlParamFields = array('controller', 'plugin', 'action', 'prefix');

		public function __construct(View $View, $settings = array()) {
			parent::__construct($View, $settings);

			if (Configure::read('UrlCache.pageFiles')) {
				$view = ClassRegistry::getObject('view');
				$path = $view->here;
				if ($this->request->here == '/') {
					$path = 'home';
				}
				$this->urlKey = '_' . strtolower(Inflector::slug($path));
			}

			$this->urlKey = 'url_map' . $this->urlKey;
			$this->urlCache = Cache::read($this->urlKey, 'core');
		}

		/**
		 * @brief before a page is rendered
		 *
		 * @access public
		 *
		 * @link http://api.cakephp.org/class/helper#method-HelperbeforeRender
		 *
		 * @return void
		 */
		public function beforeRender() {
			$this->urlExtras = array_intersect_key($this->request->params, array_combine($this->urlParamFields, $this->urlParamFields));
		}

		/**
		 * @brief write the new link cache after the page is done being rendered
		 *
		 * @link http://api.cakephp.org/class/helper#method-HelperafterLayout
		 *
		 * @access public
		 *
		 * @return void
		 */
		public function afterLayout() {
			if (is_a($this, 'HtmlHelper')) {
				Cache::write($this->urlKey, $this->urlCache, 'core');
			}
		}

		/**
		 * @brief cache urls so router does less work
		 *
		 * cache urls when the method is called saves using the router doing all
		 * the additional calculations.
		 *
		 * @link http://api.cakephp.org/class/helper#method-Helperurl
		 *
		 * @param mixed $url the url to generate
		 * @param bool $full full url returned or just relative
		 * @access public
		 *
		 * @return string the generated url
		 */
		public function url($url = null, $full = true) {
			if(CakeSession::read('Spam.bot') || $this->request->url == '/?spam=true') {
				return parent::url('/?spam=true', true);
			}

			$persistedNamedParameters = Configure::read('AppHelper.persistParameters');

			if(!empty($persistedNamedParameters) && is_array($url)) {
				foreach($persistedNamedParameters as $parameter) {
					if(!array_key_exists($parameter, $url) && !empty($this->request->params['named'][$parameter])) {
						$url[$parameter] = $this->request->params['named'][$parameter];
					}

					if(array_key_exists($parameter, $url) && $url[$parameter] === false) {
						unset($url[$parameter]);
					}
				}
			}

			$keyUrl = $url;
			if (is_array($keyUrl)) {
				$keyUrl += $this->urlExtras;
			}

			$key = md5(serialize($keyUrl) . $full);
			if (defined('INFINITAS_ROUTE_HASH')) {
				$key .= INFINITAS_ROUTE_HASH;
			}
			if (!empty($this->urlCache[$key])) {
				return $this->urlCache[$key];
			}

			$url = parent::url($url, $full);
			$this->urlCache[$key] = $url;

			return $url;
		}
	}