<?php
/**
 * TemplatesController
 *
 * @package Infinitas.Newsletter.Controller
 */

/**
 * TemplatesController
 *
 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
 * @link http://www.infinitas-cms.org
 * @package Infinitas.Newsletter.Controller
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @since 0.5a
 *
 * @author Carl Sutton <dogmatic69@infinitas-cms.org>
 */

class TemplatesController extends NewsletterAppController {
/**
 * Template sample text
 *
 * @var string
 */
	public $sampleText = '<p>This is some sample text to test your template</p>';

/**
 * List all templates
 *
 * @return void
 */
	public function admin_index() {
		$this->Paginator->settings = array(
			'fields' => array(
				'Template.id',
				'Template.name',
				'Template.created',
				'Template.modified',
			)
		);

		$templates = $this->Paginator->paginate(null, $this->Filter->filter);

		$filterOptions = $this->Filter->filterOptions;
		$filterOptions['fields'] = array(
			'name'
		);

		$this->set(compact('templates', 'filterOptions'));
	}

/**
 * View a template
 *
 * @param string $id the template id
 *
 * @return void
 */
	public function admin_view($id = null) {
		if (!$id) {
			$this->notice('invalid');
		}

		$this->set('template', $this->Template->read(null, $id));
	}

/**
 * Export newsletters
 *
 * @param string $id the newsletter id
 *
 * @return void
 */
	public function admin_export($id = null) {
		if (!$id) {
			$this->notice('invalid');
		}

		$this->Template->recursive = - 1;
		$template = $this->Template->read(array('name', 'description', 'author', 'header', 'footer'), $id);

		if (empty($template)) {
			$this->notice('invalid');
		}

		$pattern = "/src=[\\\"']?([^\\\"']?.*(png|jpg|gif|jpeg))[\\\"']?/i";
		preg_match_all($pattern, $template['Template']['header'], $images);

		$path = TMP . 'cache' . DS . 'newsletter' . DS . 'template' . DS . $template['Template']['name'];

		$Folder = new Folder($path, 0777);
		$slash = $Folder->correctSlashFor($path);

		App::import('File');
		App::import('Folder');

		$File = new File($path . DS . 'template.xml', true, 0777);

		$imageFiles = array();
		if (!empty($images[1])) {
			foreach($images[1] as $img) {
				$img = str_replace('/', $slash, $img);
				$img = str_replace('\\', $slash . $slash, $img);

				$imageFiles[] = $img;

				if (is_file(APP . 'webroot' . $img)) {
					$Folder->create(dirname($path . $img), 0777);
					$File->path = APP . 'webroot' . $img;
					$File->copy(dirname($path . $img) . DS . basename($img));
				}
			}
		}

		$xml['template']['name'] = 'Infinitas Newsletter Template';
		$xml['template']['generator'] = 'Infinitas Template Generator';
		$xml['template']['version'] = Configure::read('Infinitas.version');
		$xml['template']['template'] = $template['Template']['name'];
		$xml['template']['description'] = $template['Template']['description'];
		$xml['template']['author'] = $template['Template']['author'];
		$xml['data']['header'] = $template['Template']['header'];
		$xml['data']['footer'] = $template['Template']['footer'];
		$xml['files']['images'] = $imageFiles;

		App::Import('Helper', 'Xml');
		$Xml = new XmlHelper();

		$File->path = $path . DS . 'template.xml';
		$File->write($Xml->serialize($xml));

		App::import('Vendor', 'Zip', array('file' => 'zip.php'));

		$Zip = new CreateZipFile();
		$Zip->zipDirectory($path, null);
		$File = new File($path . DS . 'template.zip', true, 0777);
		$File->write($Zip->getZippedfile());

		$this->view = 'Media';
		$params = array(
			'id' => 'template.zip',
			'name' => $template['Template']['name'],
			'download' => true,
			'extension' => 'zip',
			'path' => $path . DS
			);

		$this->set($params);
		$Folder = new Folder($path);
		$Folder->read();
		$Folder->delete($path);
	}

/**
 * Preview a template
 *
 * @param string $id the template id
 *
 * @return void
 */
	public function admin_preview($id = null) {
		$this->layout = 'ajax';

		if (!$id) {
			return $this->set('data', __('The template was not found'));
		}

		$template = $this->Template->read(array('header', 'footer'), $id);
		$this->set('data', $template['Template']['header'] . $this->sampleText . $template['Template']['footer']);
	}

/**
 * Handle mass action deletes
 *
 * @param array $ids list of ids to delete
 *
 * @return boolean
 */
	public function __massActionDelete($ids) {
		return $this->MassAction->delete($this->__canDelete($ids));
	}

/**
 * Check if the templates can be deleted
 *
 * @param array $ids Ids to be deleted
 *
 * @return array
 */
	private function __canDelete($ids) {
		$newsletters = $this->Template->Newsletter->find(
			'list',
			array(
				'fields' => array(
					'Newsletter.template_id',
					'Newsletter.template_id'
					),
				'conditions' => array(
					'Newsletter.template_id' => $ids
					)
				)
			);

		foreach($ids as $k => $v) {
			if (isset($newsletters[$v])) {
				unset($ids[$k]);
			}
		}

		if (empty($ids)) {
			$this->notice(
				__('There are some newsletters using that template.'),
				array(
					'redirect' => true
				)
			);
		}

		$campaigns = $this->Template->Campaign->find(
			'list',
			array(
				'fields' => array(
					'Campaign.template_id',
					'Campaign.template_id'
				),
				'conditions' => array(
					'Campaign.template_id' => $ids
				)
			)
		);

		foreach($ids as $k => $v) {
			if (isset($campaigns[$v])) {
				unset($ids[$k]);
			}
		}

		if (empty($ids)) {
			$this->notice(
				__('There are some campaigns using that template.'),
				array(
					'redirect' => true
				)
			);
		}

		return $ids;
	}

}